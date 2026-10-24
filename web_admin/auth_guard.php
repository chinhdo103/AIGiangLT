<?php
/**
 * HỆ THỐNG KIỂM SOÁT PHÂN QUYỀN TRUY CẬP (RBAC - ROLE-BASED ACCESS CONTROL)
 * Đảm bảo:
 * 1. Chặn người dùng chưa đăng nhập.
 * 2. Chặn người dùng không có quyền Quản trị (Admin) gõ URL vào các trang quản lý.
 * 3. Chuyển hướng tới trang 403 Forbidden nếu vi phạm quyền hạn.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Kiểm tra xem người dùng đã đăng nhập hay chưa
 */
function is_logged_in() {
    return (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) 
        || (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);
}

/**
 * Kiểm tra một vai trò (role) có phải là Quản trị viên (Admin) hay không
 */
function is_admin_role($role) {
    if (empty($role)) return false;
    $r = mb_strtolower(trim($role), 'UTF-8');
    $adminKeywords = ['admin', 'quản trị viên', 'quan tri vien', 'quản trị', 'quan tri', 'administrator', 'giám đốc', 'superadmin'];
    foreach ($adminKeywords as $kw) {
        if (strpos($r, $kw) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Kiểm tra session hiện tại có đặc quyền Quản trị viên hay không
 */
function is_admin() {
    if (!is_logged_in()) {
        return false;
    }
    // Nếu đăng nhập trực tiếp qua cổng Master Admin
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        return true;
    }
    // Hoặc kiểm tra cờ is_admin được gắn khi quét mống mắt
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
        return true;
    }
    // Hoặc kiểm tra từ vai trò trong session
    if (isset($_SESSION['user_role']) && is_admin_role($_SESSION['user_role'])) {
        return true;
    }
    return false;
}

/**
 * Lấy thông tin người dùng hiện tại
 */
function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'          => $_SESSION['user_id'] ?? 'MASTER',
        'name'        => $_SESSION['user_name'] ?? 'Quản Trị Viên Hệ Thống',
        'email'       => $_SESSION['user_email'] ?? 'admin@security.lab',
        'role'        => $_SESSION['user_role'] ?? 'Quản trị viên Tối cao',
        'is_admin'    => is_admin(),
        'auth_method' => $_SESSION['auth_method'] ?? 'CREDENTIALS',
        'lab_id'      => $_SESSION['lab_id'] ?? 1,
        'login_time'  => $_SESSION['login_time'] ?? date('H:i:s d/m/Y')
    ];
}

/**
 * Bắt buộc người dùng phải đăng nhập mới được xem trang
 */
function require_login() {
    if (!is_logged_in()) {
        $currentUri = $_SERVER['REQUEST_URI'] ?? 'dashboard.php';
        header("Location: login.php?redirect=" . urlencode($currentUri));
        exit;
    }
}

/**
 * Bắt buộc người dùng phải có ĐẶC QUYỀN QUẢN TRỊ VIÊN (ADMIN)
 * Nếu không có quyền: Chặn ngay lập tức và chuyển hướng sang trang 403 Forbidden!
 */
function require_admin() {
    // 1. Chưa đăng nhập -> đá về trang Login
    if (!is_logged_in()) {
        $currentUri = $_SERVER['REQUEST_URI'] ?? 'dashboard.php';
        header("Location: login.php?redirect=" . urlencode($currentUri));
        exit;
    }

    // 2. Đã đăng nhập nhưng KHÔNG CÓ QUYỀN ADMIN -> Chặn và gửi tới 403
    if (!is_admin()) {
        // Ghi log bảo mật vi phạm truy cập trái phép
        try {
            require_once __DIR__ . '/config.php';
            if (isset($pdo)) {
                $uid = $_SESSION['user_id'] ?? null;
                $empId = is_numeric($uid) ? intval($uid) : null;
                $uri = htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'admin_page');
                $stmt = $pdo->prepare("INSERT INTO access_logs (emp_id, lab_id, action, confidence_score, ai_reason) VALUES (?, 1, 'ENTRY_DENIED', 0, ?)");
                $stmt->execute([$empId, "CẢNH BÁO BẢO MẬT: Người dùng không có quyền Admin cố tình truy cập URL bị cấm: $uri"]);
            }
        } catch (Exception $e) {
            // Không ngắt script nếu ghi log lỗi
        }

        header("Location: 403.php?attempt=" . urlencode($_SERVER['REQUEST_URI'] ?? ''));
        exit;
    }
}
?>
