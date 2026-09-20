from __future__ import annotations

from dataclasses import asdict, dataclass
from typing import Sequence

import numpy as np

from .errors import RecognitionServiceError


@dataclass(frozen=True, slots=True)
class FaceQuality:
    blur_score: float
    brightness: float
    face_width: int
    face_height: int
    face_ratio: float
    roll: float | None
    yaw_proxy: float | None
    pitch_proxy: float | None

    def to_dict(self) -> dict[str, float | int | None]:
        return asdict(self)


class FaceQualityValidator:
    """Deterministic, CPU-friendly checks applied to enrollment and live scans."""

    def __init__(
        self,
        *,
        min_face_ratio: float,
        min_face_width: int,
        min_face_height: int,
        min_blur_score: float,
        min_brightness: float,
        max_brightness: float,
        max_roll: float,
        max_yaw_proxy: float,
        max_pitch_proxy: float,
    ):
        self.min_face_ratio = min_face_ratio
        self.min_face_width = min_face_width
        self.min_face_height = min_face_height
        self.min_blur_score = min_blur_score
        self.min_brightness = min_brightness
        self.max_brightness = max_brightness
        self.max_roll = max_roll
        self.max_yaw_proxy = max_yaw_proxy
        self.max_pitch_proxy = max_pitch_proxy

    def validate(self, image: np.ndarray, face: Sequence[float]) -> FaceQuality:
        import cv2

        height, width = image.shape[:2]
        x, y, face_width, face_height = (int(value) for value in face[:4])
        ratio = min(face_width / width, face_height / height)
        if face_width < self.min_face_width or face_height < self.min_face_height or ratio < self.min_face_ratio:
            raise RecognitionServiceError(
                "Move closer to the camera so the face fills more of the frame.",
                code="face_too_small",
            )

        x1, y1 = max(0, x), max(0, y)
        x2, y2 = min(width, x + face_width), min(height, y + face_height)
        crop = image[y1:y2, x1:x2]
        if crop.size == 0:
            raise RecognitionServiceError("The detected face is outside the camera frame.", code="invalid_face_bounds")

        gray = cv2.cvtColor(crop, cv2.COLOR_BGR2GRAY)
        blur_score = float(cv2.Laplacian(gray, cv2.CV_64F).var())
        brightness = float(gray.mean())
        if brightness < self.min_brightness:
            raise RecognitionServiceError("The image is too dark. Improve the lighting and try again.", code="image_too_dark")
        if brightness > self.max_brightness:
            raise RecognitionServiceError("The image is too bright. Reduce backlighting and try again.", code="image_too_bright")
        if blur_score < self.min_blur_score:
            raise RecognitionServiceError("The image is blurry. Hold still and let the camera focus.", code="image_blurry")

        roll, yaw_proxy, pitch_proxy = self._pose_metrics(face)
        if roll is not None and abs(roll) > self.max_roll:
            raise RecognitionServiceError("Face the camera without tilting your head.", code="face_roll_excessive")
        if yaw_proxy is not None and abs(yaw_proxy) > self.max_yaw_proxy:
            raise RecognitionServiceError("Face the camera more directly.", code="face_yaw_excessive")
        if pitch_proxy is not None and abs(pitch_proxy) > self.max_pitch_proxy:
            raise RecognitionServiceError("Keep your face level with the camera.", code="face_pitch_excessive")

        return FaceQuality(
            blur_score=round(blur_score, 3),
            brightness=round(brightness, 3),
            face_width=face_width,
            face_height=face_height,
            face_ratio=round(ratio, 4),
            roll=round(roll, 3) if roll is not None else None,
            yaw_proxy=round(yaw_proxy, 3) if yaw_proxy is not None else None,
            pitch_proxy=round(pitch_proxy, 3) if pitch_proxy is not None else None,
        )

    @staticmethod
    def _pose_metrics(face: Sequence[float]) -> tuple[float | None, float | None, float | None]:
        # YuNet returns five landmarks after x, y, width, height: eyes, nose,
        # and mouth corners. These are conservative proxies, not a 3-D pose.
        if len(face) < 14:
            return None, None, None
        points = np.asarray(face[4:14], dtype=np.float32).reshape(5, 2)
        left_eye, right_eye, nose, left_mouth, right_mouth = points
        eye_distance = float(np.linalg.norm(right_eye - left_eye))
        mouth_center = (left_mouth + right_mouth) / 2
        vertical_distance = float(np.linalg.norm(mouth_center - (left_eye + right_eye) / 2))
        if eye_distance < 1 or vertical_distance < 1:
            return None, None, None
        roll = float(np.degrees(np.arctan2(right_eye[1] - left_eye[1], right_eye[0] - left_eye[0])))
        yaw_proxy = float((nose[0] - (left_eye[0] + right_eye[0]) / 2) / eye_distance)
        expected_nose_y = ((left_eye[1] + right_eye[1]) / 2 + mouth_center[1]) / 2
        pitch_proxy = float((nose[1] - expected_nose_y) / vertical_distance)
        return roll, yaw_proxy, pitch_proxy
