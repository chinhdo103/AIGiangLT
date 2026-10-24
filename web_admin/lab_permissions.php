<!-- Lab Permissions Manager -->
<?php
require_once 'config.php';

$labs = $pdo->query("SELECT * FROM laboratories")->fetchAll();
$employees = $pdo->query("SELECT emp_id, name FROM employees WHERE iris_enrolled = 1")->fetchAll();
?>

<div class="container-fluid">
    <h3 class="mb-4">Quản Lý Quyền Phòng Lab</h3>
    
    <div class="card">
        <div class="card-body">
            <h5>Cấp Quyền Mới</h5>
            <form id="permission-form">
                <div class="row">
                    <div class="col-md-3">
                        <select class="form-select" name="emp_id" required>
                            <option>Chọn Nhân Viên</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['emp_id'] ?>"><?= $emp['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="lab_id" required>
                            <option>Chọn Lab</option>
                            <?php foreach ($labs as $lab): ?>
                                <option value="<?= $lab['lab_id'] ?>"><?= $lab['lab_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="permission_type" required>
                            <option value="READ">READ</option>
                            <option value="WRITE">WRITE</option>
                            <option value="ADMIN">ADMIN</option>
                            <option value="FULL">FULL</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">Cấp Quyền</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card mt-4">
        <div class="card-body">
            <h5>Danh Sách Quyền Hiện Tại</h5>
            <table class="table">
                <thead>
                    <tr>
                        <th>Nhân Viên</th>
                        <th>Phòng Lab</th>
                        <th>Loại Quyền</th>
                        <th>Cấp Bởi</th>
                        <th>Ngày Cấp</th>
                        <th>Hành Động</th>
                    </tr>
                </thead>
                <tbody id="permissions-list"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.getElementById('permission-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    const res = await fetch('permission_handler.php', {
        method: 'POST',
        body: formData
    });
    
    if (res.ok) {
        alert('Cấp quyền thành công!');
        loadPermissions();
    }
});

async function loadPermissions() {
    const res = await fetch('get_permissions.php');
    const data = await res.json();
    
    const tbody = document.getElementById('permissions-list');
    tbody.innerHTML = data.map(p => `
        <tr>
            <td>${p.emp_name}</td>
            <td>${p.lab_name}</td>
            <td><span class="badge bg-primary">${p.permission_type}</span></td>
            <td>${p.granted_by_name || 'System'}</td>
            <td>${new Date(p.granted_at).toLocaleDateString()}</td>
            <td>
                <button class="btn btn-sm btn-danger" onclick="revokePermission(${p.permission_id})">
                    Thu Hồi
                </button>
            </td>
        </tr>
    `).join('');
}

async function revokePermission(id) {
    if (!confirm('Thu hồi quyền này?')) return;
    await fetch(`permission_handler.php?delete=${id}`, {method: 'DELETE'});
    loadPermissions();
}

loadPermissions();
</script>
