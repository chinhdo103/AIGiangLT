<?php
require_once 'config.php';
$stmt = $pdo->query('SELECT log_id, pipeline_details FROM access_logs ORDER BY log_id DESC LIMIT 3');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>

