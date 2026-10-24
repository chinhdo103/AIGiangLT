<?php
require_once __DIR__ . '/auth_guard.php';
$user = current_user();
$attemptUri = htmlspecialchars($_GET['attempt'] ?? 'Khu vực Quản trị tối mật');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Forbidden - Truy cập bị từ chối</title>
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
            position: relative;
        }
        .error-card {
            max-width: 620px;
            width: 100%;
            background: #ffffff;
            border: 1px solid #fecdd3;
            box-shadow: 0 10px 25px -5px rgba(225, 29, 72, 0.08);
        }
        .error-badge-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            background: #fff1f2;
            border: 2px solid #fecdd3;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.4rem;
            color: #e11d48;
            margin: 0 auto 1.5rem;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="glass-card error-card p-4 p-md-5 text-center">
            <div class="error-badge-icon">
                <i class="fas fa-shield-halved"></i>
            </div>
            
            <span class="badge badge-role badge-role-admin text-rose mb-3" style="background: rgba(244, 63, 94, 0.15); border-color: rgba(244, 63, 94, 0.35);">
                <i class="fas fa-lock"></i> MÃ LỖI BẢO MẬT: HTTP 403 FORBIDDEN
            </span>
            
            <h2 class="fw-bold mb-3 text-dark">Truy Cập Bị Từ Chối!</h2>
            <p class="text-secondary mb-4">
                Bạn không có đặc quyền <strong class="text-primary">Quản trị viên (ADMIN)</strong> để truy cập phân hệ này. Hệ thống đã chủ động kích hoạt cơ chế phòng vệ và ngăn chặn URL trực tiếp.
            </p>
            
            <div class="p-3 mb-4 rounded-3 text-start font-mono small bg-light border">
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted"><i class="fas fa-user-circle me-1"></i> Định danh người dùng:</span>
                    <strong class="text-primary"><?= $user ? htmlspecialchars($user['name']) : 'Khách lạ (Chưa xác thực)' ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted"><i class="fas fa-id-badge me-1"></i> Vai trò hiện tại:</span>
                    <span class="text-warning fw-bold"><?= $user ? htmlspecialchars($user['role']) : 'GUEST' ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted"><i class="fas fa-link me-1"></i> URL yêu cầu:</span>
                    <span class="text-danger text-truncate" style="max-width: 280px;"><?= $attemptUri ?></span>
                </div>
            </div>

            <div class="d-flex flex-wrap justify-content-center gap-3">
                <?php if ($user && !$user['is_admin']): ?>
                    <a href="user_portal.php" class="btn btn-cyber-primary">
                        <i class="fas fa-user"></i> Về Trang Cá Nhân Của Bạn
                    </a>
                <?php endif; ?>
                
                <a href="login.php" class="btn btn-cyber-secondary">
                    <i class="fas fa-arrow-right-to-bracket"></i> Đăng Nhập Tài Khoản Admin
                </a>

                <a href="kiosk.php" target="_blank" class="btn btn-cyber-secondary">
                    <i class="fas fa-camera"></i> Đến Kiosk Quét Cửa
                </a>
            </div>
            
            <div class="mt-4 pt-3 border-top border-secondary border-opacity-10 text-muted small">
                <i class="fas fa-robot text-cyan me-1"></i> Hệ thống Iris AI Security Core v2.4 • Mọi hành động xâm nhập đều được ghi nhận
            </div>
        </div>
    </div>
</body>
</html>
