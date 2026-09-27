from typing import Literal

from pydantic import BaseModel, ConfigDict, Field


BananaPart = Literal[
    "Leaf",
    "Pseudostem",
    "Crown / Upper Leaves",
    "Whole Plant",
    "Unknown",
]


class GeminiPartDetection(BaseModel):
    """The structured output requested directly from Gemini."""

    model_config = ConfigDict(extra="forbid")

    is_banana_image: bool
    part: BananaPart
    usable: bool
    message: str = Field(min_length=1, max_length=500)


class PartDetectionResult(GeminiPartDetection):
    """The stable public contract returned by POST /detect-part."""

    success: bool = True
