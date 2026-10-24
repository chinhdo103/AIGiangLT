<?php
/**
 * TRANG QUẢN LÝ NHÂN SỰ & PHÂN QUYỀN TRUY CẬP (RBAC MANAGEMENT)
 * Bắt buộc quyền Quản trị viên (Admin).
 */
require_once __DIR__ . '/auth_guard.php';
require_admin();

require_once __DIR__ . '/config.php';

$pageTitle = "Quản Lý Nhân Sự & Phân Quyền";
$currentPage = "employees";

$message = null;
$messageType = null;

// Xử lý Cập nhật vai trò / Trạng thái
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_role') {
        $empId = intval($_POST['emp_id']);
        $newRole = trim($_POST['role']);
        $labId = intval($_POST['lab_id']);
        $status = trim($_POST['status']);

        try {
            $stmt = $pdo->prepare("UPDATE employees SET role = ?, allowed_lab_id = ?, status = ? WHERE emp_id = ?");
            $stmt->execute([$newRole, $labId, $status, $empId]);
            $message = "Đã cập nhật vai trò và quyền hạn thành công cho nhân viên ID #$empId!";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Lỗi cập nhật: " . $e->getMessage();
            $messageType = "danger";
        }
    } elseif ($_POST['action'] === 'delete_employee') {
        $empId = intval($_POST['emp_id']);
        try {
            $stmt = $pdo->prepare("DELETE FROM employees WHERE emp_id = ?");
            $stmt->execute([$empId]);
            $message = "Đã xóa hồ sơ nhân sự ID #$empId khỏi hệ thống!";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Lỗi khi xóa: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Truy vấn danh sách nhân viên cùng phòng lab và trạng thái mống mắt
$query = "SELECT e.*, l.lab_name, 
          (SELECT COUNT(*) FROM iris_templates it WHERE it.emp_id = e.emp_id) as has_iris
          FROM employees e
          LEFT JOIN laboratories l ON e.allowed_lab_id = l.lab_id
          ORDER BY e.emp_id DESC";
$employees = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Danh sách phòng lab để select
$labs = $pdo->query("SELECT * FROM laboratories ORDER BY lab_id ASC")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="main-wrapper">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="content-container">

        <!-- Tiêu đề & Nút thêm mới -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h3 class="fw-bold mb-1">Quản Lý Nhân Sự & Phân Quyền Vai Trò</h3>
                <p class="text-secondary small mb-0 font-mono">Quản lý đặc quyền Quản trị viên (ADMIN) và cấp phép ra vào phòng thí nghiệm</p>
            </div>
            <a href="enroll.php" class="btn btn-cyber-primary">
                <i class="fas fa-user-plus"></i> Đăng Ký Nhân Viên Mới + Mống Mắt
            </a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> glass-card p-3 mb-4 d-flex align-items-center gap-2">
                <i class="fas <?= $messageType === 'success' ? 'fa-circle-check text-emerald' : 'fa-circle-exclamation text-rose' ?> fs-5"></i>
                <div><?= htmlspecialchars($message) ?></div>
            </div>
        <?php endif; ?>

        <!-- Hộp Hướng Dẫn Phân Quyền -->
        <div class="glass-card p-3 mb-4" style="background: #ffffff; border-left: 4px solid var(--color-primary);">
            <div class="d-flex align-items-center gap-2 small text-secondary">
                <i class="fas fa-shield-halved text-primary fs-5"></i>
                <div>
                    <strong class="text-dark">Cơ chế Phân quyền Tự động:</strong> 
                    Nhân viên có vai trò <strong>"Quản trị viên"</strong> khi quét mống mắt sẽ có menu Quản trị và vào được mọi trang. 
                    Nhân viên có vai trò <strong>"Nhà nghiên cứu"</strong> hoặc <strong>"Nhân viên"</strong> chỉ được vào Cổng Cá Nhân (User Portal) và sẽ bị <strong>chặn 403</strong> nếu cố gõ URL Quản trị.
                </div>
            </div>
        </div>

        <!-- Bảng Danh Sách Nhân Viên -->
        <div class="glass-card p-4">
            <div class="table-responsive">
                <table class="table-cyber">
                    <thead>
                        <tr>
                            <th>Mã NV</th>
                            <th>Họ và Tên</th>
                            <th>Email Liên Hệ</th>
                            <th>Vai Trò (Role)</th>
                            <th>Phòng Lab Được Vào</th>
                            <th>Mống Mắt AI</th>
                            <th>Trạng Thái</th>
                            <th class="text-end">Hành Động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($employees)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-5">Chưa có dữ liệu nhân viên nào!</td></tr>
                        <?php else: ?>
                            <?php foreach ($employees as $emp): 
                                $isAdminEmp = is_admin_role($emp['role']);
                            ?>
                                <tr>
                                    <td class="font-mono text-cyan fw-bold">
                                        EMP-<?= str_pad($emp['emp_id'], 3, '0', STR_PAD_LEFT) ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($emp['full_name']) ?></div>
                                        <small class="text-muted font-mono" style="font-size: 0.72rem;">Đăng ký: <?= date('d/m/Y', strtotime($emp['created_at'])) ?></small>
                                    </td>
                                    <td class="font-mono small text-muted">
                                        <?= htmlspecialchars($emp['email']) ?>
                                    </td>
                                    <td>
                                        <?php if ($isAdminEmp): ?>
                                            <span class="badge badge-role badge-role-admin">
                                                <i class="fas fa-crown"></i> Quản trị viên (ADMIN)
                                            </span>
                                        <?php elseif (mb_stripos($emp['role'], 'nghiên cứu') !== false): ?>
                                            <span class="badge badge-role badge-role-researcher">
                                                <i class="fas fa-flask"></i> <?= htmlspecialchars($emp['role']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-role badge-role-user">
                                                <i class="fas fa-user"></i> <?= htmlspecialchars($emp['role']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="small text-light font-mono">
                                            <i class="fas fa-door-closed text-cyan me-1"></i>
                                            <?= htmlspecialchars($emp['lab_name'] ?? 'Chưa gán') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($emp['has_iris'] > 0): ?>
                                            <span class="badge bg-success bg-opacity-25 text-emerald border border-success border-opacity-35 small font-mono">
                                                <i class="fas fa-check-circle"></i> 1280-D READY
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning bg-opacity-25 text-amber border border-warning border-opacity-35 small font-mono">
                                                <i class="fas fa-triangle-exclamation"></i> CHƯA CÓ
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (($emp['status'] ?? 'ACTIVE') === 'ACTIVE'): ?>
                                            <span class="text-emerald small font-mono fw-semibold">
                                                <i class="fas fa-circle" style="font-size: 8px;"></i> Hoạt động
                                            </span>
                                        <?php else: ?>
                                            <span class="text-rose small font-mono fw-semibold">
                                                <i class="fas fa-circle" style="font-size: 8px;"></i> Tạm khóa
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-cyber-secondary me-1" 
                                                onclick="openEditModal(<?= htmlspecialchars(json_encode($emp)) ?>)" 
                                                title="Chỉnh sửa vai trò & phân quyền">
                                            <i class="fas fa-pen-to-square text-cyan"></i>
                                        </button>
                                        <button class="btn btn-sm btn-cyber-secondary" 
                                                onclick="confirmDelete(<?= $emp['emp_id'] ?>, '<?= htmlspecialchars(addslashes($emp['full_name'])) ?>')" 
                                                title="Xóa nhân viên">
                                            <i class="fas fa-trash-can text-rose"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

<!-- Modal Chỉnh Sửa Phân Quyền & Vai Trò -->
<div class="modal fade" id="editRoleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-cyan" id="editModalTitle">
                    <i class="fas fa-user-shield me-2"></i> Phân Quyền & Cập Nhật Vai Trò
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_role">
                <input type="hidden" name="emp_id" id="edit_emp_id">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">Họ và Tên Nhân Viên</label>
                        <input type="text" id="edit_fullname" class="form-control" readonly disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-cyan fw-bold">
                            <i class="fas fa-shield-alt me-1"></i> Chọn Vai Trò (Quyết định Menu & Quyền Truy Cập)
                        </label>
                        <select name="role" id="edit_role" class="form-select" required>
                            <option value="Quản trị viên">Quản trị viên (ADMIN) - Toàn quyền Dashboard & Hệ thống</option>
                            <option value="Nhà nghiên cứu">Nhà nghiên cứu (RESEARCHER) - Vào Cổng User & Phòng Lab</option>
                            <option value="Nhân viên">Nhân viên (STAFF) - Vào Cổng User & Cửa được phân bổ</option>
                        </select>
                        <div class="text-muted small mt-1">
                            Chọn <strong>"Quản trị viên"</strong> để cấp quyền mở menu quản trị khi quét mắt.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Phòng Lab Được Phép Ra/Vào</label>
                        <select name="lab_id" id="edit_lab_id" class="form-select">
                            <?php foreach ($labs as $lab): ?>
                                <option value="<?= $lab['lab_id'] ?>">
                                    <?= htmlspecialchars($lab['lab_name']) ?> (Cấp độ: <?= $lab['security_level'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Trạng Thái Hoạt Động</label>
                        <select name="status" id="edit_status" class="form-select">
                            <option value="ACTIVE">Hoạt động bình thường (ACTIVE)</option>
                            <option value="SUSPENDED">Tạm khóa quyền truy cập (SUSPENDED)</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-cyber-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn btn-cyber-primary">
                        <i class="fas fa-save"></i> Lưu Thay Đổi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Form Xóa Ẩn -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_employee">
    <input type="hidden" name="emp_id" id="delete_emp_id">
</form>

<script>
    let editModal = null;
    window.addEventListener('DOMContentLoaded', () => {
        editModal = new bootstrap.Modal(document.getElementById('editRoleModal'));
    });

    function openEditModal(emp) {
        document.getElementById('edit_emp_id').value = emp.emp_id;
        document.getElementById('edit_fullname').value = emp.full_name;
        document.getElementById('edit_lab_id').value = emp.allowed_lab_id || 1;
        document.getElementById('edit_status').value = emp.status || 'ACTIVE';

        const roleSelect = document.getElementById('edit_role');
        // So khớp role hiện tại
        const currentRole = emp.role.toLowerCase();
        if (currentRole.includes('admin') || currentRole.includes('quản trị')) {
            roleSelect.value = 'Quản trị viên';
        } else if (currentRole.includes('nghiên cứu')) {
            roleSelect.value = 'Nhà nghiên cứu';
        } else {
            roleSelect.value = 'Nhân viên';
        }

        editModal.show();
    }

    function confirmDelete(id, name) {
        Swal.fire({
            title: 'Xác nhận xóa nhân viên?',
            text: `Bạn có chắc muốn xóa hồ sơ [${name}] và toàn bộ mẫu mống mắt liên kết?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f43f5e',
            cancelButtonColor: '#334155',
            confirmButtonText: 'Đồng ý xóa',
            cancelButtonText: 'Hủy bỏ',
            background: '#0f172a',
            color: '#fff'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('delete_emp_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        });
    }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

