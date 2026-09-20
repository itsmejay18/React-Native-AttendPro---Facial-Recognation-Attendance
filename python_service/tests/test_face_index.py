from attendpro_recognition.face_engine import MODEL_NAME
from attendpro_recognition.face_index import FaceIndex


def test_index_filters_other_models_and_finds_best_match():
    index = FaceIndex()
    count = index.replace([
        {
            "person_id": 1,
            "institution_id": "2026-0001",
            "full_name": "Student One",
            "person_type": "student",
            "model": MODEL_NAME,
            "dimensions": 3,
            "embedding": [1.0, 0.0, 0.0],
            "version": 1,
        },
        {
            "person_id": 2,
            "institution_id": "2026-0002",
            "full_name": "Wrong Model",
            "person_type": "student",
            "model": "some-other-model",
            "dimensions": 3,
            "embedding": [0.0, 1.0, 0.0],
            "version": 1,
        },
    ])

    match = index.best_match([0.99, 0.01, 0.0])

    assert count == 1
    assert match is not None
    assert match.profile.institution_id == "2026-0001"
    assert match.confidence > 0.99


def test_index_keeps_multiple_samples_per_person_and_reports_second_best_person():
    index = FaceIndex()
    index.replace([
        {"profile_id": 11, "person_id": 1, "institution_id": "A", "full_name": "A", "person_type": "student", "model": MODEL_NAME, "dimensions": 3, "embedding": [1, 0, 0], "version": 1},
        {"profile_id": 12, "person_id": 1, "institution_id": "A", "full_name": "A", "person_type": "student", "model": MODEL_NAME, "dimensions": 3, "embedding": [0.98, 0.2, 0], "version": 1},
        {"profile_id": 21, "person_id": 2, "institution_id": "B", "full_name": "B", "person_type": "student", "model": MODEL_NAME, "dimensions": 3, "embedding": [0.8, 0.6, 0], "version": 1},
    ])

    match = index.best_match([0.99, 0.1, 0])

    assert index.count == 3
    assert match is not None
    assert match.profile.institution_id == "A"
    assert match.second_profile is not None
    assert match.second_profile.institution_id == "B"
    assert match.margin > 0


def test_remove_person_drops_stale_reenrollment_samples():
    index = FaceIndex()
    index.replace([
        {"profile_id": 11, "person_id": 1, "institution_id": "A", "full_name": "A", "person_type": "student", "model": MODEL_NAME, "dimensions": 3, "embedding": [1, 0, 0], "version": 1},
        {"profile_id": 21, "person_id": 2, "institution_id": "B", "full_name": "B", "person_type": "student", "model": MODEL_NAME, "dimensions": 3, "embedding": [0, 1, 0], "version": 1},
    ])

    assert index.remove_person(1) == 1
    assert index.best_match([1, 0, 0]).profile.institution_id == "B"
