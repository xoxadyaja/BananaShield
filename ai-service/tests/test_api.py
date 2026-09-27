from io import BytesIO

from fastapi.testclient import TestClient
from PIL import Image, ImageDraw

from app.main import app, TOKEN

client = TestClient(app)


def picture(size=(300, 300)):
    image = Image.new("RGB", size, "green")
    if min(size) >= 224:
        draw = ImageDraw.Draw(image)
        draw.rectangle((30, 30, 260, 260), outline="yellow", width=12)
        draw.line((20, 280, 280, 20), fill="black", width=8)
    buffer = BytesIO()
    image.save(buffer, "JPEG")
    return buffer.getvalue()


def request(scenario=None, size=(300, 300), analysis_mode="automatic"):
    data = {"analysis_mode": analysis_mode}
    if scenario:
        data["demo_scenario"] = scenario
    return client.post(
        "/api/v1/predict",
        headers={"X-AI-Token": TOKEN},
        data=data,
        files={"image": ("plant.jpg", picture(size), "image/jpeg")},
    )


def test_health_describes_one_four_class_model_with_automatic_area_analysis():
    payload = client.get("/health").json()
    assert payload["status"] == "ok"
    assert payload["architecture"] == "EfficientNet-B0"
    assert len(payload["supported_classes"]) == 4
    assert payload["image_area_analysis"] == "automatic"


def test_uploaded_image_uses_automatic_area_analysis():
    response = request("fusarium_wilt")
    assert response.status_code == 200
    assert response.json()["predicted_class"] == "fusarium_wilt"
    assert response.json()["image_path"] == "auto_detected"
    assert response.json()["specific_view"] == "auto_detected"


def test_automatic_area_analysis_uses_the_shared_model_class_set():
    response = request("black_sigatoka")
    assert response.status_code == 200
    assert response.json()["predicted_class"] == "black_sigatoka"


def test_manual_area_override_is_rejected():
    assert request("healthy_banana", analysis_mode="manual").status_code == 422


def test_low_resolution_is_inconclusive():
    response = request(size=(100, 100))
    assert response.json()["decision_status"] == "inconclusive"


def test_detect_part_returns_the_strict_allowed_contract(monkeypatch):
    monkeypatch.setenv("PART_DETECTION_MODE", "mock")
    monkeypatch.setenv("PART_DETECTION_MOCK_SCENARIO", "Crown / Upper Leaves")
    response = client.post(
        "/detect-part",
        headers={"X-AI-Token": TOKEN},
        files={"file": ("plant.jpg", picture(), "image/jpeg")},
    )

    assert response.status_code == 200
    assert response.json() == {
        "is_banana_image": True,
        "part": "Crown / Upper Leaves",
        "usable": True,
        "message": "The primary banana plant view is Crown / Upper Leaves.",
        "success": True,
    }


def test_detect_part_can_block_a_non_banana_image(monkeypatch):
    monkeypatch.setenv("PART_DETECTION_MODE", "mock")
    monkeypatch.setenv("PART_DETECTION_MOCK_SCENARIO", "invalid_image")
    response = client.post(
        "/detect-part",
        headers={"X-AI-Token": TOKEN},
        files={"file": ("dog.jpg", picture(), "image/jpeg")},
    )

    assert response.status_code == 200
    assert response.json()["part"] == "Unknown"
    assert response.json()["is_banana_image"] is False
    assert response.json()["usable"] is False


def test_detect_part_rejects_unsupported_or_corrupted_files(monkeypatch):
    monkeypatch.setenv("PART_DETECTION_MODE", "mock")
    unsupported = client.post(
        "/detect-part",
        headers={"X-AI-Token": TOKEN},
        files={"file": ("plant.gif", b"GIF89a", "image/gif")},
    )
    corrupted = client.post(
        "/detect-part",
        headers={"X-AI-Token": TOKEN},
        files={"file": ("plant.jpg", b"not-an-image", "image/jpeg")},
    )

    assert unsupported.status_code == 422
    assert corrupted.status_code == 422
