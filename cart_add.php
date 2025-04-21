<?php
// MUST be the very first line
session_start();

// --- Database Configuration ---
$servername = "localhost";
$username_db = "root"; // Renamed to avoid conflict with session username
$password_db = "";
$databaseName = "foodnow";
$foodTableName = 'food_data';
$orderTableName = 'orders'; // Your table name

// --- Configuration ---
$default_redirect = 'index.php';

// --- Helper function ---
function redirect_with_message($url, $message, $is_error = false) {
    // ... (function remains the same as before) ...
    if ($is_error) {
        $_SESSION['cart_error'] = $message;
        unset($_SESSION['cart_message']);
    } else {
        $_SESSION['cart_message'] = $message;
        unset($_SESSION['cart_error']);
    }
    header("Location: " . $url);
    exit();
}

// --- Input Processing ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['food_id']) && isset($_POST['quantity'])) {

        $food_id = filter_input(INPUT_POST, 'food_id', FILTER_VALIDATE_INT);
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
        $redirect_url = $_SERVER['HTTP_REFERER'] ?? $default_redirect;

        if ($food_id === false || $food_id <= 0 || $quantity === false || $quantity <= 0) {
             redirect_with_message($redirect_url, "Dữ liệu không hợp lệ. Vui lòng nhập số lượng hợp lệ.", true);
        }

        // --- Database Operations ---

        $session_username = isset($_SESSION['username']) ? trim($_SESSION['username']) : null;
        $session_id = session_id();

        $conn = mysqli_connect($servername, $username_db, $password_db, $databaseName);

        if (!$conn) {
            error_log("Database Connection failed in cart_add.php: " . mysqli_connect_error());
            redirect_with_message($redirect_url, 'Lỗi kết nối cơ sở dữ liệu. Vui lòng thử lại sau.', true);
        }
        mysqli_set_charset($conn, "utf8mb4");

        try {
            // --- 7. Fetch Food Price AND NAME - *** MODIFIED *** ---
            // Select both price and name now
            $sql_food_details = "SELECT price, name FROM `{$foodTableName}` WHERE id = ? LIMIT 1";
            $stmt_food_details = mysqli_prepare($conn, $sql_food_details);
            // ... (error checking for prepare same as before) ...
            if (!$stmt_food_details) throw new Exception("Lỗi chuẩn bị câu lệnh lấy chi tiết món ăn: " . mysqli_error($conn));

            mysqli_stmt_bind_param($stmt_food_details, "i", $food_id);
            mysqli_stmt_execute($stmt_food_details);
            $result_food_details = mysqli_stmt_get_result($stmt_food_details);
            $food_data = mysqli_fetch_assoc($result_food_details);
            mysqli_stmt_close($stmt_food_details);

            if (!$food_data) {
                 redirect_with_message($redirect_url, 'Món ăn không tồn tại (ID: ' . htmlspecialchars($food_id) . ').', true);
            }
            // Get both price and name from the fetched data
            $current_price = (float)$food_data['price'];
            $current_food_name = $food_data['name']; // <-- Store the food name

            // --- 8. Check if item already exists ---
            // ** This part remains the same (checks by food_id and user/session) **
            $existing_cart_item = null;
            $sql_check = "SELECT id, quantity FROM `{$orderTableName}` WHERE food_id = ? AND status = 'cart'";
            // ... (rest of the checking logic for user/guest remains the same) ...
            $stmt_check = null;
            if ($session_username !== null) {
                $sql_check .= " AND username = ?";
                $stmt_check = mysqli_prepare($conn, $sql_check);
                if (!$stmt_check) throw new Exception("Lỗi chuẩn bị câu lệnh kiểm tra (user): " . mysqli_error($conn));
                mysqli_stmt_bind_param($stmt_check, "is", $food_id, $session_username);
            } else {
                $sql_check .= " AND session_id = ? AND username IS NULL";
                $stmt_check = mysqli_prepare($conn, $sql_check);
                 if (!$stmt_check) throw new Exception("Lỗi chuẩn bị câu lệnh kiểm tra (guest): " . mysqli_error($conn));
                mysqli_stmt_bind_param($stmt_check, "is", $food_id, $session_id);
            }
            $sql_check .= " LIMIT 1";
            mysqli_stmt_execute($stmt_check);
            $result_check = mysqli_stmt_get_result($stmt_check);
            $existing_cart_item = mysqli_fetch_assoc($result_check);
            mysqli_stmt_close($stmt_check);

            // --- 9. Update or Insert ---
            if ($existing_cart_item) {
                // --- UPDATE existing item quantity ---
                // ** We generally don't need to update the food_name here **
                // ** unless food names can change and you want the cart to reflect that **
                // ** Keeping it simple: only update quantity and price (if needed) **
                $existing_item_id = $existing_cart_item['id'];
                $new_quantity = $existing_cart_item['quantity'] + $quantity;

                // Update quantity and price_at_add (in case price changed since first add - optional)
                // We are NOT updating food_name on quantity change here.
                $sql_update = "UPDATE `{$orderTableName}` SET quantity = ?, price_at_add = ?, updated_at = NOW() WHERE id = ?";
                $stmt_update = mysqli_prepare($conn, $sql_update);
                 if (!$stmt_update) throw new Exception("Lỗi chuẩn bị câu lệnh cập nhật: " . mysqli_error($conn));

                mysqli_stmt_bind_param($stmt_update, "idi", $new_quantity, $current_price, $existing_item_id);

                if (!mysqli_stmt_execute($stmt_update)) {
                    throw new Exception("Lỗi thực thi câu lệnh cập nhật: " . mysqli_stmt_error($stmt_update));
                }
                mysqli_stmt_close($stmt_update);
                $message = "Số lượng món ăn đã được cập nhật trong giỏ hàng.";

            } else {
                // --- INSERT new item - *** MODIFIED FOR food_name *** ---
                // Add `food_name` column to INSERT list
                $sql_insert = "INSERT INTO `{$orderTableName}`
                               (session_id, user_id, username, food_id, food_name, quantity, price_at_add, status, added_at, updated_at)
                               VALUES (?, ?, ?, ?, ?, ?, ?, 'cart', NOW(), NOW())"; // Added ?, for food_name

                $stmt_insert = mysqli_prepare($conn, $sql_insert);
                 if (!$stmt_insert) throw new Exception("Lỗi chuẩn bị câu lệnh thêm mới: " . mysqli_error($conn));

                $bind_session_id_val = ($session_username === null) ? $session_id : null;
                $bind_user_id_val = null;
                $bind_username_val = $session_username;
                // ** $current_food_name is the new value to bind **

                // Bind parameters:
                // s: session_id (string/null)
                // s: user_id (binding NULL)
                // s: username (string/null)
                // i: food_id (integer)
                // s: food_name (string) <-- NEW
                // i: quantity (integer)
                // d: price_at_add (double)
                // ** Adjusted type string: sssisid **
                mysqli_stmt_bind_param($stmt_insert, "sssisid", // <-- Updated type string
                    $bind_session_id_val,
                    $bind_user_id_val,
                    $bind_username_val,
                    $food_id,
                    $current_food_name, // <-- Bind the food name
                    $quantity,
                    $current_price
                );


                 if (!mysqli_stmt_execute($stmt_insert)) {
                    throw new Exception("Lỗi thực thi câu lệnh thêm mới: " . mysqli_stmt_error($stmt_insert));
                }
                mysqli_stmt_close($stmt_insert);
                $message = "Đã thêm món ăn vào giỏ hàng.";
            }

            // --- Success ---
            mysqli_close($conn);
            redirect_with_message($redirect_url, $message, false);

        } catch (Exception $e) {
            // --- Handle Database Errors ---
            error_log("Error in cart_add.php database operation: " . $e->getMessage());
            redirect_with_message($redirect_url, 'Đã xảy ra lỗi khi xử lý giỏ hàng. Vui lòng thử lại.', true);
        }

    } else {
        // Required POST data missing
        $redirect_url = $_SERVER['HTTP_REFERER'] ?? $default_redirect;
        redirect_with_message($redirect_url, "Thiếu thông tin món ăn hoặc số lượng.", true);
    }

} else {
    // Not a POST request
    header('Location: ' . $default_redirect);
    exit();
}
?>