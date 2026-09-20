# AttendPro Python Recognition Service

This service is the facial-recognition backend for the camera hosted in Laravel. It is not a second user interface and it does not own attendance records.

## Responsibilities

- Receive a captured frame only from Laravel's authenticated bridge
- Revalidate that exactly one face is present (InsightFace buffalo_l SCRFD by default)
- Align the face and extract a normalized embedding (512-d ArcFace R100 by default)
- Keep active Laravel facial profiles in an in-memory index
- Select the closest cosine match and apply Laravel's configured confidence threshold
- Return face-match data to Laravel, which applies the program, room, and time attendance rules
- Create consented facial enrollments through Laravel and immediately refresh the in-memory index

Raw camera images are processed in memory and are never written to disk. The ONNX model files are the only files stored in `models/`.

## Setup

Use `setup.sh` and `start.sh` on Linux/macOS or `setup.bat` and `start.bat` on Windows. Both launchers resolve paths from their own directory, so they work regardless of the terminal's current working directory. On Windows, `start.bat` repairs an incomplete first-time setup and configures the local Laravel-to-Python connection automatically.

To start the complete single-device Windows application, double-click the repository-root `start-attendpro.bat` instead. It starts Laravel at `127.0.0.1:8000`, starts this service at `127.0.0.1:5001`, and opens Laravel's Recognition Center.

The repository root `.env` is loaded first. An optional `python_service/.env` can override Python-only settings.

Required settings:

```dotenv
ATTENDPRO_LARAVEL_API_URL=http://127.0.0.1:8000/api/v1
ATTENDPRO_PYTHON_SERVICE_KEY=<same secret configured in Laravel>
```

Laravel controls facial-profile access, attendance schedules, rooms, and time windows. Python only identifies faces and returns the result to Laravel.

## Face backend option

`ATTENDPRO_FACE_BACKEND` selects the engine (default `insightface`):

| Backend | Engine | Install | Notes |
| --- | --- | --- | --- |
| `insightface` | `insightface-master/` buffalo_l (SCRFD detection + ArcFace R100 512-d) | `pip install -r requirements-insightface.txt` (setup default) | Best accuracy; pack weights (~300–500 MB) auto-download to `models/` on first run; uses the vendored `insightface-master/` folder so no PyPI `insightface` build is needed |
| `sface` | OpenCV YuNet detection + SFace embedding | `requirements.txt` | Lightweight, no compiler needed, ONNX models auto-download to `models/` |
| `dlib` | `face_recognition-master/` (dlib ResNet, 99.38% LFW) | `pip install -r requirements-dlib.txt` (`dlib-bin` + `face_recognition_models` on Windows) | Higher accuracy than SFace, slower on CPU; uses the vendored `face_recognition-master/` folder so no PyPI `face_recognition` build is needed |

Tuning for dlib: `ATTENDPRO_DLIB_DETECTOR=hog|cnn` (`hog` = fast CPU, `cnn` = accurate, needs GPU/CUDA), `ATTENDPRO_DLIB_UPSAMPLE=0-3`, `ATTENDPRO_DLIB_JITTERS=1-100`.
Switching backends changes the embedding space: re-enroll faces afterwards (the index only matches profiles with the active `model` name). The `/v1/health` response reports the active `backend`, `model`, and `capability` (`opencv-sface` vs `dlib-face-recognition`).

dlib confidence scale: the index compares euclidean face distance (`confidence = 1 - distance`, per the library's distance semantics). The default `ATTENDPRO_MIN_CONFIDENCE=0.65` therefore accepts distances up to ~0.35 (stricter than the library's classic 0.6 tolerance). Lower the threshold toward `0.40` for behavior closer to the upstream default, and always calibrate with the held-out evaluation below.

## Internal endpoints

All endpoints require `X-AttendPro-Service-Key`.

| Method | Path | Description |
| --- | --- | --- |
| `GET` | `/v1/health` | Service, configuration, model, and profile-cache status |
| `POST` | `/v1/sync` | Force an immediate Laravel facial-profile refresh |
| `POST` | `/v1/recognize` | Process one multipart camera frame and submit attendance |
| `POST` | `/v1/enroll` | Process one frame and create a consented facial profile |

The service intentionally disables interactive FastAPI documentation and binds to `127.0.0.1:5001` by default.

## Tests

```bash
cd python_service
.venv/bin/python -m pytest
```

Windows:

```bat
cd python_service
.venv\Scripts\python.exe -m pytest
```
