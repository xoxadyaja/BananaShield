from io import BytesIO
import os

from fastapi import HTTPException, UploadFile
from PIL import Image, UnidentifiedImageError


ALLOWED_IMAGE_TYPES = {"image/jpeg", "image/png", "image/webp"}
MAX_IMAGE_BYTES = int(os.getenv("MAX_IMAGE_SIZE_MB", "5")) * 1024 * 1024


async def read_validated_image(file: UploadFile) -> bytes:
    if file.content_type not in ALLOWED_IMAGE_TYPES:
        raise HTTPException(
            status_code=422,
            detail="Unsupported image format. Upload a JPG, PNG, or WebP image.",
        )

    raw = await file.read(MAX_IMAGE_BYTES + 1)
    if not raw:
        raise HTTPException(status_code=422, detail="The uploaded image is empty.")
    if len(raw) > MAX_IMAGE_BYTES:
        max_size_mb = MAX_IMAGE_BYTES // (1024 * 1024)
        raise HTTPException(
            status_code=422,
            detail=f"Image exceeds the {max_size_mb} MB upload limit.",
        )

    try:
        with Image.open(BytesIO(raw)) as image:
            image.verify()
    except (UnidentifiedImageError, OSError, ValueError) as exc:
        raise HTTPException(
            status_code=422,
            detail="The uploaded file is corrupted or cannot be read as an image.",
        ) from exc

    return raw
