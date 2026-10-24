<?php
// includes/navbar.php
$currentUser = current_user();
?>
<header class="top-navbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-cyber-secondary d-lg-none p-2" id="sidebarToggleBtn" type="button">
            <i class="fas fa-bars"></i>
        </button>
        <div class="d-none d-sm-block">
            <h5 class="mb-0 fw-bold"><?= htmlspecialchars($pageTitle ?? 'Bảng điều khiển') ?></h5>
            <small class="text-muted font-mono" style="font-size: 0.75rem;">
                <i class="fas fa-microchip text-cyan me-1"></i> AI Core: MobileNetV2 + Daugman Normalization
            </small>
        </div>
    </div>

    <div class="d-flex align-items-center gap-3">
        <!-- AI Server Status Pill -->
        <div class="d-none d-md-flex align-items-center gap-2 px-3 py-1 rounded-pill" style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3);">
            <span class="rounded-circle bg-success" style="width: 8px; height: 8px; box-shadow: 0 0 8px #10b981;"></span>
            <span class="text-emerald small font-mono fw-semibold" style="font-size: 0.75rem;">AI FASTAPI: ONLINE</span>
        </div>

        <!-- Auth Method Badge -->
        <span class="badge badge-role <?= ($currentUser['auth_method'] ?? '') === 'IRIS' ? 'badge-role-researcher' : 'badge-role-user' ?>">
            <i class="fas <?= ($currentUser['auth_method'] ?? '') === 'IRIS' ? 'fa-eye' : 'fa-key' ?>"></i>
            <?= ($currentUser['auth_method'] ?? '') === 'IRIS' ? 'Xác thực Mống mắt' : 'Mật khẩu' ?>
        </span>

        <!-- Logout Pill Button -->
        <a href="logout.php" class="btn btn-sm btn-cyber-secondary d-flex align-items-center gap-2">
            <i class="fas fa-power-off text-rose"></i>
            <span class="d-none d-sm-inline">Đăng xuất</span>
        </a>
    </div>
</header>

