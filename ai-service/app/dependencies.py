import os

from fastapi import Header, HTTPException


def authorize(x_ai_token: str = Header(default="")) -> None:
    """Authenticate server-to-server requests from the Laravel application."""
    expected_token = os.getenv("AI_SERVICE_TOKEN", "change-me")
    if not x_ai_token or x_ai_token != expected_token:
        raise HTTPException(status_code=401, detail="Unauthorized service token")
