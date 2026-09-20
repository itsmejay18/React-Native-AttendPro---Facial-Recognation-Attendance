"""InsightFace backend for AttendPro, powered by the vendored insightface-master/ folder.

Uses ``insightface.app.FaceAnalysis`` (default ``buffalo_l`` pack: SCRFD
detection + ArcFace R100 512-d embeddings, cosine metric) while keeping the
exact same AttendPro service contract as the OpenCV YuNet+SFace and dlib
backends:

* ``extract(image_bytes, enrollment=..., validate_quality=...)``
  returns an ``ExtractedFace`` with a unit-normalized embedding, so the
  shared cosine ``FaceIndex`` works unchanged.
* Error codes (``no_face``, ``multiple_faces``, ``invalid_image``, ...)
  match the other engines, so Laravel needs no changes.
* Embeddings are a different vector space than SFace/dlib, so switching
  backends requires re-enrollment (the index filters by ``MODEL_NAME``).

Set ``ATTENDPRO_FACE_BACKEND=insightface`` to use this engine.
``ATTENDPRO_INSIGHTFACE_PACK`` (default ``buffalo_l``),
``ATTENDPRO_INSIGHTFACE_DET_SIZE`` (default ``640`` square),
``ATTENDPRO_INSIGHTFACE_CTX_ID`` (default ``-1`` = CPU) tune it.

The vendored ``insightface-master/python-package`` folder is used first so
no PyPI ``insightface`` install is required; only the runtime deps in
``python_service/requirements-insightface.txt`` (onnxruntime, onnx, ...)
must be installed. Pretrained pack weights download on first use into
``ATTENDPRO_PYTHON_MODEL_DIR`` (FaceAnalysis ``root``).
"""
from __future__ import annotations

import sys
import threading
from dataclasses import dataclass
from pathlib import Path
from typing import Any

from .errors import RecognitionServiceError
from .quality import FaceQuality, FaceQualityValidator


MODEL_NAME = "insightface_buffalo_l_arcface_r100"
CAPABILITY = "insightface-buffalo"
METRIC = "cosine"
EMBEDDING_DIM = 512

# Repository root holds the vendored ``insightface-master/`` folder, so the
# service works without installing ``insightface`` from PyPI (which has no
# prebuilt Windows wheels for every Python version).
SERVICE_ROOT = Path(__file__).resolve().parents[1]
REPOSITORY_ROOT = SERVICE_ROOT.parent
VENDORED_PACKAGE = REPOSITORY_ROOT / "insightface-master" / "python-package"


def _load_face_analysis() -> Any:
    """Import ``insightface.app.FaceAnalysis``, preferring vendored source."""
    try:
        from insightface.app import FaceAnalysis  # type: ignore

        return FaceAnalysis
    except ImportError:
        pass
    if VENDORED_PACKAGE.is_dir() and str(VENDORED_PACKAGE) not in sys.path:
        sys.path.insert(0, str(VENDORED_PACKAGE))
    try:
        from insightface.app import FaceAnalysis  # type: ignore

        return FaceAnalysis
    except ImportError as exc:
        raise RecognitionServiceError(
            "The InsightFace backend is selected but the 'insightface' library "
            "could not be imported. Install python_service/requirements-insightface.txt "
            "(onnxruntime + onnx + scikit-image ...) and keep the "
            "insightface-master/ folder in the repository root.",
            code="model_initialization_failed",
            status_code=503,
        ) from exc


def _load_face_type() -> Any:
    """Load InsightFace's lightweight Face container for landmark inference."""
    try:
        from insightface.app.common import Face  # type: ignore

        return Face
    except ImportError:
        if VENDORED_PACKAGE.is_dir() and str(VENDORED_PACKAGE) not in sys.path:
            sys.path.insert(0, str(VENDORED_PACKAGE))
        try:
            from insightface.app.common import Face  # type: ignore

            return Face
        except ImportError as exc:
            raise RecognitionServiceError(
                "InsightFace landmark support could not be loaded.",
                code="model_initialization_failed",
                status_code=503,
            ) from exc


@dataclass(frozen=True, slots=True)
class ExtractedFace:
    embedding: list[float]
    detection_score: float
    bounding_box: tuple[int, int, int, int]
    quality: FaceQuality | None = None


class InsightFaceModelManager:
    """Wraps FaceAnalysis init so warm-up/status code matches other backends."""

    def __init__(
        self,
        model_dir: Path,
        pack: str = "buffalo_l",
        det_size: int = 640,
        ctx_id: int = -1,
    ) -> None:
        self.model_dir = model_dir
        self.pack = pack or "buffalo_l"
        self.det_size = det_size
        self.ctx_id = ctx_id
        self._app: Any | None = None
        self._ready = False

    @property
    def ready(self) -> bool:
        return self._ready and self._app is not None

    @property
    def app(self) -> Any | None:
        return self._app

    def ensure(self) -> tuple[Path, Path]:
        FaceAnalysis = _load_face_analysis()
        if self._app is not None:
            self._ready = True
            return (self.model_dir, self.model_dir)
        try:
            self.model_dir.mkdir(parents=True, exist_ok=True)
            app = FaceAnalysis(
                name=self.pack,
                root=str(self.model_dir),
                # buffalo_l includes the 2d106 alignment model. Loading it lets
                # the camera show the same dense landmark output as InsightFace's
                # official alignment demonstration, while recognition continues
                # to use the existing ArcFace embedding model.
                allowed_modules=["detection", "recognition", "landmark_2d_106"],
            )
            # CPU by default (ctx_id=-1); ctx_id>=0 selects a CUDA device.
            app.prepare(ctx_id=self.ctx_id, det_size=(self.det_size, self.det_size))
        except Exception as exc:
            raise RecognitionServiceError(
                f"InsightFace could not initialize the recognition models: {exc}",
                code="model_initialization_failed",
                status_code=503,
            ) from exc
        self._app = app
        self._ready = True
        return (self.model_dir, self.model_dir)


class InsightFaceEngine:
    """Drop-in replacement for FaceEngine using InsightFace buffalo_l."""

    MODEL_NAME = MODEL_NAME
    CAPABILITY = CAPABILITY
    METRIC = METRIC

    def __init__(
        self,
        model_dir: Path | str,
        pack: str = "buffalo_l",
        det_size: int = 640,
        ctx_id: int = -1,
        minimum_face_ratio: float = 0.12,
        **quality_options: Any,
    ):
        root = Path(model_dir).expanduser() if model_dir else SERVICE_ROOT / "models"
        self.models = InsightFaceModelManager(root, pack, det_size, ctx_id)
        self.minimum_face_ratio = minimum_face_ratio
        self._lock = threading.RLock()
        defaults = {
            "min_face_width": 120, "min_face_height": 120, "min_blur_score": 45.0,
            "min_brightness": 45.0, "max_brightness": 210.0, "max_roll": 18.0,
            "max_yaw_proxy": 0.38, "max_pitch_proxy": 0.32,
        }
        defaults.update(quality_options)
        self.quality = FaceQualityValidator(min_face_ratio=minimum_face_ratio, **defaults)

    @property
    def ready(self) -> bool:
        return self.models.ready

    def extract(self, image_bytes: bytes, *, enrollment: bool = False, validate_quality: bool = True) -> ExtractedFace:
        import cv2
        import numpy as np

        image = cv2.imdecode(np.frombuffer(image_bytes, dtype=np.uint8), cv2.IMREAD_COLOR)
        if image is None:
            raise RecognitionServiceError("The uploaded camera frame is not a valid image.", code="invalid_image")

        height, width = image.shape[:2]
        if width < 160 or height < 160:
            raise RecognitionServiceError("The camera frame is too small for reliable recognition.", code="image_too_small")

        with self._lock:
            self.models.ensure()
            assert self.models.app is not None
            try:
                faces = self.models.app.get(image)
            except Exception as exc:
                raise RecognitionServiceError(
                    f"InsightFace could not process the camera frame: {exc}",
                    code="invalid_embedding",
                    status_code=502,
                ) from exc

            count = len(faces)
            if count == 0:
                raise RecognitionServiceError("No face was detected in the captured frame.", code="no_face")
            if count > 1:
                raise RecognitionServiceError("More than one face was detected. Only one person may scan at a time.", code="multiple_faces")

            face = faces[0]
            embedding = np.asarray(face.embedding, dtype=np.float32).reshape(-1)
            if embedding.size != EMBEDDING_DIM or not np.all(np.isfinite(embedding)):
                raise RecognitionServiceError("The facial embedding could not be calculated.", code="invalid_embedding")
            norm = float(np.linalg.norm(embedding))
            if not np.isfinite(norm) or norm <= 0:
                raise RecognitionServiceError("The facial embedding could not be calculated.", code="invalid_embedding")
            embedding /= norm

            bbox = np.asarray(face.bbox, dtype=np.float64).reshape(-1)
            x1, y1, x2, y2 = (int(v) for v in bbox[:4])
            x1 = max(0, min(x1, width - 1))
            y1 = max(0, min(y1, height - 1))
            x2 = max(x1 + 1, min(x2, width))
            y2 = max(y1 + 1, min(y2, height))
            box = (x1, y1, x2 - x1, y2 - y1)
            det_score = float(face.det_score) if getattr(face, "det_score", None) is not None else 1.0

            quality = None
            if validate_quality and not enrollment:
                # Reuse the shared validator on the BGR frame; insightface's
                # 5-point landmarks are not needed for the blur/brightness/size
                # proxy checks, so pass the bounding box directly.
                quality = self.quality.validate(image, box)

        x, y, box_width, box_height = (int(v) for v in box)
        return ExtractedFace(
            embedding=[float(v) for v in embedding],
            detection_score=det_score,
            bounding_box=(x, y, box_width, box_height),
            quality=quality,
        )

    def preview(self, image_bytes: bytes) -> dict[str, Any]:
        """Detect up to three faces and expose only their five visual keypoints.

        The buffalo_l 2d106 alignment model supplies the dense point cloud used
        by InsightFace's alignment demonstrations. Points are used exclusively
        by the browser canvas to guide a person into frame; no embedding is
        generated, stored, or returned by this method.
        """
        import cv2
        import numpy as np

        image = cv2.imdecode(np.frombuffer(image_bytes, dtype=np.uint8), cv2.IMREAD_COLOR)
        if image is None:
            raise RecognitionServiceError("The uploaded camera frame is not a valid image.", code="invalid_image")

        height, width = image.shape[:2]
        with self._lock:
            self.models.ensure()
            assert self.models.app is not None
            Face = _load_face_type()
            try:
                boxes, keypoints = self.models.app.det_model.detect(image, max_num=3)
            except Exception as exc:
                raise RecognitionServiceError(
                    f"InsightFace could not detect camera landmarks: {exc}",
                    code="preview_failed",
                    status_code=502,
                ) from exc

        faces: list[dict[str, Any]] = []
        detected_boxes = [] if boxes is None else boxes
        for index, raw_box in enumerate(detected_boxes):
            box = np.asarray(raw_box, dtype=np.float64).reshape(-1)
            if box.size < 5:
                continue
            x1, y1, x2, y2 = (int(value) for value in box[:4])
            x1 = max(0, min(x1, width - 1))
            y1 = max(0, min(y1, height - 1))
            x2 = max(x1 + 1, min(x2, width))
            y2 = max(y1 + 1, min(y2, height))
            detector_points = None if keypoints is None or len(keypoints) <= index else keypoints[index]
            points_array = None
            landmark_model = self.models.app.models.get("landmark_2d_106")
            if landmark_model is not None:
                face = Face(
                    bbox=np.asarray(box[:4], dtype=np.float32),
                    kps=np.asarray(detector_points, dtype=np.float32) if detector_points is not None else None,
                    det_score=float(box[4]),
                )
                points_array = landmark_model.get(image, face)
            elif detector_points is not None:
                points_array = detector_points

            points = [] if points_array is None else [
                {"x": round(float(point[0]), 2), "y": round(float(point[1]), 2)}
                for point in np.asarray(points_array, dtype=np.float64).reshape(-1, 2)
            ]
            faces.append({
                "bounding_box": {"x": x1, "y": y1, "width": x2 - x1, "height": y2 - y1},
                "detection_score": round(float(box[4]), 6),
                "keypoints": points,
                "landmark_type": "dense_106" if len(points) >= 100 else "five_point",
            })

        return {
            "image_width": width,
            "image_height": height,
            "face_count": len(faces),
            "faces": faces,
            "landmark_type": "dense_106" if any(face["landmark_type"] == "dense_106" for face in faces) else "five_point",
        }
