<?php
require_once 'config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'stats';

if ($action === 'stats') {
    $range = $_GET['range'] ?? '24h';
    
    $logs = $pdo->query("
        SELECT 
            DATE_FORMAT(logged_at, '%H:00') as hour,
            SUM(access_granted = 1) as granted,
            SUM(access_granted = 0) as denied
        FROM access_logs
        WHERE logged_at >= NOW() - INTERVAL 24 HOUR
        GROUP BY hour
        ORDER BY hour
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'labels' => array_column($logs, 'hour'),
        'granted' => array_column($logs, 'granted'),
        'denied' => array_column($logs, 'denied')
    ]);
}

if ($action === 'recent_logs') {
    $logs = $pdo->query("
        SELECT 
            al.*,
            e.name as emp_name,
            l.lab_name
        FROM access_logs al
        LEFT JOIN employees e ON al.emp_id = e.emp_id
        LEFT JOIN laboratories l ON al.lab_id = l.lab_id
        ORDER BY al.logged_at DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['logs' => $logs]);
}
?>
