<?php
session_start(); // Start session FIRST

// --- Database Configuration ---
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // Replace if needed
define('DB_PASSWORD', '');     // Replace if needed
define('DB_NAME', 'foodnow');
// -----------------------------

$error_message = '';

// --- Handle Standard Registration Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Basic check to ensure it's the registration form being submitted
    if (isset($_POST['new_username'], $_POST['new_password'], $_POST['email'], $_POST['fullname'], $_POST['confirm_password'])) {

        $conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

        if ($conn === false) {
            // Log the error: error_log("Database Connection Error: " . mysqli_connect_error());
            $error_message = "Lỗi: Không thể kết nối đến cơ sở dữ liệu.";
        } else {
             mysqli_set_charset($conn, "utf8mb4"); // Set charset

             // Use trim() directly without intermediate variables if preferred
             $fullname = mysqli_real_escape_string($conn, trim($_POST['fullname']));
             $email = mysqli_real_escape_string($conn, trim($_POST['email']));
             $username = mysqli_real_escape_string($conn, trim($_POST['new_username']));
             $password = trim($_POST['new_password']);
             $confirm_password = trim($_POST['confirm_password']);

             // --- Validation ---
             if (empty($fullname) || empty($email) || empty($username) || empty($password) || empty($confirm_password)) {
                 $error_message = "Vui lòng điền đầy đủ tất cả các trường.";
             } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                 $error_message = "Định dạng email không hợp lệ.";
             } elseif ($password !== $confirm_password) {
                 $error_message = "Mật khẩu và xác nhận mật khẩu không khớp.";
             } elseif (strlen($password) < 6) { // Example: Minimum password length
                  $error_message = "Mật khẩu phải có ít nhất 6 ký tự.";
             } else {
                 // --- Check if username or email already exists ---
                 $sql_check = "SELECT id FROM users WHERE username = ? OR email = ?";
                 if ($stmt_check = mysqli_prepare($conn, $sql_check)) {
                     mysqli_stmt_bind_param($stmt_check, "ss", $param_username_check, $param_email_check);
                     $param_username_check = $username;
                     $param_email_check = $email;

                     if (mysqli_stmt_execute($stmt_check)) {
                         mysqli_stmt_store_result($stmt_check);

                         if (mysqli_stmt_num_rows($stmt_check) > 0) {
                              mysqli_stmt_bind_result($stmt_check, $existing_id);
                              mysqli_stmt_fetch($stmt_check); // Fetch to see which one matched if needed later

                              // More specific error (optional)
                              // $check_again_sql = "SELECT COUNT(*) FROM users WHERE username = ?";
                              // $check_again_stmt = mysqli_prepare($conn, $check_again_sql);
                              // mysqli_stmt_bind_param($check_again_stmt, "s", $param_username_check);
                              // mysqli_stmt_execute($check_again_stmt);
                              // mysqli_stmt_bind_result($check_again_stmt, $username_count);
                              // mysqli_stmt_fetch($check_again_stmt);
                              // mysqli_stmt_close($check_again_stmt);
                              // if ($username_count > 0) {
                              //    $error_message = "Tên đăng nhập đã tồn tại.";
                              // } else {
                              //    $error_message = "Email đã được sử dụng.";
                              // }

                              $error_message = "Tên đăng nhập hoặc email đã tồn tại.";

                         } else {
                              // --- Hash Password and Insert User ---
                              $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                              $sql_insert = "INSERT INTO users (fullname, email, username, password, role) VALUES (?, ?, ?, ?, 'user')"; // Default role 'user'
                              if ($stmt_insert = mysqli_prepare($conn, $sql_insert)) {
                                  mysqli_stmt_bind_param($stmt_insert, "ssss", $param_fullname, $param_email_insert, $param_username_insert, $param_hashed_password);

                                  $param_fullname = $fullname;
                                  $param_email_insert = $email;
                                  $param_username_insert = $username;
                                  $param_hashed_password = $hashed_password; // No role needed in bind if hardcoded

                                  if (mysqli_stmt_execute($stmt_insert)) {
                                      // --- Registration Successful ---
                                      mysqli_stmt_close($stmt_insert);
                                      mysqli_stmt_close($stmt_check); // Close check statement here
                                      mysqli_close($conn);
                                      // Redirect to login page with a success message
                                      header("Location: login.php?registered=success");
                                      exit; // Important: stop script execution after redirect
                                  } else {
                                      error_log("MySQL Execute Error (Register Insert): " . mysqli_error($conn));
                                      $error_message = "Đã xảy ra lỗi trong quá trình đăng ký. Vui lòng thử lại sau.";
                                  }
                                  mysqli_stmt_close($stmt_insert); // Close insert statement if execute failed
                              } else {
                                   error_log("MySQL Prepare Error (Register Insert): " . mysqli_error($conn));
                                   $error_message = "Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.";
                              }
                         }
                     } else {
                          error_log("MySQL Execute Error (Register Check): " . mysqli_error($conn));
                          $error_message = "Đã xảy ra lỗi khi kiểm tra thông tin. Vui lòng thử lại sau.";
                     }
                     // Close check statement only if it was prepared successfully
                     if ($stmt_check) {
                         mysqli_stmt_close($stmt_check);
                     }
                 } else {
                      error_log("MySQL Prepare Error (Register Check): " . mysqli_error($conn));
                      $error_message = "Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.";
                 }
             }
             // Close connection only if it was opened successfully
             if ($conn) {
                 mysqli_close($conn);
             }
        }
    }
    // else {
        // Handle potential other POST requests if needed, e.g., from social logins later
    // }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoodNow - Đăng ký</title>
    <style>
        :root {
            --primary-color: #ff6b6b;
            --primary-hover-color: #e65a5a;
            --text-color: #333;
            --label-color: #555;
            --border-color: #ddd;
            --background-color: #f4f4f4;
            --white-color: #fff;
            --error-bg: #FFD2D2; /* Changed from #ffebee */
            --error-border: #D8000C; /* Changed from #e57373 */
            --error-text: #D8000C; /* Changed from red */
            --google-border: #ccc;
            --google-hover-bg: #f5f5f5;
            --facebook-bg: #1877f2;
            --facebook-hover-bg: #166FE5;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            font-size: 16px; /* Base font size */
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 1rem; /* Add padding for small screens */
        }

        .register-container { /* Renamed from login-container */
            background-color: var(--white-color);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column; /* Stack image and form vertically by default */
            width: 100%;
            max-width: 900px; /* Max width of the container */
            margin: 2rem 0; /* Add some margin for scrolling if content overflows */
        }

        .register-image-section { /* Renamed */
            display: none; /* Hide image on small screens by default */
            width: 100%;
            height: 200px; /* Fixed height for stacked view */
        }

        .register-image-section img {
            width: 100%;
            height: 100%;
            object-fit: cover; /* Cover the area without distortion */
            display: block;
        }

        .register-form-section { /* Renamed */
            padding: 1.5rem; /* Reduced padding slightly for mobile */
            width: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .foodnow-title-link {
             text-decoration: none;
             display: block; /* Make link take full width for centering text */
             margin-bottom: 1.5rem;
        }

        .foodnow-title {
            text-align: center;
            color: var(--primary-color);
            font-size: 2.5rem; /* Slightly smaller title */
            font-weight: bold;
            margin: 0; /* Remove default margin */
        }

        /* Using a general message class for potential future success messages too */
        .message {
            padding: 0.8rem 1rem;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 1.5rem;
            font-weight: 500;
            font-size: 0.95rem;
            border: 1px solid transparent;
        }

        .error-message { /* Style specifically for errors */
            color: var(--error-text);
            background-color: var(--error-bg);
            border-color: var(--error-border);
        }

        .form-group {
            margin-bottom: 1rem; /* Slightly reduced margin */
        }

        label {
            display: block;
            margin-bottom: 0.4rem; /* Slightly reduced margin */
            color: var(--label-color);
            font-weight: 600;
            font-size: 0.95rem; /* Slightly smaller label */
        }

        input[type="text"],
        input[type="password"],
        input[type="email"] {
            width: 100%;
            padding: 0.7rem 0.9rem; /* Slightly reduced padding */
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 1rem;
            line-height: 1.4;
            transition: border-color 0.2s ease;
        }

        input[type="text"]:focus,
        input[type="password"]:focus,
        input[type="email"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(255, 107, 107, 0.2);
        }

        .btn {
            display: inline-flex; /* Use inline-flex for alignment */
            align-items: center;
            justify-content: center;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            user-select: none;
            border: 1px solid transparent;
            padding: 0.8rem 1.5rem;
            font-size: 1.1rem;
            font-weight: 600;
            line-height: 1.5;
            border-radius: 6px;
            cursor: pointer;
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            text-decoration: none; /* Remove underline from links styled as buttons */
            width: 100%; /* Make buttons full width by default */
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: var(--white-color);
            margin-top: 1rem; /* Adjusted space above the main button */
        }

        .btn-primary:hover {
            background-color: var(--primary-hover-color);
            color: var(--white-color);
        }

        .social-login-divider {
            text-align: center;
            margin: 1.5rem 0 1rem; /* Adjusted spacing */
            color: #aaa;
            font-weight: 500;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            text-transform: uppercase;
        }

        .social-login-divider::before,
        .social-login-divider::after {
            content: '';
            flex-grow: 1;
            height: 1px;
            background-color: var(--border-color);
            margin: 0 0.8rem;
        }

        .social-login-buttons {
            display: flex;
            flex-direction: column; /* Stack social buttons on mobile */
            gap: 0.8rem; /* Space between stacked buttons */
            margin-top: 0.5rem;
        }

        .btn-social {
            padding: 0.7rem 1rem; /* Slightly smaller padding for social buttons */
            font-size: 1rem;
            font-weight: 500;
        }

        .btn-social svg,
        .btn-social img {
            width: 20px;
            height: 20px;
            margin-right: 0.8rem;
        }

        .btn-google {
            background-color: var(--white-color);
            color: var(--label-color);
            border-color: var(--google-border);
        }

        .btn-google:hover {
            background-color: var(--google-hover-bg);
            border-color: #bbb;
            color: var(--label-color); /* Keep text color consistent on hover */
        }

        .btn-facebook {
             background-color: var(--facebook-bg);
             color: var(--white-color);
             border-color: var(--facebook-bg);
             /* opacity: 0.7;
             pointer-events: none; */ /* Temporarily disable if not implemented */
         }

         .btn-facebook:hover {
              background-color: var(--facebook-hover-bg);
              border-color: var(--facebook-hover-bg);
              color: var(--white-color);
         }
          /* Facebook uses inline SVG, slightly different fill required */
         .btn-facebook .fb-icon path:first-child { fill: var(--facebook-bg); }
         .btn-facebook:hover .fb-icon path:first-child { fill: var(--facebook-hover-bg); }
         .btn-facebook .fb-icon path:last-child { fill: var(--white-color); }


        .form-footer {
            margin-top: 1.5rem; /* Adjusted spacing */
            text-align: center;
            color: var(--label-color);
            font-size: 0.95rem;
        }

        .form-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        /* --- Responsive Adjustments --- */

        /* Medium screens (Tablets, larger phones) */
        @media (min-width: 576px) {
             .register-form-section {
                 padding: 2rem; /* Increase padding */
             }
             .foodnow-title {
                 font-size: 2.8rem;
             }
              /* Make social buttons side-by-side */
             .social-login-buttons {
                flex-direction: row; /* Side-by-side */
                gap: 1rem;
             }
             .btn-social {
                flex: 1; /* Make buttons share space */
             }
        }

        /* Large screens (Desktops) */
        @media (min-width: 768px) {
            .register-container {
                flex-direction: row; /* Side-by-side layout */
                max-width: 850px; /* Adjust max-width if needed */
            }

            .register-image-section {
                display: block; /* Show the image */
                flex: 0 0 40%; /* Image takes 40% of width */
                height: auto; /* Reset height */
            }

            .register-form-section {
                flex: 0 0 60%; /* Form takes 60% of width */
                padding: 2.5rem; /* Increase padding */
            }
             .foodnow-title {
                 font-size: 3rem;
             }
        }

         /* Extra Large screens (Larger Desktops) */
        @media (min-width: 992px) {
             .register-container {
                max-width: 950px; /* Increase container width */
             }
            .register-image-section {
                flex: 0 0 45%;
            }
            .register-form-section {
                flex: 0 0 55%;
                padding: 3rem 3.5rem; /* Fine-tune padding */
            }
        }

    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-image-section">
            <!-- Ensure the image path is correct relative to register.php -->
            <img src="image/img1.jpg" alt="Food ingredients">
        </div>
        <div class="register-form-section">
            <a href="index.php" class="foodnow-title-link"><h1 class="foodnow-title">FoodNow</h1></a>

            <?php
            // Display error message if it exists
            if (!empty($error_message)) {
                // Wrap the message in the standard message structure
                echo '<div class="message error-message">' . htmlspecialchars($error_message) . '</div>';
            }
            ?>

            <!-- Add novalidate to rely on server-side validation first -->
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>
                <div class="form-group">
                    <label for="fullname">Họ và tên:</label>
                    <input type="text" id="fullname" name="fullname" required value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="new_username">Tên đăng nhập:</label>
                    <input type="text" id="new_username" name="new_username" required value="<?php echo isset($_POST['new_username']) ? htmlspecialchars($_POST['new_username'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="new_password">Mật khẩu (ít nhất 6 ký tự):</label>
                    <input type="password" id="new_password" name="new_password" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Xác nhận mật khẩu:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn btn-primary">Đăng ký</button>
            </form>

            <div class="social-login-divider"><span>Hoặc đăng ký với</span></div>

             <div class="social-login-buttons">
                 <!-- Google Sign-Up Button (Link needs Google API setup similar to login) -->
                 <a href="#" class="btn btn-social btn-google">
                     <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48px" height="48px"><path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"/><path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"/><path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"/><path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571l0.001-0.001l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"/></svg>
                     Google
                </a>
                 <!-- Facebook Sign-Up Button (Link needs Facebook SDK/API setup) -->
                 <a href="#" class="btn btn-social btn-facebook">
                      <svg class="fb-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50" width="50px" height="50px"><path d="M41,4H9C6.24,4,4,6.24,4,9v32c0,2.76,2.24,5,5,5h32c2.76,0,5-2.24,5-5V9C46,6.24,43.76,4,41,4z" /><path d="M34.5,25H31v16h-6V25h-4v-5h4v-3.9c0-4.33,1.96-7.1,6.54-7.1C33.17,9,34.8,9.22,35,9.26V14h-2.8c-1.81,0-2.2,0.85-2.2,2.34V20h5L34.5,25z" /></svg>
                      Facebook
                 </a>
                 <!-- Note: Implementing social registration requires backend handling similar to social login -->
             </div>

            <div class="form-footer">
                Đã có tài khoản? <a href="login.php">Đăng nhập ngay</a>
            </div>
        </div>
    </div>
</body>
</html>