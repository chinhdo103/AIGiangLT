import cv2
import numpy as np

class IrisNormalizer:
    def __init__(self, polar_height=64, polar_width=512):
        """
        Khởi tạo mô hình chuẩn hóa dựa trên thuật toán Daugman Rubber Sheet Model.
        Thuật toán này duỗi vùng hình vành khăn của mống mắt thành một ma trận phẳng chữ nhật,
        giúp loại bỏ sự sai lệch do giãn nở đồng tử (khi ánh sáng thay đổi).
        
        Thông số:
        - polar_height: Chiều cao ảnh đầu ra (bán kính)
        - polar_width: Chiều rộng ảnh đầu ra (góc độ 0-360)
        """
        self.polar_height = polar_height
        self.polar_width = polar_width
        self.clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))

    def normalize(self, image, pupil_circle, iris_circle):
        """
        Thực hiện biến đổi không gian cực (Daugman Polar Transform).
        Áp dụng tăng cường tương phản cục bộ CLAHE để nổi rõ vân mống mắt.
        """
        if len(image.shape) == 3:
            image = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)

        xp, yp, rp = pupil_circle
        xi, yi, ri = iris_circle

        # 1. Tạo lưới (Meshgrid) cho ma trận đích (R: bán kính, T: góc theta)
        theta = np.linspace(0, 2 * np.pi, self.polar_width)
        radius = np.linspace(0, 1, self.polar_height)
        T, R = np.meshgrid(theta, radius)
        
        # 2. Ánh xạ lưới từ ma trận cực sang tọa độ Descartes (x, y) trên ảnh gốc
        # Tọa độ đường viền đồng tử
        x_pupil = xp + rp * np.cos(T)
        y_pupil = yp + rp * np.sin(T)
        
        # Tọa độ đường viền mống mắt ngoài
        x_iris = xi + ri * np.cos(T)
        y_iris = yi + ri * np.sin(T)
        
        # Nội suy tuyến tính để tìm các điểm nằm giữa đồng tử và viền mống mắt ngoài
        X = (1 - R) * x_pupil + R * x_iris
        Y = (1 - R) * y_pupil + R * y_iris
        
        # 3. Sử dụng cv2.remap để lấy pixel từ ảnh gốc và gán vào ảnh phẳng chữ nhật mới
        normalized_iris = cv2.remap(image, X.astype(np.float32), Y.astype(np.float32), cv2.INTER_LINEAR)
        
        # 4. Tăng cường độ rõ của vân mống mắt phẳng bằng CLAHE
        if normalized_iris is not None and normalized_iris.size > 0:
            normalized_iris = self.clahe.apply(normalized_iris)

        return normalized_iris
