<?php
/**
 * File cấu hình kết nối CSDL và Hệ thống Máy chủ AI
 */

// 1. Cấu hình Database MySQL
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'iris_lab_security');

// 2. Cấu hình URL gọi tới FastAPI Backend (Python)
define('API_BASE_URL', 'http://127.0.0.1:8000/api');

try {
    // Kết nối MySQL qua PDO
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    // Bật chế độ báo lỗi Exception để dễ debug
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die(json_encode([
        "status" => "error", 
        "message" => "LỖI KẾT NỐI DATABASE MYSQL: " . $e->getMessage()
    ]));
}
?>

