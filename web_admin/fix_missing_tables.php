<?php
/**
 * Fix Missing Tables: lab_permissions và ai_settings
 */

require_once __DIR__ . '/config.php';

echo "Creating missing tables...\n\n";

try {
    // 1. lab_permissions
    $pdo->exec("CREATE TABLE IF NOT EXISTS lab_permissions (
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
    )");
    echo "✓ lab_permissions created\n";
    
    // 2. ai_settings
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_settings (
        setting_id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT NOT NULL,
        value_type ENUM('STRING', 'INT', 'FLOAT', 'BOOL', 'JSON') DEFAULT 'STRING',
        description VARCHAR(255) NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "✓ ai_settings created\n";
    
    // Seed ai_settings
    $settings = [
        ['match_threshold', '62.00', 'FLOAT', 'Ngưỡng khớp tối thiểu để cấp quyền vào (%)'],
        ['hybrid_cnn_weight', '0.65', 'FLOAT', 'Trọng số CNN trong điểm hybrid (0-1)'],
        ['hybrid_gabor_weight', '0.35', 'FLOAT', 'Trọng số Gabor trong điểm hybrid (0-1)'],
        ['max_templates_per_emp', '3', 'INT', 'Số mẫu tối đa mỗi nhân viên'],
        ['min_quality_score', '45.00', 'FLOAT', 'Điểm chất lượng ảnh tối thiểu'],
        ['session_timeout_min', '15', 'INT', 'Thời gian hết hạn phiên đăng ký (phút)'],
        ['liveness_required', 'true', 'BOOL', 'Bắt buộc kiểm tra liveness'],
        ['gabor_orientations', '8', 'INT', 'Số hướng bộ lọc Gabor'],
        ['gabor_scales', '4', 'INT', 'Số tỉ lệ bộ lọc Gabor']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO ai_settings (setting_key, setting_value, value_type, description) VALUES (?, ?, ?, ?)");
    foreach ($settings as $s) {
        $stmt->execute($s);
    }
    echo "✓ ai_settings seeded (9 parameters)\n";
    
    echo "\n✅ All done!\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
}
?>
