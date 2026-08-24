from io import BytesIO
import hashlib
import os
import time

from fastapi import Depends, FastAPI, File, Form, Header, HTTPException, UploadFile
import numpy as np
from PIL import Image, ImageFilter, ImageStat
from dotenv import load_dotenv

load_dotenv()

app = FastAPI(title="BananaShield AI Service", version="0.2.0")
MODE = os.getenv("AI_MODE", "mock")
TOKEN = os.getenv("AI_SERVICE_TOKEN", "change-me")
MODEL_PATH = os.getenv("MODEL_PATH", "models/efficientnet_b0.keras")
MODEL_VERSION = os.getenv("MODEL_VERSION", "efficientnet-b0-demo-v0.2" if MODE == "mock" else "configured")
CONFIDENCE_THRESHOLD = float(os.getenv("CONFIDENCE_THRESHOLD", "0.75"))

# The revised system uses one class set and one model regardless of the guided
# capture path. Paths remain contextual capture instructions only.
CLASSES = [
    ("healthy_banana", "Healthy Banana"),
    ("black_sigatoka", "Black Sigatoka"),
    ("fusarium_wilt", "Fusarium Wilt"),
    ("banana_bunchy_top_disease", "Banana Bunchy Top Disease"),
]
VALID_VIEWS = {
    "leaf": {"whole_leaf", "leaf_surface", "leaf_underside", "leaf_margins", "midrib_veins"},
    "whole_plant": {"full_plant", "crown_upper_leaves", "lower_older_leaves", "pseudostem_base"},
}
_model = None


def authorize(x_ai_token: str = Header(default="")):
    if x_ai_token != TOKEN:
        raise HTTPException(401, "Unauthorized service token")


def load_configured_model():
    global _model
    if _model is not None:
        return _model
    if MODE != "model":
        raise RuntimeError("Model loading is available only when AI_MODE=model")
    if not os.path.exists(MODEL_PATH):
        raise RuntimeError(f"Configured EfficientNet-B0 model file was not found: {MODEL_PATH}")
    try:
        from tensorflow.keras.models import load_model
    except ImportError as exc:
        raise RuntimeError("TensorFlow is required for AI_MODE=model; install requirements-model.txt") from exc
    _model = load_model(MODEL_PATH)
    return _model


def quality_flags(image: Image.Image) -> list[str]:
    gray = image.convert("L")
    mean_light = ImageStat.Stat(gray).mean[0]
    edge_variance = ImageStat.Stat(gray.filter(ImageFilter.FIND_EDGES)).var[0]
    flags: list[str] = []
    if mean_light < 25:
        flags.append("too_dark")
    elif mean_light > 240:
        flags.append("too_bright")
    if edge_variance < 18:
        flags.append("possible_blur_or_low_detail")
    return flags


def model_choice(image: Image.Image):
    model = load_configured_model()
    prepared = image.convert("RGB").resize((224, 224))
    batch = np.expand_dims(np.asarray(prepared, dtype=np.float32), axis=0)
    probabilities = np.asarray(model.predict(batch, verbose=0))[0]
    if len(probabilities) != len(CLASSES):
        raise RuntimeError(f"Model output must contain exactly {len(CLASSES)} class probabilities")
    index = int(np.argmax(probabilities))
    return CLASSES[index], float(probabilities[index])


@app.get("/health")
def health():
    return {
        "status": "ok",
        "ai_mode": MODE,
        "architecture": "EfficientNet-B0",
        "model_version": MODEL_VERSION,
        "supported_classes": [slug for slug, _ in CLASSES],
        "capture_paths": list(VALID_VIEWS),
    }


@app.post("/api/v1/predict", dependencies=[Depends(authorize)])
async def predict(
    image: UploadFile = File(...),
    screening_path: str = Form(...),
    view_type: str = Form(...),
    demo_scenario: str | None = Form(None),
):
    started = time.perf_counter()
    if screening_path not in VALID_VIEWS:
        raise HTTPException(422, "Invalid screening path")
    if view_type not in VALID_VIEWS[screening_path]:
        raise HTTPException(422, "Image view does not match the selected screening path")
    if image.content_type not in {"image/jpeg", "image/png", "image/webp"}:
        raise HTTPException(422, "Unsupported image format")

    raw = await image.read()
    if len(raw) > 5 * 1024 * 1024:
        raise HTTPException(422, "Image exceeds 5 MB")
    try:
        submitted = Image.open(BytesIO(raw))
        submitted.load()
    except Exception as exc:
        raise HTTPException(422, "Unreadable image") from exc

    if submitted.width < 224 or submitted.height < 224:
        return build_result(screening_path, view_type, None, 0.0, ["low_resolution"], started)

    flags = quality_flags(submitted)
    scenario = demo_scenario or os.getenv("MOCK_SCENARIO", "auto")
    if MODE == "mock" and demo_scenario is not None:
        flags = []  # Explicit test/demo scenarios exercise the response contract.
    if flags:
        return build_result(screening_path, view_type, None, 0.0, flags, started)

    if MODE == "model":
        try:
            choice, confidence = model_choice(submitted)
        except RuntimeError as exc:
            raise HTTPException(503, str(exc)) from exc
    else:
        if scenario == "inconclusive":
            return build_result(screening_path, view_type, None, 0.42, ["insufficient_confidence"], started)
        if scenario == "auto":
            bucket = hashlib.sha256(raw).digest()[0] % (len(CLASSES) + 1)
            if bucket == len(CLASSES):
                return build_result(screening_path, view_type, None, 0.42, ["insufficient_confidence"], started)
            choice = CLASSES[bucket]
        else:
            choice = next((item for item in CLASSES if item[0] == scenario), CLASSES[0])
        confidence = 0.87

    if confidence < CONFIDENCE_THRESHOLD:
        return build_result(screening_path, view_type, None, confidence, ["below_confidence_threshold"], started)
    return build_result(screening_path, view_type, choice, confidence, [], started)


def build_result(path, view, choice, confidence, flags, started):
    conclusive = choice is not None
    label = choice[1] if conclusive else "Inconclusive result"
    slug = choice[0] if conclusive else "inconclusive"
    message = (
        f"Visible symptoms are consistent with {label}."
        if conclusive
        else "No sufficiently reliable preliminary result could be produced. Retake the image or seek professional assessment."
    )
    return {
        "success": True,
        "screening_path": path,
        "view_type": view,
        "image_path": path,
        "specific_view": view,
        "predicted_class": slug,
        "display_label": label,
        "decision_status": "conclusive" if conclusive else "inconclusive",
        "confidence": confidence,
        "confidence_threshold": CONFIDENCE_THRESHOLD,
        "architecture": "EfficientNet-B0" if MODE == "model" else "EfficientNet-B0 integration (mock output)",
        "model_version": MODEL_VERSION,
        "inference_time_ms": round((time.perf_counter() - started) * 1000),
        "quality_status": "accepted" if not flags else "inconclusive",
        "quality_flags": flags,
        "message": message,
        "disclaimer": "This is a preliminary visual-screening result and not a confirmed diagnosis.",
        "mock_fallback": MODE == "mock",
    }
