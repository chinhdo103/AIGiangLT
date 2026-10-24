<?php
/**
 * MODULE: Quản lý Nhân sự & Đăng ký Sinh trắc học.
 */
require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');

// Hàm Helper để giao tiếp cURL với hệ thống AI lấy Vector Mống Mắt
function enrollIrisToAI($emp_id, $file_path, $file_type, $file_name) {
    $cfile = new CURLFile($file_path, $file_type, $file_name);
    // Lưu ý: Ta định danh nhân viên bằng Prefix 'EMP' gửi sang AI
    $post_data = array(
        'user_id' => 'EMP' . $emp_id, 
        'file' => $cfile
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, API_BASE_URL . "/enroll");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if($http_code != 200) {
        return ["status" => "error", "message" => "Lỗi HTTP từ Server AI: $http_code"];
    }
    return json_decode($response, true);
}

// Xử lý Request Tạo mới Nhân viên
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $fullName = $_POST['fullname'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? 'Nhân viên';
    $labId = $_POST['lab_id'] ?? 1;
    
    if (empty($fullName) || empty($email)) {
        echo json_encode(["status" => "error", "message" => "Vui lòng điền đủ Tên và Email."]);
        exit;
    }

    try {
        // Dùng Transaction để đảm bảo tính toàn vẹn CSDL (Rollback nếu AI lỗi)
        $pdo->beginTransaction();
        
        // 1. Khởi tạo hồ sơ nhân viên trong MySQL
        $stmt = $pdo->prepare("INSERT INTO employees (full_name, email, role, allowed_lab_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$fullName, $email, $role, $labId]);
        $emp_id = $pdo->lastInsertId(); // Lấy ID vừa tạo
        
        // 2. Nếu trong Form có tải lên ảnh Mống mắt -> Đẩy cho Python xử lý
        if (isset($_FILES['eye_image']) && $_FILES['eye_image']['error'] === UPLOAD_ERR_OK) {
            $api_res = enrollIrisToAI(
                $emp_id, 
                $_FILES['eye_image']['tmp_name'], 
                $_FILES['eye_image']['type'], 
                $_FILES['eye_image']['name']
            );
            
            if (isset($api_res['status']) && $api_res['status'] === 'success') {
                // Nhận mảng 1280 phần tử (Embedding) trả về từ Python CNN
                $embedding_json = json_encode($api_res['embedding']);
                
                // 3. Lưu mảng JSON này vào MySQL
                $stmt2 = $pdo->prepare("INSERT INTO iris_templates (emp_id, embedding_vector) VALUES (?, ?)");
                $stmt2->execute([$emp_id, $embedding_json]);
            } else {
                // Nếu AI từ chối ảnh (do mờ, nhắm mắt, giả mạo...)
                throw new Exception("Lỗi Đăng ký Sinh trắc: " . ($api_res['message'] ?? 'AI không phản hồi hợp lệ.'));
            }
        }
        
        $pdo->commit();
        echo json_encode(["status" => "success", "message" => "Tuyệt vời! Đã thêm nhân viên {$fullName} và lưu thành công cấu trúc Mống mắt vào Database."]);
        
    } catch(Exception $e) {
        $pdo->rollBack(); // Hủy bỏ insert nếu xảy ra lỗi giữa chừng
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Thao tác không được hỗ trợ."]);
}
?>

