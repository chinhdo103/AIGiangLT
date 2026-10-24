<?php
/**
 * CỔNG THÔNG TIN DÀNH RIÊNG CHO ENDUSER (NHÂN VIÊN / NHÀ NGHIÊN CỨU)
 * Hiển thị thẻ sinh trắc học mống mắt điện tử, lịch sử ra vào và quyền phòng lab.
 * Không chứa bất kỳ menu quản trị nào.
 */
require_once __DIR__ . '/auth_guard.php';
require_login(); // Yêu cầu đăng nhập

require_once __DIR__ . '/config.php';

$pageTitle = "Cổng Thông Tin Nhân Viên";
$currentPage = "user_portal";

$currentUser = current_user();
$empId = $currentUser['id'];

// Truy vấn thông tin chi tiết nhân viên từ MySQL
$employee = null;
$labInfo = null;
$myLogs = [];

if (is_numeric($empId)) {
    // Lấy thông tin nhân viên & phòng lab
    $stmt = $pdo->prepare("SELECT e.*, l.lab_name, l.security_level 
                           FROM employees e 
                           LEFT JOIN laboratories l ON e.allowed_lab_id = l.lab_id 
                           WHERE e.emp_id = ?");
    $stmt->execute([$empId]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    // Lấy lịch sử ra vào của riêng nhân viên này
    $stmtLogs = $pdo->prepare("SELECT al.*, l.lab_name 
                               FROM access_logs al 
                               LEFT JOIN laboratories l ON al.lab_id = l.lab_id 
                               WHERE al.emp_id = ? 
                               ORDER BY al.access_time DESC LIMIT 10");
    $stmtLogs->execute([$empId]);
    $myLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Tài khoản Master Admin
    $employee = [
        'emp_id' => '001',
        'full_name' => $currentUser['name'],
        'email' => $currentUser['email'],
        'role' => $currentUser['role'],
        'lab_name' => 'Toàn Quyền Hệ Thống Lab (CRITICAL)',
        'security_level' => 'CRITICAL',
        'status' => 'ACTIVE'
    ];
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="content-container">

        <!-- Thông báo nếu là Admin đang xem Portal -->
        <?php if ($currentUser['is_admin']): ?>
            <div class="alert glass-card border-warning mb-4 d-flex align-items-center justify-content-between p-3" style="background: rgba(245, 158, 11, 0.1);">
                <div class="d-flex align-items-center gap-3">
                    <i class="fas fa-crown text-warning fs-4"></i>
                    <div>
                        <strong class="text-warning">Đặc quyền Quản trị viên được kích hoạt!</strong>
                        <div class="text-secondary small">Bạn đang xem giao diện mẫu của Nhân viên (Enduser). Bạn có toàn quyền truy cập menu Quản trị.</div>
                    </div>
                </div>
                <a href="dashboard.php" class="btn btn-sm btn-cyber-primary">
                    <i class="fas fa-gauge-high"></i> Vào Trang Quản Trị
                </a>
            </div>
        <?php endif; ?>

        <!-- Tiêu đề trang -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h3 class="fw-bold mb-1">Hồ Sơ Sinh Trắc Học Điện Tử</h3>
                <p class="text-secondary small mb-0 font-mono">Định danh cá nhân & Giám sát quyền truy cập cơ sở nghiên cứu</p>
            </div>
            <a href="kiosk.php" target="_blank" class="btn btn-cyber-primary">
                <i class="fas fa-camera"></i> Mở Kiosk Quét Cửa <i class="fas fa-arrow-up-right-from-square small ms-1"></i>
            </a>
        </div>

        <div class="row g-4">
            
            <!-- CỘT TRÁI: THẺ SINH TRẮC CYBER BIOMETRIC ID CARD -->
            <div class="col-lg-5">
                <div class="glass-card p-4 position-relative overflow-hidden bg-white" 
                     style="border: 1px solid #cbd5e1; box-shadow: var(--shadow-md);">
                    
                    <!-- Hiệu ứng Thẻ Doanh Nghiệp -->
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-microchip text-primary fs-3"></i>
                            <span class="font-mono text-muted small" style="letter-spacing: 1px;">DIGITAL BIOMETRIC ID</span>
                        </div>
                        <span class="badge badge-role <?= $currentUser['is_admin'] ? 'badge-role-admin' : 'badge-role-researcher' ?>">
                            <?= htmlspecialchars($employee['role'] ?? 'Nhân viên') ?>
                        </span>
                    </div>

                    <!-- Ảnh đại diện / Mống mắt -->
                    <div class="text-center my-3">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center p-1 bg-light border" 
                             style="width: 100px; height: 100px;">
                            <div class="rounded-circle w-100 h-100 d-flex align-items-center justify-content-center" 
                                 style="background: #eff6ff;">
                                <i class="fas fa-eye text-primary fs-2"></i>
                            </div>
                        </div>
                        <h4 class="fw-bold mt-3 mb-0 text-dark"><?= htmlspecialchars($employee['full_name'] ?? $currentUser['name']) ?></h4>
                        <div class="text-primary small font-mono fw-semibold">ID: EMP-<?= str_pad($employee['emp_id'] ?? '1', 3, '0', STR_PAD_LEFT) ?></div>
                    </div>

                    <!-- Chi tiết thẻ -->
                    <div class="pt-3 border-top font-mono small">
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted"><i class="fas fa-envelope me-1"></i> Email:</span>
                            <span class="text-dark fw-semibold"><?= htmlspecialchars($employee['email'] ?? $currentUser['email']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted"><i class="fas fa-building me-1"></i> Phòng cấp phép:</span>
                            <span class="text-dark fw-semibold text-truncate" style="max-width: 200px;"><?= htmlspecialchars($employee['lab_name'] ?? 'Chưa phân bổ') ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted"><i class="fas fa-shield-alt me-1"></i> Mức an ninh:</span>
                            <span class="badge bg-danger"><?= htmlspecialchars($employee['security_level'] ?? 'HIGH') ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted"><i class="fas fa-fingerprint me-1"></i> Mống mắt AI:</span>
                            <span class="text-success fw-bold"><i class="fas fa-check-circle"></i> ĐÃ ĐĂNG KÝ (1280-D)</span>
                        </div>
                    </div>

                </div>

                <!-- Card Tình Trạng Thiết Bị & Xác Thực -->
                <div class="glass-card p-4 mt-4">
                    <h6 class="fw-bold text-cyan mb-3">
                        <i class="fas fa-clock-rotate-left me-1"></i> Phiên Đăng Nhập Hiện Tại
                    </h6>
                    <div class="small text-secondary">
                        <div class="mb-2"><strong>Thời gian đăng nhập:</strong> <span class="font-mono text-light"><?= $currentUser['login_time'] ?></span></div>
                        <div class="mb-2"><strong>Phương thức:</strong> <span class="badge bg-info text-dark font-mono"><?= $currentUser['auth_method'] === 'IRIS' ? 'Sinh trắc học Mống mắt' : 'Mật khẩu Master' ?></span></div>
                        <div><strong>Trạng thái tài khoản:</strong> <span class="text-emerald fw-semibold">Đang hoạt động (Active)</span></div>
                    </div>
                </div>
            </div>

            <!-- CỘT PHẢI: LỊCH SỬ RA/VÀO CỦA CÁ NHÂN -->
            <div class="col-lg-7">
                <div class="glass-card p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="fw-bold mb-0">
                            <i class="fas fa-list-check text-cyan me-2"></i> Nhật Ký Quét Cửa Cá Nhân
                        </h5>
                        <span class="badge bg-secondary font-mono"><?= count($myLogs) ?> lượt gần nhất</span>
                    </div>
                    
                    <p class="text-muted small mb-4">
                        Hệ thống tự động lưu trữ mọi lần bạn quét mống mắt tại các cửa kiểm soát ra vào cơ sở.
                    </p>

                    <?php if (empty($myLogs)): ?>
                        <div class="p-5 text-center text-muted">
                            <i class="fas fa-door-closed fs-1 mb-3 text-secondary"></i>
                            <h6>Chưa ghi nhận lượt quét cửa nào!</h6>
                            <p class="small">Hãy đến trạm Kiosk quét mắt để mở cửa phòng thí nghiệm.</p>
                            <a href="kiosk.php" target="_blank" class="btn btn-sm btn-cyber-primary mt-2">
                                Đến Kiosk Ngay
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table-cyber">
                                <thead>
                                    <tr>
                                        <th>Thời Gian</th>
                                        <th>Khu Vực / Phòng Lab</th>
                                        <th>Kết Quả</th>
                                        <th>Độ Khớp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($myLogs as $log): 
                                        $isGranted = $log['action'] === 'ENTRY_GRANTED';
                                    ?>
                                        <tr>
                                            <td class="font-mono small">
                                                <i class="fas fa-clock text-muted me-1"></i>
                                                <?= date('H:i:s d/m/Y', strtotime($log['access_time'])) ?>
                                            </td>
                                            <td>
                                                <strong class="text-light"><?= htmlspecialchars($log['lab_name'] ?? 'Phòng Lab AI') ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($isGranted): ?>
                                                    <span class="badge bg-success bg-opacity-25 text-emerald border border-success border-opacity-25 px-2 py-1">
                                                        <i class="fas fa-unlock me-1"></i> CỬA ĐÃ MỞ
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger bg-opacity-25 text-rose border border-danger border-opacity-25 px-2 py-1">
                                                        <i class="fas fa-lock me-1"></i> TỪ CHỐI
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="font-mono fw-bold text-cyan">
                                                <?= number_format(floatval($log['confidence_score']), 1) ?>%
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>

<?php include __DIR__ . '/includes/footer.php'; ?>

