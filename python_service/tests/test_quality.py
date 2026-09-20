import numpy as np
import pytest

from attendpro_recognition.errors import RecognitionServiceError
from attendpro_recognition.quality import FaceQualityValidator


def validator():
    return FaceQualityValidator(
        min_face_ratio=0.1, min_face_width=20, min_face_height=20, min_blur_score=1,
        min_brightness=20, max_brightness=230, max_roll=30, max_yaw_proxy=0.8, max_pitch_proxy=0.8,
    )


def face():
    return [10, 10, 60, 60, 25, 30, 55, 30, 40, 42, 28, 58, 52, 58, 0.99]


def test_quality_returns_metrics_for_a_clear_well_lit_face():
    image = (((np.indices((100, 100)).sum(axis=0) % 2) * 120) + 60).astype(np.uint8)
    image = np.repeat(image[:, :, None], 3, axis=2)
    quality = validator().validate(image, face())
    assert quality.face_width == 60
    assert quality.blur_score > 1


def test_quality_rejects_underexposed_image():
    with pytest.raises(RecognitionServiceError, match="too dark"):
        validator().validate(np.zeros((100, 100, 3), dtype=np.uint8), face())
