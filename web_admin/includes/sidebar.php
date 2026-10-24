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
            <!-- ================= QUẢN TRỊ VIÊN MENU ================= -->
            <div class="menu-category text-cyan">
                <i class="fas fa-shield-alt me-1"></i> BẢNG QUẢN TRỊ HỆ THỐNG
            </div>

            <a href="dashboard.php" class="nav-link-custom <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-chart-line text-cyan"></i>
                <span>Tổng quan (Dashboard)</span>
            </a>

            <a href="employees.php" class="nav-link-custom <?= $currentPage === 'employees' ? 'active' : '' ?>">
                <i class="fas fa-users-gear text-indigo"></i>
                <span>Quản lý Nhân sự & Quyền</span>
            </a>

            <a href="enroll.php" class="nav-link-custom <?= $currentPage === 'enroll' ? 'active' : '' ?>">
                <i class="fas fa-id-card-clip text-emerald"></i>
                <span>Đăng ký Mống mắt Mới</span>
            </a>

            <a href="tracking.php" class="nav-link-custom <?= $currentPage === 'tracking' ? 'active' : '' ?>">
                <i class="fas fa-microchip text-cyan"></i>
                <span>Theo dõi AI Pipeline</span>
            </a>

            <a href="reports.php" class="nav-link-custom <?= $currentPage === 'reports' ? 'active' : '' ?>">
                <i class="fas fa-file-invoice text-emerald"></i>
                <span>Báo Cáo & Xuất Dữ Liệu</span>
            </a>

            <a href="labs.php" class="nav-link-custom <?= $currentPage === 'labs' ? 'active' : '' ?>">
                <i class="fas fa-door-open text-amber"></i>
                <span>Quản lý Phòng Lab</span>
            </a>

            <div class="menu-category text-muted mt-3">
                <i class="fas fa-terminal me-1"></i> TRẠM QUÉT & TIỆN ÍCH
            </div>

            <a href="kiosk.php" target="_blank" class="nav-link-custom">
                <i class="fas fa-camera text-rose"></i>
                <span>Mở Kiosk Quét Cửa</span>
                <span class="badge bg-danger ms-auto small" style="font-size: 0.65rem;">LIVE</span>
            </a>

            <a href="user_portal.php" class="nav-link-custom <?= $currentPage === 'user_portal' ? 'active' : '' ?>">
                <i class="fas fa-address-card text-secondary"></i>
                <span>Xem Thẻ Sinh Trắc Của Tôi</span>
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

            <div class="p-3 my-3 rounded-3 small text-muted bg-light border">
                <i class="fas fa-info-circle text-primary me-1"></i> Bạn đang đăng nhập với tư cách <strong><?= htmlspecialchars($currentUser['role'] ?? 'Nhân viên') ?></strong>. Khu vực Quản trị đã được ẩn an toàn.
            </div>
        <?php endif; ?>
    </div>

    <!-- User Profile Footer -->
    <div class="sidebar-user-card">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2 overflow-hidden">
                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" 
                     style="width: 38px; height: 38px; background: <?= $isAdmin ? 'linear-gradient(135deg, #f59e0b, #ef4444)' : 'linear-gradient(135deg, #2563eb, #3b82f6)' ?>; flex-shrink: 0;">
                    <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="overflow-hidden">
                    <div class="text-dark text-truncate fw-semibold small"><?= htmlspecialchars($currentUser['name'] ?? 'Người dùng') ?></div>
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
            <a href="logout.php" class="btn btn-sm btn-outline-danger border-0 p-2" title="Đăng xuất khỏi hệ thống">
                <i class="fas fa-arrow-right-from-bracket"></i>
            </a>
        </div>
    </div>
</aside>

