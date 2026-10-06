"""Call BananaShield's FastAPI plant-part endpoint without involving Laravel."""

from __future__ import annotations

import argparse
import mimetypes
import os
from pathlib import Path
import sys

import httpx
from dotenv import load_dotenv


ALLOWED_PARTS = {
    "Leaf",
    "Pseudostem",
    "Crown / Upper Leaves",
    "Whole Plant",
    "Unknown",
}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Send one image directly to BananaShield POST /detect-part."
    )
    parser.add_argument("image", type=Path, help="Path to a JPG, PNG, or WebP image")
    parser.add_argument(
        "--url",
        default="http://127.0.0.1:8001",
        help="FastAPI base URL (default: %(default)s)",
    )
    parser.add_argument(
        "--token",
        default=None,
        help="AI service token; defaults to AI_SERVICE_TOKEN from ai-service/.env",
    )
    parser.add_argument("--timeout", type=float, default=30.0)
    return parser.parse_args()


def main() -> int:
    load_dotenv(Path(__file__).resolve().parents[1] / ".env")
    args = parse_args()
    image_path = args.image.expanduser().resolve()
    if not image_path.is_file():
        print(f"Image not found: {image_path}", file=sys.stderr)
        return 2

    token = args.token or os.getenv("AI_SERVICE_TOKEN", "")
    if not token:
        print("Set AI_SERVICE_TOKEN in ai-service/.env or pass --token.", file=sys.stderr)
        return 2

    mime_type = mimetypes.guess_type(image_path.name)[0]
    if mime_type not in {"image/jpeg", "image/png", "image/webp"}:
        print("Image must be a JPG, PNG, or WebP file.", file=sys.stderr)
        return 2

    endpoint = f"{args.url.rstrip('/')}/detect-part"
    try:
        with image_path.open("rb") as image_file:
            response = httpx.post(
                endpoint,
                headers={"X-AI-Token": token, "Accept": "application/json"},
                files={"file": (image_path.name, image_file, mime_type)},
                timeout=args.timeout,
            )
    except httpx.RequestError as exc:
        print(f"Could not reach FastAPI: {exc}", file=sys.stderr)
        return 1

    try:
        payload = response.json()
    except ValueError:
        print(f"FastAPI returned non-JSON HTTP {response.status_code}.", file=sys.stderr)
        return 1

    print(f"HTTP {response.status_code}")
    for key in ("success", "is_banana_image", "part", "usable", "message"):
        print(f"{key}: {payload.get(key)!r}")

    if response.status_code != 200:
        return 1
    if payload.get("success") is not True:
        return 1
    if payload.get("part") not in ALLOWED_PARTS:
        print("Endpoint returned an unsupported part category.", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
