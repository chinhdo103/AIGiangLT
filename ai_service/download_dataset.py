import cv2
import numpy as np
import os

def generate_dummy_iris(filename, center_x, center_y, pupil_radius, iris_radius):
    """
    Hàm tạo ảnh giả lập con mắt (bao gồm đồng tử và mống mắt)
    Giúp kiểm thử các thuật toán OpenCV trong bước tiếp theo
    """
    # Tạo một ảnh xám (grayscale) trống làm nền
    img = np.ones((400, 600), dtype=np.uint8) * 200
    
    # Vẽ mống mắt (Iris) màu xám đậm
    cv2.circle(img, (center_x, center_y), iris_radius, (100,), -1)
    
    # Thêm nhiễu ngẫu nhiên cho mống mắt (giả lập texture của mống mắt thật)
    noise = np.random.randint(0, 50, (400, 600), dtype=np.uint8)
    mask = np.zeros((400, 600), dtype=np.uint8)
    cv2.circle(mask, (center_x, center_y), iris_radius, (255,), -1)
    img = np.where(mask == 255, np.clip(img - noise, 0, 255).astype(np.uint8), img)

    # Vẽ đồng tử (Pupil) màu đen ở giữa
    cv2.circle(img, (center_x, center_y), pupil_radius, (20,), -1)
    
    # Lưu ảnh ra file
    cv2.imwrite(filename, img)
    print(f"Đã tạo ảnh mẫu: {filename}")

if __name__ == "__main__":
    # Tạo thư mục chứa dataset
    dataset_dir = "dataset/sample"
    os.makedirs(dataset_dir, exist_ok=True)
    
    print("Đang tạo dữ liệu mống mắt mẫu (Dummy Dataset) để kiểm thử...")
    
    # Tạo 2 ảnh mẫu mô phỏng 2 mắt với kích thước và vị trí hơi khác nhau
    generate_dummy_iris(os.path.join(dataset_dir, "eye_1.jpg"), 300, 200, 30, 90)
    generate_dummy_iris(os.path.join(dataset_dir, "eye_2.jpg"), 310, 190, 35, 95)
    
    print("Hoàn tất! Các ảnh mẫu đã được lưu trong thư mục:", dataset_dir)

