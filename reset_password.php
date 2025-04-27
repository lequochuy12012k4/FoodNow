<?php
session_start();
require_once 'config/db_connect.php'; // !!! Đảm bảo bạn có file này hoặc dùng code kết nối MySQLi trực tiếp !!!

// --- Database Configuration (Nếu không dùng file riêng) ---
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'foodnow');

$token = $_GET['token'] ?? null;
$error = '';
$message = '';
$show_form = false;
$user_id_from_token = null; // Lưu ID người dùng nếu token hợp lệ ban đầu

if (!$token || !ctype_xdigit($token)) { // Kiểm tra token có phải chuỗi hex không
    $error = "Token đặt lại mật khẩu không hợp lệ hoặc bị thiếu.";
} else {
    // Kết nối CSDL bằng MySQLi
    $conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

    if ($conn === false) {
        error_log("Database Connection failed: " . mysqli_connect_error());
        $error = "Lỗi kết nối cơ sở dữ liệu.";
    } else {
        mysqli_set_charset($conn, "utf8mb4");

        try { // Dùng try...finally để đóng kết nối
            // 1. Tìm người dùng có token HASH khớp và CHƯA hết hạn
            $current_time = date('Y-m-d H:i:s');

            // !!! THAY 'users' BẰNG TÊN BẢNG NGƯỜI DÙNG CỦA BẠN !!!
            // Lấy hash và ID từ DB để so sánh
            $sql_find_token = "SELECT id, reset_token_hash FROM users WHERE reset_token_expires_at > ?";
            $stmt_find_token = mysqli_prepare($conn, $sql_find_token);

            if ($stmt_find_token) {
                mysqli_stmt_bind_param($stmt_find_token, "s", $current_time);
                mysqli_stmt_execute($stmt_find_token);
                $result_find_token = mysqli_stmt_get_result($stmt_find_token);

                $found_match = false;
                while ($user = mysqli_fetch_assoc($result_find_token)) {
                    // So sánh token gốc từ URL với hash trong DB
                    if (password_verify($token, $user['reset_token_hash'])) {
                         $user_id_from_token = $user['id']; // Lưu lại ID người dùng
                         $show_form = true;      // Cho phép hiển thị form
                         $found_match = true;
                         break; // Tìm thấy khớp, thoát vòng lặp
                    }
                }
                mysqli_stmt_close($stmt_find_token);

                if (!$found_match) {
                     $error = "Token đặt lại mật khẩu không hợp lệ, đã hết hạn hoặc đã được sử dụng. Vui lòng yêu cầu một liên kết mới.";
                }
            } else {
                 error_log("MySQLi prepare failed for token check: " . mysqli_error($conn));
                 $error = "Lỗi truy vấn cơ sở dữ liệu.";
            }

        } catch (Exception $e) {
            error_log("Error during reset password token check: " . $e->getMessage());
            $error = "Đã xảy ra lỗi không mong muốn. Vui lòng thử lại sau.";
        } finally {
             // Đóng kết nối MySQLi nếu nó đã được mở
             if ($conn) {
                 mysqli_close($conn);
             }
        }
    }
}

// Xử lý khi người dùng submit mật khẩu mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $show_form) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $submitted_token = $_POST['token'] ?? ''; // Lấy token từ hidden input
    $user_id_from_form = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT); // Lấy user_id từ hidden input

    // *** Quan trọng: Xác thực lại token VÀ user_id một lần nữa khi submit form ***
    if ($submitted_token !== $token || $user_id_from_form !== $user_id_from_token) {
         $error = "Yêu cầu không hợp lệ hoặc token đã thay đổi.";
         $show_form = false; // Không cho hiển thị form nữa
    } elseif (empty($password) || empty($confirm_password)) {
        $error = "Vui lòng nhập cả mật khẩu mới và xác nhận mật khẩu.";
        // Giữ $show_form = true để hiển thị lại form với lỗi
    } elseif (strlen($password) < 6) { // Thêm kiểm tra độ dài tối thiểu
         $error = "Mật khẩu phải có ít nhất 6 ký tự.";
         // Giữ $show_form = true
    } elseif ($password !== $confirm_password) {
        $error = "Mật khẩu và xác nhận mật khẩu không khớp.";
         // Giữ $show_form = true
    } else {
        // Mọi thứ hợp lệ -> Hash mật khẩu mới và cập nhật CSDL
        $conn_update = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME); // Kết nối lại để update

        if ($conn_update === false) {
             error_log("Database Connection failed for update: " . mysqli_connect_error());
             $error = "Lỗi kết nối khi cập nhật mật khẩu.";
             // Giữ $show_form = true
        } else {
             mysqli_set_charset($conn_update, "utf8mb4");
             try {
                $new_password_hash = password_hash($password, PASSWORD_DEFAULT);

                // !!! THAY 'users' BẰNG TÊN BẢNG NGƯỜI DÙNG CỦA BẠN !!!
                $sql_update_password = "UPDATE users SET password = ?, reset_token_hash = NULL, reset_token_expires_at = NULL WHERE id = ?";
                $stmt_update_password = mysqli_prepare($conn_update, $sql_update_password);

                if($stmt_update_password) {
                    mysqli_stmt_bind_param($stmt_update_password, "si", $new_password_hash, $user_id_from_token);
                    if (mysqli_stmt_execute($stmt_update_password)) {
                         $message = "Mật khẩu của bạn đã được cập nhật thành công! Bạn có thể đăng nhập ngay bây giờ.";
                         $show_form = false; // Không hiển thị form nữa sau khi thành công
                    } else {
                         error_log("MySQLi execute failed for password update: " . mysqli_stmt_error($stmt_update_password));
                         $error = "Không thể cập nhật mật khẩu. Lỗi thực thi.";
                         // Giữ $show_form = true
                    }
                    mysqli_stmt_close($stmt_update_password);
                } else {
                    error_log("MySQLi prepare failed for password update: " . mysqli_error($conn_update));
                    $error = "Không thể cập nhật mật khẩu. Lỗi chuẩn bị.";
                    // Giữ $show_form = true
                }

             } catch (Exception $e) {
                 error_log("Error during password update: " . $e->getMessage());
                 $error = "Đã xảy ra lỗi không mong muốn khi cập nhật.";
                 // Giữ $show_form = true
             } finally {
                 if ($conn_update) {
                     mysqli_close($conn_update);
                 }
             }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt Lại Mật Khẩu</title>
    <!-- Link tới file CSS giống trang register.php của bạn -->
    <link rel="shortcut icon" href="image/foodnow_icon.png" sizes="32x32" type="image/x-icon">
    <style>
        /* Copy các CSS từ ví dụ forgot_password.php trước đó vào đây */
        /* Hoặc đảm bảo file css/auth_style.css chứa các style đó */
        body { margin: 0; font-family: sans-serif; background-color: #f8f9fa; }
        .auth-container { display: flex; min-height: 100vh; width: 100%; align-items: stretch; }
        .auth-image-side { flex: 1 1 55%; background-image: url('image/img1.jpg'); /* THAY ẢNH NỀN KHÁC NẾU MUỐN */ background-size: cover; background-position: center; display: none; }
        @media (min-width: 992px) { .auth-image-side { display: block; } }
        .auth-form-side { flex: 1 1 45%; display: flex; align-items: center; justify-content: center; padding: 2rem; background-color: #fff; }
         @media (min-width: 992px) { .auth-form-side { flex: 1 1 45%; background-color: #fff; } }
        .auth-form-box { width: 100%; max-width: 450px; padding: 2.5rem; border-radius: 8px; }
        .auth-logo { text-align: center; margin-bottom: 1.5rem; font-size: 3rem; font-weight: bold; color: #e54d26; }
        .auth-title { text-align: center; margin-bottom: 1rem; font-size: 1.5rem; font-weight: 500; color: #333; }
        .auth-instruction { text-align: center; margin-bottom: 1.5rem; color: #666; font-size: 0.95em; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #555; }
        .form-control { display: block; width: 100%; padding: 0.75rem 1rem; font-size: 1rem; line-height: 1.5; color: #495057; background-color: #fff; background-clip: padding-box; border: 1px solid #ced4da; border-radius: 0.25rem; transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out; box-sizing: border-box; }
        .form-control:focus { border-color: #80bdff; outline: 0; box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25); }
        .btn-submit { display: inline-block; font-weight: 400; color: #fff; text-align: center; cursor: pointer; background-color: #28a745; /* Màu xanh lá */ border: 1px solid #28a745; padding: 0.75rem 1.25rem; font-size: 1rem; line-height: 1.5; border-radius: 0.25rem; transition: background-color .15s ease-in-out, border-color .15s ease-in-out; width: 100%; }
        .btn-submit:hover { background-color: #218838; border-color: #1e7e34; }
        .auth-form-box .login-link { margin-top: 1.5rem; text-align: center; font-size: 0.9em; }
        .auth-form-box .login-link a { color: #e44d26; text-decoration: none; font-weight: bold; }
        .auth-form-box .login-link a:hover { text-decoration: underline; }
        .alert { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 4px; text-align: center; }
        .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .alert a { font-weight: bold; color: #0056b3; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="auth-container">
        <!-- Phần ảnh nền bên trái -->
        <div class="auth-image-side"></div>

        <!-- Phần form bên phải -->
        <div class="auth-form-side">
            <div class="auth-form-box">
            <a href="index.php" style="text-decoration: none;"><div class="auth-logo">FoodNow</div></a>
                <h2 class="auth-title">Đặt Lại Mật Khẩu</h2>

                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($message); ?>
                        <p style="margin-top: 15px;"><a href="login.php">Đi đến trang Đăng nhập</a></p>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error); ?>
                         <?php if (strpos($error, 'hết hạn') !== false || strpos($error, 'không hợp lệ') !== false || strpos($error, 'đã được sử dụng') !== false): ?>
                            <p style="margin-top: 10px;"><a href="forgot_password.php">Yêu cầu liên kết đặt lại mới</a></p>
                         <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($show_form): ?>
                    <p class="auth-instruction">Nhập mật khẩu mới cho tài khoản của bạn.</p>
                    <form action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" method="POST">
                        <!-- Hidden fields để gửi lại token và user_id đã xác thực -->
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id_from_token); ?>">

                        <div class="form-group">
                            <label for="password">Mật khẩu mới (ít nhất 6 ký tự):</label>
                            <input type="password" id="password" name="password" class="form-control" required>
                        </div>
                         <div class="form-group">
                            <label for="confirm_password">Xác nhận mật khẩu mới:</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn-submit">Đặt Lại Mật Khẩu</button>
                    </form>
                 <?php elseif(empty($message) && empty($error)): ?>
                     <!-- Trường hợp token không hợp lệ ngay từ đầu đã hiển thị lỗi ở trên -->
                     <!-- Có thể thêm thông báo nếu cần, nhưng thường lỗi đã đủ -->
                <?php endif; ?>

                 <?php if (!$show_form && empty($message)): // Nếu không hiển thị form và không có thông báo thành công -> có lỗi ?>
                      <div class="login-link">
                          <a href="login.php">Quay lại Đăng nhập</a>
                      </div>
                 <?php endif; ?>

            </div>
        </div>
    </div>
</body>
</html>