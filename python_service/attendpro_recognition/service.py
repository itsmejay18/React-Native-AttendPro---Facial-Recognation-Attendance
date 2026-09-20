from __future__ import annotations

import threading
import time
import uuid
from datetime import datetime, timezone
from typing import Any

from . import __version__
from .errors import RecognitionServiceError
from .face_index import FaceIndex, FaceMatch
from .laravel_client import LaravelClient
from .settings import Settings


class RecognitionService:
    def __init__(self, settings: Settings, engine, client: LaravelClient, index: FaceIndex):
        self.settings = settings
        self.engine = engine
        self.client = client
        self.index = index
        # Both engines expose MODEL_NAME; fall back to SFace for old tests.
        self.model_name: str = getattr(engine, "MODEL_NAME", "opencv_sface_2021dec")
        self.capability: str = getattr(engine, "CAPABILITY", "opencv-sface")
        self.minimum_confidence = 0.65
        self.minimum_margin = settings.minimum_margin
        self.last_sync_at: str | None = None
        self.last_error: str | None = None
        self._last_sync_monotonic = 0.0
        self._sync_lock = threading.RLock()

    def warm_up(self) -> None:
        try:
            self.engine.models.ensure()
            self.sync(force=True)
            self.last_error = None
        except Exception as exc:
            self.last_error = str(exc)

    def sync(self, *, force: bool = False) -> dict[str, Any]:
        with self._sync_lock:
            age = time.monotonic() - self._last_sync_monotonic
            if not force and self._last_sync_monotonic and age < self.settings.sync_seconds:
                return {"profiles": self.index.count, "synced_at": self.last_sync_at, "cached": True}

            configuration = self.client.configuration()
            records = self.client.fetch_profiles()
            count = self.index.replace(records)
            self.minimum_confidence = float(configuration.get("minimum_confidence", 0.65))
            self.minimum_margin = float(configuration.get("minimum_margin", self.settings.minimum_margin))
            self.last_sync_at = datetime.now(timezone.utc).isoformat()
            self._last_sync_monotonic = time.monotonic()
            self.last_error = None
            return {"profiles": count, "synced_at": self.last_sync_at, "cached": False}

    def recognize(self, image_bytes: bytes, direction: str = "auto") -> dict[str, Any]:
        if direction not in {"auto", "time_in", "time_out"}:
            raise RecognitionServiceError("Direction must be auto, time_in, or time_out.", code="invalid_direction")

        extracted = self.engine.extract(image_bytes, validate_quality=False)
        match = self.index.best_match(extracted.embedding)
        is_match, rejection_reason = self._is_acceptable_match(match)
        event_id = str(uuid.uuid4())
        payload: dict[str, Any] = {
            "event_id": event_id,
            "result": "matched" if is_match else "unknown",
            "confidence": round(match.confidence if match else 0.0, 6),
            "direction": direction,
            "captured_at": datetime.now(timezone.utc).isoformat(),
            "metadata": {
                "recognition_model": self.model_name,
                "python_service_version": __version__,
                "detection_score": round(extracted.detection_score, 6),
                "quality": extracted.quality.to_dict() if extracted.quality else None,
            },
        }
        if is_match and match:
            payload["institution_id"] = match.profile.institution_id

        payload["recognition"] = {
            "event_id": event_id,
            "confidence": payload["confidence"],
            "threshold": self.minimum_confidence,
            "minimum_margin": self.minimum_margin,
            "margin": round(match.margin, 6) if match else None,
            "detection_score": round(extracted.detection_score, 6),
            "bounding_box": extracted.bounding_box,
            "rejection_reason": rejection_reason,
            "candidate": {
                "institution_id": match.profile.institution_id,
                "full_name": match.profile.full_name,
                "person_type": match.profile.person_type,
            } if is_match and match else None,
        }

        return payload

    def recognize_many(self, images: list[bytes], direction: str = "auto") -> dict[str, Any]:
        if not images:
            raise RecognitionServiceError("No camera frames were supplied.", code="empty_frame_batch")
        if len(images) > self.settings.max_batch_images:
            raise RecognitionServiceError("Too many camera frames were supplied.", code="frame_batch_too_large", status_code=413)
        if direction not in {"auto", "time_in", "time_out"}:
            raise RecognitionServiceError("Direction must be auto, time_in, or time_out.", code="invalid_direction")

        accepted: list[tuple[FaceMatch, Any]] = []
        rejected: list[str] = []
        for image in images:
            try:
                extracted = self.engine.extract(image, validate_quality=False)
                match = self.index.best_match(extracted.embedding)
                matched, reason = self._is_acceptable_match(match)
                if matched and match:
                    accepted.append((match, extracted))
                else:
                    rejected.append(reason or "unknown")
            except RecognitionServiceError as error:
                rejected.append(error.code)

        event_id = str(uuid.uuid4())
        votes: dict[int, list[tuple[FaceMatch, Any]]] = {}
        for match, extracted in accepted:
            votes.setdefault(match.profile.person_id, []).append((match, extracted))
        winner_id, winner_votes = max(votes.items(), key=lambda item: len(item[1]), default=(None, []))
        # Attendance is a direct similarity lookup against each person's saved
        # reference set. One detected matching frame is sufficient; the scan
        # batch simply provides extra chances if a frame has no detectable face.
        required = 1
        is_match = winner_id is not None and len(winner_votes) >= required
        winner_match = max((vote[0] for vote in winner_votes), key=lambda match: match.confidence, default=None)
        average_confidence = sum(match.confidence for match, _ in winner_votes) / len(winner_votes) if winner_votes else 0.0
        rejection_reason = None if is_match else (
            "temporal_confirmation_failed" if winner_votes else (rejected[0] if rejected else "unknown")
        )
        payload: dict[str, Any] = {
            "event_id": event_id,
            "result": "matched" if is_match else "unknown",
            "confidence": round(average_confidence, 6),
            "direction": direction,
            "captured_at": datetime.now(timezone.utc).isoformat(),
            "metadata": {
                "recognition_model": self.model_name,
                "frames_received": len(images),
                "frames_accepted": len(accepted),
                "consistent_votes": len(winner_votes),
                "required_votes": required,
                "rejected_reasons": rejected[:5],
            },
            "recognition": {
                "event_id": event_id,
                "confidence": round(average_confidence, 6),
                "threshold": self.minimum_confidence,
                "minimum_margin": self.minimum_margin,
                "frames_received": len(images),
                "valid_frames": len(accepted),
                "consistent_votes": len(winner_votes),
                "required_votes": required,
                "rejection_reason": rejection_reason,
                "candidate": self._candidate(winner_match) if is_match else None,
            },
        }
        if is_match and winner_match:
            payload["institution_id"] = winner_match.profile.institution_id
        return payload

    def extract(self, image_bytes: bytes) -> dict[str, Any]:
        extracted = self.engine.extract(image_bytes, enrollment=True)

        return {
            "embedding": extracted.embedding,
            "model": self.model_name,
            "dimensions": len(extracted.embedding),
            "detection_score": round(extracted.detection_score, 6),
            "bounding_box": extracted.bounding_box,
            "quality": extracted.quality.to_dict() if extracted.quality else None,
        }

    def preview(self, image_bytes: bytes) -> dict[str, Any]:
        """Return non-persistent camera guidance when the selected engine supports it."""
        preview = getattr(self.engine, "preview", None)
        if not callable(preview):
            return {
                "supported": False,
                "backend": self.settings.face_backend,
                "face_count": 0,
                "faces": [],
            }

        result = preview(image_bytes)
        result["supported"] = True
        result["backend"] = self.settings.face_backend
        return result

    def extract_many(self, images: list[bytes]) -> dict[str, Any]:
        if not images:
            raise RecognitionServiceError("No enrollment frames were supplied.", code="empty_enrollment_batch")
        if len(images) > self.settings.max_batch_images:
            raise RecognitionServiceError("Too many enrollment frames were supplied.", code="enrollment_batch_too_large", status_code=413)

        samples: list[dict[str, Any]] = []
        rejected: list[dict[str, str]] = []
        for index, image in enumerate(images):
            try:
                extracted = self.engine.extract(image, enrollment=True)
                vector = extracted.embedding
                # Multiple clear frames of the same face are useful enrollment
                # evidence. The matcher already groups profiles by person, so
                # rejecting normal consecutive webcam frames leaves a user with
                # only a few accepted pose groups and blocks registration.
                samples.append({
                    "frame": index,
                    "embedding": vector,
                    "model": self.model_name,
                    "dimensions": len(vector),
                    "detection_score": round(extracted.detection_score, 6),
                    "bounding_box": extracted.bounding_box,
                    "quality": extracted.quality.to_dict() if extracted.quality else None,
                })
            except RecognitionServiceError as error:
                rejected.append({"frame": str(index + 1), "reason": error.code})
        return {"samples": samples, "rejected": rejected, "required_samples": self.settings.enrollment_samples}

    def index_profile(self, profile: dict[str, Any]) -> dict[str, Any]:
        count = self.index.upsert(profile)
        self.last_sync_at = datetime.now(timezone.utc).isoformat()
        self.last_error = None

        return {"profiles": count, "synced_at": self.last_sync_at, "cached": False}

    def index_profiles(self, profiles: list[dict[str, Any]]) -> dict[str, Any]:
        person_ids = {int(profile["person_id"]) for profile in profiles if profile.get("person_id") is not None}
        for person_id in person_ids:
            self.index.remove_person(person_id)
        for profile in profiles:
            self.index.upsert(profile)
        self.last_sync_at = datetime.now(timezone.utc).isoformat()
        self.last_error = None
        return {"profiles": self.index.count, "synced_at": self.last_sync_at, "cached": False}

    def enroll(
        self,
        image_bytes: bytes,
        institution_id: str,
        consented_at: str,
        retention_until: str | None,
        enrolled_by: int | None = None,
    ) -> dict[str, Any]:
        if not institution_id.strip():
            raise RecognitionServiceError("Institution ID is required.", code="institution_id_required")

        extracted = self.engine.extract(image_bytes)
        response = self.client.enroll(
            institution_id.strip(),
            extracted.embedding,
            self.model_name,
            consented_at,
            retention_until,
            enrolled_by,
        )
        sync_result = self.sync(force=True)
        return {
            "profile": response.get("data"),
            "message": response.get("message", "Facial profile enrolled."),
            "detection_score": round(extracted.detection_score, 6),
            "sync": sync_result,
        }

    def status(self) -> dict[str, Any]:
        ready = self.settings.is_configured and self.engine.models.ready and self.last_sync_at is not None and self.last_error is None
        return {
            "status": "ready" if ready else "initializing",
            "configured": self.settings.is_configured,
            "models_downloaded": self.engine.models.ready,
            "engine_initialized": self.engine.ready,
            "profiles_loaded": self.index.count,
            "last_sync_at": self.last_sync_at,
            "last_error": self.last_error,
            "laravel_api_url": self.settings.laravel_api_url,
            "model": self.model_name,
            "backend": self.settings.face_backend,
            "capability": self.capability,
            "version": __version__,
        }

    def _is_acceptable_match(self, match: FaceMatch | None) -> tuple[bool, str | None]:
        if match is None:
            return False, "no_enrolled_profiles"
        if match.confidence < self.minimum_confidence:
            return False, "below_similarity_threshold"
        if match.second_profile is not None and match.margin < self.minimum_margin:
            return False, "ambiguous_second_best_match"
        return True, None

    @staticmethod
    def _candidate(match: FaceMatch | None) -> dict[str, Any] | None:
        if match is None:
            return None
        return {
            "institution_id": match.profile.institution_id,
            "full_name": match.profile.full_name,
            "person_type": match.profile.person_type,
            "second_best_institution_id": match.second_profile.institution_id if match.second_profile else None,
            "second_best_confidence": round(match.second_confidence, 6) if match.second_confidence is not None else None,
        }
