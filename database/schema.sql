CREATE DATABASE IF NOT EXISTS iris_lab_security CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE iris_lab_security;

-- 1. Bảng danh sách Phòng thí nghiệm (Nơi đặt cửa kiểm soát)
CREATE TABLE laboratories (
    lab_id INT AUTO_INCREMENT PRIMARY KEY,
    lab_name VARCHAR(100) NOT NULL,
    security_level ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'HIGH',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Bảng Nhân viên / Nhà nghiên cứu
CREATE TABLE employees (
    emp_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role VARCHAR(50) NOT NULL,
    allowed_lab_id INT,
    status ENUM('ACTIVE', 'SUSPENDED') DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (allowed_lab_id) REFERENCES laboratories(lab_id)
);

-- 3. Bảng lưu trữ Vector Đặc trưng Mống mắt (Iris Templates) do AI trích xuất
CREATE TABLE iris_templates (
    template_id INT AUTO_INCREMENT PRIMARY KEY,
    emp_id INT NOT NULL,
    -- Lưu trữ mảng vector nhúng (Embedding) độ dài 1280 chiều của CNN MobileNetV2
    -- Dạng JSON thích hợp để load lại vào mảng NumPy bên Python
    embedding_vector JSON NOT NULL, 
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (emp_id) REFERENCES employees(emp_id) ON DELETE CASCADE
);

-- 4. Bảng Nhật ký Ra vào (Access Logs) - Dùng để gửi cho Gemini Copilot phân tích bảo mật
CREATE TABLE access_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    emp_id INT NULL, -- Sẽ là NULL nếu là người lạ (Unknown) không thể nhận dạng
    lab_id INT NOT NULL,
    access_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    action ENUM('ENTRY_GRANTED', 'ENTRY_DENIED', 'SPOOFING_ATTEMPT') NOT NULL,
    confidence_score DECIMAL(5, 2) DEFAULT 0.00,
    ai_reason TEXT, -- Lý do nếu bị AI từ chối (Vd: "Ảnh mờ", "Ảnh chụp màn hình điện thoại")
    FOREIGN KEY (emp_id) REFERENCES employees(emp_id),
    FOREIGN KEY (lab_id) REFERENCES laboratories(lab_id)
);

-- Đổ trước một số dữ liệu mẫu (Seeding)
INSERT INTO laboratories (lab_name, security_level) VALUES ('Phòng Lab Trí Tuệ Nhân Tạo (AI)', 'CRITICAL');
INSERT INTO laboratories (lab_name, security_level) VALUES ('Phòng Nghiên Cứu Vi Sinh', 'HIGH');

