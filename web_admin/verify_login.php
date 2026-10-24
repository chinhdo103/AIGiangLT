<?php
/**
 * XÁC THỰC SINH TRẮC MỐNG MẮT ĐĂNG NHẬP (BIOMETRIC LOGIN PROCESSOR)
 * Gửi ảnh mống mắt qua FastAPI -> Nhận dạng định danh -> Kiểm tra quyền hạn trong MySQL -> Thiết lập Session.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_guard.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['eye_image'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Yêu cầu không hợp lệ hoặc thiếu dữ liệu ảnh mống mắt.'
    ]);
    exit;
}

try {
    // 1. Chuẩn bị file gửi sang Python AI Service
    $cfile = new CURLFile(
        $_FILES['eye_image']['tmp_name'],
        $_FILES['eye_image']['type'],
        $_FILES['eye_image']['name']
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, API_BASE_URL . "/verify");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ['file' => $cfile]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $api_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code !== 200 || !$api_response) {
        echo json_encode([
            'status' => 'error',
            'message' => "Lỗi kết nối Máy chủ AI Mống mắt (HTTP $http_code): " . ($curl_error ?: 'Không phản hồi')
        ]);
        exit;
    }

    $result = json_decode($api_response, true);
    $is_match = $result['match'] ?? false;
    $raw_user_id = $result['user_id'] ?? null;
    $confidence = floatval($result['confidence'] ?? 0.0);
    $ai_message = $result['message'] ?? '';
    $pipeline_details = $result['pipeline_details'] ?? null;
    $is_spoof = !empty($result['spoof_detected']) || (strpos($ai_message, 'CẢNH BÁO') !== false) || (strpos($ai_message, 'GIẢ MẠO') !== false);

    if (!$is_match || empty($raw_user_id)) {
        // Ghi log từ chối đăng nhập hoặc phát hiện giả mạo
        $log_action = $is_spoof ? 'SPOOFING_ATTEMPT' : 'ENTRY_DENIED';
        try {
            $stmt = $pdo->prepare("INSERT INTO access_logs (emp_id, lab_id, action, confidence_score, ai_reason, pipeline_details) VALUES (NULL, 1, ?, ?, ?, ?)");
            $stmt->execute([$log_action, $confidence, ($is_spoof ? "[CẢNH BÁO GIẢ MẠO]: " : "ĐĂNG NHẬP THẤT BẠI: ") . $ai_message, $pipeline_details ? json_encode($pipeline_details) : null]);
        } catch (Exception $e) {}

        echo json_encode([
            'status' => 'error',
            'spoof_detected' => $is_spoof,
            'spoof_type' => $result['spoof_type'] ?? null,
            'message' => $ai_message ?: "Mống mắt không khớp với bất kỳ hồ sơ nào trong hệ thống.",
            'confidence' => $confidence
        ]);
        exit;
    }

    // 2. Chuyển đổi định danh EMP sang số nguyên (vd: EMP8 -> 8)
    $emp_id = intval(str_replace('EMP', '', $raw_user_id));

    // 3. Tìm thông tin nhân viên trong CSDL MySQL
    $stmt = $pdo->prepare("SELECT e.*, l.lab_name 
                           FROM employees e 
                           LEFT JOIN laboratories l ON e.allowed_lab_id = l.lab_id 
                           WHERE e.emp_id = ? LIMIT 1");
    $stmt->execute([$emp_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        echo json_encode([
            'status' => 'error',
            'message' => "Mống mắt nhận diện được (ID: $raw_user_id) nhưng hồ sơ nhân sự đã bị xóa khỏi cơ sở dữ liệu."
        ]);
        exit;
    }

    if (isset($employee['status']) && $employee['status'] === 'SUSPENDED') {
        echo json_encode([
            'status' => 'error',
            'message' => "Tài khoản nhân sự [{$employee['full_name']}] đang bị TẠM KHÓA do vi phạm quy chế an ninh."
        ]);
        exit;
    }

    // 4. Phân tích quyền hạn: ADMIN hay ENDUSER?
    $isAdmin = is_admin_role($employee['role']);

    // 5. Khởi tạo Session hoàn chỉnh
    $_SESSION['logged_in']   = true;
    $_SESSION['user_id']     = $employee['emp_id'];
    $_SESSION['user_name']   = $employee['full_name'];
    $_SESSION['user_email']  = $employee['email'];
    $_SESSION['user_role']   = $employee['role'];
    $_SESSION['is_admin']    = $isAdmin;
    $_SESSION['auth_method'] = 'IRIS';
    $_SESSION['lab_id']      = $employee['allowed_lab_id'];
    $_SESSION['lab_name']    = $employee['lab_name'] ?? 'Phòng Lab AI';
    $_SESSION['login_time']  = date('H:i:s d/m/Y');

    // 6. Ghi log đăng nhập thành công vào access_logs
    try {
        $stmt = $pdo->prepare("INSERT INTO access_logs (emp_id, lab_id, action, confidence_score, ai_reason, pipeline_details) VALUES (?, ?, 'ENTRY_GRANTED', ?, ?, ?)");
        $stmt->execute([
            $employee['emp_id'],
            $employee['allowed_lab_id'] ?: 1,
            $confidence,
            "ĐĂNG NHẬP SINH TRẮC MỐNG MẮT THÀNH CÔNG [Role: {$employee['role']}]",
            $pipeline_details ? json_encode($pipeline_details) : null
        ]);
    } catch (Exception $e) {}

    // 7. Xác định trang chuyển hướng
    // - Quản trị viên -> dashboard.php
    // - Nhân viên thông thường -> user_portal.php
    $redirect = $isAdmin ? 'dashboard.php' : 'user_portal.php';

    echo json_encode([
        'status' => 'success',
        'is_admin' => $isAdmin,
        'user_name' => $employee['full_name'],
        'role' => $employee['role'],
        'confidence' => $confidence,
        'redirect' => $redirect,
        'message' => "Đăng nhập thành công! Xin chào {$employee['full_name']}."
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Lỗi xử lý máy chủ: ' . $e->getMessage()
    ]);
}
?>
