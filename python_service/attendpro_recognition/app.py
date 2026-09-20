from __future__ import annotations

import asyncio
import secrets
from contextlib import asynccontextmanager, suppress
from typing import Annotated, AsyncIterator

from fastapi import Body, Depends, FastAPI, File, Form, Header, Request, UploadFile
from fastapi.concurrency import run_in_threadpool
from fastapi.responses import JSONResponse

from .errors import RecognitionServiceError
from .face_engine import FaceEngine
from .face_index import FaceIndex
from .laravel_client import LaravelClient
from .model_manager import ModelManager
from .service import RecognitionService
from .settings import Settings


def build_service(settings: Settings) -> RecognitionService:
    client = LaravelClient(settings.laravel_api_url, settings.service_key, settings.request_timeout)
    if settings.face_backend == "dlib":
        from .dlib_engine import DlibEngine

        engine = DlibEngine(
            settings.dlib_detector,
            settings.dlib_upsample,
            settings.dlib_jitters,
            settings.minimum_face_ratio,
            min_face_width=settings.min_face_width,
            min_face_height=settings.min_face_height,
            min_blur_score=settings.min_blur_score,
            min_brightness=settings.min_brightness,
            max_brightness=settings.max_brightness,
            max_roll=settings.max_roll,
            max_yaw_proxy=settings.max_yaw_proxy,
            max_pitch_proxy=settings.max_pitch_proxy,
        )
    elif settings.face_backend == "insightface":
        from .insightface_engine import InsightFaceEngine

        engine = InsightFaceEngine(
            settings.model_dir,
            settings.insightface_pack,
            settings.insightface_det_size,
            settings.insightface_ctx_id,
            settings.minimum_face_ratio,
            min_face_width=settings.min_face_width,
            min_face_height=settings.min_face_height,
            min_blur_score=settings.min_blur_score,
            min_brightness=settings.min_brightness,
            max_brightness=settings.max_brightness,
            max_roll=settings.max_roll,
            max_yaw_proxy=settings.max_yaw_proxy,
            max_pitch_proxy=settings.max_pitch_proxy,
        )
    else:
        engine = FaceEngine(
            ModelManager(settings.model_dir),
            settings.detection_score,
            settings.minimum_face_ratio,
            min_face_width=settings.min_face_width,
            min_face_height=settings.min_face_height,
            min_blur_score=settings.min_blur_score,
            min_brightness=settings.min_brightness,
            max_brightness=settings.max_brightness,
            max_roll=settings.max_roll,
            max_yaw_proxy=settings.max_yaw_proxy,
            max_pitch_proxy=settings.max_pitch_proxy,
        )
    index = FaceIndex(
        model_name=getattr(engine, "MODEL_NAME", None),
        metric=getattr(engine, "METRIC", "cosine"),
    )
    return RecognitionService(settings, engine, client, index)


def create_app(settings: Settings | None = None, service: RecognitionService | None = None) -> FastAPI:
    resolved_settings = settings or Settings.load()
    resolved_service = service or build_service(resolved_settings)

    @asynccontextmanager
    async def lifespan(_: FastAPI) -> AsyncIterator[None]:
        warmup = asyncio.create_task(asyncio.to_thread(resolved_service.warm_up))
        yield
        if not warmup.done():
            warmup.cancel()
            with suppress(asyncio.CancelledError):
                await warmup

    application = FastAPI(
        title="AttendPro Facial Recognition Service",
        version="1.0.0",
        docs_url=None,
        redoc_url=None,
        lifespan=lifespan,
    )
    application.state.settings = resolved_settings
    application.state.recognition = resolved_service

    async def authorize(
        x_attendpro_service_key: Annotated[str | None, Header()] = None,
    ) -> None:
        expected = resolved_settings.service_key
        if not expected:
            raise RecognitionServiceError(
                "ATTENDPRO_PYTHON_SERVICE_KEY is not configured.",
                code="service_not_configured",
                status_code=503,
            )
        if not x_attendpro_service_key or not secrets.compare_digest(x_attendpro_service_key, expected):
            raise RecognitionServiceError("The Python service key is invalid.", code="unauthorized", status_code=401)

    async def image_bytes(image: UploadFile) -> bytes:
        if image.content_type not in {"image/jpeg", "image/png", "image/webp"}:
            raise RecognitionServiceError("The camera frame must be JPEG, PNG, or WebP.", code="invalid_media_type")
        content = await image.read(resolved_settings.max_image_bytes + 1)
        if not content:
            raise RecognitionServiceError("The camera frame is empty.", code="empty_image")
        if len(content) > resolved_settings.max_image_bytes:
            raise RecognitionServiceError("The camera frame exceeds the configured size limit.", code="image_too_large", status_code=413)
        return content

    async def image_batch(images: list[UploadFile]) -> list[bytes]:
        if not images:
            raise RecognitionServiceError("At least one camera frame is required.", code="empty_frame_batch")
        if len(images) > resolved_settings.max_batch_images:
            raise RecognitionServiceError("Too many camera frames were supplied.", code="frame_batch_too_large", status_code=413)
        return [await image_bytes(image) for image in images]

    @application.exception_handler(RecognitionServiceError)
    async def handle_service_error(_: Request, exc: RecognitionServiceError) -> JSONResponse:
        return JSONResponse(
            status_code=exc.status_code,
            content={"message": str(exc), "error": {"code": exc.code}},
        )

    @application.get("/v1/health", dependencies=[Depends(authorize)])
    async def health() -> dict:
        return {"data": resolved_service.status()}

    @application.post("/v1/sync", dependencies=[Depends(authorize)])
    async def sync() -> dict:
        return {"message": "Facial profiles synchronized.", "data": await run_in_threadpool(resolved_service.sync, force=True)}

    @application.post("/v1/recognize", dependencies=[Depends(authorize)])
    async def recognize(
        image: Annotated[UploadFile, File()],
        direction: Annotated[str, Form()] = "auto",
    ) -> dict:
        content = await image_bytes(image)
        result = await run_in_threadpool(resolved_service.recognize, content, direction)
        return {"message": "Camera frame processed.", "data": result}

    @application.post("/v1/recognize-batch", dependencies=[Depends(authorize)])
    async def recognize_batch(
        images: Annotated[list[UploadFile], File()],
        direction: Annotated[str, Form()] = "auto",
    ) -> dict:
        result = await run_in_threadpool(resolved_service.recognize_many, await image_batch(images), direction)
        return {"message": "Camera frames processed.", "data": result}

    @application.post("/v1/extract", dependencies=[Depends(authorize)])
    async def extract(image: Annotated[UploadFile, File()]) -> dict:
        content = await image_bytes(image)
        result = await run_in_threadpool(resolved_service.extract, content)
        return {"message": "Face embedding extracted.", "data": result}

    @application.post("/v1/preview", dependencies=[Depends(authorize)])
    async def preview(image: Annotated[UploadFile, File()]) -> dict:
        """Return transient InsightFace landmarks for the live camera overlay.

        The result intentionally contains no embedding, identity, or database
        information. It is visual guidance only; recognition remains on the
        existing scan and enrollment endpoints.
        """
        content = await image_bytes(image)
        result = await run_in_threadpool(resolved_service.preview, content)
        return {"message": "Camera landmarks processed.", "data": result}

    @application.post("/v1/extract-batch", dependencies=[Depends(authorize)])
    async def extract_batch(images: Annotated[list[UploadFile], File()]) -> dict:
        result = await run_in_threadpool(resolved_service.extract_many, await image_batch(images))
        return {"message": "Enrollment frames processed.", "data": result}

    @application.post("/v1/index-profile", dependencies=[Depends(authorize)])
    async def index_profile(profile: Annotated[dict, Body()]) -> dict:
        result = await run_in_threadpool(resolved_service.index_profile, profile)
        return {"message": "Facial profile added to local index.", "data": result}

    @application.post("/v1/index-profiles", dependencies=[Depends(authorize)])
    async def index_profiles(profiles: Annotated[list[dict], Body()]) -> dict:
        result = await run_in_threadpool(resolved_service.index_profiles, profiles)
        return {"message": "Facial profiles added to local index.", "data": result}

    @application.post("/v1/enroll", dependencies=[Depends(authorize)])
    async def enroll(
        image: Annotated[UploadFile, File()],
        institution_id: Annotated[str, Form()],
        consented_at: Annotated[str, Form()],
        retention_until: Annotated[str | None, Form()] = None,
        enrolled_by: Annotated[int | None, Form()] = None,
    ) -> dict:
        content = await image_bytes(image)
        result = await run_in_threadpool(
            resolved_service.enroll,
            content,
            institution_id,
            consented_at,
            retention_until or None,
            enrolled_by,
        )
        return {"message": result["message"], "data": result}

    return application


app = create_app()
