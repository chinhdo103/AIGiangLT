<?php
require_once 'config.php';
header('Content-Type: application/json');

$perms = $pdo->query("
    SELECT 
        p.*,
        e.name as emp_name,
        l.lab_name,
        admin.name as granted_by_name
    FROM lab_permissions p
    JOIN employees e ON p.emp_id = e.emp_id
    JOIN laboratories l ON p.lab_id = l.lab_id
    LEFT JOIN employees admin ON p.granted_by = admin.emp_id
    ORDER BY p.granted_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($perms);
?>
