<?php
require_once 'config.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$emp_id = $data['emp_id'] ?? null;
$templates = $data['templates'] ?? [];

if (!$emp_id || empty($templates)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Thiếu dữ liệu nhân viên hoặc mẫu mống mắt']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    $lastCnn = null;
    foreach ($templates as $t) {
        $cnnArr = $t['cnn_vector'] ?? $t['embedding'] ?? [];
        $gaborArr = $t['gabor_vector'] ?? [];
        $cnnJson = json_encode($cnnArr);
        $gaborJson = json_encode($gaborArr);
        if (!empty($cnnArr)) {
            $lastCnn = $cnnArr;
        }

        $stmt = $pdo->prepare("
            INSERT INTO iris_templates 
            (emp_id, template_index, embedding_vector, cnn_vector, gabor_vector, quality_score, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                embedding_vector = VALUES(embedding_vector),
                cnn_vector = VALUES(cnn_vector),
                gabor_vector = VALUES(gabor_vector),
                quality_score = VALUES(quality_score),
                status = VALUES(status),
                registered_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            $emp_id,
            $t['template_index'] ?? 1,
            $cnnJson,
            $cnnJson,
            $gaborJson,
            $t['quality_score'] ?? 20.0,
            $t['status'] ?? 'ACTIVE'
        ]);
    }
    
    $pdo->exec("UPDATE employees SET iris_enrolled = 1 WHERE emp_id = " . intval($emp_id));
    $pdo->commit();

    // Đồng bộ vào ai_service/iris_database.json để AI nhận diện tức thì
    if ($lastCnn) {
        $jsonPath = __DIR__ . '/../ai_service/iris_database.json';
        if (file_exists($jsonPath)) {
            $dbJson = json_decode(file_get_contents($jsonPath), true) ?: [];
            $dbJson["EMP" . $emp_id] = $lastCnn;
            file_put_contents($jsonPath, json_encode($dbJson, JSON_PRETTY_PRINT));
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Lưu sinh trắc thành công']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
