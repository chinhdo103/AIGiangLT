<?php
require_once __DIR__ . '/auth_guard.php';
$user = current_user();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found - Không tìm thấy tài nguyên</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .error-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .error-card {
            max-width: 580px;
            width: 100%;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
        }
        .huge-404 {
            font-size: 6rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -2px;
            color: #2563eb;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="glass-card error-card p-4 p-md-5 text-center">
            <div class="huge-404 font-mono">404</div>
            
            <h3 class="fw-bold mb-3">Tài Nguyên Không Tồn Tại</h3>
            <p class="text-secondary mb-4">
                Đường dẫn URL bạn vừa yêu cầu không nằm trong bản đồ điều phối của máy chủ an ninh hoặc đã bị di dời.
            </p>

            <div class="d-flex flex-wrap justify-content-center gap-3">
                <?php if ($user && $user['is_admin']): ?>
                    <a href="dashboard.php" class="btn btn-cyber-primary">
                        <i class="fas fa-chart-pie"></i> Về Bảng Điều Khiển Admin
                    </a>
                <?php elseif ($user): ?>
                    <a href="user_portal.php" class="btn btn-cyber-primary">
                        <i class="fas fa-user-circle"></i> Về Cổng Thông Tin Của Tôi
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-cyber-primary">
                        <i class="fas fa-sign-in-alt"></i> Về Trang Đăng Nhập
                    </a>
                <?php endif; ?>

                <a href="kiosk.php" target="_blank" class="btn btn-cyber-secondary">
                    <i class="fas fa-camera"></i> Đến Kiosk Quét Mắt
                </a>
            </div>
            
            <div class="mt-4 pt-3 border-top border-secondary border-opacity-10 text-muted small">
                <i class="fas fa-eye text-cyan me-1"></i> Iris Recognition Lab Security • Status: Secure
            </div>
        </div>
    </div>
</body>
</html>
