from datetime import datetime, timezone
from pathlib import Path

from attendpro_recognition.face_engine import ExtractedFace, MODEL_NAME
from attendpro_recognition.face_index import FaceIndex
from attendpro_recognition.service import RecognitionService
from attendpro_recognition.settings import Settings


class FakeModels:
    ready = True

    def ensure(self):
        return None


class FakeEngine:
    models = FakeModels()
    ready = True

    def extract(self, _, **_options):
        return ExtractedFace([1.0, 0.0, 0.0], 0.98, (10, 20, 100, 100))


class FakeLaravel:
    def __init__(self):
        self.submitted = None
        self.enrolled_by = None
        self.configuration_calls = 0
        self.profile_fetches = 0

    def configuration(self):
        self.configuration_calls += 1
        return {"minimum_confidence": 0.65}

    def fetch_profiles(self):
        self.profile_fetches += 1
        return [{
            "person_id": 1,
            "institution_id": "2026-0001",
            "full_name": "Student One",
            "person_type": "student",
            "model": MODEL_NAME,
            "dimensions": 3,
            "embedding": [1.0, 0.0, 0.0],
            "version": 1,
        }]

    def submit_recognition(self, payload):
        self.submitted = payload
        return {"data": {"result": payload["result"], "action": "time_in", "attendance": {"status": "present"}}}

    def enroll(self, institution_id, embedding, model, consented_at, retention_until, enrolled_by=None):
        self.enrolled_by = enrolled_by
        return {"message": "Facial profile enrolled.", "data": {"institution_id": institution_id, "version": 1}}

    def heartbeat(self, *_args):
        return {}


def settings(tmp_path: Path) -> Settings:
    return Settings(
        laravel_api_url="http://127.0.0.1:8000/api/v1",
        service_key="secret",
        host="127.0.0.1",
        port=5001,
        model_dir=tmp_path,
        sync_seconds=60,
        request_timeout=5,
        max_image_bytes=1024,
        detection_score=0.85,
        minimum_face_ratio=0.12,
    )


def test_recognition_matches_and_returns_identity_for_laravel_to_record(tmp_path):
    laravel = FakeLaravel()
    service = RecognitionService(settings(tmp_path), FakeEngine(), laravel, FaceIndex())
    service.sync(force=True)

    result = service.recognize(b"image", "auto")

    assert result["result"] == "matched"
    assert result["institution_id"] == "2026-0001"
    assert result["recognition"]["candidate"]["institution_id"] == "2026-0001"
    assert result["captured_at"] <= datetime.now(timezone.utc).isoformat()
    assert laravel.submitted is None
    assert laravel.configuration_calls == 1
    assert laravel.profile_fetches == 1


def test_enrollment_sends_embedding_then_refreshes_index(tmp_path):
    laravel = FakeLaravel()
    service = RecognitionService(settings(tmp_path), FakeEngine(), laravel, FaceIndex())

    result = service.enroll(b"image", "2026-0001", datetime.now(timezone.utc).isoformat(), None, 42)

    assert result["profile"]["institution_id"] == "2026-0001"
    assert result["sync"]["profiles"] == 1
    assert laravel.enrolled_by == 42
