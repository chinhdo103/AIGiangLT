<?php
/**
 * MODULE: Giao tiếp Xác thực Ra/Vào.
 * Nhận ảnh chụp từ giao diện Web Kiosk trước cửa -> Gửi cho AI -> Quyết định mở cửa -> Ghi Log.
 */
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

// Chỉ cho phép POST request và phải có file đính kèm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['eye_image'])) {
    
    $lab_id = $_POST['lab_id'] ?? 1; // Mặc định là Lab AI (ID: 1)

    // 1. Gói file ảnh vào định dạng CURLFile để gửi đi qua mạng
    $cfile = new CURLFile(
        $_FILES['eye_image']['tmp_name'], 
        $_FILES['eye_image']['type'], 
        $_FILES['eye_image']['name']
    );
    
    $post_data = array('file' => $cfile);
    
    // 2. Tạo HTTP Request gọi sang Máy chủ Trí tuệ Nhân tạo (Python FastAPI)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, API_BASE_URL . "/verify");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $api_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Xử lý lỗi nếu máy chủ AI sập hoặc chưa bật
    if ($http_code !== 200 || !$api_response) {
        echo json_encode(["status" => "error", "message" => "Lỗi: Không thể kết nối đến Máy chủ AI Mống mắt (Code: $http_code)."]);
        exit;
    }

    // 3. Phân tích kết quả trả về từ Lõi AI
    $result = json_decode($api_response, true);
    
    $is_match = $result['match'] ?? false;
    $door_status = $result['door_status'] ?? 'LOCKED';
    $message = $result['message'] ?? '';
    $confidence = $result['confidence'] ?? 0.0;
    $is_spoof = !empty($result['spoof_detected']) || (strpos($message, 'CẢNH BÁO') !== false) || (strpos($message, 'GIẢ MẠO') !== false);
    
    // 4. Quyết định hành vi logic & phân loại log dựa trên AI phán xét
    $action = 'ENTRY_DENIED';
    if ($is_match && $door_status === 'UNLOCKED') {
        $action = 'ENTRY_GRANTED';
    } elseif ($is_spoof) {
        $action = 'SPOOFING_ATTEMPT'; // Phát hiện có dấu hiệu cố tình qua mặt hệ thống
    }
    
    // 5. Ghi nhận hành vi vào CSDL Nhật ký để Gemini Audit
    $stmt = $pdo->prepare("INSERT INTO access_logs (emp_id, lab_id, action, confidence_score, ai_reason, pipeline_details) VALUES (?, ?, ?, ?, ?, ?)");
    $emp_id = !empty($result['user_id']) ? intval(str_replace('EMP', '', $result['user_id'])) : null;
    $pipeline_json = isset($result['pipeline_details']) ? json_encode($result['pipeline_details']) : null;
    $stmt->execute([$emp_id, $lab_id, $action, $confidence, $message, $pipeline_json]);

    // 6. Trả lệnh về cho Giao diện UI WebRTC để thực thi hiệu ứng hình ảnh/âm thanh
    echo json_encode([
        "status" => "success",
        "door" => $door_status,
        "ai_message" => $message,
        "confidence" => $confidence,
        "spoof_detected" => $is_spoof,
        "spoof_type" => $result['spoof_type'] ?? null
    ]);

} else {
    echo json_encode(["status" => "error", "message" => "Yêu cầu không hợp lệ hoặc thiếu dữ liệu ảnh (eye_image)."]);
}
?>

