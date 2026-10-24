<?php
require_once 'config.php';
try {
    $pdo->exec("ALTER TABLE access_logs ADD COLUMN pipeline_details JSON NULL");
    echo "Column added successfully.\n";
} catch (Exception $e) {
    echo "Notice: " . $e->getMessage() . "\n";
}
?>

