<?php
session_start(); // Access session cart data

// --- Database Configuration ---
$servername = "localhost";
$username = "root";
$password = "";
$databaseName = "foodnow"; // Make sure this is correct
$tableName = 'food_data'; // CHANGE THIS if necessary

// *** DEFINE SHIPPING FEE ***
define('SHIPPING_FEE', 15000.00); // Example: 15,000 VNĐ

// *** DEFINE CORRECT PATHS HERE ***
$uploadDir = 'uploads/';
$webUploadDir = 'uploads/';
$placeholderPath = 'image/placeholder-food.png';

define('VIETQR_BANK_BIN', 'techcombank'); // REPLACE
define('VIETQR_ACCOUNT_NO', '0931910JQK'); // REPLACE
define('VIETQR_ACCOUNT_NAME', 'NGUYEN VAN A'); // REPLACE (Optional)
$bank_bin = VIETQR_BANK_BIN;
$bank_number = VIETQR_ACCOUNT_NO;
$account_name = VIETQR_ACCOUNT_NAME; // Optional, but recommended
// --- Include Header ---
include 'parts/header.php'; // Assumes <head> section with basic CSS link

// --- Initialize variables ---
$cart_items = [];
$cart_item_details = [];
$subtotal_price = 0.00;  // Price of items only
$shipping_cost = 0.00;   // Calculated shipping cost
$grand_total_price = 0.00; // Subtotal + Shipping
$error_message = '';
$success_message = '';
$vietqr_base64 = null; // Variable to hold the QR code image data
$vietqr_purpose = '';  // Variable to hold the QR purpose text
$grand_total_for_qr = 0; // Variable for the amount encoded in QR

// --- Feedback messages ---
if (isset($_SESSION['cart_message'])) {
    $success_message = $_SESSION['cart_message'];
    unset($_SESSION['cart_message']);
}
if (isset($_SESSION['cart_error'])) {
    $error_message = $_SESSION['cart_error'];
    unset($_SESSION['cart_error']);
}

function crc16_ccitt($str) {
    $crc = 0xFFFF; $strlen = strlen($str);
    for($c = 0; $c < $strlen; $c++) {
        $crc ^= ord(substr($str, $c, 1)) << 8;
        for($i = 0; $i < 8; $i++) { $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1); }
    }
    return $crc & 0xFFFF;
}
function buildVietQRField($id, $value) {
    $value = mb_convert_encoding((string)$value, 'UTF-8');
    $len = str_pad(mb_strlen($value, 'UTF-8'), 2, '0', STR_PAD_LEFT);
    return $id . $len . $value;
}

// --- Cart Logic ---
if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    $cart_items = $_SESSION['cart'];
    $food_ids = array_keys($cart_items);

    if (!empty($food_ids)) {
        $conn = mysqli_connect($servername, $username, $password, $databaseName);
        if (!$conn) {
            error_log("Database Connection failed in cart.php: " . mysqli_connect_error());
            $error_message = "Lỗi kết nối cơ sở dữ liệu.";
            $cart_items = [];
            // Reset prices on connection error
            $subtotal_price = 0.00;
            $shipping_cost = 0.00;
            $grand_total_price = 0.00;
        } else {
            mysqli_set_charset($conn, "utf8mb4");
            try {
                $placeholders = implode(',', array_fill(0, count($food_ids), '?'));
                $types = str_repeat('i', count($food_ids));
                $sql = "SELECT id, name, price, image FROM {$tableName} WHERE id IN ({$placeholders})";
                $stmt = mysqli_prepare($conn, $sql);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, $types, ...$food_ids);
                    if (mysqli_stmt_execute($stmt)) {
                        $result = mysqli_stmt_get_result($stmt);
                        while ($row = mysqli_fetch_assoc($result)) {
                            $cart_item_details[$row['id']] = $row;
                        }
                    } else {
                        throw new Exception("Lỗi thực thi câu lệnh.");
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    throw new Exception("Lỗi chuẩn bị câu lệnh.");
                }

                // Recalculate subtotal price
                $subtotal_price = 0.00; // Reset before recalculating
                foreach ($cart_items as $food_id => $quantity) {
                    if (isset($cart_item_details[$food_id])) {
                        $subtotal_price += (float) $cart_item_details[$food_id]['price'] * (int) $quantity;
                    } else {
                        unset($_SESSION['cart'][$food_id]); // Remove invalid items
                        error_log("Item ID {$food_id} in cart but not DB. Removed.");
                    }
                }

                // Calculate shipping cost (only if there are items)
                $shipping_cost = 0.00;
                if ($subtotal_price > 0) {
                    $shipping_cost = SHIPPING_FEE;
                }

                // Calculate grand total
                $grand_total_price = $subtotal_price + $shipping_cost;

            } catch (Exception $e) {
                error_log("Cart Item Fetch Error: " . $e->getMessage());
                $error_message = "Lỗi tải chi tiết giỏ hàng.";
                $cart_items = [];
                $subtotal_price = 0.00; // Ensure totals are 0 on error
                $shipping_cost = 0.00;
                $grand_total_price = 0.00;
            } finally {
                if ($conn) {
                    mysqli_close($conn);
                }
            }
        }
    } else {
        // Cart array exists but is empty
        $cart_items = [];
        $subtotal_price = 0.00;
        $shipping_cost = 0.00;
        $grand_total_price = 0.00;
    }
} else {
    // Session cart doesn't exist or is not an array
     $cart_items = [];
     $subtotal_price = 0.00;
     $shipping_cost = 0.00;
     $grand_total_price = 0.00;
}


/** Helper function get_image_path - same as before */
function get_image_path(?string $imageFilename, string $uploadDir, string $webUploadDir, string $placeholderPath): string
{
    if (!empty($imageFilename)) {
        $fullFilePath = rtrim($uploadDir, '/') . '/' . $imageFilename;
        if (file_exists($fullFilePath) && is_file($fullFilePath)) {
            return rtrim($webUploadDir, '/') . '/' . htmlspecialchars($imageFilename);
        }
        error_log("Image file not found but listed in DB: " . $fullFilePath);
    }
    return htmlspecialchars($placeholderPath);
}
?>

<body>
    <?php include 'parts/navbar.php'; ?>
    <main class="cart-container">
        <section class="cart-page card">
            <h1 class="cart-title">Giỏ hàng của bạn</h1>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <?php if (empty($cart_items) || empty($cart_item_details)): ?>
                <div class="cart-empty">
                    <p>Giỏ hàng của bạn đang trống.</p>
                    <a href="food.php" class="btn btn-primary">Tiếp tục mua sắm</a>
                </div>
            <?php else: ?>
                <!-- Main form for updating quantities -->
                <!-- Added id="cart-form" -->
                <form action="cart_action.php" method="post" class="cart-form" id="cart-form">
                    <input type="hidden" name="action" value="update">

                    <div class="cart-items-list">
                        <table class="cart-table">
                             <!-- Table Head -->
                            <thead>
                                <tr>
                                    <th colspan="2">Sản phẩm</th>
                                    <th>Giá</th>
                                    <th>Số lượng</th>
                                    <th>Tạm tính</th>
                                    <th>Xóa</th>
                                </tr>
                            </thead>
                             <!-- Table Body -->
                            <tbody>
                                <?php foreach ($cart_items as $food_id => $quantity): ?>
                                    <?php
                                    if (!isset($cart_item_details[$food_id])) continue;
                                    $item = $cart_item_details[$food_id];
                                    $imageSrc = get_image_path($item['image'] ?? null, $uploadDir, $webUploadDir, $placeholderPath);
                                    $item_price = (float) $item['price'];
                                    $item_subtotal = $item_price * (int) $quantity; // Renamed to avoid conflict
                                    ?>
                                    <tr data-food-id="<?php echo $food_id; ?>">
                                        <td class="cart-item-image">
                                            <a href="food_detail.php?id=<?php echo $food_id; ?>">
                                                <img src="<?php echo $imageSrc; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                            </a>
                                        </td>
                                        <td class="cart-item-name">
                                            <a href="food_detail.php?id=<?php echo $food_id; ?>">
                                                <?php echo htmlspecialchars($item['name']); ?>
                                            </a>
                                        </td>
                                        <td class="cart-item-price">
                                            <?php echo number_format($item_price, 0, ',', '.'); ?> VNĐ
                                        </td>
                                        <td class="cart-item-quantity">
                                            <input type="number" name="quantity[<?php echo $food_id; ?>]"
                                                value="<?php echo (int)$quantity; ?>"
                                                min="1" required class="quantity-input"
                                                data-price="<?php echo $item_price; ?>"
                                                aria-label="Số lượng cho <?php echo htmlspecialchars($item['name']); ?>">
                                        </td>
                                        <td class="cart-item-subtotal">
                                            <?php echo number_format($item_subtotal, 0, ',', '.'); ?> VNĐ
                                        </td>
                                        <td class="cart-item-remove">
                                            <form action="cart_action.php" method="post" class="remove-form">
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="food_id" value="<?php echo $food_id; ?>">
                                                <button type="submit" class="remove-button" title="Xóa món này">×</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div> <!-- /.cart-items-list -->

                    <!-- Cart Summary - Shows only Subtotal -->
                    <div class="cart-summary">
                        <div class="cart-actions">
                            <form action="cart_action.php" method="post" class="clear-cart-form">
                                <input type="hidden" name="action" value="clear">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa toàn bộ giỏ hàng?');">Xóa hết giỏ hàng</button>
                            </form>
                        </div>
                        <div class="cart-totals-section">
                            <div class="cart-total">
                                <span class="cart-total-label">Tổng cộng:</span>
                                <!-- Display SUBtotal price here -->
                                <span class="cart-total-value final-total-value">
                                    <?php echo number_format($subtotal_price, 0, ',', '.'); ?> VNĐ
                                </span>
                            </div>
                        </div>
                    </div><!-- /.cart-summary -->

                </form> <!-- End of main cart update form -->

                <!-- Checkout Actions -->
                <div class="cart-checkout-actions">
                    <a href="index.php" class="btn btn-outline-secondary">Tiếp tục mua sắm</a>
                    <button type="button" id="open-checkout-sidebar-btn" class="btn btn-success btn-checkout">Tiến hành thanh toán</button>
                </div><!-- /.cart-checkout-actions -->

            <?php endif; ?>
        </section><!-- /.cart-page -->
    </main><!-- /.cart-container -->

    <!-- *** Checkout Sidebar Structure *** -->
    <div id="checkout-overlay"></div>
    <div id="checkout-sidebar">
        <button id="close-checkout-sidebar-btn" class="close-btn" aria-label="Đóng">×</button>
        <h2>Xác nhận Thanh toán</h2>

        <form id="checkout-form" action="process_checkout.php" method="POST">
            <div class="sidebar-section">
                <h3>Chi tiết đơn hàng</h3>
                <div id="sidebar-cart-items">
                    <p>Đang tải chi tiết...</p>
                </div>
                <!-- Summary Breakdown in Sidebar -->
                <div id="sidebar-summary-details">
                    <!-- Shipping Row (Value from PHP Constant) -->
                    <div id="sidebar-shipping-fee" class="sidebar-shipping" style="display: none;">
                        <strong>Phí vận chuyển:</strong><span class="shipping-amount"><?php echo number_format(SHIPPING_FEE, 0, ',', '.'); ?> VNĐ</span>
                    </div>
                    <!-- Grand Total Row -->
                    <div id="sidebar-cart-total" class="sidebar-total" style="display: none;">
                        <strong>Tổng cộng:</strong> <span class="total-amount">0 VNĐ</span>
                    </div>
                </div>
            </div>

            <!-- Payment Options -->
            <div class="sidebar-section">
                <h3>Phương thức thanh toán</h3>
                <div class="payment-options">
                    <label>
                        <input type="radio" name="payment_method" value="cod" checked required> Thanh toán khi nhận hàng (COD)
                    </label>
                    <label>
                        <form action="" method="post" id="qr-form">
                            <input type="hidden" name="action" value="generate_qr">
                            <input type="hidden" name="amount" id="qr-amount-input" value="<?php echo $grand_total_for_qr; ?>">
                            <input type="hidden" name="purpose" id="qr-purpose-input" value="<?php echo htmlspecialchars($vietqr_purpose); ?>">
                            <input type="radio" name="payment_method" value="online" required> Thanh toán Online (Chuyển khoản/QR)
                        </form>
                    </label>
                </div>
                <div id="qr-code-area" style="display: none; margin-top: 15px; text-align: center;">
                    <p>Quét mã QR bằng ứng dụng ngân hàng của bạn để thanh toán <strong id="qr-amount">0 VNĐ</strong>:</p>
                    <div id="qr-image-container" style="min-height: 212px; display: flex; align-items: center; justify-content: center; background-color: #f8f9fa; border: 1px solid #6c757d; border-radius: 4px; padding: 5px; margin-bottom: 10px;">
                        <img id="vietqr-image" src="">
                        <p id="qr-loading" style="display: none; color: #333;">Đang tạo mã QR...</p>
                        <p id="qr-error" style="display: none; color: #dc3545;">Không thể tạo mã QR.</p>
                    </div>
                    <p id="qr-purpose" style="font-size: 0.9em; color: #adb5bd; margin-bottom: 5px;"></p>
                    <p><small>(Sau khi chuyển khoản thành công, vui lòng nhấn "Xác nhận đặt hàng")</small></p>
                </div>  
            </div>

            <!-- Shipping Information -->
            <div class="sidebar-section">
                <h3>Thông tin giao hàng</h3>
                 <label for="customer_name">Họ tên:</label>
                 <input type="text" id="customer_name" name="customer_name" required>
                 <label for="customer_phone">Số điện thoại:</label>
                 <input type="tel" id="customer_phone" name="customer_phone" required>
                 <label for="customer_address">Địa chỉ:</label>
                 <textarea id="customer_address" name="customer_address" rows="3" required></textarea>
            </div>

            <!-- Sidebar Actions -->
            <div class="sidebar-actions">
                 <button type="submit" class="btn btn-success btn-block">Xác nhận đặt hàng</button>
                 <button type="button" id="cancel-checkout-btn" class="btn btn-secondary btn-block">Hủy</button>
            </div>
        </form>

    </div>
    <!-- *** END: Checkout Sidebar Structure *** -->


    <footer>
        <?php include 'parts/footer.php'; ?>
    </footer>

    <style>
        /* --- Existing CSS styles --- */
        img { max-width: 100%; height: auto; display: block; }
        main.cart-container { max-width: 1140px; margin: 10rem auto 2rem auto; padding: 0 1rem; }
        .cart-page.card { background-color: #343a40; border: 1px solid #495057; border-radius: 8px; padding: 2rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); margin-bottom: 2rem; }
        .cart-title { text-align: center; font-size: 2em; color: #f8f9fa; margin-bottom: 2rem; padding-bottom: 0.5rem; border-bottom: 1px solid #495057; }
        .alert { padding: 1rem 1.25rem; margin-bottom: 1.5rem; border: 1px solid transparent; border-radius: 0.25rem; color: #fff; }
        .alert-success { background-color: #198754; border-color: #198754; }
        .alert-danger { background-color: #dc3545; border-color: #dc3545; }
        .cart-empty { text-align: center; padding: 3rem 1rem; color: #adb5bd; }
        .cart-empty p { font-size: 1.1em; margin-bottom: 1.5rem; }
        .cart-items-list { overflow-x: auto; }
        .cart-table { width: 100%; border-collapse: collapse; margin-bottom: 2rem; color: #dee2e6; }
        .cart-table thead th { text-align: left; padding: 0.75rem 1rem; border-bottom: 2px solid #495057; color: #ced4da; font-weight: 600; text-transform: uppercase; font-size: 0.9em; white-space: nowrap; }
        .cart-table thead th:nth-child(3), .cart-table thead th:nth-child(4), .cart-table thead th:nth-child(5), .cart-table thead th:nth-child(6) { text-align: center; }
        .cart-table tbody tr { border-bottom: 1px solid #495057; transition: background-color 0.2s ease; }
        .cart-table tbody tr:last-child { border-bottom: none; }
        .cart-table tbody tr:hover { background-color: #495057; }
        .cart-table tbody td { padding: 1rem; vertical-align: middle; }
        .cart-item-image { width: 100px; }
        .cart-item-image img { width: 80px; height: 80px; object-fit: cover; border-radius: 4px; border: 1px solid #495057; }
        .cart-item-name a { font-weight: 500; color: #e9ecef; text-decoration: none;}
        .cart-item-name a:hover { color: #fff; }
        .cart-item-price,
        .cart-item-subtotal { font-weight: 500; white-space: nowrap; width: 10em; vertical-align: middle; }
        .cart-item-price { text-align: left; }
        .cart-item-subtotal { text-align: center; }
        .cart-item-quantity { text-align: center; width: 100px; }
        .quantity-input { width: 60px; padding: 0.5rem; text-align: center; border: 1px solid #6c757d; background-color: #495057; color: #f8f9fa; border-radius: 4px;}
        .quantity-input::-webkit-inner-spin-button, .quantity-input::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        .cart-item-remove { text-align: center; width: 60px; }
        .remove-form { display: inline-block; margin: 0; padding: 0; }
        .remove-button { background: none; border: none; color: #ff6b6b; font-size: 1.6em; font-weight: bold; cursor: pointer; padding: 0 0.5rem; line-height: 1; transition: color 0.2s ease; }
        .remove-button:hover { color: #e03131; }
        .cart-summary { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-top: 1rem; padding-top: 1.5rem; border-top: 1px solid #495057; }
        .cart-actions { display: flex; flex-wrap: wrap; gap: 1rem; flex-basis: 50%; justify-content: flex-start;}
        .clear-cart-form { display: inline-block; margin: 0; }
        .cart-totals-section { text-align: right; flex-basis: 45%; min-width: 250px; }
        .cart-total { display: flex; justify-content: space-between; font-size: 1.2em; }
        .cart-total-label { font-weight: 500; color: #adb5bd; margin-right: 1rem;}
        .cart-total-value { font-weight: bold; color: #f8f9fa; font-size: 1.3em;}
        .cart-checkout-actions { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #495057; }
        .btn { display: inline-block; font-weight: 500; line-height: 1.5; color: #212529; text-align: center; vertical-align: middle; cursor: pointer; user-select: none; background-color: transparent; border: 1px solid transparent; padding: 0.5rem 1.25rem; font-size: 1rem; border-radius: 0.25rem; transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out; text-decoration: none; }
        .btn-primary { color: #fff; background-color: #0d6efd; border-color: #0d6efd; }
        .btn-primary:hover { background-color: #0b5ed7; border-color: #0a58ca; }
        .btn-secondary { color: #fff; background-color: #6c757d; border-color: #6c757d; }
        .btn-secondary:hover { background-color: #5c636a; border-color: #565e64; }
        .btn-success { color: #fff; background-color: #198754; border-color: #198754; }
        .btn-success:hover { background-color: #157347; border-color: #146c43; }
        .btn-danger { color: #fff; background-color: #dc3545; border-color: #dc3545; }
        .btn-danger:hover { background-color: #bb2d3b; border-color: #b02a37; }
        .btn-outline-secondary { color: #adb5bd; border-color: #6c757d; }
        .btn-outline-secondary:hover { color: #fff; background-color: #6c757d; border-color: #6c757d; }
        .btn-checkout { padding: 0.75rem 1.5rem; font-size: 1.1em; }
        .btn-block { display: block; width: 100%; margin-top: 0.5rem; }

        /* --- Responsive Adjustments --- */
        @media (max-width: 768px) { /* Adjust as needed */ }
        @media (max-width: 576px) { /* Adjust as needed */ }


        /* --- Checkout Sidebar Styles --- */
        #checkout-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); z-index: 1040; display: none; opacity: 0; transition: opacity 0.3s ease-in-out; }
        #checkout-overlay.active { display: block; opacity: 1; }
        #checkout-sidebar { position: fixed; top: 0; right: -450px; width: 400px; height: 100vh; background-color: #2c3034; color: #e9ecef; padding: 25px; box-shadow: -5px 0 15px rgba(0, 0, 0, 0.5); z-index: 1050; transition: right 0.4s ease-in-out; overflow-y: auto; display: flex; flex-direction: column; }
        #checkout-sidebar.active { right: 0; }
        #checkout-sidebar h2 { margin-top: 0; margin-bottom: 1.5rem; padding-bottom: 0.75rem; border-bottom: 1px solid #495057; color: #fff; text-align: center; }
        #close-checkout-sidebar-btn { position: absolute; top: 10px; right: 15px; background: none; border: none; font-size: 2rem; color: #adb5bd; cursor: pointer; line-height: 1; padding: 0; }
        #close-checkout-sidebar-btn:hover { color: #fff; }
        .sidebar-section { margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #495057; }
        .sidebar-section:last-of-type { border-bottom: none; margin-bottom: 0; flex-grow: 1; }
        .sidebar-section h3 { margin-top: 0; margin-bottom: 1rem; color: #ced4da; font-size: 1.1em; }
        #sidebar-cart-items { max-height: 30vh; overflow-y: auto; margin-bottom: 1rem; padding-right: 5px; }
        #sidebar-cart-items .sidebar-item { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.8rem; font-size: 0.95em; padding-bottom: 0.5rem; border-bottom: 1px dashed #495057; }
        #sidebar-cart-items .sidebar-item:last-child { border-bottom: none; margin-bottom: 0; }
        #sidebar-cart-items .item-info { flex-grow: 1; margin-right: 10px; }
        #sidebar-cart-items .item-name { display: block; margin-bottom: 2px; }
        #sidebar-cart-items .item-qty-price { font-size: 0.9em; color: #adb5bd; }
        #sidebar-cart-items .item-subtotal { font-weight: 500; white-space: nowrap; }

        /* Sidebar Summary Styling */
         #sidebar-summary-details { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #495057; }
         .sidebar-subtotal,
         .sidebar-shipping,
         .sidebar-total { display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 1.05em; }
          .sidebar-shipping span, .sidebar-subtotal span { font-weight: 500; }
         .sidebar-total { margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid #495057; font-size: 1.2em; }
         .sidebar-total strong { color: #fff; }
         .sidebar-total .total-amount { font-weight: bold; color: #fff; }

        .payment-options label { display: block; margin-bottom: 0.75rem; cursor: pointer; padding: 10px; border: 1px solid #495057; border-radius: 4px; transition: background-color 0.2s, border-color 0.2s; }
        .payment-options label:hover { background-color: #3a3f44; }
        .payment-options input[type="radio"] { margin-right: 10px; accent-color: #198754; }
        #qr-code-area img { border: 1px solid #6c757d; padding: 5px; background-color: white; display: inline-block; }
        #qr-code-area p { margin-bottom: 10px; }
        #qr-code-area p small { color: #adb5bd; }
        .sidebar-actions { margin-top: auto; padding-top: 1.5rem; border-top: 1px solid #495057; }
        #checkout-sidebar label { margin-bottom: 0.5rem; font-weight: 500; color: #ced4da; }
        #checkout-sidebar input[type="text"],
        #checkout-sidebar input[type="tel"],
        #checkout-sidebar textarea { width: 100%; padding: 0.6rem 0.8rem; margin-bottom: 1rem; background-color: #495057; border: 1px solid #6c757d; color: #f8f9fa; border-radius: 4px; }
        #checkout-sidebar textarea { resize: vertical; }
        #checkout-sidebar input:focus,
        #checkout-sidebar textarea:focus { outline: none; border-color: #198754; box-shadow: 0 0 0 2px rgba(25, 135, 84, 0.25); }

    </style>

<script>

document.addEventListener('DOMContentLoaded', () => {
    const quantityInputs = document.querySelectorAll('.quantity-input');
    // Main Cart Total Element (Displays Subtotal)
    const mainFinalTotalValueElement = document.querySelector('.cart-total .final-total-value');
    const cartTableBody = document.querySelector('.cart-table tbody');

    // --- Sidebar Elements ---
    const openCheckoutBtn = document.getElementById('open-checkout-sidebar-btn');
    const closeCheckoutBtn = document.getElementById('close-checkout-sidebar-btn');
    const cancelCheckoutBtn = document.getElementById('cancel-checkout-btn');
    const checkoutSidebar = document.getElementById('checkout-sidebar');
    const checkoutOverlay = document.getElementById('checkout-overlay');
    const sidebarItemsContainer = document.getElementById('sidebar-cart-items');
    // Sidebar Summary Elements
    const sidebarSubtotalRow = document.getElementById('sidebar-cart-subtotal'); // Check if this ID exists in your HTML (it wasn't in the provided one)
    const sidebarSubtotalAmount = sidebarSubtotalRow ? sidebarSubtotalRow.querySelector('.subtotal-amount') : null;
    const sidebarShippingRow = document.getElementById('sidebar-shipping-fee');
    const sidebarTotalRow = document.getElementById('sidebar-cart-total');
    const sidebarTotalAmount = sidebarTotalRow ? sidebarTotalRow.querySelector('.total-amount') : null;

    const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
    const qrCodeArea = document.getElementById('qr-code-area');
    const qrImageContainer = document.getElementById('qr-image-container');
    const vietQrImage = document.getElementById('vietqr-image');
    const qrLoading = document.getElementById('qr-loading');
    const qrError = document.getElementById('qr-error');
    const qrAmount = document.getElementById('qr-amount');
    const qrPurpose = document.getElementById('qr-purpose');

    // --- Define Shipping Fee in JS ---
    const shippingFee = <?php echo SHIPPING_FEE; ?>; // Get value from PHP

    // Function to format number as Vietnamese currency
    function formatCurrency(value) {
         if (isNaN(value)) return '0 VNĐ';
         // Using de-DE locale for dot separators, common in VN currency display
         return Number(value).toLocaleString('de-DE', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) + ' VNĐ';
    }

    // --- Function to update totals ---
    function updateAllCartTotals() {
        let currentSubtotal = 0;
        if (!cartTableBody) {
            if (mainFinalTotalValueElement) mainFinalTotalValueElement.textContent = formatCurrency(0);
            updateSidebarTotals(0, 0); // Update sidebar too
            return;
        }

        const rows = cartTableBody.querySelectorAll('tr');
        if (rows.length === 0) {
             if (mainFinalTotalValueElement) mainFinalTotalValueElement.textContent = formatCurrency(0);
             updateSidebarTotals(0, 0); // Update sidebar too
             return;
        }

        rows.forEach(row => {
            const input = row.querySelector('.quantity-input');
            if (input && input.dataset.price !== undefined) {
                const price = parseFloat(input.dataset.price);
                const quantity = parseInt(input.value, 10);
                if (!isNaN(price) && !isNaN(quantity) && quantity >= 1) {
                    currentSubtotal += price * quantity;
                }
            }
        });

        let currentShipping = 0;
        if (currentSubtotal > 0) {
            currentShipping = shippingFee;
        }

        if (mainFinalTotalValueElement) {
            mainFinalTotalValueElement.textContent = formatCurrency(currentSubtotal);
        }

        updateSidebarTotals(currentSubtotal, currentShipping);
    }

    // --- Function specifically for updating Sidebar Totals ---
    function updateSidebarTotals(subtotal, shippingCost) {
         const finalTotal = subtotal + shippingCost;
         let showDetails = subtotal > 0;

         // Update Subtotal Row (Check if sidebarSubtotalRow exists)
         if (sidebarSubtotalRow && sidebarSubtotalAmount) {
             if (showDetails) {
                console.log(subtotal);
                sidebarSubtotalAmount.textContent = formatCurrency(subtotal);
                sidebarSubtotalRow.style.display = 'flex';
             } else {
                sidebarSubtotalRow.style.display = 'none';
             }
         } else if (showDetails) {
             // console.warn("Sidebar subtotal row/amount element not found."); // Optional warning
         }


         // Update Shipping Row
         if (sidebarShippingRow) {
             if (showDetails && shippingCost > 0) {
                sidebarShippingRow.style.display = 'flex';
             } else {
                sidebarShippingRow.style.display = 'none';
             }
         }

         // Update Final Total Row
         if (sidebarTotalRow && sidebarTotalAmount) {
              if (showDetails) {
                 sidebarTotalAmount.textContent = formatCurrency(finalTotal);
                 sidebarTotalRow.style.display = 'flex';
              } else {
                 sidebarTotalRow.style.display = 'none';
              }
         }
    }

    // Add event listener to each quantity input
    quantityInputs.forEach(input => {
        input.addEventListener('input', (event) => {
            const currentInput = event.target;
            const price = parseFloat(currentInput.dataset.price);
            let quantity = parseInt(currentInput.value, 10);
            const row = currentInput.closest('tr');
            const subtotalCell = row ? row.querySelector('.cart-item-subtotal') : null;

            if (isNaN(price) || price < 0) {
                 if(subtotalCell) subtotalCell.textContent = 'Lỗi giá';
                 updateAllCartTotals();
                 return;
             }
             if (isNaN(quantity) || quantity < 1) {
                  quantity = 1;
                  currentInput.value = quantity; // Force back to 1 if invalid
             }

            if (subtotalCell) {
                const rowSubtotal = price * quantity;
                subtotalCell.textContent = formatCurrency(rowSubtotal);
            }
             updateAllCartTotals(); // Recalculate all totals
        });
    });

    // --- Sidebar Logic ---

    // Function to populate sidebar items (Calls updateAllCartTotals at the end)
    function populateSidebarItems() {
        if (!cartTableBody || !sidebarItemsContainer) {
             console.error("Sidebar item container or cart table body not found");
             if (sidebarItemsContainer) sidebarItemsContainer.innerHTML = '<p>Lỗi tải giỏ hàng.</p>';
             updateSidebarTotals(0,0); // Ensure totals are zeroed on error
             return;
        }

        sidebarItemsContainer.innerHTML = ''; // Clear previous items
        const rows = cartTableBody.querySelectorAll('tr');

        if (rows.length === 0) {
            sidebarItemsContainer.innerHTML = '<p>Giỏ hàng trống.</p>';
        } else {
            rows.forEach(row => {
                const nameElement = row.querySelector('.cart-item-name a');
                const quantityInput = row.querySelector('.quantity-input');
                const price = parseFloat(quantityInput?.dataset.price);
                const quantity = parseInt(quantityInput?.value, 10);

                if (nameElement && quantityInput && !isNaN(price) && !isNaN(quantity) && quantity >= 1) {
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
                } else {
                    console.warn("Skipping row due to missing data or invalid quantity/price:", row);
                }
            });
        }
         updateAllCartTotals(); // Call update totals AFTER potentially populating items
    }

    // Corrected Helper to prevent basic XSS
     function escapeHtml(unsafe) {
        if (typeof unsafe !== 'string') return '';
         return unsafe
              .replace(/&/g, "&") // Use HTML entities
              .replace(/</g, "<")
              .replace(/>/g, ">")
              .replace(/"/g, "")
              .replace(/'/g, "'");
     }


    // Function to open the sidebar (Calls populateSidebarItems)
    function openSidebar() {
        populateSidebarItems(); // This calls updateAllCartTotals internally
        if (checkoutSidebar && checkoutOverlay) {
            checkoutOverlay.classList.add('active');
            checkoutSidebar.classList.add('active');
            document.body.style.overflow = 'hidden';

            // Optional: Check if 'online' is selected when opening and generate QR
            const checkedOnline = document.querySelector('input[name="payment_method"][value="online"]:checked');
            if (checkedOnline && qrCodeArea) {
                generateAndDisplayQR();
            } else if (qrCodeArea) {
                 qrCodeArea.style.display = 'none'; // Ensure it's hidden if COD is selected
            }

        } else {
             console.error("Checkout sidebar or overlay element not found.");
        }
    }

    // Function to close the sidebar
    function closeSidebar() {
        if (checkoutSidebar && checkoutOverlay) {
            checkoutOverlay.classList.remove('active');
            checkoutSidebar.classList.remove('active');
            document.body.style.overflow = ''; // Restore scrolling
        }
    }

    // --- Function to Generate and Display VietQR ---
    async function generateAndDisplayQR() {
        if (!qrCodeArea || !vietQrImage || !qrLoading || !qrError || !qrAmount || !qrPurpose || !sidebarTotalAmount) {
            console.error("QR Code elements or total amount element not found.");
            return;
        }

        // 1. Show loading state
        qrCodeArea.style.display = 'block';
        qrLoading.style.display = 'block';
        vietQrImage.src = '';
        
        vietQrImage.src = ''; // Clear previous QR
        qrAmount.textContent = '...';
        qrPurpose.textContent = '';
        // 2. Get the *current* Grand Total from the sidebar display
        // We trust the sidebar display which was calculated securely by PHP initially
        // and updated by JS (updateSidebarTotals) which bases its calculation
        // on values derived originally from PHP.
        const totalText = sidebarTotalAmount.textContent; // e.g., "165.000 VNĐ"
        // Extract the number carefully
        let amount = 0;
        try {
             // Remove currency symbol and thousand separators (dots for de-DE)
             const cleanedTotalText = totalText.replace(/ VNĐ/g, '').replace(/\./g, '');
             amount = parseInt(cleanedTotalText, 10); // Amount must be integer for VietQR
             if (isNaN(amount) || amount <= 0) {
                 throw new Error("Invalid total amount found in sidebar.");
             }
        } catch (e) {
             console.error("Error parsing amount from sidebar:", e);
             qrLoading.style.display = 'none';
             qrError.textContent = "Lỗi lấy tổng tiền.";
             
             return;
        }
        // 3. Make AJAX request to the PHP endpoint
        try {
            const data = await response.json();
            if (data.success && data.imageData) {
                // 4. Display QR Code on success
                vietQrImage.src = '';
                vietQrImage.style.display = 'block';
                qrLoading.style.display = 'none';
                
                // Update amount and purpose display based on server response
                qrAmount.textContent = formatCurrency(data.amount);
                qrPurpose.textContent = data.purpose ? `Nội dung: ${escapeHtml(data.purpose)}` : '';

            } else {
                throw new Error(data.error || 'Phản hồi không hợp lệ từ máy chủ.');
            }

        } catch (error) {
            console.error("Error generating QR code:", error);
            // 5. Show error state
            qrLoading.style.display = 'none';
        
            const accountName = 'FoodNow';
            vietQrImage.src = `https://img.vietqr.io/image/<?php echo htmlspecialchars($bank_bin); ?>-<?php echo htmlspecialchars($bank_number); ?>-print.jpg?amount=${amount}&accountName=${accountName}`;
            qrAmount.textContent = formatCurrency(amount);
            qrPurpose.textContent = '';
        }
    }


    // --- Event Listeners ---
    if (openCheckoutBtn) {
        openCheckoutBtn.addEventListener('click', openSidebar);
    } else {
        console.error("Open checkout button not found.");
    }
    if (closeCheckoutBtn) {
        closeCheckoutBtn.addEventListener('click', closeSidebar);
    }
     if (cancelCheckoutBtn) {
        cancelCheckoutBtn.addEventListener('click', closeSidebar);
    }
    if (checkoutOverlay) {
        checkoutOverlay.addEventListener('click', closeSidebar);
    }

    // --- CORRECTED Payment Radio Listener ---
    paymentRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (qrCodeArea) {
                if (this.value === 'online' && this.checked) {
                    generateAndDisplayQR(); // Generate QR when 'Online' is selected
                } else {
                    qrCodeArea.style.display = 'none'; // Hide QR area for COD
                    // Clear previous QR info if needed
                    if (vietQrImage) vietQrImage.src = '';
                    if (qrLoading) qrLoading.style.display = 'none';
                    
                    if (qrAmount) qrAmount.textContent = '0 VNĐ';
                    if (qrPurpose) qrPurpose.textContent = '';
                }
            }
        });
    });

    // Initial calculation on page load
    updateAllCartTotals();

    // Ensure QR area is hidden initially if COD is checked by default
    const checkedPayment = document.querySelector('input[name="payment_method"]:checked');
    if (qrCodeArea && (!checkedPayment || checkedPayment.value !== 'online')) {
        qrCodeArea.style.display = 'none';
    } else if (qrCodeArea && checkedPayment && checkedPayment.value === 'online') {
         // Optional: Generate QR on load if Online is pre-selected (might be slow)
         generateAndDisplayQR();
    }

});
</script>

</body>
</html>