from __future__ import annotations

import os
from dataclasses import dataclass
from pathlib import Path

from dotenv import load_dotenv


SERVICE_ROOT = Path(__file__).resolve().parents[1]
REPOSITORY_ROOT = SERVICE_ROOT.parent


@dataclass(frozen=True, slots=True)
class Settings:
    laravel_api_url: str
    service_key: str
    host: str
    port: int
    model_dir: Path
    sync_seconds: int
    request_timeout: float
    max_image_bytes: int
    detection_score: float
    minimum_face_ratio: float
    min_face_width: int = 120
    min_face_height: int = 120
    min_blur_score: float = 45.0
    min_brightness: float = 45.0
    max_brightness: float = 210.0
    max_roll: float = 18.0
    max_yaw_proxy: float = 0.38
    max_pitch_proxy: float = 0.32
    minimum_margin: float = 0.04
    face_backend: str = "insightface"
    dlib_detector: str = "hog"
    dlib_upsample: int = 1
    dlib_jitters: int = 1
    insightface_pack: str = "buffalo_l"
    insightface_det_size: int = 640
    insightface_ctx_id: int = -1
    frame_window: int = 7
    min_consistent_matches: int = 5
    enrollment_samples: int = 15
    max_batch_images: int = 30
    enrollment_max_similarity: float = 0.9999

    @classmethod
    def load(cls) -> "Settings":
        load_dotenv(REPOSITORY_ROOT / ".env", override=False)
        load_dotenv(SERVICE_ROOT / ".env", override=True)

        app_url = os.getenv("APP_URL", "http://127.0.0.1:8000").rstrip("/")
        model_dir_value = os.getenv("ATTENDPRO_PYTHON_MODEL_DIR", "").strip()
        model_dir = Path(model_dir_value).expanduser().resolve() if model_dir_value else SERVICE_ROOT / "models"

        backend = os.getenv("ATTENDPRO_FACE_BACKEND", "insightface").strip().lower()
        if backend not in {"sface", "dlib", "insightface"}:
            backend = "insightface"
        detector = os.getenv("ATTENDPRO_DLIB_DETECTOR", "hog").strip().lower()
        if detector not in {"hog", "cnn"}:
            detector = "hog"

        return cls(
            laravel_api_url=os.getenv("ATTENDPRO_LARAVEL_API_URL", f"{app_url}/api/v1").rstrip("/"),
            service_key=os.getenv("ATTENDPRO_PYTHON_SERVICE_KEY", "").strip(),
            host=os.getenv("ATTENDPRO_PYTHON_HOST", "127.0.0.1"),
            port=int(os.getenv("ATTENDPRO_PYTHON_PORT", "5001")),
            model_dir=model_dir,
            sync_seconds=max(5, int(os.getenv("ATTENDPRO_PYTHON_SYNC_SECONDS", "60"))),
            request_timeout=max(1.0, float(os.getenv("ATTENDPRO_PYTHON_REQUEST_TIMEOUT", "15"))),
            max_image_bytes=max(1, int(os.getenv("ATTENDPRO_PYTHON_MAX_IMAGE_MB", "5"))) * 1024 * 1024,
            detection_score=min(0.99, max(0.5, float(os.getenv("ATTENDPRO_PYTHON_DETECTION_SCORE", "0.85")))),
            minimum_face_ratio=min(0.5, max(0.05, float(os.getenv("ATTENDPRO_PYTHON_MIN_FACE_RATIO", "0.12")))),
            min_face_width=max(80, int(os.getenv("ATTENDPRO_MIN_FACE_WIDTH", "120"))),
            min_face_height=max(80, int(os.getenv("ATTENDPRO_MIN_FACE_HEIGHT", "120"))),
            min_blur_score=max(1.0, float(os.getenv("ATTENDPRO_MIN_BLUR_SCORE", "45"))),
            min_brightness=min(200.0, max(1.0, float(os.getenv("ATTENDPRO_MIN_BRIGHTNESS", "45")))),
            max_brightness=min(254.0, max(55.0, float(os.getenv("ATTENDPRO_MAX_BRIGHTNESS", "210")))),
            max_roll=min(45.0, max(5.0, float(os.getenv("ATTENDPRO_MAX_ROLL", "18")))),
            max_yaw_proxy=min(1.0, max(0.05, float(os.getenv("ATTENDPRO_MAX_YAW_PROXY", "0.38")))),
            max_pitch_proxy=min(1.0, max(0.05, float(os.getenv("ATTENDPRO_MAX_PITCH_PROXY", "0.32")))),
            minimum_margin=min(0.5, max(0.0, float(os.getenv("ATTENDPRO_MIN_SECOND_BEST_MARGIN", "0.04")))),
            face_backend=backend,
            dlib_detector=detector,
            dlib_upsample=min(3, max(0, int(os.getenv("ATTENDPRO_DLIB_UPSAMPLE", "1")))),
            dlib_jitters=min(100, max(1, int(os.getenv("ATTENDPRO_DLIB_JITTERS", "1")))),
            insightface_pack=os.getenv("ATTENDPRO_INSIGHTFACE_PACK", "buffalo_l").strip() or "buffalo_l",
            insightface_det_size=min(1024, max(320, int(os.getenv("ATTENDPRO_INSIGHTFACE_DET_SIZE", "640")))),
            insightface_ctx_id=max(-1, int(os.getenv("ATTENDPRO_INSIGHTFACE_CTX_ID", "-1"))),
            frame_window=min(15, max(3, int(os.getenv("ATTENDPRO_FRAME_WINDOW", "7")))),
            min_consistent_matches=min(15, max(2, int(os.getenv("ATTENDPRO_MIN_CONSISTENT_MATCHES", "5")))),
            enrollment_samples=min(30, max(1, int(os.getenv("ATTENDPRO_ENROLLMENT_SAMPLES", "15")))),
            max_batch_images=min(30, max(5, int(os.getenv("ATTENDPRO_MAX_BATCH_IMAGES", "30")))),
            enrollment_max_similarity=min(0.99999, max(0.9, float(os.getenv("ATTENDPRO_ENROLLMENT_MAX_SIMILARITY", "0.9999")))),
        )

    @property
    def is_configured(self) -> bool:
        return bool(self.service_key and self.laravel_api_url)
