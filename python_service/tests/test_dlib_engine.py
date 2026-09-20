import sys
import types

import numpy as np
import pytest

from attendpro_recognition import dlib_engine
from attendpro_recognition.dlib_engine import DlibEngine, MODEL_NAME
from attendpro_recognition.errors import RecognitionServiceError
from attendpro_recognition.face_index import FaceIndex


def _install_fake_face_recognition(locations, encodings):
    module = types.ModuleType("face_recognition")
    module.face_locations = lambda *a, **k: locations
    module.face_encodings = lambda *a, **k: encodings
    sys.modules["face_recognition"] = module
    return module


def _jpeg_bytes(width=320, height=320):
    import cv2

    image = np.full((height, width, 3), 128, dtype=np.uint8)
    ok, buffer = cv2.imencode(".jpg", image)
    assert ok
    return bytes(buffer.tobytes())


def test_dlib_engine_extracts_normalized_embedding(monkeypatch):
    monkeypatch.delitem(sys.modules, "face_recognition", raising=False)
    monkeypatch.setattr(dlib_engine, "_load_face_recognition", lambda: _install_fake_face_recognition(
        [(50, 200, 180, 60)], [np.ones(128, dtype=np.float64)],
    ))
    engine = DlibEngine(validate_quality=False) if False else DlibEngine()
    extracted = engine.extract(_jpeg_bytes(), validate_quality=False)

    assert len(extracted.embedding) == 128
    assert extracted.bounding_box == (60, 50, 140, 130)
    assert extracted.detection_score == 1.0
    assert abs(float(np.linalg.norm(np.asarray(extracted.embedding))) - 1.0) < 1e-5


def test_dlib_engine_error_codes_match_sface_contract(monkeypatch):
    monkeypatch.delitem(sys.modules, "face_recognition", raising=False)
    engine = DlibEngine()

    monkeypatch.setattr(dlib_engine, "_load_face_recognition", lambda: _install_fake_face_recognition([], []))
    with pytest.raises(RecognitionServiceError) as exc:
        engine.extract(_jpeg_bytes(), validate_quality=False)
    assert exc.value.code == "no_face"

    monkeypatch.setattr(
        dlib_engine, "_load_face_recognition",
        lambda: _install_fake_face_recognition([(0, 10, 10, 0), (0, 20, 20, 10)], []),
    )
    with pytest.raises(RecognitionServiceError) as exc:
        engine.extract(_jpeg_bytes(), validate_quality=False)
    assert exc.value.code == "multiple_faces"


def test_face_index_isolates_backends_by_model_name():
    sface_index = FaceIndex(model_name="opencv_sface_2021dec")
    dlib_index = FaceIndex(model_name=MODEL_NAME, metric="euclidean")
    record = {
        "profile_id": 1, "person_id": 1, "institution_id": "2026-0001",
        "full_name": "Student One", "person_type": "student",
        "model": MODEL_NAME, "dimensions": 128,
        "embedding": (np.ones(128) / np.linalg.norm(np.ones(128))).tolist(),
        "version": 1,
    }
    assert sface_index.replace([record]) == 0
    assert dlib_index.replace([record]) == 1


def test_euclidean_metric_accepts_genuine_and_rejects_impostor():
    index = FaceIndex(model_name=MODEL_NAME, metric="euclidean")
    base = np.zeros(128, dtype=np.float32)
    base[0] = 1.0
    genuine = base + 0.02 * np.ones(128, dtype=np.float32)
    genuine /= np.linalg.norm(genuine)
    impostor = np.zeros(128, dtype=np.float32)
    impostor[1] = 1.0
    index.replace([{
        "profile_id": 1, "person_id": 1, "institution_id": "2026-0001",
        "full_name": "Student One", "person_type": "student",
        "model": MODEL_NAME, "dimensions": 128,
        "embedding": base.tolist(), "version": 1,
    }])

    assert index.best_match(genuine.tolist()).confidence > 0.65
    assert index.best_match(impostor.tolist()).confidence < 0.65
