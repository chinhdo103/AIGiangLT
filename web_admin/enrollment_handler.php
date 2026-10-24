<?php
require_once 'config.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$emp_id = $data['emp_id'];
$templates = $data['templates'];

try {
    $pdo->beginTransaction();
    
    foreach ($templates as $t) {
        $stmt = $pdo->prepare("
            INSERT INTO iris_templates 
            (emp_id, template_index, cnn_vector, gabor_vector, quality_score, status)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $emp_id,
            $t['template_index'],
            json_encode($t['cnn_vector']),
            json_encode($t['gabor_vector']),
            $t['quality_score'],
            $t['status']
        ]);
    }
    
    $pdo->exec("UPDATE employees SET iris_enrolled = 1 WHERE emp_id = $emp_id");
    $pdo->commit();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
