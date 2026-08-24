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


def request(path, view, scenario=None, size=(300, 300)):
    data = {"screening_path": path, "view_type": view}
    if scenario:
        data["demo_scenario"] = scenario
    return client.post(
        "/api/v1/predict",
        headers={"X-AI-Token": TOKEN},
        data=data,
        files={"image": ("plant.jpg", picture(size), "image/jpeg")},
    )


def test_health_describes_one_four_class_model_contract():
    payload = client.get("/health").json()
    assert payload["status"] == "ok"
    assert payload["architecture"] == "EfficientNet-B0"
    assert len(payload["supported_classes"]) == 4


def test_leaf_path_can_return_any_shared_model_class():
    response = request("leaf", "leaf_underside", "fusarium_wilt")
    assert response.status_code == 200
    assert response.json()["predicted_class"] == "fusarium_wilt"
    assert response.json()["specific_view"] == "leaf_underside"


def test_whole_plant_path_uses_same_class_set():
    response = request("whole_plant", "crown_upper_leaves", "black_sigatoka")
    assert response.status_code == 200
    assert response.json()["predicted_class"] == "black_sigatoka"


def test_capture_metadata_does_not_determine_the_disease_class():
    leaf = request("leaf", "midrib_veins", "banana_bunchy_top_disease")
    plant = request("whole_plant", "pseudostem_base", "banana_bunchy_top_disease")
    assert leaf.json()["predicted_class"] == plant.json()["predicted_class"]


def test_mismatched_view_is_rejected():
    assert request("leaf", "full_plant", "healthy_banana").status_code == 422


def test_low_resolution_is_inconclusive():
    response = request("leaf", "whole_leaf", size=(100, 100))
    assert response.json()["decision_status"] == "inconclusive"
