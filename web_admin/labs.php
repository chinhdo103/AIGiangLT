<?php
/**
 * TRANG QUẢN LÝ PHÒNG THÍ NGHIỆM & CỬA KIỂM SOÁT (LABS & ACCESS ZONES)
 * Bắt buộc quyền Quản trị viên (Admin).
 */
require_once __DIR__ . '/auth_guard.php';
require_admin();

require_once __DIR__ . '/config.php';

$pageTitle = "Quản Lý Phòng Thí Nghiệm & Cửa";
$currentPage = "labs";

$message = null;
$messageType = null;

// Thêm phòng Lab mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_lab') {
    $labName = trim($_POST['lab_name'] ?? '');
    $secLevel = trim($_POST['security_level'] ?? 'HIGH');

    if (!empty($labName)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO laboratories (lab_name, security_level) VALUES (?, ?)");
            $stmt->execute([$labName, $secLevel]);
            $message = "Đã khởi tạo thành công phòng thí nghiệm: $labName!";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Lỗi khi thêm: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Lấy danh sách lab kèm số lượng nhân viên được cấp phép
$stmt = $pdo->query("SELECT l.*, 
                     (SELECT COUNT(*) FROM employees e WHERE e.allowed_lab_id = l.lab_id) as member_count,
                     (SELECT COUNT(*) FROM access_logs al WHERE al.lab_id = l.lab_id AND DATE(al.access_time) = CURDATE()) as today_scans
                     FROM laboratories l
                     ORDER BY l.lab_id ASC");
$labs = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="content-container">

        <!-- Header -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h3 class="fw-bold mb-1">Quản Lý Phòng Thí Nghiệm & Cửa An Ninh</h3>
                <p class="text-secondary small mb-0 font-mono">Cấu hình cấp độ bảo mật (CRITICAL / HIGH / MEDIUM) và phân vùng kiểm soát</p>
            </div>
            <button class="btn btn-cyber-primary" data-bs-toggle="modal" data-bs-target="#addLabModal">
                <i class="fas fa-plus-circle"></i> Thêm Phòng Lab Mới
            </button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> glass-card p-3 mb-4 d-flex align-items-center gap-2">
                <i class="fas <?= $messageType === 'success' ? 'fa-circle-check text-emerald' : 'fa-circle-exclamation text-rose' ?> fs-5"></i>
                <div><?= htmlspecialchars($message) ?></div>
            </div>
        <?php endif; ?>

        <!-- Grid Các Phòng Lab -->
        <div class="row g-4">
            <?php foreach ($labs as $lab): 
                $badgeColor = $lab['security_level'] === 'CRITICAL' ? 'bg-danger' : ($lab['security_level'] === 'HIGH' ? 'bg-warning text-dark' : 'bg-info text-dark');
            ?>
                <div class="col-md-6 col-xl-4">
                    <div class="glass-card p-4 h-100 card-interactive">
                        
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div class="rounded-3 d-flex align-items-center justify-content-center" 
                                 style="width: 52px; height: 52px; background: rgba(6, 182, 212, 0.15); color: var(--neon-cyan); border: 1px solid rgba(6, 182, 212, 0.3);">
                                <i class="fas fa-door-closed fs-3"></i>
                            </div>
                            <span class="badge <?= $badgeColor ?> font-mono px-2 py-1">
                                <i class="fas fa-shield-halved me-1"></i> CẤP ĐỘ: <?= $lab['security_level'] ?>
                            </span>
                        </div>

                        <h5 class="fw-bold text-dark mb-2"><?= htmlspecialchars($lab['lab_name']) ?></h5>
                        <div class="text-muted small font-mono mb-4">
                            Mã trạm: ZONE-LAB-<?= str_pad($lab['lab_id'], 2, '0', STR_PAD_LEFT) ?>
                        </div>

                        <div class="row g-2 pt-3 border-top font-mono small">
                            <div class="col-6">
                                <span class="text-muted d-block">Nhân sự cấp phép:</span>
                                <strong class="text-dark fs-6"><?= $lab['member_count'] ?> Người</strong>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block">Lượt quét hôm nay:</span>
                                <strong class="text-emerald fs-6"><?= $lab['today_scans'] ?> Lượt</strong>
                            </div>
                        </div>

                        <div class="mt-4 d-flex gap-2">
                            <a href="kiosk.php" target="_blank" class="btn btn-sm btn-cyber-primary w-100 justify-content-center">
                                <i class="fas fa-camera"></i> Kiosk Quét Cửa Này
                            </a>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

<!-- Modal Thêm Lab Mới -->
<div class="modal fade" id="addLabModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-cyan">
                    <i class="fas fa-plus-circle me-2"></i> Khởi Tạo Phòng Lab / Cửa Kiểm Soát Mới
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_lab">
                
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">Tên Phòng Thí Nghiệm / Khu Vực</label>
                        <input type="text" name="lab_name" class="form-control" placeholder="Ví dụ: Phòng Thí Nghiệm Lượng Tử (Quantum Lab)" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Cấp Độ An Ninh Yêu Cầu</label>
                        <select name="security_level" class="form-select">
                            <option value="CRITICAL">CRITICAL - Tối mật (Yêu cầu mống mắt nghiêm ngặt + Liveness)</option>
                            <option value="HIGH" selected>HIGH - Bảo mật cao (Khu vực nghiên cứu chuyên sâu)</option>
                            <option value="MEDIUM">MEDIUM - Trung bình (Khu vực kỹ thuật & hành chính)</option>
                            <option value="LOW">LOW - Cơ bản (Sảnh chung)</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-cyber-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn btn-cyber-primary">
                        <i class="fas fa-save"></i> Tạo Phòng Mới
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

