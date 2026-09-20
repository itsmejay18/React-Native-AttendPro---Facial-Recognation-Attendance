from __future__ import annotations

import shutil
import tempfile
import urllib.request
from dataclasses import dataclass
from pathlib import Path

from .errors import RecognitionServiceError


@dataclass(frozen=True, slots=True)
class ModelAsset:
    filename: str
    url: str


class ModelManager:
    YUNET = ModelAsset(
        "face_detection_yunet_2023mar.onnx",
        "https://github.com/opencv/opencv_zoo/raw/main/models/face_detection_yunet/face_detection_yunet_2023mar.onnx",
    )
    SFACE = ModelAsset(
        "face_recognition_sface_2021dec.onnx",
        "https://github.com/opencv/opencv_zoo/raw/main/models/face_recognition_sface/face_recognition_sface_2021dec.onnx",
    )

    def __init__(self, model_dir: Path):
        self.model_dir = model_dir

    @property
    def detector_path(self) -> Path:
        return self.model_dir / self.YUNET.filename

    @property
    def recognizer_path(self) -> Path:
        return self.model_dir / self.SFACE.filename

    @property
    def ready(self) -> bool:
        return self.detector_path.is_file() and self.detector_path.stat().st_size > 0 \
            and self.recognizer_path.is_file() and self.recognizer_path.stat().st_size > 0

    def ensure(self) -> tuple[Path, Path]:
        self.model_dir.mkdir(parents=True, exist_ok=True)
        for asset in (self.YUNET, self.SFACE):
            target = self.model_dir / asset.filename
            if not target.is_file() or target.stat().st_size == 0:
                self._download(asset, target)

        return self.detector_path, self.recognizer_path

    def _download(self, asset: ModelAsset, target: Path) -> None:
        try:
            request = urllib.request.Request(asset.url, headers={"User-Agent": "AttendPro/1.0"})
            with urllib.request.urlopen(request, timeout=60) as response, tempfile.NamedTemporaryFile(
                dir=self.model_dir, delete=False
            ) as temporary:
                shutil.copyfileobj(response, temporary)
                temporary_path = Path(temporary.name)
            if temporary_path.stat().st_size == 0:
                raise OSError("downloaded file is empty")
            temporary_path.replace(target)
        except Exception as exc:
            raise RecognitionServiceError(
                f"Unable to download {asset.filename}: {exc}",
                code="model_download_failed",
                status_code=503,
            ) from exc
