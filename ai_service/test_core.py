import cv2
import os
import sys

# Thêm thư mục hiện tại vào sys.path để có thể import package 'core'
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from core.segmentation import IrisSegmenter
from core.normalization import IrisNormalizer
from core.feature_extractor import IrisFeatureExtractor

def test_iris_pipeline():
    print("="*50)
    print(" BẮT ĐẦU KIỂM THỬ PIPELINE LÕI XỬ LÝ MỐNG MẮT")
    print("="*50)
    
    # 1. Khởi tạo
    print("[1] Đang khởi tạo các module OpenCV & Deep Learning CNN...")
    segmenter = IrisSegmenter()
    normalizer = IrisNormalizer()
    extractor = IrisFeatureExtractor()
    print("    -> Khởi tạo thành công!\n")
    
    # 2. Đọc ảnh mẫu
    base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    img1_path = os.path.join(base_dir, "dataset", "sample", "eye_1.jpg")
    img2_path = os.path.join(base_dir, "dataset", "sample", "eye_2.jpg")
    
    if not os.path.exists(img1_path) or not os.path.exists(img2_path):
        print("LỖI: Không tìm thấy ảnh mẫu! Vui lòng chạy lệnh 'python ai_service/download_dataset.py' trước.")
        return
        
    print(f"[2] Đọc ảnh đầu vào từ: {img1_path} và {img2_path}")
    img1 = cv2.imread(img1_path)
    img2 = cv2.imread(img2_path)
    
    # 3. Phân đoạn
    print("[3] Đang bóc tách đồng tử và mống mắt...")
    pupil1, iris1 = segmenter.segment(img1)
    pupil2, iris2 = segmenter.segment(img2)
    print(f"    - Ảnh 1: Đồng tử {pupil1}, Mống mắt {iris1}")
    print(f"    - Ảnh 2: Đồng tử {pupil2}, Mống mắt {iris2}\n")
    
    # 4. Chuẩn hóa
    print("[4] Đang duỗi phẳng mống mắt (Daugman Rubber Sheet)...")
    norm1 = normalizer.normalize(img1, pupil1, iris1)
    norm2 = normalizer.normalize(img2, pupil2, iris2)
    
    results_dir = os.path.join(os.path.dirname(os.path.abspath(__file__)), "test_results")
    os.makedirs(results_dir, exist_ok=True)
    cv2.imwrite(os.path.join(results_dir, "normalized_1.jpg"), norm1)
    cv2.imwrite(os.path.join(results_dir, "normalized_2.jpg"), norm2)
    print(f"    -> Đã lưu ảnh chuẩn hóa tại: {results_dir}\n")
    
    # 5. Trích xuất đặc trưng & So khớp
    print("[5] Đang trích xuất vector đặc trưng qua mạng CNN...")
    feat1 = extractor.extract_features(norm1)
    feat2 = extractor.extract_features(norm2)
    
    print("[6] Tiến hành so khớp (Matching)...")
    # Sử dụng ngưỡng 0.85
    is_match, similarity = extractor.match(feat1, feat2, threshold=0.85)
    
    print("="*50)
    print(f" KẾT QUẢ TƯƠNG ĐỒNG (COSINE SIMILARITY): {similarity * 100:.2f}%")
    if is_match:
        print(" -> KẾT LUẬN: ĐÂY LÀ CÙNG MỘT NGƯỜI (Cho phép mở cửa) ✅")
    else:
        print(" -> KẾT LUẬN: KHÁC NGƯỜI (Từ chối truy cập) ❌")
    print("="*50)

if __name__ == "__main__":
    test_iris_pipeline()

