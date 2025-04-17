<?php
session_start();

define('DB_SERVER', 'localhost'); // or your db host
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'foodnow');


$error_message = '';
// --- Keep your existing PHP registration logic here ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if it's a standard registration submission (add a hidden field or check button name if needed)
    if (isset($_POST['new_username'])) { // Simple check assumes social buttons won't POST these fields
        $conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
        if ($conn === false) {
            $error_message = "Lỗi: Không thể kết nối đến cơ sở dữ liệu.";
        } else {
            // ... (rest of your validation and insertion code from the previous version) ...
             $fullname = mysqli_real_escape_string($conn, trim($_POST['fullname'] ?? ''));
             $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
             $username = mysqli_real_escape_string($conn, trim($_POST['new_username'] ?? ''));
             $password = trim($_POST['new_password'] ?? '');
             $confirm_password = trim($_POST['confirm_password'] ?? '');

             if (empty($fullname) || empty($email) || empty($username) || empty($password) || empty($confirm_password)) {
                 $error_message = "Vui lòng điền đầy đủ tất cả các trường.";
             } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                 $error_message = "Định dạng email không hợp lệ.";
             } elseif ($password !== $confirm_password) {
                 $error_message = "Mật khẩu và xác nhận mật khẩu không khớp.";
             } elseif (strlen($password) < 6) {
                  $error_message = "Mật khẩu phải có ít nhất 6 ký tự.";
             } else {
                 $sql_check = "SELECT id FROM users WHERE username = ? OR email = ?";
                 if ($stmt_check = mysqli_prepare($conn, $sql_check)) {
                     mysqli_stmt_bind_param($stmt_check, "ss", $param_username, $param_email);
                     $param_username = $username;
                     $param_email = $email;

                     if (mysqli_stmt_execute($stmt_check)) {
                         mysqli_stmt_store_result($stmt_check);

                         if (mysqli_stmt_num_rows($stmt_check) > 0) {
                              $error_message = "Tên đăng nhập hoặc email đã tồn tại.";
                         } else {
                              $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                              $sql_insert = "INSERT INTO users (fullname, email, username, password) VALUES (?, ?, ?, ?)";
                              if ($stmt_insert = mysqli_prepare($conn, $sql_insert)) {
                                  mysqli_stmt_bind_param($stmt_insert, "ssss", $param_fullname, $param_email_insert, $param_username_insert, $param_hashed_password);

                                  $param_fullname = $fullname;
                                  $param_email_insert = $email;
                                  $param_username_insert = $username;
                                  $param_hashed_password = $hashed_password;
                                  if (mysqli_stmt_execute($stmt_insert)) {
                                      mysqli_stmt_close($stmt_insert);
                                      mysqli_stmt_close($stmt_check);
                                      mysqli_close($conn);
                                      header("Location: login.php?registered=success");
                                      exit;
                                  } else {
                                      error_log("Registration Insert Error: " . mysqli_error($conn)); // Log error
                                      $error_message = "Đã xảy ra lỗi. Vui lòng thử lại sau.";
                                  }
                                  mysqli_stmt_close($stmt_insert);
                              } else {
                                   error_log("Registration Prepare Insert Error: " . mysqli_error($conn)); // Log error
                                   $error_message = "Đã xảy ra lỗi hệ thống (prepare insert).";
                              }
                         }
                     } else {
                          error_log("Registration Execute Check Error: " . mysqli_error($conn)); // Log error
                          $error_message = "Đã xảy ra lỗi hệ thống (execute check).";
                     }
                     mysqli_stmt_close($stmt_check);
                 } else {
                      error_log("Registration Prepare Check Error: " . mysqli_error($conn)); // Log error
                      $error_message = "Đã xảy ra lỗi hệ thống (prepare check).";
                 }
             }
             // Close connection only if it was opened successfully
             if ($conn) {
                 mysqli_close($conn);
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
    <title>FoodNow - Đăng ký</title>
    <style>
        /* --- Keep your existing styles --- */
        body { font-family: sans-serif; margin: 0; background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px 0; }
        .container { background-color: #fff; border-radius: 12px; box-shadow: 0 0 30px rgba(0, 0, 0, 0.15); overflow: hidden; display: flex; width: 90%; max-width: 1000px; }
        .image-container { flex: 0 0 45%; display: none; }
        .image-container img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .form-container { flex: 1; padding: 30px 40px; display: flex; flex-direction: column; justify-content: center; align-items: stretch; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 6px; color: #555; font-weight: bold; font-size: 1.0em; }
        input[type="text"], input[type="password"], input[type="email"] { width: calc(100% - 22px); padding: 11px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 0.95em; }
        button[type="submit"] { background-color: #ff6b6b; color: white; padding: 12px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 1.1em; transition: background-color 0.3s ease; margin-top: 20px; width: 100%; box-sizing: border-box; }
        button[type="submit"]:hover { background-color: #e65a5a; }
        .form-footer { margin-top: 25px; text-align: center; color: #777; font-size: 0.9em; }
        .form-footer a { color: #ff6b6b; text-decoration: none; font-weight: bold; }
        .form-footer a:hover { text-decoration: underline; }
        .foodnow-title { text-align: center; color: #ff6b6b; font-size: 2.8em; font-weight: bold; margin-bottom: 25px; }
        .error-message { color: red; background-color: #ffebee; border: 1px solid #e57373; padding: 10px; border-radius: 5px; text-align: center; margin-bottom: 20px; font-weight: bold; }

        /* --- Styles for Social Login Buttons --- */
        .social-login-divider {
            text-align: center;
            margin: 25px 0 20px; /* Adjust spacing */
            color: #aaa;
            font-weight: bold;
            display: flex;
            align-items: center;
        }
        .social-login-divider::before,
        .social-login-divider::after {
            content: '';
            flex-grow: 1;
            height: 1px;
            background-color: #ddd;
            margin: 0 10px;
        }

        .social-login-buttons {
            display: flex;
            /* flex-direction: column; */ /* Remove or comment out */
            flex-direction: row; /* MODIFICATION: Make items go side-by-side */
            gap: 15px; /* Space between buttons (horizontal now) */
            margin-top: 5px;
        }

        .btn-social {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 15px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 1em;
            font-weight: bold;
            border: 1px solid transparent;
            cursor: pointer;
            transition: background-color 0.3s ease, border-color 0.3s ease;
            /* width: 100%; */ /* Remove or comment out */
            flex: 1; /* MODIFICATION: Make buttons share the space */
            box-sizing: border-box;
        }
         .btn-social svg, .btn-social img {
            width: 20px;
            height: 20px;
            margin-right: 10px;
         }

        .btn-google {
            background-color: #fff;
            color: #757575;
            border-color: #ddd;
        }
        .btn-google:hover {
            background-color:rgb(136, 136, 136);
            border-color: #ccc;
            color: white;
        }

        .btn-facebook {
            background-color:rgb(255, 255, 255);
            color: #757575;
            border-color: #ddd;
        }
        .btn-facebook:hover {
            color: white;
            background-color: #166FE5;
            border-color: #166FE5;
        }

        /* --- Responsive Styles --- */
        @media (min-width: 768px) { .image-container { display: block; } .form-container { padding: 40px 50px; } }
        @media (min-width: 992px) { .form-container { padding: 50px 60px; } .foodnow-title { font-size: 3em; } }

    </style>
     <!-- Optional: Add Font Awesome for icons (if you prefer) -->
     <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> -->
</head>
<body>
    <div class="container">
        <div class="image-container">
            <img src="image/img1.jpg" alt="FoodNow Image">
        </div>
        <div class="form-container">
            <a href="index.php" style="text-decoration: none;"><h1 class="foodnow-title">FoodNow</h1></a>

            <?php
            if (!empty($error_message)) {
                echo '<div class="error-message">' . htmlspecialchars($error_message) . '</div>';
            }
            ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>
                <div class="form-group">
                    <label for="fullname">Họ và tên:</label>
                    <input type="text" id="fullname" name="fullname" required value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="new_username">Tên đăng nhập:</label>
                    <input type="text" id="new_username" name="new_username" required value="<?php echo isset($_POST['new_username']) ? htmlspecialchars($_POST['new_username']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="new_password">Mật khẩu:</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Xác nhận mật khẩu:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit">Đăng ký</button>
            </form>
             <div class="social-login-divider"><span>HOẶC</span></div>

             <div class="social-login-buttons">
                 <a href="#" class="btn-social btn-google">
                     <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48px" height="48px"><path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"/><path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"/><path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"/><path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571l0.001-0.001l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"/></svg>
                     Google
                </a>
                 <a href="#" class="btn-social btn-facebook">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50" width="50px" height="50px"><path d="M41,4H9C6.24,4,4,6.24,4,9v32c0,2.76,2.24,5,5,5h32c2.76,0,5-2.24,5-5V9C46,6.24,43.76,4,41,4z" fill="#1877f2"/><path d="M34.5,25H31v16h-6V25h-4v-5h4v-3.9c0-4.33,1.96-7.1,6.54-7.1C33.17,9,34.8,9.22,35,9.26V14h-2.8c-1.81,0-2.2,0.85-2.2,2.34V20h5L34.5,25z" fill="#fff"/></svg>
                      Facebook
                 </a>
             </div>


            <div class="form-footer">
                Đã có tài khoản? <a href="login.php">Đăng nhập</a>
            </div>
        </div>
    </div>
</body>
</html>