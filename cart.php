<?php
session_start(); // Access session for user ID/session ID and messages

// --- Database Configuration ---
$servername = "localhost";
$username = "root"; // DB username
$password = ""; // DB password
$databaseName = "foodnow";
$foodTableName = 'food_data';
$orderTableName = 'orders';

// --- Constants and Paths ---
define('SHIPPING_FEE', 15000.00);
$uploadDir = 'uploads/'; // Server path for file operations
$webUploadDir = 'uploads/'; // Web path for src attributes
$placeholderPath = 'image/placeholder-food.png'; // Placeholder image
define('VIETQR_BANK_BIN', '970436'); // Example: Techcombank BIN
define('VIETQR_ACCOUNT_NO', '1903xxxxxxxxxx'); // Replace with YOUR account number
define('VIETQR_ACCOUNT_NAME', 'NGUYEN VAN A'); // Replace with YOUR account name
$bank_bin = VIETQR_BANK_BIN;
$bank_number = VIETQR_ACCOUNT_NO;
$account_name = VIETQR_ACCOUNT_NAME;

// --- Include Header ---
include 'parts/header.php';

// --- Initialize variables ---
$cart_display_items = [];
$subtotal_price = 0.00;
$shipping_cost = 0.00;
$grand_total_price = 0.00;
$error_message = '';
$success_message = '';

// --- Feedback messages from session ---
if (isset($_SESSION['cart_message'])) {
    $success_message = $_SESSION['cart_message'];
    unset($_SESSION['cart_message']);
}
if (isset($_SESSION['cart_error'])) {
    $error_message = $_SESSION['cart_error'];
    unset($_SESSION['cart_error']);
}

// --- Helper Functions ---

/**
 * Generates image path, handling missing files and providing a placeholder.
 */
function get_image_path(?string $imageFilename, string $uploadDir, string $webUploadDir, string $placeholderPath): string
{
    if (!empty($imageFilename)) {
        // Use realpath to get the canonicalized absolute pathname
        $basePath = realpath(rtrim($uploadDir, '/'));
        if ($basePath === false) {
             error_log("Upload directory does not exist or is inaccessible: " . $uploadDir);
             return htmlspecialchars($placeholderPath);
        }
        $fullFilePath = $basePath . '/' . $imageFilename;

        if (file_exists($fullFilePath) && is_file($fullFilePath)) {
            // Ensure web path uses forward slashes for URLs
            return str_replace('\\', '/', rtrim($webUploadDir, '/')) . '/' . htmlspecialchars($imageFilename);
        } else {
            // Log if the file is referenced but not found
             error_log("Image file not found but listed in DB: " . $fullFilePath . " (Referenced filename: " . $imageFilename . ")");
        }
    }
    return htmlspecialchars($placeholderPath);
}


/**
 * Calculates CRC16 CCITT checksum.
 * Used for VietQR payload generation (Tag 63).
 * @param string $str Input string.
 * @return int The CRC16 value.
 */
function crc16_ccitt(string $str): int
{
    $crc = 0xFFFF; // Initial value
    $strlen = strlen($str);
    for ($c = 0; $c < $strlen; $c++) {
        $crc ^= ord(substr($str, $c, 1)) << 8; // XOR with byte shifted left
        for ($i = 0; $i < 8; $i++) {
            // If leftmost bit is 1, XOR with polynomial 0x1021
            $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
        }
    }
    return $crc & 0xFFFF; // Return final 16-bit value
}

/**
 * Builds a TLV (Tag-Length-Value) field for VietQR payload.
 * @param string $id The Tag ID (2 digits).
 * @param string|int $value The Value.
 * @return string The formatted TLV field string.
 */
function buildVietQRField(string $id, $value): string
{
    $valueStr = (string)$value; // Ensure value is a string
    // Convert value to UTF-8 for correct length calculation
    $valueUtf8 = mb_convert_encoding($valueStr, 'UTF-8');
    // Calculate length of UTF-8 string
    $len = mb_strlen($valueUtf8, 'UTF-8');
    // Pad length to 2 digits with leading zero if needed
    $lenPadded = str_pad((string)$len, 2, '0', STR_PAD_LEFT);
    // Concatenate ID + Length + Value
    return $id . $lenPadded . $valueUtf8;
}


// --- Cart Logic: Fetch items from `orders` table ---
// 1. Determine user identifier
$session_username = isset($_SESSION['username']) ? trim($_SESSION['username']) : null;
$session_id = session_id();

// 2. Connect to Database
$conn = mysqli_connect($servername, $username, $password, $databaseName);

if (!$conn) {
    error_log("Database Connection failed in cart.php: " . mysqli_connect_error());
    $error_message = "Lỗi kết nối cơ sở dữ liệu. Không thể tải giỏ hàng.";
} else {
    mysqli_set_charset($conn, "utf8mb4"); // Set charset for connection
    try {
        // 3. Prepare SQL Query
        // Fetch items with status 'cart' for the current user/session
        $sql = "SELECT
                    o.id as order_item_id, o.food_id, o.quantity, o.price_at_add,
                    o.food_name, fd.image as food_image
                FROM `{$orderTableName}` o
                LEFT JOIN `{$foodTableName}` fd ON o.food_id = fd.id
                WHERE o.status = 'cart'";

        // 4. Add user/session filtering based on login status
        $params = [];
        $types = "";
        if ($session_username !== null) {
            $sql .= " AND o.username = ?";
            $params[] = $session_username;
            $types .= "s";
        } else {
            // If not logged in, use session ID and ensure username is NULL
            $sql .= " AND o.session_id = ? AND o.username IS NULL";
            $params[] = $session_id;
            $types .= "s";
        }
        $sql .= " ORDER BY o.added_at DESC"; // Show newest items first

        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            // Bind parameters if any exist
            if (!empty($types)) {
                mysqli_stmt_bind_param($stmt, $types, ...$params);
            }

            // 5. Execute and Fetch Results
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                while ($row = mysqli_fetch_assoc($result)) {
                    // Use fetched food_name, provide default if somehow missing
                    $row['food_name'] = $row['food_name'] ?? 'Món ăn không xác định';
                    $cart_display_items[] = $row;
                    // Calculate subtotal based on price *at the time of adding*
                    $subtotal_price += (float)$row['price_at_add'] * (int)$row['quantity'];
                }
            } else {
                // Throw exception on execution error
                throw new Exception("Lỗi thực thi câu lệnh lấy giỏ hàng: " . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt); // Close statement
        } else {
            // Throw exception on preparation error
            throw new Exception("Lỗi chuẩn bị câu lệnh lấy giỏ hàng: " . mysqli_error($conn));
        }

        // 6. Calculate Shipping and Grand Total
        if ($subtotal_price > 0) {
            $shipping_cost = SHIPPING_FEE; // Apply shipping fee if cart is not empty
        }
        $grand_total_price = $subtotal_price + $shipping_cost;

    } catch (Exception $e) {
        // Log error and set user-facing message
        error_log("Cart Item Fetch/Calculation Error in cart.php: " . $e->getMessage());
        $error_message = "Đã xảy ra lỗi khi tải chi tiết giỏ hàng. Vui lòng thử lại.";
        // Reset totals and items on error
        $cart_display_items = [];
        $subtotal_price = 0.00;
        $shipping_cost = 0.00;
        $grand_total_price = 0.00;
    } finally {
        // Ensure database connection is closed
        if ($conn) {
            mysqli_close($conn);
        }
    }
} // End DB connection block
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giỏ hàng</title>
    <!-- Link to your main CSS file -->
    <link rel="stylesheet" href="css/style.css"> <!-- Adjust path if needed -->
    <link rel="shortcut icon" href="image/foodnow_icon.png" sizes="32x32" type="image/x-icon">

    <!-- Inline styles are included below for completeness, but external CSS is recommended -->
    <style>
        /* --- General Styles --- */
        img {
            max-width: 100%;
            height: auto;
            display: block;
        }

        main.cart-container {
            max-width: 1140px;
            margin: 10rem auto 2rem auto; /* Adjust top margin based on your header height */
            padding: 0 1rem;
        }

        .cart-page.card {
            background-color: #343a40; /* Dark background for the card */
            border: 1px solid #495057; /* Slightly lighter border */
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            margin-bottom: 2rem;
            color: #e9ecef; /* Light text color */
        }

        .cart-title {
            text-align: center;
            font-size: 2em;
            color: #f8f9fa; /* White title */
            margin-bottom: 2rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #495057;
        }

        /* --- Alerts --- */
        .alert {
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid transparent;
            border-radius: 0.25rem;
            color: #fff; /* White text for alerts */
        }

        .alert-success {
            background-color: #198754; /* Bootstrap success green */
            border-color: #198754;
        }

        .alert-danger {
            background-color: #dc3545; /* Bootstrap danger red */
            border-color: #dc3545;
        }

        /* --- Empty Cart --- */
        .cart-empty {
            text-align: center;
            padding: 3rem 1rem;
            color: #adb5bd; /* Lighter gray for empty cart text */
        }

        .cart-empty p {
            font-size: 1.1em;
            margin-bottom: 1.5rem;
        }

        /* --- Cart Table --- */
        .cart-items-list {
            overflow-x: auto; /* Allow horizontal scrolling on small screens */
        }

        .cart-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
            color: #dee2e6; /* Default text color for table content */
        }

        .cart-table thead th {
            text-align: left;
            padding: 0.75rem 1rem;
            border-bottom: 2px solid #495057;
            color: #ced4da; /* Lighter color for headers */
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9em;
            white-space: nowrap;
        }

        .cart-table thead th:nth-child(3), /* Price */
        .cart-table thead th:nth-child(4), /* Quantity */
        .cart-table thead th:nth-child(5), /* Subtotal */
        .cart-table thead th:nth-child(6) { /* Remove */
            text-align: center;
        }

        .cart-table tbody tr {
            border-bottom: 1px solid #495057;
            transition: background-color 0.2s ease;
        }
        .cart-table tbody tr:last-child {
            border-bottom: none;
        }
        .cart-table tbody tr:hover {
            background-color: #495057; /* Highlight row on hover */
        }

        .cart-table tbody td {
            padding: 1rem;
            vertical-align: middle;
        }

        .cart-item-image {
            width: 100px; /* Fixed width for image column */
        }

        .cart-item-image img {
            width: 80px;
            height: 80px;
            object-fit: cover; /* Crop image nicely */
            border-radius: 4px;
            border: 1px solid #495057;
        }

        .cart-item-name {
           /* Allow name to take available space */
        }
        .cart-item-name a {
            font-weight: 500;
            color: #e9ecef;
            text-decoration: none;
            transition: color 0.2s;
        }
        .cart-item-name a:hover {
            color: #fff;
        }

        .cart-item-price,
        .cart-item-subtotal {
            font-weight: 500;
            white-space: nowrap; /* Prevent price/subtotal wrapping */
            width: 10em; /* Give enough width */
        }
         .cart-item-price { text-align: right; padding-right: 1em;} /* Align price right */
         .cart-item-subtotal { text-align: right; padding-right: 1em;} /* Align subtotal right */


        .cart-item-quantity {
            text-align: center;
            width: 100px; /* Fixed width for quantity input area */
        }

        .quantity-input {
            width: 60px;
            padding: 0.5rem;
            text-align: center;
            border: 1px solid #6c757d;
            background-color: #495057; /* Darker input background */
            color: #f8f9fa; /* Light text in input */
            border-radius: 4px;
            -moz-appearance: textfield; /* Hides spinners in Firefox */
        }
        /* Hide spinners in WebKit browsers (Chrome, Safari) */
        .quantity-input::-webkit-inner-spin-button,
        .quantity-input::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .cart-item-remove {
            text-align: center;
            width: 60px; /* Fixed width for remove button */
        }

        .remove-form { /* Ensure form doesn't add extra space */
            display: inline-block;
            margin: 0;
            padding: 0;
        }

        .remove-button {
            background: none;
            border: none;
            color: #ff6b6b; /* Light red for remove icon */
            font-size: 1.6em;
            font-weight: bold;
            cursor: pointer;
            padding: 0 0.5rem;
            line-height: 1;
            transition: color 0.2s ease;
        }
        .remove-button:hover {
            color: #e03131; /* Darker red on hover */
        }

        /* --- Cart Summary & Actions --- */
        .cart-summary {
            display: flex;
            justify-content: space-between;
            align-items: flex-start; /* Align items to the top */
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
            gap: 1rem;
            margin-top: 1rem;
            padding-top: 1.5rem;
            border-top: 1px solid #495057;
        }

        .cart-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            flex-basis: 50%; /* Roughly half width */
            justify-content: flex-start;
        }
        .clear-cart-form { /* Ensure form doesn't add extra space */
            display: inline-block;
            margin: 0;
        }

        .cart-totals-section {
            text-align: right;
            flex-basis: 45%; /* Slightly less than half */
            min-width: 250px; /* Minimum width before wrapping */
        }

        .cart-total {
            display: flex;
            justify-content: space-between; /* Label left, value right */
            font-size: 1.2em; /* Slightly larger total */
        }

        .cart-total-label {
            font-weight: 500;
            color: #adb5bd; /* Lighter label color */
            margin-right: 1rem;
        }

        .cart-total-value {
            font-weight: bold;
            color: #f8f9fa; /* White total value */
            font-size: 1.3em; /* Emphasize total */
        }

        .cart-checkout-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #495057;
        }

        /* --- Buttons (Adopted from Bootstrap-like dark theme) --- */
        .btn {
            display: inline-block;
            font-weight: 500;
            line-height: 1.5;
            text-align: center;
            vertical-align: middle;
            cursor: pointer;
            user-select: none;
            background-color: transparent;
            border: 1px solid transparent;
            padding: 0.5rem 1.25rem;
            font-size: 1rem;
            border-radius: 0.25rem;
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            text-decoration: none;
        }

        .btn-primary { /* Blue */
            color: #fff;
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .btn-primary:hover { background-color: #0b5ed7; border-color: #0a58ca; }

        .btn-secondary { /* Gray */
            color: #fff;
            background-color: #6c757d;
            border-color: #6c757d;
        }
        .btn-secondary:hover { background-color: #5c636a; border-color: #565e64; }

        .btn-success { /* Green */
            color: #fff;
            background-color: #198754;
            border-color: #198754;
        }
        .btn-success:hover { background-color: #157347; border-color: #146c43; }

        .btn-danger { /* Red */
            color: #fff;
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .btn-danger:hover { background-color: #bb2d3b; border-color: #b02a37; }

        .btn-outline-secondary { /* Outline gray */
            color: #adb5bd;
            border-color: #6c757d;
        }
        .btn-outline-secondary:hover { color: #fff; background-color: #6c757d; border-color: #6c757d; }

        .btn-checkout {
            padding: 0.75rem 1.5rem;
            font-size: 1.1em;
        }

        .btn-block { /* Button takes full width */
            display: block;
            width: 100%;
            margin-top: 0.5rem;
        }

        /* --- Checkout Sidebar Styles --- */
        #checkout-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6); /* Semi-transparent black */
            z-index: 1040; /* Below sidebar */
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
        }
        #checkout-overlay.active {
            display: block;
            opacity: 1;
        }

        #checkout-sidebar {
            position: fixed;
            top: 0;
            right: -450px; /* Start off-screen */
            width: 400px; /* Sidebar width */
            max-width: 90vw; /* Max width on small screens */
            height: 100vh; /* Full height */
            background-color: #2c3034; /* Darker sidebar background */
            color: #e9ecef; /* Light text */
            padding: 25px;
            box-shadow: -5px 0 15px rgba(0, 0, 0, 0.5); /* Shadow on the left */
            z-index: 1050; /* Above overlay */
            transition: right 0.4s ease-in-out; /* Smooth slide animation */
            overflow-y: auto; /* Allow scrolling within sidebar */
            display: flex;
            flex-direction: column; /* Stack sections vertically */
        }
        #checkout-sidebar.active {
            right: 0; /* Slide in */
        }

        #checkout-sidebar h2 {
            margin-top: 0;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #495057;
            color: #fff;
            text-align: center;
            font-size: 1.5em;
        }

        #close-checkout-sidebar-btn {
            position: absolute;
            top: 10px;
            right: 15px;
            background: none;
            border: none;
            font-size: 2rem;
            color: #adb5bd;
            cursor: pointer;
            line-height: 1;
            padding: 0;
        }
        #close-checkout-sidebar-btn:hover { color: #fff; }

        .sidebar-section {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #495057;
        }
        .sidebar-section:last-of-type {
            border-bottom: none; /* No border on the last section */
            margin-bottom: 0;
            /* Removed flex-grow: 1; let actions push down naturally */
        }

        .sidebar-section h3 {
            margin-top: 0;
            margin-bottom: 1rem;
            color: #ced4da;
            font-size: 1.1em;
        }

        /* Sidebar Cart Items */
        #sidebar-cart-items {
            max-height: 30vh; /* Limit height */
            overflow-y: auto; /* Scroll if needed */
            margin-bottom: 1rem;
            padding-right: 5px; /* Space for scrollbar */
        }
        #sidebar-cart-items::-webkit-scrollbar { width: 5px; }
        #sidebar-cart-items::-webkit-scrollbar-track { background: #495057; }
        #sidebar-cart-items::-webkit-scrollbar-thumb { background: #6c757d; border-radius: 3px;}

        #sidebar-cart-items .sidebar-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.8rem;
            font-size: 0.95em;
            padding-bottom: 0.5rem;
            border-bottom: 1px dashed #495057;
        }
        #sidebar-cart-items .sidebar-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        #sidebar-cart-items .item-info { flex-grow: 1; margin-right: 10px; }
        #sidebar-cart-items .item-name { display: block; margin-bottom: 2px; font-weight: 500; }
        #sidebar-cart-items .item-qty-price { font-size: 0.9em; color: #adb5bd; }
        #sidebar-cart-items .item-subtotal { font-weight: 500; white-space: nowrap; }

        /* Sidebar Summary Details */
        #sidebar-summary-details {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #495057;
        }

        .sidebar-shipping, /* Shipping row */
        .sidebar-total {   /* Total row */
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 1.05em;
        }

        .sidebar-shipping strong,
        .sidebar-total strong {
            color: #ced4da; /* Slightly lighter labels */
        }

        .sidebar-shipping span,
        .sidebar-total span {
            font-weight: 500;
            color: #f8f9fa; /* White values */
        }

        .sidebar-total { /* Style total row differently */
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid #495057;
            font-size: 1.2em;
            font-weight: bold;
        }
        .sidebar-total strong { color: #fff; } /* White bold label */
        .sidebar-total .total-amount { color: #fff; } /* White bold amount */


        /* Payment Options */
        .payment-options label {
            display: block;
            margin-bottom: 0.75rem;
            cursor: pointer;
            padding: 10px;
            border: 1px solid #495057;
            border-radius: 4px;
            transition: background-color 0.2s, border-color 0.2s;
        }
        .payment-options label:hover { background-color: #3a3f44; }
        .payment-options input[type="radio"] {
            margin-right: 10px;
            accent-color: #198754; /* Green accent for radio */
        }

        /* QR Code Area */
         #qr-code-area {
            /* Styles already set inline, but can add more here */
             margin-top: 15px;
             text-align: center;
         }
        #qr-image-container {
            min-height: 200px; /* Ensure space while loading */
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa; /* White background for QR */
            border: 1px solid #6c757d;
            border-radius: 4px;
            padding: 10px; /* Padding around QR */
            margin-bottom: 10px;
            position: relative; /* For potential overlays or loading indicators */
        }
        #vietqr-image {
            /* max-width: 100%; /* Ensure QR fits */
            max-width: 100%; /* Limit QR image size */
            height: auto;
            display: none; /* Hidden initially */
             border: 1px solid #dee2e6; /* Light border inside white area */
        }
        #qr-loading, #qr-error {
            color: #343a40; /* Dark text for loading/error */
            position: absolute; /* Center inside container */
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: none; /* Hidden initially */
        }
         #qr-error { color: #dc3545; font-weight: bold; } /* Red error text */

        #qr-purpose { font-size: 0.9em; color: #adb5bd; margin-bottom: 5px; word-break: break-all; } /* Break long purpose text */
        #qr-code-area p small { color: #adb5bd; }

        /* --- Styles for Payment Status Indicator --- */
        #payment-status-indicator {
            padding: 8px 12px;
            border-radius: 4px;
            margin-top: 15px; /* Ensure spacing */
            font-size: 0.95em;
            border: 1px solid #495057; /* Subtle border */
            background-color: #3a3f44; /* Slightly different background */
            display: none; /* Hidden by default, shown via JS */
            /* display: inline-block; Don't take full width */
            text-align: center;
        }

        #payment-status-indicator strong {
            color: #ced4da; /* Match other labels */
            margin-right: 5px;
        }

        #payment-status-indicator .status-text {
            font-weight: bold;
            padding: 3px 6px;
            border-radius: 3px;
            color: #fff; /* Default text color */
             display: inline-block; /* Needed for padding/border */
        }

        #payment-status-indicator .status-pending {
            color: #ffc107; /* Yellow/Amber */
            /* background-color: rgba(255, 193, 7, 0.15); Optional background tint */
            /* border: 1px solid #ffc107; Optional border */
        }

        /* Add classes for other potential statuses if you implement backend checks */
        #payment-status-indicator .status-paid {
            color: #198754; /* Green */
           /* background-color: rgba(25, 135, 84, 0.15); */
           /* border: 1px solid #198754; */
        }

         #payment-status-indicator .status-error {
            color: #dc3545; /* Red */
           /* background-color: rgba(220, 53, 69, 0.15); */
           /* border: 1px solid #dc3545; */
        }

        /* Shipping Information Form */
        #checkout-sidebar label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #ced4da;
        }

        #checkout-sidebar input[type="text"],
        #checkout-sidebar input[type="tel"],
        #checkout-sidebar textarea {
            width: 100%;
            padding: 0.6rem 0.8rem;
            margin-bottom: 1rem;
            background-color: #495057; /* Input background */
            border: 1px solid #6c757d;
            color: #f8f9fa; /* Input text color */
            border-radius: 4px;
            box-sizing: border-box; /* Include padding/border in width */
            font-family: inherit; /* Use main font */
            font-size: 1rem;
        }
        #checkout-sidebar textarea { resize: vertical; } /* Allow vertical resize */

        #checkout-sidebar input:focus,
        #checkout-sidebar textarea:focus {
            outline: none;
            border-color: #198754; /* Highlight focus with green */
            box-shadow: 0 0 0 2px rgba(25, 135, 84, 0.25); /* Subtle glow */
        }

        /* Sidebar Actions */
        .sidebar-actions {
            margin-top: auto; /* Push actions to the bottom */
            padding-top: 1.5rem;
            border-top: 1px solid #495057;
        }


        /* --- Responsive Adjustments --- */
        @media (max-width: 768px) {
            .cart-summary {
                flex-direction: column; /* Stack summary items */
                align-items: stretch; /* Make items full width */
            }
            .cart-actions, .cart-totals-section {
                flex-basis: 100%; /* Full width on smaller screens */
                text-align: left; /* Align totals left */
            }
             .cart-totals-section { text-align: right; } /* Keep totals right aligned */
            .cart-checkout-actions {
                flex-direction: column;
                align-items: stretch;
            }
             .cart-checkout-actions .btn { width: 100%; margin-bottom: 0.5rem;}
             .cart-checkout-actions .btn:last-child { margin-bottom: 0; }
             .cart-item-price, .cart-item-subtotal { width: auto; text-align: center; } /* Adjust text alignment */
             .cart-table thead th:nth-child(3), .cart-table thead th:nth-child(5) { text-align: center; } /* Center price/subtotal header */

        }

        @media (max-width: 576px) {
            .cart-page.card { padding: 1rem; }
            .cart-title { font-size: 1.6em; }
            .cart-item-image { width: 80px; }
            .cart-item-image img { width: 60px; height: 60px; }
            .cart-table tbody td { padding: 0.75rem 0.5rem; }
            .cart-item-name { font-size: 0.9em; }
            .quantity-input { width: 50px; padding: 0.4rem; }
            .remove-button { font-size: 1.4em; }
            .cart-total { font-size: 1.1em; }
            .cart-total-value { font-size: 1.2em; }
            .btn { font-size: 0.9rem; padding: 0.4rem 1rem; }
            .btn-checkout { font-size: 1rem; padding: 0.6rem 1.2rem; }
            #checkout-sidebar { width: 95vw; padding: 15px; } /* Almost full width */
             #checkout-sidebar h2 { font-size: 1.3em; }
             #checkout-sidebar h3 { font-size: 1em; }
        }

    </style>
</head>

<body>
    <?php include 'parts/navbar.php'; ?>

    <main class="cart-container">
        <section class="cart-page card">
            <h1 class="cart-title">Giỏ hàng của bạn</h1>

            <!-- Display Feedback Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <?php if (empty($cart_display_items)): ?>
                <!-- Display Empty Cart Message -->
                <div class="cart-empty">
                    <p>Giỏ hàng của bạn đang trống.</p>
                    <a href="food.php" class="btn btn-primary">Tiếp tục mua sắm</a>
                </div>
            <?php else: ?>
                <!-- Display Cart Items Table -->
                <!-- NOTE: The form for updating quantities is removed as quantity updates might be better handled via AJAX or explicit button -->
                <!-- If you need bulk update, add <form id="cart-form" action="cart_action.php" method="post"> here -->
                <div class="cart-items-list">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th colspan="2">Sản phẩm</th>
                                <th style="text-align: right; padding-right: 1em;">Giá</th>
                                <th>Số lượng</th>
                                <th style="text-align: right; padding-right: 1em;">Tạm tính</th>
                                <th>Xóa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart_display_items as $item): ?>
                                <?php
                                // Get image path using the helper function
                                $imageSrc = get_image_path($item['food_image'] ?? null, $uploadDir, $webUploadDir, $placeholderPath);
                                $item_price = (float) $item['price_at_add']; // Use price at time of adding
                                $item_quantity = (int) $item['quantity'];
                                $item_subtotal = $item_price * $item_quantity;
                                $food_id = $item['food_id']; // Needed for product link
                                $order_item_id = $item['order_item_id']; // Needed for actions (remove, update)
                                $item_name = htmlspecialchars($item['food_name']); // Use name from orders table
                                ?>
                                <tr data-order-item-id="<?php echo $order_item_id; ?>" data-food-id="<?php echo $food_id; ?>">
                                    <td class="cart-item-image">
                                        <a href="food_detail.php?id=<?php echo $food_id; ?>">
                                            <img src="<?php echo $imageSrc; ?>" alt="<?php echo $item_name; ?>">
                                        </a>
                                    </td>
                                    <td class="cart-item-name">
                                        <a href="food_detail.php?id=<?php echo $food_id; ?>">
                                            <?php echo $item_name; ?>
                                        </a>
                                    </td>
                                    <td class="cart-item-price">
                                        <?php echo number_format($item_price, 0, ',', '.'); ?> VNĐ
                                    </td>
                                    <td class="cart-item-quantity">
                                        <!-- Direct update via input change (handled by JS) or could link to cart_action.php -->
                                        <input type="number" name="quantity[<?php echo $order_item_id; ?>]"
                                            value="<?php echo $item_quantity; ?>"
                                            min="1" required class="quantity-input"
                                            data-price="<?php echo $item_price; ?>"
                                            aria-label="Số lượng cho <?php echo $item_name; ?>">
                                        <!-- Optional: Add update button per item if not using JS auto-update -->
                                        <!-- <button type="submit" name="action" value="update_item" form="update-item-form-<?php echo $order_item_id; ?>">Update</button> -->
                                    </td>
                                    <td class="cart-item-subtotal">
                                        <?php echo number_format($item_subtotal, 0, ',', '.'); ?> VNĐ
                                    </td>
                                    <td class="cart-item-remove">
                                        <!-- Form to remove a single item -->
                                        <form action="cart_action.php" method="post" class="remove-form">
                                            <input type="hidden" name="action" value="remove_item">
                                            <input type="hidden" name="order_item_id" value="<?php echo $order_item_id; ?>">
                                            <button type="submit" class="remove-button" title="Xóa món này">×</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div> <!-- /.cart-items-list -->

                <!-- Cart Summary -->
                <div class="cart-summary">
                    <div class="cart-actions">
                         <!-- Form to clear the entire cart -->
                         <form action="cart_action.php" method="post" class="clear-cart-form">
                             <input type="hidden" name="action" value="clear_cart">
                             <button type="submit" class="btn btn-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa toàn bộ giỏ hàng?');">Xóa hết giỏ hàng</button>
                         </form>
                         <!-- Optional: Add update all button if needed -->
                         <!-- <button type="submit" form="cart-form" name="action" value="update_quantities" class="btn btn-primary">Cập nhật giỏ hàng</button> -->
                    </div>
                    <div class="cart-totals-section">
                        <div class="cart-total">
                            <span class="cart-total-label">Tổng cộng:</span>
                            <span class="cart-total-value final-total-value">
                                <?php echo number_format($subtotal_price, 0, ',', '.'); ?> VNĐ
                            </span>
                        </div>
                         <!-- Note: Shipping and Grand Total are shown in the checkout sidebar -->
                    </div>
                </div><!-- /.cart-summary -->

                <!-- Checkout Actions -->
                <div class="cart-checkout-actions">
                    <a href="food.php" class="btn btn-outline-secondary">Tiếp tục mua sắm</a>
                    <button type="button" id="open-checkout-sidebar-btn" class="btn btn-success btn-checkout">Tiến hành thanh toán</button>
                </div><!-- /.cart-checkout-actions -->

            <?php endif; ?>
        </section><!-- /.cart-page -->
    </main><!-- /.cart-container -->

    <!-- Checkout Sidebar Structure -->
    <div id="checkout-overlay"></div>
    <div id="checkout-sidebar">
        <button id="close-checkout-sidebar-btn" class="close-btn" aria-label="Đóng">×</button>
        <h2>Xác nhận Thanh toán</h2>

        <form id="checkout-form" action="process_checkout.php" method="POST">
            <!-- Order Details Section -->
            <div class="sidebar-section">
                <h3>Chi tiết đơn hàng</h3>
                <div id="sidebar-cart-items">
                    <!-- Items will be populated by JavaScript -->
                    <p>Đang tải chi tiết...</p>
                </div>
                <div id="sidebar-summary-details">
                    <!-- Shipping Fee (shown conditionally) -->
                    <div id="sidebar-shipping-fee" class="sidebar-shipping" style="display: none;">
                        <strong>Phí vận chuyển:</strong>
                        <span class="shipping-amount"><?php echo number_format(SHIPPING_FEE, 0, ',', '.'); ?> VNĐ</span>
                    </div>
                    <!-- Grand Total (shown conditionally) -->
                    <div id="sidebar-cart-total" class="sidebar-total" style="display: none;">
                        <strong>Tổng cộng thanh toán:</strong>
                        <span class="total-amount">0 VNĐ</span>
                    </div>
                </div>
            </div>

            <!-- Payment Options Section -->
            <div class="sidebar-section">
                <h3>Phương thức thanh toán</h3>
                <div class="payment-options">
                    <label><input type="radio" name="payment_method" value="cod" checked required> Thanh toán khi nhận hàng (COD)</label>
                    <label><input type="radio" name="payment_method" value="online" required> Thanh toán Online (Chuyển khoản/QR)</label>
                </div>
                <!-- QR Code Area (shown conditionally) -->
                <div id="qr-code-area" style="display: none;">
                    <p>Quét mã QR bằng ứng dụng ngân hàng của bạn để thanh toán <strong id="qr-amount">0 VNĐ</strong>:</p>
                    <div id="qr-image-container">
                        <img id="vietqr-image" src="" alt="VietQR Code">
                        <p id="qr-loading" style="display: none;">Đang tạo mã QR...</p>
                        <p id="qr-error" style="display: none;">Lỗi tạo QR</p>
                    </div>
                    <p id="qr-purpose" style="font-size: 0.9em; color: #adb5bd; margin-bottom: 5px;"></p>
                    <p><small>(Sau khi chuyển khoản thành công, vui lòng nhấn "Xác nhận đặt hàng")</small></p>

                    <!-- Payment Status Indicator -->
                    <div id="payment-status-indicator" style="margin-top: 15px;">
                        <strong>Trạng thái:</strong>
                        <span class="status-text status-pending">Chờ thanh toán</span>
                    </div>
                </div>
            </div>

            <!-- Shipping Information Section -->
            <div class="sidebar-section">
                <h3>Thông tin giao hàng</h3>
                <label for="customer_name">Họ tên:</label>
                <input type="text" id="customer_name" name="customer_name" required placeholder="Nhập họ tên của bạn" value="<?php echo isset($_SESSION['user_fullname']) ? htmlspecialchars($_SESSION['user_fullname']) : ''; ?>">

                <label for="customer_phone">Số điện thoại:</label>
                <input type="tel" id="customer_phone" placeholder="Nhập số điện thoại của bạn" name="customer_phone" required pattern="[0-9]{10,11}" title="Nhập số điện thoại hợp lệ (10-11 chữ số)" value="<?php echo isset($_SESSION['user_phone']) ? htmlspecialchars($_SESSION['user_phone']) : ''; ?>">

                <label for="customer_address">Địa chỉ:</label>
                <textarea id="customer_address" name="customer_address" placeholder="Nhập địa chỉ nhận hàng chi tiết (số nhà, đường, phường/xã, quận/huyện, tỉnh/thành phố)" rows="4" required><?php echo isset($_SESSION['user_address']) ? htmlspecialchars($_SESSION['user_address']) : ''; ?></textarea>
            </div>

            <!-- Sidebar Actions -->
            <div class="sidebar-actions">
                <button type="submit" class="btn btn-success btn-block">Xác nhận đặt hàng</button>
                <button type="button" id="cancel-checkout-btn" class="btn btn-secondary btn-block">Hủy</button>
            </div>
        </form>
    </div> <!-- /#checkout-sidebar -->

    <footer>
        <?php include 'parts/footer.php'; ?>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // --- DOM Element Selection ---
            const quantityInputs = document.querySelectorAll('.quantity-input');
            const mainSubtotalValueElement = document.querySelector('.cart-total .final-total-value'); // Renamed for clarity
            const cartTableBody = document.querySelector('.cart-table tbody');

            // Sidebar Elements
            const openCheckoutBtn = document.getElementById('open-checkout-sidebar-btn');
            const closeCheckoutBtn = document.getElementById('close-checkout-sidebar-btn');
            const cancelCheckoutBtn = document.getElementById('cancel-checkout-btn');
            const checkoutSidebar = document.getElementById('checkout-sidebar');
            const checkoutOverlay = document.getElementById('checkout-overlay');
            const sidebarItemsContainer = document.getElementById('sidebar-cart-items');
            const sidebarShippingRow = document.getElementById('sidebar-shipping-fee');
            const sidebarTotalRow = document.getElementById('sidebar-cart-total');
            const sidebarTotalAmount = sidebarTotalRow ? sidebarTotalRow.querySelector('.total-amount') : null;

            // Payment Elements
            const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
            const qrCodeArea = document.getElementById('qr-code-area');
            const qrImageContainer = document.getElementById('qr-image-container');
            const vietQrImage = document.getElementById('vietqr-image');
            const qrLoading = document.getElementById('qr-loading');
            const qrError = document.getElementById('qr-error');
            const qrAmount = document.getElementById('qr-amount');
            const qrPurpose = document.getElementById('qr-purpose');
            const paymentStatusIndicator = document.getElementById('payment-status-indicator');
            const statusTextElement = paymentStatusIndicator ? paymentStatusIndicator.querySelector('.status-text') : null;

            // PHP Variables to JS
            const shippingFee = <?php echo SHIPPING_FEE; ?>;
            const phpBankBin = '<?php echo htmlspecialchars($bank_bin, ENT_QUOTES, 'UTF-8'); ?>';
            const phpBankNumber = '<?php echo htmlspecialchars($bank_number, ENT_QUOTES, 'UTF-8'); ?>';
            const phpAccountName = '<?php echo htmlspecialchars($account_name ?: "", ENT_QUOTES, 'UTF-8'); ?>'; // Default to empty string if null


            // --- Helper Functions ---
            function formatCurrency(value) {
                if (isNaN(value)) return '0 VNĐ';
                // Format for Vietnamese Dong
                return Number(value).toLocaleString('vi-VN', { style: 'currency', currency: 'VND', minimumFractionDigits: 0, maximumFractionDigits: 0 }).replace(/\s/g, ''); // Remove space before currency symbol if any
                 // Fallback if Intl not fully supported or locale is different
                 // return Number(value).toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ".") + ' VNĐ';
            }

             function escapeHtml(unsafe) {
                 if (typeof unsafe !== 'string') return '';
                 return unsafe
                      .replace(/&/g, "&") // Basic escaping
                      .replace(/</g, "<")
                      .replace(/>/g, ">")
                      .replace(/"/g, "")
                      .replace(/'/g, "'");
             }

            // --- Calculate Cart Subtotal from Table ---
            function calculateCartSubtotal() {
                let currentSubtotal = 0;
                if (!cartTableBody) return 0;

                const rows = cartTableBody.querySelectorAll('tr');
                rows.forEach(row => {
                    const input = row.querySelector('.quantity-input');
                    const price = parseFloat(input?.dataset.price);
                    const quantity = parseInt(input?.value, 10);

                    // Validate input before adding to subtotal
                    if (!isNaN(price) && price >= 0 && !isNaN(quantity) && quantity >= 1) {
                        currentSubtotal += price * quantity;
                    } else {
                         console.warn("Invalid price or quantity detected in row:", row);
                         // Optionally update UI to show an error for this row
                    }
                });
                return currentSubtotal;
            }

            // --- Update Main Cart and Sidebar Totals ---
            function updateAllTotals() {
                const currentSubtotal = calculateCartSubtotal();
                const currentShipping = (currentSubtotal > 0) ? shippingFee : 0;
                const currentGrandTotal = currentSubtotal + currentShipping;
                let showDetails = currentSubtotal > 0; // Show details only if there's a subtotal

                // Update Main Cart Subtotal Display
                if (mainSubtotalValueElement) {
                    mainSubtotalValueElement.textContent = formatCurrency(currentSubtotal);
                }

                // Update Sidebar Totals Display
                if (sidebarShippingRow) {
                    sidebarShippingRow.style.display = (showDetails && currentShipping > 0) ? 'flex' : 'none';
                    // Update shipping amount just in case (though it's constant)
                    const shippingAmountEl = sidebarShippingRow.querySelector('.shipping-amount');
                    if (shippingAmountEl) shippingAmountEl.textContent = formatCurrency(currentShipping);
                }
                if (sidebarTotalRow && sidebarTotalAmount) {
                    if (showDetails) {
                        sidebarTotalAmount.textContent = formatCurrency(currentGrandTotal);
                        sidebarTotalRow.style.display = 'flex';
                    } else {
                        sidebarTotalRow.style.display = 'none'; // Hide total if cart is empty
                    }
                }

                // Return grand total for QR generation if needed
                return currentGrandTotal;
            }


            // --- Quantity Input Listener ---
            quantityInputs.forEach(input => {
                input.addEventListener('input', (event) => {
                    const currentInput = event.target;
                    const price = parseFloat(currentInput.dataset.price);
                    let quantity = parseInt(currentInput.value, 10);
                    const row = currentInput.closest('tr');
                    const subtotalCell = row ? row.querySelector('.cart-item-subtotal') : null;

                    if (isNaN(price) || price < 0) {
                        if (subtotalCell) subtotalCell.textContent = 'Lỗi giá';
                         console.error("Invalid price on input:", price);
                        updateAllTotals(); // Recalculate totals even on error
                        return;
                    }

                    // Ensure quantity is at least 1
                    if (isNaN(quantity) || quantity < 1) {
                        quantity = 1;
                        currentInput.value = quantity; // Correct the input value
                    }

                    // Update row subtotal
                    if (subtotalCell) {
                        subtotalCell.textContent = formatCurrency(price * quantity);
                    }

                    // Update all cart totals (main and sidebar)
                    updateAllTotals();

                    // If sidebar is open, update its items too (optional, but good UX)
                    if (checkoutSidebar && checkoutSidebar.classList.contains('active')) {
                         populateSidebarItems(); // Repopulate to reflect new quantity/subtotal
                    }
                });
            });

            // --- Sidebar Logic ---
            function populateSidebarItems() {
                if (!cartTableBody || !sidebarItemsContainer) {
                    if (sidebarItemsContainer) sidebarItemsContainer.innerHTML = '<p>Lỗi tải giỏ hàng.</p>';
                    // Ensure totals are hidden if there's an error here
                    if (sidebarShippingRow) sidebarShippingRow.style.display = 'none';
                    if (sidebarTotalRow) sidebarTotalRow.style.display = 'none';
                    return;
                }
                sidebarItemsContainer.innerHTML = ''; // Clear existing items
                const rows = cartTableBody.querySelectorAll('tr');

                if (rows.length === 0) {
                    sidebarItemsContainer.innerHTML = '<p>Giỏ hàng trống.</p>';
                } else {
                    rows.forEach(row => {
                        const nameElement = row.querySelector('.cart-item-name a');
                        const quantityInput = row.querySelector('.quantity-input');
                        const price = parseFloat(quantityInput?.dataset.price);
                        const quantity = parseInt(quantityInput?.value, 10);

                        if (nameElement && !isNaN(price) && price >= 0 && !isNaN(quantity) && quantity >= 1) {
                            const name = nameElement.textContent.trim();
                            const itemSubtotal = price * quantity;
                            const itemDiv = document.createElement('div');
                            itemDiv.classList.add('sidebar-item');
                            itemDiv.innerHTML = `
                                <div class="item-info">
                                    <span class="item-name">${escapeHtml(name)}</span>
                                    <span class="item-qty-price">${quantity} x ${formatCurrency(price)}</span>
                                </div>
                                <span class="item-subtotal">${formatCurrency(itemSubtotal)}</span>
                            `;
                            sidebarItemsContainer.appendChild(itemDiv);
                        }
                    });
                }
                // Update totals after populating sidebar items
                updateAllTotals();
            }

            function openSidebar() {
                 // First, calculate and update totals based on the main cart
                const currentGrandTotal = updateAllTotals();

                // If the cart is empty (grand total is 0 or less), don't open sidebar
                if (currentGrandTotal <= 0) {
                    alert("Giỏ hàng của bạn đang trống. Vui lòng thêm sản phẩm trước khi thanh toán.");
                    return;
                }

                populateSidebarItems(); // Populate sidebar content AFTER confirming cart isn't empty

                if (checkoutSidebar && checkoutOverlay) {
                    checkoutOverlay.classList.add('active');
                    checkoutSidebar.classList.add('active');
                    document.body.style.overflow = 'hidden'; // Prevent background scrolling

                    // Check if online payment is selected *and* sidebar is now open, then generate QR
                    const checkedOnline = document.querySelector('input[name="payment_method"][value="online"]:checked');
                    if (checkedOnline && qrCodeArea) {
                        generateAndDisplayQR(currentGrandTotal); // Pass the calculated total
                    } else if (qrCodeArea) {
                        qrCodeArea.style.display = 'none'; // Ensure QR area is hidden if COD is selected
                        if (paymentStatusIndicator) paymentStatusIndicator.style.display = 'none';
                    }
                }
            }


            function closeSidebar() {
                if (checkoutSidebar && checkoutOverlay) {
                    checkoutOverlay.classList.remove('active');
                    checkoutSidebar.classList.remove('active');
                    document.body.style.overflow = ''; // Restore background scrolling
                }
            }

            // --- Generate VietQR ---
            // Pass grandTotal directly to avoid recalculating or parsing from display element
            function generateAndDisplayQR(grandTotal) {
                 // Check crucial elements
                 if (!qrCodeArea || !vietQrImage || !qrLoading || !qrError || !qrAmount || !qrPurpose || !paymentStatusIndicator || !statusTextElement) {
                    console.error("QR Code or Status elements missing for generation.");
                    if (qrCodeArea) { // Try to show error in the QR area itself
                         qrCodeArea.innerHTML = '<p id="qr-error" style="color: red; display: block;">Lỗi: Thiếu thành phần QR hoặc Trạng thái.</p>';
                         qrCodeArea.style.display = 'block'; // Make sure area is visible to show error
                    }
                    return;
                 }

                // --- UI updates for loading state ---
                qrCodeArea.style.display = 'block'; // Show the whole QR section
                if(qrImageContainer) qrImageContainer.style.display = 'flex'; // Ensure container is visible
                qrLoading.style.display = 'block'; // Show loading text
                qrError.style.display = 'none';    // Hide any previous error
                vietQrImage.style.display = 'none'; // Hide the image itself while loading
                vietQrImage.src = '';              // Clear previous QR image source
                qrAmount.textContent = '...';      // Placeholder for amount
                qrPurpose.textContent = '';        // Clear purpose text
                paymentStatusIndicator.style.display = 'block'; // Show status indicator area
                statusTextElement.textContent = 'Chờ thanh toán'; // Reset status text
                statusTextElement.className = 'status-text status-pending'; // Reset status class

                // --- Validate Amount ---
                const amount = Math.round(grandTotal); // Use the passed grand total, ensure integer
                if (isNaN(amount) || amount <= 0) {
                    console.error("Invalid amount for QR:", amount);
                    qrLoading.style.display = 'none';
                    qrError.textContent = "Lỗi lấy tổng tiền.";
                    qrError.style.display = 'block';
                    vietQrImage.style.display = 'none'; // Keep image hidden
                    paymentStatusIndicator.style.display = 'none'; // Hide status on error
                    return;
                }

                // --- Validate Bank Details ---
                const bankBin = phpBankBin;
                const bankNumber = phpBankNumber;
                const accountName = phpAccountName;

                if (!bankBin || !bankNumber) {
                    console.error("Bank BIN or Account Number is missing.");
                    qrLoading.style.display = 'none';
                    qrError.textContent = "Lỗi cấu hình thanh toán.";
                    qrError.style.display = 'block';
                     vietQrImage.style.display = 'none';
                    paymentStatusIndicator.style.display = 'none';
                    return;
                }

                // --- Generate QR URL ---
                // Create a unique-ish identifier for the transaction purpose
                const orderIdentifier = `FN${Date.now().toString().slice(-6)}`; // Example: FN123456
                const orderPurpose = `TT ${orderIdentifier}`; // Short purpose "TT" = Thanh Toán
                const encodedPurpose = encodeURIComponent(orderPurpose);
                const encodedAccountName = encodeURIComponent(accountName);

                // Construct the VietQR API URL (using Print template for better scanning)
                const qrUrl = `https://img.vietqr.io/image/${bankBin}-${bankNumber}-print.png?amount=${amount}&addInfo=${encodedPurpose}&accountName=${encodedAccountName}`;
                console.log("Generating QR URL:", qrUrl); // Log for debugging

                // --- Set Image Source and Event Handlers ---
                vietQrImage.onload = () => {
                    console.log("QR Image loaded successfully.");
                    qrLoading.style.display = 'none';  // Hide loading indicator
                    qrError.style.display = 'none';    // Ensure error is hidden
                    vietQrImage.style.display = 'block';// Show the loaded QR image
                    qrAmount.textContent = formatCurrency(amount); // Display formatted amount
                    qrPurpose.textContent = `Nội dung CK: ${escapeHtml(orderPurpose)}`; // Display purpose
                    paymentStatusIndicator.style.display = 'block'; // Ensure status is visible
                     // Status text remains "Chờ thanh toán"
                };

                vietQrImage.onerror = () => {
                    console.error("Failed to load VietQR image from URL:", qrUrl);
                    qrLoading.style.display = 'none';
                    qrError.textContent = "Không thể tải mã QR. Vui lòng thử lại hoặc chọn COD.";
                    qrError.style.display = 'block';
                    vietQrImage.style.display = 'none'; // Keep image hidden on error
                    qrAmount.textContent = formatCurrency(amount); // Still show the amount
                    qrPurpose.textContent = ''; // Clear purpose on error
                    paymentStatusIndicator.style.display = 'none'; // Hide status on QR load error
                };

                // Set the src to trigger loading
                vietQrImage.src = qrUrl;
            }


            // --- Event Listeners ---
            if (openCheckoutBtn) {
                 openCheckoutBtn.addEventListener('click', openSidebar);
            } else {
                 console.error("Checkout button not found.");
            }

            if (closeCheckoutBtn) closeCheckoutBtn.addEventListener('click', closeSidebar);
            if (cancelCheckoutBtn) cancelCheckoutBtn.addEventListener('click', closeSidebar);
            if (checkoutOverlay) checkoutOverlay.addEventListener('click', closeSidebar);

            // Payment Radio Button Listener
            paymentRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (qrCodeArea) {
                        if (this.value === 'online' && this.checked) {
                            // Only generate QR if the sidebar is *already* active
                            if (checkoutSidebar && checkoutSidebar.classList.contains('active')) {
                                const currentGrandTotal = updateAllTotals(); // Recalculate total before generating
                                if (currentGrandTotal > 0) {
                                     generateAndDisplayQR(currentGrandTotal);
                                } else {
                                     // If somehow cart became empty while sidebar open
                                     qrCodeArea.style.display = 'none';
                                     if(paymentStatusIndicator) paymentStatusIndicator.style.display = 'none';
                                }
                            } else {
                                 // If sidebar isn't open yet, don't generate QR now,
                                 // openSidebar() will handle it when the button is clicked.
                                 // Just ensure the area is conceptually ready (though hidden)
                                 qrCodeArea.style.display = 'none'; // Keep hidden until sidebar opens
                                 if(paymentStatusIndicator) paymentStatusIndicator.style.display = 'none';
                            }
                        } else { // If switched to COD or other method
                            qrCodeArea.style.display = 'none';
                            if (paymentStatusIndicator) paymentStatusIndicator.style.display = 'none';
                            // Clear QR image to prevent showing old one if reopened
                            if (vietQrImage) vietQrImage.src = '';
                            if (qrLoading) qrLoading.style.display = 'none';
                            if (qrError) qrError.style.display = 'none';
                        }
                    }
                });
            });

             // Form Submission Validation (Basic)
             const checkoutForm = document.getElementById('checkout-form');
             if (checkoutForm) {
                 checkoutForm.addEventListener('submit', function(event) {
                     // Add any client-side validation needed before submitting
                     // Example: Check if address is long enough, phone format looks okay etc.
                     const phoneInput = document.getElementById('customer_phone');
                     if (phoneInput && !phoneInput.checkValidity()) {
                          alert('Vui lòng nhập số điện thoại hợp lệ (10-11 chữ số).');
                          event.preventDefault(); // Stop submission
                          phoneInput.focus();
                          return;
                     }
                     // Add more validation as needed
                 });
             }


            // --- Initial Setup on Page Load ---
            updateAllTotals(); // Calculate and display initial totals for the main cart page

            // Hide QR area and status indicator initially, regardless of default selection.
            // It will be shown only when the sidebar opens *and* 'online' is selected.
            if (qrCodeArea) qrCodeArea.style.display = 'none';
            if (paymentStatusIndicator) paymentStatusIndicator.style.display = 'none';


        }); // End DOMContentLoaded
    </script>

</body>
</html>