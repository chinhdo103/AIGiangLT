<?php
// includes/sidebar.php
if (!isset($currentPage)) {
    $currentPage = '';
}
$currentUser = current_user();
$isAdmin = $currentUser && $currentUser['is_admin'];
?>
<aside class="sidebar-wrapper" id="appSidebar">
    <!-- Brand Header -->
    <div class="brand-header">
        <a href="<?= $isAdmin ? 'dashboard.php' : 'user_portal.php' ?>" class="d-flex align-items-center gap-3 text-decoration-none">
            <div class="brand-icon">
                <i class="fas fa-eye"></i>
            </div>
            <div>
                <div class="fw-bold text-primary font-mono" style="letter-spacing: 1px;">IRIS LAB AI</div>
                <div class="small text-muted font-mono" style="font-size: 0.7rem;">BIOMETRIC SECURITY</div>
            </div>
        </a>
    </div>

    <!-- Navigation Menu -->
    <div class="sidebar-menu">
        <?php if ($isAdmin): ?>
            <!-- ================= ADMIN DASHBOARD ================= -->
            <div class="menu-category text-cyan">
                <i class="fas fa-dashboard me-1"></i> ĐIỀU KHIỂN TRUNG TÂM
            </div>

            <a href="dashboard.php" class="nav-link-custom <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-chart-pie text-cyan"></i>
                <span>Tổng quan & Thống kê</span>
            </a>

            <a href="live_monitor.php" class="nav-link-custom <?= $currentPage === 'live_monitor' ? 'active' : '' ?>">
                <i class="fas fa-radar text-emerald"></i>
                <span>Giám sát Thời gian thực</span>
                <span class="badge bg-success ms-auto" style="font-size: 0.6rem; padding: 2px 6px;">LIVE</span>
            </a>

            <!-- ================= ENROLLMENT & IDENTITY ================= -->
            <div class="menu-category text-muted mt-3">
                <i class="fas fa-fingerprint me-1"></i> ĐĂNG KÝ SINH TRẮC
            </div>

            <a href="enroll.php" class="nav-link-custom <?= $currentPage === 'enroll' ? 'active' : '' ?>">
                <i class="fas fa-user-plus text-emerald"></i>
                <span>Đăng ký Mống mắt Mới</span>
            </a>

            <a href="employees.php" class="nav-link-custom <?= $currentPage === 'employees' ? 'active' : '' ?>">
                <i class="fas fa-users text-indigo"></i>
                <span>Quản lý Nhân sự</span>
            </a>

            <a href="employee_manager.php" class="nav-link-custom <?= $currentPage === 'employee_manager' ? 'active' : '' ?>">
                <i class="fas fa-id-card text-orange"></i>
                <span>Quản lý Templates</span>
            </a>

            <!-- ================= ACCESS CONTROL ================= -->
            <div class="menu-category text-muted mt-3">
                <i class="fas fa-shield-halved me-1"></i> KIỂM SOÁT TRUY CẬP
            </div>

            <a href="access_control.php" class="nav-link-custom <?= $currentPage === 'access_control' ? 'active' : '' ?>">
                <i class="fas fa-door-open text-amber"></i>
                <span>Quản lý Phân quyền Lab</span>
            </a>

            <a href="labs.php" class="nav-link-custom <?= $currentPage === 'labs' ? 'active' : '' ?>">
                <i class="fas fa-building-lock text-indigo"></i>
                <span>Cấu hình Phòng Lab</span>
            </a>

            <!-- ================= AI ANALYTICS ================= -->
            <div class="menu-category text-muted mt-3">
                <i class="fas fa-brain me-1"></i> PHÂN TÍCH AI & DỮ LIỆU
            </div>

            <a href="tracking.php" class="nav-link-custom <?= $currentPage === 'tracking' ? 'active' : '' ?>">
                <i class="fas fa-microchip text-cyan"></i>
                <span>Theo dõi AI Pipeline</span>
            </a>

            <a href="reports.php" class="nav-link-custom <?= $currentPage === 'reports' ? 'active' : '' ?>">
                <i class="fas fa-file-chart-line text-emerald"></i>
                <span>Báo Cáo & Xuất Dữ Liệu</span>
            </a>

            <!-- ================= KIOSK & UTILITIES ================= -->
            <div class="menu-category text-muted mt-3">
                <i class="fas fa-gears me-1"></i> HỆ THỐNG & CÔNG CỤ
            </div>

            <a href="kiosk.php" target="_blank" class="nav-link-custom">
                <i class="fas fa-camera text-rose"></i>
                <span>Trạm Quét Kiosk</span>
                <span class="badge bg-danger ms-auto" style="font-size: 0.6rem; padding: 2px 6px;">DEMO</span>
            </a>

            <a href="user_portal.php" class="nav-link-custom <?= $currentPage === 'user_portal' ? 'active' : '' ?>">
                <i class="fas fa-id-badge text-secondary"></i>
                <span>Hồ sơ Cá nhân</span>
            </a>

        <?php else: ?>
            <!-- ================= ENDUSER / NHÂN VIÊN MENU (KHÔNG CÓ MENU QUẢN TRỊ) ================= -->
            <div class="menu-category text-emerald">
                <i class="fas fa-user-check me-1"></i> CỔNG NHÂN VIÊN (USER PORTAL)
            </div>

            <a href="user_portal.php" class="nav-link-custom <?= $currentPage === 'user_portal' ? 'active' : '' ?>">
                <i class="fas fa-id-badge text-emerald"></i>
                <span>Hồ sơ Sinh trắc & Thẻ</span>
            </a>

            <a href="kiosk.php" target="_blank" class="nav-link-custom">
                <i class="fas fa-door-open text-cyan"></i>
                <span>Quét Mắt Ra/Vào Cửa</span>
                <span class="badge bg-success ms-auto small" style="font-size: 0.65rem;">KIOSK</span>
            </a>

            <div class="p-3 my-3 rounded-3 small text-muted border" style="background: rgba(51,65,85,0.3); border-color: var(--border-light) !important;">
                <i class="fas fa-info-circle text-cyan me-1"></i> Đăng nhập với vai trò <strong class="text-secondary"><?= htmlspecialchars($currentUser['role'] ?? 'Nhân viên') ?></strong>. Khu vực Quản trị đã ẩn an toàn.
            </div>
        <?php endif; ?>
    </div>

    <!-- User Profile Footer -->
    <div class="sidebar-user-card">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2 overflow-hidden">
                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold" 
                     style="width: 38px; height: 38px; background: <?= $isAdmin ? 'linear-gradient(135deg, var(--color-amber), var(--color-rose))' : 'linear-gradient(135deg, var(--color-primary), var(--color-indigo))' ?>; color: var(--bg-body); flex-shrink: 0; box-shadow: <?= $isAdmin ? 'var(--shadow-glow-rose)' : 'var(--shadow-glow-cyan)' ?>;">
                    <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="overflow-hidden">
                    <div class="text-truncate fw-semibold small" style="color: var(--text-primary);"><?= htmlspecialchars($currentUser['name'] ?? 'Người dùng') ?></div>
                    <div>
                        <?php if ($isAdmin): ?>
                            <span class="badge badge-role badge-role-admin" style="font-size: 0.65rem;">
                                <i class="fas fa-crown"></i> ADMIN
                            </span>
                        <?php else: ?>
                            <span class="badge badge-role badge-role-user" style="font-size: 0.65rem;">
                                <i class="fas fa-user"></i> <?= htmlspecialchars($currentUser['role'] ?? 'USER') ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <a href="logout.php" class="btn btn-sm border-0 p-2" style="background: rgba(251,113,133,0.2); color: var(--color-rose);" title="Đăng xuất khỏi hệ thống">
                <i class="fas fa-arrow-right-from-bracket"></i>
            </a>
        </div>
    </div>
</aside>

