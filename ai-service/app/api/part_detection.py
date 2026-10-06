import logging

from fastapi import APIRouter, Depends, File, UploadFile
from fastapi.responses import JSONResponse

from app.dependencies import authorize
from app.schemas.part_detection import PartDetectionResult
from app.services.gemini_service import GeminiServiceError, detect_part
from app.utilities.image_validation import read_validated_image


logger = logging.getLogger(__name__)
router = APIRouter(tags=["plant-part detection"])


def _provider_unavailable_response() -> JSONResponse:
    """Return the stable failure contract expected by the Laravel boundary."""
    return JSONResponse(
        status_code=503,
        content={
            "success": False,
            "is_banana_image": False,
            "part": "Unknown",
            "usable": False,
            "message": "Image analysis is temporarily unavailable. Please try again.",
        },
    )


@router.post(
    "/detect-part",
    response_model=PartDetectionResult,
    dependencies=[Depends(authorize)],
)
async def detect_banana_part(file: UploadFile = File(...)):
    raw = await read_validated_image(file)
    try:
        return await detect_part(raw, file.content_type or "application/octet-stream")
    except GeminiServiceError:
        logger.exception("Plant-part detection failed")
        return _provider_unavailable_response()
    except Exception:
        # Keep provider/library defects behind the same stable API boundary. The
        # exception is logged server-side, but no provider detail or key is sent
        # to Laravel or the browser.
        logger.exception("Unexpected plant-part detection failure")
        return _provider_unavailable_response()
