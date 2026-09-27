import base64
import json
import os
from typing import Any

import httpx
from pydantic import ValidationError

from app.schemas.part_detection import GeminiPartDetection, PartDetectionResult


SYSTEM_INSTRUCTION = """You are the banana plant image-view identification component of BananaShield.

Your ONLY responsibility is to determine what banana plant part or image view is primarily visible in the submitted image. You MUST NOT identify, classify, suggest, or diagnose any banana disease.

First determine whether the image contains a recognizable banana plant or banana plant part. Then select exactly ONE category: Leaf, Pseudostem, Crown / Upper Leaves, Whole Plant, or Unknown.

Use Leaf when a banana leaf, leaf surface, underside, margin, tip, or close-up leaf section is the main subject. Use Pseudostem when the trunk-like pseudostem or its base is the main subject. Use Crown / Upper Leaves for the crown, emerging leaves, or the upper portion of the plant. Use Whole Plant only when most or all of the banana plant is clearly visible and the photograph is intended to show its overall appearance.

Select Unknown when the image is not a banana plant, shows another plant, is too dark, overexposed, blurry, obstructed, unsupported, or cannot be classified reliably. Never force an uncertain image into another category. Return structured JSON only and do not return additional categories."""

PART_ALIASES = {
    "leaf": "Leaf",
    "banana leaf": "Leaf",
    "leaf blade": "Leaf",
    "leaf surface": "Leaf",
    "leaf underside": "Leaf",
    "stem": "Pseudostem",
    "banana stem": "Pseudostem",
    "pseudostem": "Pseudostem",
    "trunk": "Pseudostem",
    "crown": "Crown / Upper Leaves",
    "upper leaves": "Crown / Upper Leaves",
    "crown / upper leaves": "Crown / Upper Leaves",
    "top portion": "Crown / Upper Leaves",
    "whole plant": "Whole Plant",
    "plant": "Whole Plant",
    "banana plant": "Whole Plant",
    "banana tree": "Whole Plant",
    "unknown": "Unknown",
}


class GeminiServiceError(RuntimeError):
    """Raised when Gemini cannot produce a trustworthy structured result."""


def _mock_result() -> PartDetectionResult:
    scenario = os.getenv("PART_DETECTION_MOCK_SCENARIO", "Leaf").strip()
    if scenario == "invalid_image":
        return PartDetectionResult(
            is_banana_image=False,
            part="Unknown",
            usable=False,
            message="The submitted image does not appear to contain a valid banana plant.",
        )
    if scenario == "unclear_image":
        return PartDetectionResult(
            is_banana_image=True,
            part="Unknown",
            usable=False,
            message="A banana plant may be present, but the image is too unclear to determine the plant part.",
        )

    allowed = {"Leaf", "Pseudostem", "Crown / Upper Leaves", "Whole Plant"}
    part = scenario if scenario in allowed else "Leaf"
    return PartDetectionResult(
        is_banana_image=True,
        part=part,
        usable=True,
        message=f"The primary banana plant view is {part}.",
    )


def _normalize_payload(payload: dict[str, Any]) -> PartDetectionResult:
    normalized_part = PART_ALIASES.get(str(payload.get("part", "Unknown")).strip().lower(), "Unknown")
    is_banana = payload.get("is_banana_image") is True
    usable = payload.get("usable") is True and is_banana and normalized_part != "Unknown"
    message = str(payload.get("message") or "").strip()

    if not is_banana:
        normalized_part = "Unknown"
        usable = False
        message = message or "The submitted image does not appear to contain a valid banana plant."
    elif normalized_part == "Unknown":
        usable = False
        message = message or "A banana plant may be present, but the image is too unclear to determine the plant part."
    elif not message:
        message = f"The primary banana plant view is {normalized_part}."

    try:
        return PartDetectionResult(
            is_banana_image=is_banana,
            part=normalized_part,
            usable=usable,
            message=message[:500],
        )
    except ValidationError as exc:
        raise GeminiServiceError("Gemini returned an invalid part-detection response.") from exc


def _extract_output_text(payload: dict[str, Any]) -> str:
    output_text = payload.get("output_text")
    if isinstance(output_text, str) and output_text.strip():
        return output_text

    for step in reversed(payload.get("steps", [])):
        if step.get("type") != "model_output":
            continue
        for block in step.get("content", []):
            if block.get("type") == "text" and isinstance(block.get("text"), str):
                return block["text"]
    raise GeminiServiceError("Gemini returned no structured output.")


async def detect_part(image_bytes: bytes, mime_type: str) -> PartDetectionResult:
    if os.getenv("PART_DETECTION_MODE", os.getenv("AI_MODE", "mock")) == "mock":
        return _mock_result()

    api_key = os.getenv("GEMINI_API_KEY", "").strip()
    if not api_key:
        raise GeminiServiceError("Gemini part detection is not configured.")

    model = os.getenv("GEMINI_MODEL", "gemini-3.8-flash")
    endpoint = os.getenv(
        "GEMINI_API_URL",
        "https://generativelanguage.googleapis.com/v1beta/interactions",
    )
    request_payload = {
        "model": model,
        "store": False,
        "input": [
            {"type": "text", "text": SYSTEM_INSTRUCTION},
            {
                "type": "image",
                "data": base64.b64encode(image_bytes).decode("ascii"),
                "mime_type": mime_type,
            },
        ],
        "response_format": {
            "type": "text",
            "mime_type": "application/json",
            "schema": GeminiPartDetection.model_json_schema(),
        },
    }
    headers = {
        "x-goog-api-key": api_key,
        "Content-Type": "application/json",
        "Api-Revision": os.getenv("GEMINI_API_REVISION", "2026-05-20"),
    }
    timeout = float(os.getenv("GEMINI_TIMEOUT_SECONDS", "15"))

    try:
        async with httpx.AsyncClient(timeout=timeout) as client:
            response = await client.post(endpoint, headers=headers, json=request_payload)
            response.raise_for_status()
            response_payload = response.json()
    except (httpx.TimeoutException, httpx.NetworkError) as exc:
        raise GeminiServiceError("Gemini is temporarily unavailable.") from exc
    except httpx.HTTPStatusError as exc:
        raise GeminiServiceError("Gemini rejected the part-detection request.") from exc
    except (json.JSONDecodeError, ValueError) as exc:
        raise GeminiServiceError("Gemini returned an unreadable response.") from exc

    try:
        raw_result = json.loads(_extract_output_text(response_payload))
    except (json.JSONDecodeError, TypeError) as exc:
        raise GeminiServiceError("Gemini returned invalid structured output.") from exc
    if not isinstance(raw_result, dict):
        raise GeminiServiceError("Gemini returned an invalid part-detection response.")

    return _normalize_payload(raw_result)
