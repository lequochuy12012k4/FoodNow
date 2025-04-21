<?php
session_start(); // Start session to access user info and set messages

// Include database configuration (adjust path if needed)
include 'config/db_config.php'; // Assuming you have a config file like this

// --- Database Connection ---
$conn = mysqli_connect($servername, $username, $password, $databaseName);
if (!$conn) {
    error_log("Database Connection failed in process_checkout.php: " . mysqli_connect_error());
    $_SESSION['checkout_error'] = "Lỗi hệ thống. Không thể xử lý đơn hàng.";
    header("Location: cart.php"); // Redirect back to cart
    exit;
}
mysqli_set_charset($conn, "utf8mb4");

// --- Check Request Method ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['checkout_error'] = "Yêu cầu không hợp lệ.";
    header("Location: cart.php");
    exit;
}

// --- Validate Input ---
$recipient_name = trim($_POST['customer_name'] ?? '');
$recipient_phone = trim($_POST['customer_phone'] ?? '');
$recipient_address = trim($_POST['customer_address'] ?? '');
$payment_method = trim($_POST['payment_method'] ?? '');
$new_status = 'pending'; // Or 'processing' if you prefer

$errors = [];
if (empty($recipient_name)) {
    $errors[] = "Vui lòng nhập họ tên người nhận.";
}
if (empty($recipient_phone)) {
    $errors[] = "Vui lòng nhập số điện thoại người nhận.";
} elseif (!preg_match('/^[0-9]{10,11}$/', $recipient_phone)) {
    $errors[] = "Số điện thoại không hợp lệ (cần 10-11 chữ số).";
}
if (empty($recipient_address)) {
    $errors[] = "Vui lòng nhập địa chỉ nhận hàng.";
}
if (empty($payment_method) || !in_array($payment_method, ['cod', 'online'])) {
    $errors[] = "Vui lòng chọn phương thức thanh toán hợp lệ.";
}

if (!empty($errors)) {
    $_SESSION['checkout_error'] = implode("<br>", $errors);
    // Optionally store submitted data to refill form (more complex)
    // $_SESSION['checkout_form_data'] = $_POST;
    header("Location: cart.php#checkout-sidebar"); // Redirect back, maybe try to reopen sidebar
    exit;
}

// --- Identify User ---
$session_username = isset($_SESSION['username']) ? trim($_SESSION['username']) : null;
$session_id = session_id();

// --- Prepare Update Statement ---
// This query updates all 'cart' items for the current user/session
// and sets the recipient details and new status for those specific rows.
$sql = "UPDATE `{$orderTableName}` SET
            status = ?,
            recipient_name = ?,
            recipient_phone = ?,
            recipient_address = ?,
            payment_method = ?
        WHERE status = 'cart'"; // Condition 1: Only update items currently in cart

// Add user/session filtering condition
$params = [$new_status, $recipient_name, $recipient_phone, $recipient_address, $payment_method];
$types = "sssss"; // 5 strings for the SET clause

if ($session_username !== null) {
    $sql .= " AND username = ?"; // Condition 2: Match logged-in user
    $params[] = $session_username;
    $types .= "s";
} else {
    $sql .= " AND session_id = ? AND username IS NULL"; // Condition 2: Match session ID for guests
    $params[] = $session_id;
    $types .= "s";
}

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    error_log("Prepare Update Error in process_checkout: " . mysqli_error($conn));
    $_SESSION['checkout_error'] = "Lỗi hệ thống khi chuẩn bị cập nhật đơn hàng.";
    mysqli_close($conn);
    header("Location: cart.php");
    exit;
}

mysqli_stmt_bind_param($stmt, $types, ...$params);

// --- Execute Update ---
$success = false;
$updated_rows = 0;

if (mysqli_stmt_execute($stmt)) {
    $updated_rows = mysqli_stmt_affected_rows($stmt);
    if ($updated_rows > 0) {
        $success = true;
    } else {
        // No rows updated - likely means cart was empty or items were already processed.
        // This might not strictly be an error if the user double-submits quickly.
        // Check if there *were* cart items just before this script ran.
        // For simplicity, we'll treat it as success if no DB error occurred,
        // assuming the cart might have been cleared elsewhere or submitted simultaneously.
        // A more robust check would query the cart count *before* the update.
         $success = true; // Assume okay if no rows were changed but no DB error
         $_SESSION['checkout_notice'] = "Giỏ hàng của bạn có thể đã được xử lý hoặc trống.";
    }
} else {
    error_log("Execute Update Error in process_checkout: " . mysqli_stmt_error($stmt));
    $_SESSION['checkout_error'] = "Lỗi hệ thống khi cập nhật trạng thái đơn hàng.";
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

// --- Redirect based on success ---
if ($success) {
    $_SESSION['checkout_success'] = "Đặt hàng thành công! Đơn hàng của bạn đang được xử lý.";
    // Redirect to a dedicated confirmation page or homepage
    header("Location: order_confirmation.php"); // Create this page
    // Or redirect home: header("Location: index.php");
    exit;
} else {
    // Error message was already set if $success is false due to DB error
    if (!isset($_SESSION['checkout_error'])) { // Set a generic error if none was set
         $_SESSION['checkout_error'] = "Không thể đặt hàng. Vui lòng thử lại.";
    }
    header("Location: cart.php");
    exit;
}
?>