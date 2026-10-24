"""
Test cho Hybrid Feature Extractor (CNN + Gabor)
Chạy: python -m pytest ai_service/tests/test_hybrid_extractor.py -v
"""
import numpy as np
import cv2
import pytest

# Mock heavy deps nếu chưa cài
try:
    from core.hybrid_extractor import HybridIrisFeatureExtractor
except ImportError:
    pytest.skip("torch/torchvision chưa được cài đặt", allow_module_level=True)

@pytest.fixture(scope="module")
def extractor():
    return HybridIrisFeatureExtractor()

@pytest.fixture
def synthetic_iris():
    """Tạo ảnh mống mắt giả (vòng tròn + texture)"""
    img = np.zeros((256, 256, 3), np.uint8)
    cv2.circle(img, (128, 128), 100, (80, 60, 40), -1)
    # Thêm texture vân
    for i in range(20):
        r = 30 + i * 3
        cv2.circle(img, (128, 128), r, (100 + i*3, 80 + i*2, 60 + i), 2)
    cv2.circle(img, (128, 128), 30, (10, 10, 10), -1)  # pupil
    return img

def test_gabor_dimensions(extractor, synthetic_iris):
    """Gabor features phải có 256 chiều"""
    gabor = extractor.extract_gabor_features(synthetic_iris)
    assert gabor.shape == (256,), f"Expected 256-D, got {gabor.shape}"
    assert np.isfinite(gabor).all()
    assert np.linalg.norm(gabor) > 0.9  # đã normalize

def test_cnn_dimensions(extractor, synthetic_iris):
    """CNN features phải có 1280 chiều"""
    cnn = extractor.extract_cnn_features(synthetic_iris)
    assert cnn.shape == (1280,), f"Expected 1280-D, got {cnn.shape}"
    assert np.isfinite(cnn).all()

def test_extract_features_dict(extractor, synthetic_iris):
    """extract_features trả về dict với cnn/gabor/hybrid"""
    result = extractor.extract_features(synthetic_iris, include_gabor=True)
    assert set(result.keys()) == {"cnn", "gabor", "hybrid"}
    assert result["hybrid"].shape == (1536,)
    assert np.isfinite(result["hybrid"]).all()

def test_match_hybrid(extractor, synthetic_iris):
    """So khớp ảnh giống nhau phải cho điểm cao"""
    f1 = extractor.extract_features(synthetic_iris)
    f2 = extractor.extract_features(synthetic_iris.copy())
    is_match, score, scores = extractor.match(f1, f2, threshold=0.72, method="hybrid")
    assert is_match, f"Expected match, got score={score}"
    assert score > 0.95
    assert "cnn_score" in scores
    assert "gabor_score" in scores
    assert "weighted_hybrid" in scores

def test_match_different_images(extractor, synthetic_iris):
    """Ảnh khác nhau phải cho điểm thấp"""
    img2 = np.zeros((256, 256, 3), np.uint8)
    cv2.circle(img2, (100, 100), 80, (200, 200, 200), -1)
    cv2.circle(img2, (100, 100), 25, (0, 0, 0), -1)
    f1 = extractor.extract_features(synthetic_iris)
    f2 = extractor.extract_features(img2)
    is_match, score, _ = extractor.match(f1, f2, threshold=0.72, method="hybrid")
    assert not is_match, f"Expected no match, got {score}"

def test_grayscale_input(extractor):
    """Input grayscale phải hoạt động"""
    gray = np.random.randint(0, 255, (256, 256), np.uint8)
    result = extractor.extract_features(gray, include_gabor=True)
    assert result["cnn"].shape == (1280,)
    assert result["gabor"].shape == (256,)

def test_weighted_score_applied(extractor, synthetic_iris):
    """Weighted hybrid = 0.65*cnn + 0.35*gabor"""
    f1 = extractor.extract_features(synthetic_iris)
    f2 = extractor.extract_features(synthetic_iris.copy())
    _, _, scores = extractor.match(f1, f2, method="hybrid", cnn_weight=0.65, gabor_weight=0.35)
    expected = 0.65 * scores["cnn_score"] + 0.35 * scores["gabor_score"]
    assert abs(scores["weighted_hybrid"] - expected) < 1e-6
