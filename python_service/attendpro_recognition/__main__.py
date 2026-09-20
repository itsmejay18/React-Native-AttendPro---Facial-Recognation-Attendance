from __future__ import annotations

import uvicorn

from .settings import Settings


def main() -> None:
    settings = Settings.load()
    uvicorn.run(
        "attendpro_recognition.app:app",
        host=settings.host,
        port=settings.port,
        reload=False,
        access_log=True,
    )


if __name__ == "__main__":
    main()
