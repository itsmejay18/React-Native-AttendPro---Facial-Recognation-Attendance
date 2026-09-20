from __future__ import annotations

import threading
from typing import Any

import requests

from .errors import LaravelApiError


class LaravelClient:
    def __init__(self, base_url: str, service_key: str, timeout: float = 15):
        self.base_url = base_url.rstrip("/")
        self.service_key = service_key
        self.timeout = timeout
        self._session = requests.Session()
        self._lock = threading.RLock()

    def health(self) -> dict[str, Any]:
        return self._request("GET", "/health", authenticated=False)

    def configuration(self) -> dict[str, Any]:
        return self._request("GET", "/recognition/configuration")["data"]

    def fetch_profiles(self) -> list[dict[str, Any]]:
        profiles: list[dict[str, Any]] = []
        page = 1
        while True:
            response = self._request("GET", "/faces", params={"page": page, "per_page": 500})
            profiles.extend(response.get("data", []))
            meta = response.get("meta", {})
            if page >= int(meta.get("last_page", page)):
                return profiles
            page += 1

    def enroll(
        self,
        institution_id: str,
        embedding: list[float],
        model: str,
        consented_at: str,
        retention_until: str | None,
        enrolled_by: int | None = None,
    ) -> dict[str, Any]:
        payload: dict[str, Any] = {
            "institution_id": institution_id,
            "embedding": embedding,
            "model": model,
            "consented_at": consented_at,
        }
        if retention_until:
            payload["retention_until"] = retention_until
        if enrolled_by is not None:
            payload["enrolled_by"] = enrolled_by

        return self._request("POST", "/faces/enroll", json=payload)

    def submit_recognition(self, payload: dict[str, Any]) -> dict[str, Any]:
        return self._request("POST", "/recognition/events", json=payload)

    def _request(self, method: str, path: str, *, authenticated: bool = True, **kwargs) -> dict[str, Any]:
        headers = {"Accept": "application/json", "User-Agent": "AttendPro-Python/1.0"}
        if authenticated:
            if not self.service_key:
                raise LaravelApiError("ATTENDPRO_PYTHON_SERVICE_KEY is not configured.", status_code=503)
            headers["X-AttendPro-Service-Key"] = self.service_key

        try:
            with self._lock:
                response = self._session.request(
                    method,
                    f"{self.base_url}{path}",
                    headers=headers,
                    timeout=self.timeout,
                    **kwargs,
                )
        except requests.Timeout as exc:
            raise LaravelApiError("Laravel did not respond before the request timeout.") from exc
        except requests.RequestException as exc:
            raise LaravelApiError(f"The Laravel API is unavailable: {exc}") from exc

        try:
            body = response.json()
        except ValueError:
            body = None

        if not response.ok:
            message = body.get("message") if isinstance(body, dict) else None
            raise LaravelApiError(
                message or f"Laravel returned HTTP {response.status_code}.",
                status_code=422 if response.status_code in (401, 403, 404, 422) else 502,
                response_status=response.status_code,
                details=body,
            )

        if not isinstance(body, dict):
            raise LaravelApiError("Laravel returned an invalid JSON response.")

        return body
