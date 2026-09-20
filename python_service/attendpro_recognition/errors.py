from __future__ import annotations

from typing import Any


class RecognitionServiceError(Exception):
    def __init__(self, message: str, *, code: str = "recognition_error", status_code: int = 422):
        super().__init__(message)
        self.code = code
        self.status_code = status_code


class LaravelApiError(RecognitionServiceError):
    def __init__(
        self,
        message: str,
        *,
        status_code: int = 502,
        response_status: int | None = None,
        details: Any = None,
    ):
        super().__init__(message, code="laravel_api_error", status_code=status_code)
        self.response_status = response_status
        self.details = details
