-- ===================================================================
-- IRIS LAB SECURITY - MIGRATION v2.0
-- Nâng cấp: Hỗ trợ 3 mẫu mống mắt (Multi-Template) / Hybrid CNN + Gabor
-- Ngày: 2026-10-24
-- Yêu cầu: MySQL 5.7+ / MariaDB 10.3+
-- ===================================================================

CREATE DATABASE IF NOT EXISTS iris_lab_security CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- -------------------------------------------------------------------
-- BƯỚC 1: Mở rộng bảng iris_templates cho multi-template enrollment
-- -------------------------------------------------------------------


-- Thêm cột đánh số thứ tự mẫu (1, 2, 3) cho mỗi nhân viên
ALTER TABLE iris_templates
    ADD COLUMN template_index TINYINT UNSIGNED NOT NULL DEFAULT 1
    COMMENT 'Thứ tự mẫu trong bộ 3 mẫu của nhân viên (1/2/3)' AFTER emp_id;

-- Thêm cột đường dẫn ảnh gốc đã chuẩn hóa
ALTER TABLE iris_templates
    ADD COLUMN image_path VARCHAR(255) NULL
    COMMENT 'Đường dẫn ảnh mống mắt đã chuẩn hóa (đã crop vùng mắt)' AFTER embedding_vector;

-- Vector đặc trưng Gabor (wavelet) - bổ sung cho Deep Feature
ALTER TABLE iris_templates
    ADD COLUMN gabor_vector JSON NULL
    COMMENT 'Vector đặc trưng Gabor 32x8=256 chiều (hybrid feature)' AFTER image_path;

-- Chất lượng chụp ảnh
ALTER TABLE iris_templates
    ADD COLUMN quality_score DECIMAL(5, 2) DEFAULT 0.00
    COMMENT 'Điểm chất lượng ảnh 0-100' AFTER gabor_vector;

-- Trạng thái template
ALTER TABLE iris_templates
    ADD COLUMN status ENUM('ACTIVE', 'PENDING', 'REJECTED') DEFAULT 'ACTIVE'
    COMMENT 'Trạng thái mẫu sinh trắc' AFTER quality_score;

-- Metadata bổ sung
ALTER TABLE iris_templates
    ADD COLUMN device_info VARCHAR(100) NULL
    COMMENT 'Thông tin thiết bị chụp' AFTER status;

-- Ghi chú phân tích từ AI
ALTER TABLE iris_templates
    ADD COLUMN ai_notes TEXT NULL
    COMMENT 'Nhận xét chất lượng từ AI' AFTER device_info;

-- Index hỗ trợ truy vấn nhanh theo nhân viên + index mẫu
ALTER TABLE iris_templates
    DROP INDEX IF EXISTS idx_iris_templates_emp_index;
-- -------------------------------------------------------------------
-- BƯỚC 2: Bảng theo dõi phiên đăng ký (Enrollment Sessions)
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS enrollment_sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    emp_id INT NOT NULL,
    session_token VARCHAR(64) NOT NULL,
    current_step TINYINT UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'Bước hiện tại (1-3) trong quy trình 3 ảnh',
    captured_count TINYINT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Số ảnh đã chụp được (0-3)',
    status ENUM('ACTIVE', 'COMPLETED', 'EXPIRED', 'CANCELLED') DEFAULT 'ACTIVE',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (emp_id) REFERENCES employees(emp_id) ON DELETE CASCADE
);

CREATE INDEX idx_enroll_sessions_token ON enrollment_sessions (session_token);
CREATE INDEX idx_enroll_sessions_emp ON enrollment_sessions (emp_id, status);

-- -------------------------------------------------------------------
-- BƯỚC 3: Mở rộng access_logs - ghi nhận phương pháp khớp
-- -------------------------------------------------------------------
ALTER TABLE access_logs
    ADD COLUMN match_method ENUM('CNN_ONLY', 'GABOR_ONLY', 'HYBRID') DEFAULT 'HYBRID'
    COMMENT 'Phương pháp so khớp được sử dụng' AFTER confidence_score;

ALTER TABLE access_logs
    ADD COLUMN matched_template_id INT NULL
    COMMENT 'ID của mẫu sinh trắc được khớp' AFTER match_method;

ALTER TABLE access_logs
    ADD COLUMN cnn_score DECIMAL(5, 2) DEFAULT 0.00
    COMMENT 'Điểm khớp CNN (0-100)' AFTER matched_template_id;

ALTER TABLE access_logs
    ADD COLUMN gabor_score DECIMAL(5, 2) DEFAULT 0.00
    COMMENT 'Điểm khớp Gabor (0-100)' AFTER cnn_score;

ALTER TABLE access_logs
    ADD COLUMN best_template_id INT NULL
    COMMENT 'Mẫu có điểm cao nhất trong 3 mẫu' AFTER gabor_score;

-- -------------------------------------------------------------------
-- BƯỚC 4: Bảng phân quyền truy cập theo phòng Lab
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lab_permissions (
    permission_id INT AUTO_INCREMENT PRIMARY KEY,
    lab_id INT NOT NULL,
    emp_id INT NOT NULL,
    permission_type ENUM('READ', 'WRITE', 'ADMIN', 'FULL') DEFAULT 'READ',
    granted_by INT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (lab_id) REFERENCES laboratories(lab_id) ON DELETE CASCADE,
    FOREIGN KEY (emp_id) REFERENCES employees(emp_id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES employees(emp_id) ON DELETE SET NULL,
    UNIQUE KEY uk_lab_perm (lab_id, emp_id)

-- -------------------------------------------------------------------
-- BƯỚC 5: Bảng cấu hình hệ thống AI
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    value_type ENUM('STRING', 'INT', 'FLOAT', 'BOOL', 'JSON') DEFAULT 'STRING',
    description VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Seed cấu hình mặc định
INSERT INTO ai_settings (setting_key, setting_value, value_type, description) VALUES
('match_threshold', '62.00', 'FLOAT', 'Ngưỡng khớp tối thiểu để cấp quyền vào (%)'),
('hybrid_cnn_weight', '0.65', 'FLOAT', 'Trọng số CNN trong điểm hybrid (0-1)'),
('hybrid_gabor_weight', '0.35', 'FLOAT', 'Trọng số Gabor trong điểm hybrid (0-1)'),
('max_templates_per_emp', '3', 'INT', 'Số mẫu tối đa mỗi nhân viên'),
('min_quality_score', '45.00', 'FLOAT', 'Điểm chất lượng ảnh tối thiểu để chấp nhận'),
('session_timeout_min', '15', 'INT', 'Thời gian hết hạn phiên đăng ký (phút)'),
('liveness_required', 'true', 'BOOL', 'Bắt buộc kiểm tra chống giả mạo (liveness)'),
('gabor_orientations', '8', 'INT', 'Số hướng của bộ lọc Gabor'),
('gabor_scales', '4', 'INT', 'Số tỉ lệ của bộ lọc Gabor')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- -------------------------------------------------------------------
-- BƯỚC 6: Cột hỗ trợ theo dõi enrollment trong bảng nhân viên
-- -------------------------------------------------------------------
ALTER TABLE employees
    ADD COLUMN iris_enrolled TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'Đã đăng ký mống mắt hay chưa' AFTER status;

ALTER TABLE employees
    ADD COLUMN template_count TINYINT UNSIGNED NOT NULL DEFAULT 0
    COMMENT 'Số mẫu sinh trắc đã đăng ký (0-3)' AFTER iris_enrolled;

-- -------------------------------------------------------------------
-- BƯỚC 7: Trigger đồng bộ template_count tự động
-- -------------------------------------------------------------------
DROP TRIGGER IF EXISTS trg_iris_templates_after_insert;
DROP TRIGGER IF EXISTS trg_iris_templates_after_delete;


-- -------------------------------------------------------------------
-- BƯỚC 8: Backfill dữ liệu cũ (template_index = 1, quality_score mặc định)
-- -------------------------------------------------------------------
UPDATE iris_templates
SET template_index = 1
WHERE template_index IS NULL OR template_index = 0;

UPDATE employees e
SET template_count = (
    SELECT COUNT(*) FROM iris_templates t
    WHERE t.emp_id = e.emp_id AND t.status = 'ACTIVE'
),
iris_enrolled = (
    SELECT COUNT(*) FROM iris_templates t
    WHERE t.emp_id = e.emp_id AND t.status = 'ACTIVE'
) > 0;

-- ===================================================================
-- HOÀN TẤT MIGRATION v2.0
-- Migration này đã thêm hỗ trợ 3 templates mỗi nhân viên
-- ===================================================================

);


ALTER TABLE iris_templates
    ADD UNIQUE KEY uk_iris_emp_template (emp_id, template_index);

CREATE INDEX idx_iris_templates_status ON iris_templates (status);
