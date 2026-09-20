"""dlib backend for AttendPro, powered by the vendored face_recognition library.

Uses ``face_recognition-master/`` (ageitgey/face_recognition, dlib ResNet,
99.38% LFW) for detection + 128-d encodings, while keeping the exact same
AttendPro service contract as the default OpenCV YuNet+SFace backend:

* ``extract(image_bytes, enrollment=..., validate_quality=...)``
  returns an ``ExtractedFace`` with a unit-normalized 128-d embedding, so
  the shared cosine ``FaceIndex`` works unchanged.
* Error codes (``no_face``, ``multiple_faces``, ``invalid_image``, ...)
  match the SFace engine, so Laravel needs no changes.
* Embeddings are a different vector space than SFace, so switching
  backends requires re-enrollment (the index filters by ``MODEL_NAME``).

Set ``ATTENDPRO_FACE_BACKEND=dlib`` to use this engine.
``ATTENDPRO_DLIB_DETECTOR=hog|cnn``, ``ATTENDPRO_DLIB_UPSAMPLE`` (0-3),
``ATTENDPRO_DLIB_JITTERS`` (1-100) tune speed vs accuracy.
"""
from __future__ import annotations

import sys
import threading
from dataclasses import dataclass
from pathlib import Path
from typing import Any

from .errors import RecognitionServiceError
from .quality import FaceQuality, FaceQualityValidator


MODEL_NAME = "dlib_resnet_v1_99_38"
CAPABILITY = "dlib-face-recognition"
METRIC = "euclidean"

# Repository root holds the vendored ``face_recognition-master/`` folder, so
# the service works without installing ``face_recognition`` from PyPI
# (which would otherwise try to compile dlib from source on Windows).
SERVICE_ROOT = Path(__file__).resolve().parents[1]
REPOSITORY_ROOT = SERVICE_ROOT.parent
VENDORED_LIB = REPOSITORY_ROOT / "face_recognition-master"


@dataclass(frozen=True, slots=True)
class ExtractedFace:
    embedding: list[float]
    detection_score: float
    bounding_box: tuple[int, int, int, int]
    quality: FaceQuality | None = None


class DlibModelManager:
    """Pretends to be a ModelManager so service warm-up/status code is shared.

    dlib models ship inside the ``face_recognition_models`` package (or the
    vendored folder), so there is nothing to download. ``ensure()`` simply
    verifies the library imports correctly.
    """

    def __init__(self) -> None:
        self._ready = False

    @property
    def ready(self) -> bool:
        return self._ready

    def ensure(self) -> tuple[Path, Path]:
        _load_face_recognition()
        self._ready = True
        return (VENDORED_LIB, VENDORED_LIB)


def _load_face_recognition() -> Any:
    """Import ``face_recognition``, falling back to the vendored folder."""
    try:
        import face_recognition  # type: ignore

        return face_recognition
    except ImportError:
        pass
    if VENDORED_LIB.is_dir() and str(VENDORED_LIB) not in sys.path:
        sys.path.insert(0, str(VENDORED_LIB))
    try:
        import face_recognition  # type: ignore

        return face_recognition
    except ImportError as exc:
        raise RecognitionServiceError(
            "The dlib face backend is selected but the 'face_recognition' library "
            "could not be imported. Install python_service/requirements-dlib.txt "
            "(dlib-bin + face_recognition_models on Windows) and keep the "
            "face_recognition-master/ folder in the repository root.",
            code="model_initialization_failed",
            status_code=503,
        ) from exc


class DlibEngine:
    """Drop-in replacement for FaceEngine using dlib + face_recognition."""

    MODEL_NAME = MODEL_NAME
    CAPABILITY = CAPABILITY
    METRIC = METRIC

    def __init__(
        self,
        detection_model: str = "hog",
        upsample: int = 1,
        num_jitters: int = 1,
        minimum_face_ratio: float = 0.12,
        **quality_options: Any,
    ):
        self.models = DlibModelManager()
        self.detection_model = detection_model if detection_model in {"hog", "cnn"} else "hog"
        self.upsample = min(3, max(0, int(upsample)))
        self.num_jitters = min(100, max(1, int(num_jitters)))
        self.minimum_face_ratio = minimum_face_ratio
        self._lock = threading.RLock()
        self._warmed = False
        defaults = {
            "min_face_width": 120, "min_face_height": 120, "min_blur_score": 45.0,
            "min_brightness": 45.0, "max_brightness": 210.0, "max_roll": 18.0,
            "max_yaw_proxy": 0.38, "max_pitch_proxy": 0.32,
        }
        defaults.update(quality_options)
        self.quality = FaceQualityValidator(min_face_ratio=minimum_face_ratio, **defaults)

    @property
    def ready(self) -> bool:
        return self._warmed and self.models.ready

    def extract(self, image_bytes: bytes, *, enrollment: bool = False, validate_quality: bool = True) -> ExtractedFace:
        import cv2
        import numpy as np

        face_recognition = _load_face_recognition()

        image = cv2.imdecode(np.frombuffer(image_bytes, dtype=np.uint8), cv2.IMREAD_COLOR)
        if image is None:
            raise RecognitionServiceError("The uploaded camera frame is not a valid image.", code="invalid_image")

        height, width = image.shape[:2]
        if width < 160 or height < 160:
            raise RecognitionServiceError("The camera frame is too small for reliable recognition.", code="image_too_small")

        with self._lock:
            self._ensure_ready()
            rgb = cv2.cvtColor(image, cv2.COLOR_BGR2RGB)
            locations = face_recognition.face_locations(
                rgb, number_of_times_to_upsample=self.upsample, model=self.detection_model
            )

            count = len(locations)
            if count == 0:
                raise RecognitionServiceError("No face was detected in the captured frame.", code="no_face")
            if count > 1:
                raise RecognitionServiceError("More than one face was detected. Only one person may scan at a time.", code="multiple_faces")

            top, right, bottom, left = locations[0]
            x, y = max(0, left), max(0, top)
            box_width, box_height = min(right, width) - x, min(bottom, height) - y
            if box_width <= 0 or box_height <= 0:
                raise RecognitionServiceError("The detected face is outside the camera frame.", code="invalid_face_bounds")

            quality = None
            if validate_quality and not enrollment:
                quality = self.quality.validate(image, (x, y, box_width, box_height))

            encodings = face_recognition.face_encodings(
                rgb, known_face_locations=[locations[0]], num_jitters=self.num_jitters
            )
            if not encodings:
                raise RecognitionServiceError("The facial embedding could not be calculated.", code="invalid_embedding")
            feature = np.asarray(encodings[0], dtype=np.float32).reshape(-1)
            if feature.size != 128 or not np.all(np.isfinite(feature)):
                raise RecognitionServiceError("The facial embedding could not be calculated.", code="invalid_embedding")
            norm = float(np.linalg.norm(feature))
            if not np.isfinite(norm) or norm <= 0:
                raise RecognitionServiceError("The facial embedding could not be calculated.", code="invalid_embedding")
            feature /= norm

        return ExtractedFace(
            embedding=[float(value) for value in feature],
            # dlib HOG/CNN detectors report no confidence score; 1.0 keeps
            # the Laravel payload shape identical to the SFace backend.
            detection_score=1.0,
            bounding_box=(int(x), int(y), int(box_width), int(box_height)),
            quality=quality,
        )

    def _ensure_ready(self) -> None:
        if self._warmed and self.models.ready:
            return
        try:
            _load_face_recognition()
        except RecognitionServiceError:
            raise
        except Exception as exc:
            raise RecognitionServiceError(
                f"dlib could not initialize the recognition models: {exc}",
                code="model_initialization_failed",
                status_code=503,
            ) from exc
        self.models._ready = True
        self._warmed = True
