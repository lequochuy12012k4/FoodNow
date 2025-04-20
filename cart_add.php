<?php
// MUST be the very first line, before any output or whitespace
session_start();

// --- Configuration ---
// Set a default redirect location in case HTTP_REFERER is not available
$default_redirect = 'index.php';
// You might want to redirect to cart.php after adding? Uncomment the line below if so.
// $default_redirect = 'cart.php';

// --- Input Processing ---

// 1. Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 2. Check if required data is present
    if (isset($_POST['food_id']) && isset($_POST['quantity'])) {

        // 3. Validate and Sanitize Input
        $food_id = filter_input(INPUT_POST, 'food_id', FILTER_VALIDATE_INT);
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

        // 4. Check if validation was successful and quantity is positive
        if ($food_id !== false && $food_id > 0 && $quantity !== false && $quantity > 0) {

            // 5. Initialize the cart in session if it doesn't exist
            if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }

            // 6. Add/Update item in the cart
            if (isset($_SESSION['cart'][$food_id])) {
                // Item already exists, increase quantity
                $_SESSION['cart'][$food_id] += $quantity;
                $_SESSION['cart_message'] = "Số lượng món ăn đã được cập nhật trong giỏ hàng.";
                // --- Optional Debugging ---
                // error_log("Cart Add: Updated quantity for ID {$food_id}. New quantity: {$_SESSION['cart'][$food_id]}");
            } else {
                // Item does not exist, add it
                $_SESSION['cart'][$food_id] = $quantity;
                $_SESSION['cart_message'] = "Món ăn đã được thêm vào giỏ hàng.";
                 // --- Optional Debugging ---
                // error_log("Cart Add: Added new item ID {$food_id} with quantity {$quantity}");
            }

             // --- Optional Debugging: Log cart contents after modification ---
            // error_log("Cart contents after add/update: " . print_r($_SESSION['cart'], true));


            // 7. Redirect back to the previous page (or default)
            $redirect_url = $_SERVER['HTTP_REFERER'] ?? $default_redirect;
            header('Location: ' . $redirect_url);
            exit(); // IMPORTANT: Stop script execution after redirect

        } else {
            // Validation failed (invalid ID, quantity <= 0, or not numbers)
            $_SESSION['cart_error'] = "Dữ liệu không hợp lệ. Vui lòng nhập số lượng hợp lệ.";
            // --- Optional Debugging ---
            // error_log("Cart Add Validation Failed: ID received='{$_POST['food_id']}', Qty received='{$_POST['quantity']}'");
            $redirect_url = $_SERVER['HTTP_REFERER'] ?? $default_redirect;
            header('Location: ' . $redirect_url);
            exit(); // Stop script execution
        }

    } else {
        // Required POST data (food_id or quantity) is missing
        $_SESSION['cart_error'] = "Thiếu thông tin món ăn hoặc số lượng.";
         // --- Optional Debugging ---
        // error_log("Cart Add Failed: Missing food_id or quantity in POST data.");
        $redirect_url = $_SERVER['HTTP_REFERER'] ?? $default_redirect;
        header('Location: ' . $redirect_url);
        exit(); // Stop script execution
    }

} else {
    // Request method is not POST (e.g., direct access via GET)
    // Optionally set an error message, but often just redirecting is enough
    // $_SESSION['cart_error'] = "Yêu cầu không hợp lệ.";
     // --- Optional Debugging ---
    // error_log("Cart Add Failed: Request method was not POST.");
    header('Location: ' . $default_redirect); // Redirect to a safe page
    exit(); // Stop script execution
}

?>