from __future__ import annotations

from .model_manager import ModelManager
from .settings import Settings


def main() -> None:
    settings = Settings.load()
    if settings.face_backend == "insightface":
        from .insightface_engine import InsightFaceEngine

        engine = InsightFaceEngine(
            settings.model_dir,
            settings.insightface_pack,
            settings.insightface_det_size,
            settings.insightface_ctx_id,
        )
        engine.models.ensure()
        print(f"InsightFace backend ready ({engine.models.pack} pack in {engine.models.model_dir}).")
        return
    if settings.face_backend == "dlib":
        from .dlib_engine import DlibEngine

        engine = DlibEngine(settings.dlib_detector, settings.dlib_upsample, settings.dlib_jitters)
        engine.models.ensure()
        print("dlib backend ready (face_recognition-master + face_recognition_models).")
        return
    detector, recognizer = ModelManager(settings.model_dir).ensure()
    print(f"YuNet model ready: {detector}")
    print(f"SFace model ready: {recognizer}")


if __name__ == "__main__":
    main()
