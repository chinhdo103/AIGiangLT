<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("
        INSERT INTO lab_permissions (lab_id, emp_id, permission_type, granted_by)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE permission_type = VALUES(permission_type)
    ");
    $stmt->execute([
        $_POST['lab_id'],
        $_POST['emp_id'],
        $_POST['permission_type'],
        $_SESSION['admin_id'] ?? 1
    ]);
    echo json_encode(['success' => true]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && isset($_GET['delete'])) {
    $pdo->exec("DELETE FROM lab_permissions WHERE permission_id = " . intval($_GET['delete']));
    echo json_encode(['success' => true]);
}
?>
