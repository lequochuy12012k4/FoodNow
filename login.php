<?php
// Start the session BEFORE any output
session_start();

// --- Database Configuration ---
// !!! USE THE SAME CREDENTIALS AS IN register.php !!!
define('DB_SERVER', 'localhost'); // or your db host
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'foodnow');
// -----------------------------

$login_error = ''; // Variable to store login error messages
$registration_success_message = ''; // Variable for success message after registration

// Check if redirected from registration success
if (isset($_GET['registered']) && $_GET['registered'] === 'success') {
    $registration_success_message = "Đăng ký thành công! Vui lòng đăng nhập.";
}

// --- Handle Login Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? ''); // Trim whitespace
    $password = trim($_POST['password'] ?? '');

    // Basic validation
    if (empty($username) || empty($password)) {
        $login_error = "Vui lòng nhập cả tên đăng nhập và mật khẩu.";
    } else {
        // Establish database connection
        $conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

        // Check connection
        if ($conn === false) {
            // Log error properly in a real app
            $login_error = "Lỗi kết nối cơ sở dữ liệu.";
            // die("ERROR: Could not connect. " . mysqli_connect_error()); // Debug only
        } else {
            // --- Prepare statement to find user by username ---
            $sql = "SELECT id, username,fullname, password FROM users WHERE username = ?"; // Select hashed password

            if ($stmt = mysqli_prepare($conn, $sql)) {
                // Bind variables to the prepared statement as parameters
                mysqli_stmt_bind_param($stmt, "s", $param_username);

                // Set parameters
                $param_username = $username;

                // Attempt to execute the prepared statement
                if (mysqli_stmt_execute($stmt)) {
                    // Store result
                    mysqli_stmt_store_result($stmt);

                    // Check if username exists
                    if (mysqli_stmt_num_rows($stmt) == 1) {
                        // Bind result variables
                        mysqli_stmt_bind_result($stmt, $db_id, $db_username,$db_fullname, $hashed_password);

                        if (mysqli_stmt_fetch($stmt)) {
                            // --- Verify password ---
                            if (password_verify($password, $hashed_password)) {
                                // --- Login Successful ---
                                // Password is correct, start a new session

                                // Store data in session variables
                                $_SESSION["loggedin"] = true;
                                $_SESSION["user_id"] = $db_id; // Store user ID if needed
                                $_SESSION["username"] = $db_username; // Store username
                                $_SESSION["fullname"] = $db_fullname;
                                // Close statement and connection before redirect
                                mysqli_stmt_close($stmt);
                                mysqli_close($conn);

                                // Redirect to user to welcome page
                                header("location: index.php");
                                exit; // Important: stop script execution
                            } else {
                                // Password is not valid
                                $login_error = "Tên đăng nhập hoặc mật khẩu không đúng.";
                            }
                        } else {
                             // Should not happen if num_rows was 1, but handle defensively
                             $login_error = "Lỗi khi lấy dữ liệu người dùng.";
                        }
                    } else {
                        // Username doesn't exist
                        $login_error = "Tên đăng nhập hoặc mật khẩu không đúng."; // Keep error generic
                    }
                } else {
                     // Log the error: error_log("MySQL Execute Error (Login): " . mysqli_error($conn));
                     $login_error = "Đã xảy ra lỗi. Vui lòng thử lại sau.";
                }

                // Close statement
                mysqli_stmt_close($stmt);

            } else {
                // Log the error: error_log("MySQL Prepare Error (Login): " . mysqli_error($conn));
                $login_error = "Đã xảy ra lỗi hệ thống.";
            }

            // Close connection
            mysqli_close($conn);
        } // End else (connection successful)
    } // End else (basic validation passed)
}
require __DIR__ . "/vendor/autoload.php";
include 'config/google_login.php';
$client = new Google\Client;
$client->setClientId('330375461320-cmqo2b80gf5b53nvgdumd4ft0doku64d.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri('http://localhost:3000/index.php/');
$client->addScope("email");
$client->addScope("profile");
$url = $client->createAuthUrl();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoodNow - Đăng nhập</title>
    <style>
        /* CSS styles from your previous code... */
        body {
            font-family: sans-serif;
            margin: 0;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            display: flex;
            width: 90%;
            max-width: 1000px;
        }

        .image-container {
            flex: 0 0 45%; /* Chiếm 45% chiều rộng */
            display: none; /* Hide image on smaller screens initially */
        }

        .image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .form-container {
            flex: 1; /* Chiếm phần còn lại */
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: stretch; /* Kéo dài các phần tử con theo chiều ngang */
        }

        /* Removed h2 as it's hidden */

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: bold;
            font-size: 1.1em;
        }

        input[type="text"],
        input[type="password"],
        input[type="email"] { /* Keep email style just in case */
            width: calc(100% - 24px); /* Adjust for padding */
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 1em;
        }

        button {
            background-color: #ff6b6b;
            color: white;
            padding: 14px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1.2em;
            transition: background-color 0.3s ease;
            margin-top: 15px; /* Reduced margin-top */
            width: 100%; /* Make button full width */
            box-sizing: border-box;
        }

        button:hover {
            background-color: #e65a5a;
        }

        .form-footer {
            margin-top: 25px; /* Adjusted margin */
            text-align: center;
            color: #777;
            font-size: 0.9em;
        }

        .form-footer a {
            color: #ff6b6b;
            text-decoration: none;
            font-weight: bold;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        /* Tiêu đề FoodNow */
        .foodnow-title {
            text-align: center;
            color: #ff6b6b;
            font-size: 3em;
            font-weight: bold;
            margin-bottom: 30px; /* Increased margin */
        }

        /* Error message style */
        .error-message {
            color: #D8000C; /* Darker red */
            background-color: #FFD2D2; /* Light red background */
            border: 1px solid #D8000C;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            margin-bottom: 15px;
            font-weight: bold;
        }

        /* Success message style */
        .success-message {
             color: #270; /* Dark green */
             background-color: #DFF2BF; /* Light green background */
             border: 1px solid #4F8A10;
             padding: 10px;
             border-radius: 5px;
             text-align: center;
             margin-bottom: 15px;
             font-weight: bold;
         }


        /* Responsive: Show image on larger screens */
        @media (min-width: 768px) {
            .image-container {
                display: block; /* Show image container */
            }
             .form-container {
                padding: 50px; /* Slightly more padding on larger screens */
            }
        }
         @media (min-width: 992px) {
             .form-container {
                padding: 60px;
            }
         }
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
</head>
<body>
    <div class="container">
        <div class="image-container">
            <!-- Make sure the image path is correct relative to login.php -->
            <img src="image/img1.jpg" alt="FoodNow Image">
        </div>
        <div class="form-container">
            <a href="index.php" style="text-decoration: none;"><h1 class="foodnow-title">FoodNow</h1></a>

            <?php
            // Display registration success message if it exists
            if (!empty($registration_success_message)) {
                echo '<p class="success-message">' . htmlspecialchars($registration_success_message) . '</p>';
            }

            // Display login error message here if it exists
            if (!empty($login_error)) {
                echo '<p class="error-message">' . htmlspecialchars($login_error) . '</p>';
            }
            ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <label for="username">Tên đăng nhập:</label>
                    <input type="text" id="username" name="username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="password">Mật khẩu:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit">Đăng nhập</button>
            </form>
            <div class="social-login-divider"><span>HOẶC</span></div>

             <div class="social-login-buttons">
                 <a href="<?= $url?>" class="btn-social btn-google">
                     <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48px" height="48px"><path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"/><path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"/><path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"/><path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571l0.001-0.001l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"/></svg>
                     Google
                </a>
                 <a href="#" class="btn-social btn-facebook">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50" width="50px" height="50px"><path d="M41,4H9C6.24,4,4,6.24,4,9v32c0,2.76,2.24,5,5,5h32c2.76,0,5-2.24,5-5V9C46,6.24,43.76,4,41,4z" fill="#1877f2"/><path d="M34.5,25H31v16h-6V25h-4v-5h4v-3.9c0-4.33,1.96-7.1,6.54-7.1C33.17,9,34.8,9.22,35,9.26V14h-2.8c-1.81,0-2.2,0.85-2.2,2.34V20h5L34.5,25z" fill="#fff"/></svg>
                      Facebook
                 </a>
             </div>
            <div class="form-footer">
                Chưa có tài khoản? <a href="register.php">Đăng ký</a>
            </div>
        </div>
    </div>
</body>
</html>