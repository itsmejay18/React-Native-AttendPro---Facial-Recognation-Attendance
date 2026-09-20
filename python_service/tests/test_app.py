from pathlib import Path

from fastapi.testclient import TestClient

from attendpro_recognition.app import create_app
from attendpro_recognition.settings import Settings


class FakeService:
    def warm_up(self):
        return None

    def status(self):
        return {"status": "ready", "configured": True, "profiles_loaded": 1}

    def sync(self, force=False):
        return {"profiles": 1, "cached": not force}

    def recognize(self, image, direction):
        return {"result": "matched", "action": "time_in", "received": len(image), "direction": direction}

    def recognize_many(self, images, direction):
        return {"result": "matched", "action": "time_in", "received": len(images), "direction": direction}

    def extract(self, image):
        return {"embedding": [1.0, 0.0, 0.0], "model": "test-model", "dimensions": 3, "received": len(image)}

    def extract_many(self, images):
        return {"samples": [{"embedding": [1.0, 0.0, 0.0], "model": "test-model", "dimensions": 3}], "received": len(images)}

    def index_profile(self, profile):
        return {"profiles": 1, "institution_id": profile["institution_id"]}

    def index_profiles(self, profiles):
        return {"profiles": len(profiles)}

    def enroll(self, image, institution_id, consented_at, retention_until, enrolled_by=None):
        return {"message": "Enrolled", "profile": {"institution_id": institution_id}, "received": len(image)}


def configured_settings(tmp_path: Path) -> Settings:
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


def test_service_key_and_multipart_recognition(tmp_path):
    app = create_app(configured_settings(tmp_path), FakeService())
    with TestClient(app) as client:
        assert client.get("/v1/health").status_code == 401
        response = client.post(
            "/v1/recognize",
            headers={"X-AttendPro-Service-Key": "secret"},
            files={"image": ("capture.jpg", b"fake-image", "image/jpeg")},
            data={"direction": "auto"},
        )

    assert response.status_code == 200
    assert response.json()["data"]["result"] == "matched"


def test_extract_and_index_profile_endpoints(tmp_path):
    app = create_app(configured_settings(tmp_path), FakeService())
    with TestClient(app) as client:
        extraction = client.post(
            "/v1/extract",
            headers={"X-AttendPro-Service-Key": "secret"},
            files={"image": ("capture.jpg", b"fake-image", "image/jpeg")},
        )
        indexed = client.post(
            "/v1/index-profile",
            headers={"X-AttendPro-Service-Key": "secret"},
            json={"institution_id": "2026-0001"},
        )

    assert extraction.status_code == 200
    assert extraction.json()["data"]["embedding"] == [1.0, 0.0, 0.0]
    assert indexed.status_code == 200
    assert indexed.json()["data"]["profiles"] == 1


def test_batch_endpoints_accept_multiple_camera_frames(tmp_path):
    app = create_app(configured_settings(tmp_path), FakeService())
    headers = {"X-AttendPro-Service-Key": "secret"}
    files = [("images", ("capture.jpg", b"fake-image", "image/jpeg")) for _ in range(3)]
    with TestClient(app) as client:
        recognition = client.post("/v1/recognize-batch", headers=headers, files=files, data={"direction": "auto"})
        extraction = client.post("/v1/extract-batch", headers=headers, files=files)
        indexed = client.post("/v1/index-profiles", headers=headers, json=[{"institution_id": "2026-0001"}])

    assert recognition.status_code == 200
    assert recognition.json()["data"]["received"] == 3
    assert extraction.status_code == 200
    assert extraction.json()["data"]["received"] == 3
    assert indexed.status_code == 200
