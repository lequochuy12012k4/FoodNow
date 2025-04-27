<?php
// Bắt đầu session TRƯỚC bất kỳ output nào
session_start();
require_once 'vendor/autoload.php'; // Hoặc đường dẫn tới PHPMailer nếu không dùng Composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// --- Database Configuration ---
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'foodnow');
// -----------------------------

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

    if (!$email) {
        $error = 'Vui lòng nhập địa chỉ email hợp lệ.';
    } else {
        // Kết nối CSDL bằng MySQLi
        $conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

        if ($conn === false) {
             error_log("Database Connection failed: " . mysqli_connect_error());
             $error = "Lỗi kết nối cơ sở dữ liệu. Vui lòng thử lại sau.";
        } else {
            mysqli_set_charset($conn, "utf8mb4");

            try { // Dùng try...finally để đảm bảo đóng kết nối
                // 1. Kiểm tra email tồn tại
                // !!! THAY 'users' BẰNG TÊN BẢNG NGƯỜI DÙNG CỦA BẠN !!!
                $sql_check_email = "SELECT id FROM users WHERE email = ? LIMIT 1";
                $stmt_check_email = mysqli_prepare($conn, $sql_check_email);
                mysqli_stmt_bind_param($stmt_check_email, "s", $email);
                mysqli_stmt_execute($stmt_check_email);
                $result_check_email = mysqli_stmt_get_result($stmt_check_email);
                $user = mysqli_fetch_assoc($result_check_email);
                mysqli_stmt_close($stmt_check_email);

                // 2. Nếu email tồn tại -> Tạo token và gửi email
                if ($user) {
                    $user_id = $user['id'];

                    $token_bytes = random_bytes(32);
                    $reset_token = bin2hex($token_bytes);
                    $reset_token_hash = password_hash($reset_token, PASSWORD_DEFAULT);
                    $expires_at = date('Y-m-d H:i:s', time() + 3600); // 1 giờ

                    // 3. Cập nhật token hash và thời gian hết hạn vào CSDL
                     // !!! THAY 'users' BẰNG TÊN BẢNG NGƯỜI DÙNG CỦA BẠN !!!
                     $sql_update_token = "UPDATE users SET reset_token_hash = ?, reset_token_expires_at = ? WHERE id = ?";
                     $stmt_update_token = mysqli_prepare($conn, $sql_update_token);
                     mysqli_stmt_bind_param($stmt_update_token, "ssi", $reset_token_hash, $expires_at, $user_id);
                     mysqli_stmt_execute($stmt_update_token);
                     mysqli_stmt_close($stmt_update_token);

                    // 4. Gửi email chứa link đặt lại mật khẩu
                    $mail = new PHPMailer(true);

                    try {
                        // Cấu hình Server Settings (SMTP) - Thay bằng thông tin của bạn
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com'; // VD: smtp.gmail.com
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'lequochuy12012k4@gmail.com'; // Email dùng để gửi
                        $mail->Password   = 'wlab opzf vjyo unvu'; // Mật khẩu email hoặc mật khẩu ứng dụng
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;
                        
                        // Người gửi và người nhận
                        $mail->setFrom('foodnow@example.com', 'FoodNow'); // Email và Tên người gửi
                        $mail->addAddress($email); // Email người nhận lấy từ form

                        // Nội dung Email
                        $mail->isHTML(true);
                        $mail->CharSet = 'UTF-8';
                        $mail->Subject = 'Yêu cầu đặt lại mật khẩu FoodNow';

                        // Tạo link reset (Trỏ đến trang reset_password.php)
                        // !!! THAY 'yourwebsite.com' BẰNG DOMAIN CỦA BẠN !!!
                        $reset_link = "http://localhost:3000/reset_password.php?token=" . $reset_token; // Gửi token gốc trong link

                        $mail->Body    = "Chào bạn,<br><br>Nhấp vào liên kết sau để đặt lại mật khẩu: <a href='" . $reset_link . "'>" . $reset_link . "</a><br><br>Liên kết này sẽ hết hạn sau 1 giờ.<br><br>Nếu bạn không yêu cầu, vui lòng bỏ qua email này.";
                        $mail->AltBody = "Chào bạn,\n\nTruy cập liên kết sau để đặt lại mật khẩu: " . $reset_link . "\n\nLiên kết này sẽ hết hạn sau 1 giờ.\n\nNếu bạn không yêu cầu, vui lòng bỏ qua email này.";
                        $mail->send();
                        $message = 'Kiểm tra email của bạn. Chúng tôi đã gửi yêu cầu để đặt lại mật khẩu';

                    } catch (Exception $e) {
                        error_log("PHPMailer Error: {$mail->ErrorInfo}");
                        $error = "Không thể gửi email đặt lại mật khẩu vào lúc này. Vui lòng thử lại sau.";
                    }

                } else {
                    // Email không tồn tại - Vẫn hiển thị thông báo thành công chung chung
                    $message = 'Email không tồn tại';
                }
            } catch (Exception $e) { // Bắt lỗi chung (ví dụ: lỗi prepare)
                error_log("Error during forgot password process: " . $e->getMessage());
                 $error = "Đã xảy ra lỗi không mong muốn. Vui lòng thử lại sau.";
            } finally {
                // Đảm bảo đóng kết nối MySQLi
                if ($conn) {
                    mysqli_close($conn);
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
    <title>Quên Mật Khẩu</title>
    <link rel="shortcut icon" href="image/foodnow_icon.png" sizes="32x32" type="image/x-icon">
    <style>
        /* Copy các CSS từ ví dụ forgot_password.php trước đó vào đây */
        /* Hoặc đảm bảo file css/auth_style.css chứa các style đó */
        body { margin: 0; font-family: sans-serif; background-color: #f8f9fa; }
        .auth-container { display: flex; min-height: 100vh; width: 100%; align-items: stretch; }
        .auth-image-side { flex: 1 1 55%; background-image: url('image/img1.jpg'); /* THAY ẢNH NỀN */ background-size: cover; background-position: center; display: none; }
        @media (min-width: 992px) { .auth-image-side { display: block; } } /* Chỉ hiện ảnh trên màn lớn */
        .auth-form-side { flex: 1 1 45%; display: flex; align-items: center; justify-content: center; padding: 2rem; background-color: #fff; }
         @media (min-width: 992px) { .auth-form-side { flex: 1 1 45%; background-color: #fff; } } /* Giữ nền trắng form trên màn lớn */
        .auth-form-box { width: 100%; max-width: 450px; padding: 2.5rem; border-radius: 8px; /* box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); */ /* Bỏ shadow nếu nền form trắng */}
        .auth-logo { text-align: center; margin-bottom: 1.5rem; font-size: 3rem; font-weight: bold; color: #e54d26; }
        .auth-title { text-align: center; margin-bottom: 1rem; font-size: 1.5rem; font-weight: 500; color: #333; }
        .auth-instruction { text-align: center; margin-bottom: 1.5rem; color: #666; font-size: 0.95em; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #555; }
        .form-control { display: block; width: 100%; padding: 0.75rem 1rem; font-size: 1rem; line-height: 1.5; color: #495057; background-color: #fff; background-clip: padding-box; border: 1px solid #ced4da; border-radius: 0.25rem; transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out; box-sizing: border-box; }
        .form-control:focus { border-color: #80bdff; outline: 0; box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25); }
        .btn-submit { display: inline-block; font-weight: 400; color: #fff; text-align: center; cursor: pointer; background-color: #e44d26; /* Màu đỏ cam */ border: 1px solid #e44d26; padding: 0.75rem 1.25rem; font-size: 1rem; line-height: 1.5; border-radius: 0.25rem; transition: background-color .15s ease-in-out, border-color .15s ease-in-out; width: 100%; }
        .btn-submit:hover { background-color: #d1411c; border-color: #bd3b1a; }
        .auth-form-box .login-link { margin-top: 1.5rem; text-align: center; font-size: 0.9em; }
        .auth-form-box .login-link a { color: #e44d26; text-decoration: none; font-weight: bold; }
        .auth-form-box .login-link a:hover { text-decoration: underline; }
        .alert { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 4px; text-align: center; }
        .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
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
                <h2 class="auth-title">Quên Mật Khẩu</h2>
                <p class="auth-instruction">Nhập địa chỉ email liên kết với tài khoản của bạn. Chúng tôi sẽ gửi một liên kết để bạn đặt lại mật khẩu.</p>

                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($message)): // Chỉ hiển thị form nếu chưa có thông báo thành công ?>
                    <form action="forgot_password.php" method="POST">
                        <div class="form-group">
                            <label for="email">Địa chỉ Email:</label>
                            <input type="email" id="email" name="email" class="form-control" required autofocus>
                        </div>
                        <button type="submit" class="btn-submit">Gửi Liên Kết Đặt Lại</button>
                    </form>
                <?php endif; ?>

                <div class="login-link">
                    <a href="login.php">Quay lại Đăng nhập</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>