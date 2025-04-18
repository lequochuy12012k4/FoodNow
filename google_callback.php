<?php
// Start session FIRST
session_start();

// Include dependencies
require __DIR__ . "/vendor/autoload.php";

// --- Database Configuration (Should match login.php) ---
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'foodnow');

// --- Google Client Configuration (MUST MATCH login.php EXACTLY) ---
$client = new Google\Client();
$client->setClientId('330375461320-cmqo2b80gf5b53nvgdumd4ft0doku64d.apps.googleusercontent.com'); // Your Client ID
$client->setClientSecret('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');           // Your Client Secret
$client->setRedirectUri('http://localhost:3000/google_callback.php'); // Your Redirect URI (NO trailing slash unless configured that way everywhere)
$client->addScope("email");
$client->addScope("profile");
// --------------------------------------------------------

// --- Database Connection ---
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn === false) {
    // Redirect back to login with a generic error if DB connection fails
    header("location: login.php?google_error=" . urlencode("Lỗi kết nối CSDL."));
    exit;
}
// Set charset AFTER connection
mysqli_set_charset($conn, "utf8mb4");
// --------------------------

// --- Handle Google Response ---
if (isset($_GET['code'])) {
    $token = null; // Initialize token variable
    try {
        // Exchange authorization code for an access token.
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

        // Check if fetching token failed
        if (!isset($token['access_token'])) {
             $error_desc = $token['error_description'] ?? 'Unknown token error';
             throw new Exception("Lỗi lấy token: " . $error_desc);
        }

        // Set the access token used for requests
        $client->setAccessToken($token['access_token']);

        // Get profile info
        $google_oauth = new Google\Service\Oauth2($client);
        $google_account_info = $google_oauth->userinfo->get();

        $google_id = $google_account_info->getId();
        $email = $google_account_info->getEmail();
        $fullname = $google_account_info->getName();
        // $picture = $google_account_info->getPicture(); // Optional: Store profile picture URL if needed

        // --- Check if user exists in Database based on email ---
        $sql_check = "SELECT id, username, fullname, role FROM users WHERE email = ?";
        if ($stmt_check = mysqli_prepare($conn, $sql_check)) {
            mysqli_stmt_bind_param($stmt_check, "s", $email);
            if (mysqli_stmt_execute($stmt_check)) {
                mysqli_stmt_store_result($stmt_check);

                if (mysqli_stmt_num_rows($stmt_check) == 1) {
                    // --- User Exists ---
                    mysqli_stmt_bind_result($stmt_check, $db_id, $db_username, $db_fullname, $db_role);
                    mysqli_stmt_fetch($stmt_check);
                    mysqli_stmt_close($stmt_check); // Close check statement

                    // Update google_id if it's missing (e.g., user registered normally first)
                    $sql_update_google_id = "UPDATE users SET google_id = ? WHERE id = ? AND google_id IS NULL";
                    if ($stmt_update = mysqli_prepare($conn, $sql_update_google_id)) {
                        mysqli_stmt_bind_param($stmt_update, "si", $google_id, $db_id);
                        mysqli_stmt_execute($stmt_update); // Execute update
                        mysqli_stmt_close($stmt_update); // Close update statement
                    } // No critical error if update fails, just means google_id might already be set

                    // --- Set Session Variables ---
                    $_SESSION["loggedin"] = true;
                    $_SESSION["user_id"] = $db_id;
                    $_SESSION["username"] = $db_username; // Keep original username
                    $_SESSION["fullname"] = $db_fullname; // Keep original fullname
                    $_SESSION["role"] = $db_role;

                    // --- Redirect based on role ---
                    if ($db_role === 'admin') {
                        header("location: admin.php");
                    } else {
                        header("location: index.php");
                    }
                    mysqli_close($conn); // Close connection before exiting
                    exit;

                } else {
                    // --- User Doesn't Exist - Create New User ---
                    mysqli_stmt_close($stmt_check); // Close check statement

                    // Use email as username for simplicity, ensure it doesn't conflict if username is also unique
                    // Consider adding logic to handle potential username clashes if needed
                    $new_username = $email;
                    $new_role = 'user'; // Default role for new Google signups

                    $sql_insert = "INSERT INTO users (username, fullname, email, google_id, role, password) VALUES (?, ?, ?, ?, ?, NULL)";
                    if ($stmt_insert = mysqli_prepare($conn, $sql_insert)) {
                        mysqli_stmt_bind_param($stmt_insert, "sssss", $new_username, $fullname, $email, $google_id, $new_role);

                        if (mysqli_stmt_execute($stmt_insert)) {
                            $new_user_id = mysqli_insert_id($conn);

                            // --- Set Session Variables ---
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $new_user_id;
                            $_SESSION["username"] = $new_username;
                            $_SESSION["fullname"] = $fullname;
                            $_SESSION["role"] = $new_role;

                            mysqli_stmt_close($stmt_insert); // Close insert statement
                            mysqli_close($conn); // Close connection before redirecting

                            // Redirect new user to index page
                            header("location: index.php");
                            exit;

                        } else {
                             // Handle potential duplicate email/username if constraints are tight
                            if (mysqli_errno($conn) == 1062) { // 1062 is duplicate entry error code
                                // Decide how to handle: log them in as the existing user or show specific error?
                                // Option 1: Try logging in as the user found via email (safer if email is truly unique identifier)
                                // [Add logic here to re-query user by email and log them in if desired]
                                // Option 2: Show specific error
                                 throw new Exception("Email hoặc tên đăng nhập đã được sử dụng.");
                            } else {
                                throw new Exception("Lỗi khi tạo tài khoản mới: " . mysqli_error($conn));
                            }
                        }
                         // Ensure statement is closed even if execute failed before error
                        if (isset($stmt_insert) && $stmt_insert instanceof mysqli_stmt) {
                            mysqli_stmt_close($stmt_insert);
                        }
                    } else {
                         throw new Exception("Lỗi chuẩn bị câu lệnh INSERT: " . mysqli_error($conn));
                    }
                } // End if user exists check
            } else {
                 throw new Exception("Lỗi thực thi câu lệnh SELECT: " . mysqli_stmt_error($stmt_check));
            }

        } else {
            throw new Exception("Lỗi chuẩn bị câu lệnh SELECT: " . mysqli_error($conn));
        }

    } catch (Exception $e) {
        // Catch any errors during the process
        // Log the actual error for server admin: error_log("Google Login Callback Error: " . $e->getMessage());

        // Close connection if it's still open
        if ($conn instanceof mysqli) {
           mysqli_close($conn);
        }

        // Redirect back to login page with a user-friendly error message
        header("location: login.php?google_error=" . urlencode("Đã xảy ra lỗi: " . $e->getMessage()));
        exit;
    }

} else {
    // No 'code' parameter found - redirect back to login page
    if ($conn instanceof mysqli) {
        mysqli_close($conn); // Close connection if open
    }
    header("location: login.php?google_error=" . urlencode("Không nhận được mã ủy quyền từ Google."));
    exit;
}

// Close connection if execution somehow reaches here (shouldn't normally happen due to exits)
if ($conn instanceof mysqli) {
    mysqli_close($conn);
}
?>