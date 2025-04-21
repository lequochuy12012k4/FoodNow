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
$uploadDir = 'uploads/';
$webUploadDir = 'uploads/';
$placeholderPath = 'image/placeholder-food.png';
define('VIETQR_BANK_BIN', 'techcombank');
define('VIETQR_ACCOUNT_NO', '0931910JQK');
define('VIETQR_ACCOUNT_NAME', 'PHAM THI PHUONG VY');
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
        $fullFilePath = rtrim($uploadDir, '/') . '/' . $imageFilename;
        if (file_exists($fullFilePath) && is_file($fullFilePath)) {
            return rtrim($webUploadDir, '/') . '/' . htmlspecialchars($imageFilename);
        } else {
            error_log("Image file not found but listed in DB: " . $fullFilePath);
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
// ... (Cart fetching logic remains the same as the corrected version) ...
// 1. Determine user identifier
$session_username = isset($_SESSION['username']) ? trim($_SESSION['username']) : null;
$session_id = session_id();

// 2. Connect to Database
$conn = mysqli_connect($servername, $username, $password, $databaseName);

if (!$conn) {
    error_log("Database Connection failed in cart.php: " . mysqli_connect_error());
    $error_message = "Lỗi kết nối cơ sở dữ liệu. Không thể tải giỏ hàng.";
} else {
    mysqli_set_charset($conn, "utf8mb4");
    try {
        // 3. Prepare SQL Query
        $sql = "SELECT
                    o.id as order_item_id, o.food_id, o.quantity, o.price_at_add,
                    o.food_name, fd.image as food_image
                FROM `{$orderTableName}` o
                LEFT JOIN `{$foodTableName}` fd ON o.food_id = fd.id
                WHERE o.status = 'cart'";

        // 4. Add user/session filtering
        $params = [];
        $types = "";
        if ($session_username !== null) {
            $sql .= " AND o.username = ?";
            $params[] = $session_username;
            $types .= "s";
        } else {
            $sql .= " AND o.session_id = ? AND o.username IS NULL";
            $params[] = $session_id;
            $types .= "s";
        }
        $sql .= " ORDER BY o.added_at DESC";

        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            if (!empty($types)) {
                mysqli_stmt_bind_param($stmt, $types, ...$params);
            }

            // 5. Execute and Fetch Results
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                while ($row = mysqli_fetch_assoc($result)) {
                    $row['food_name'] = $row['food_name'] ?? 'Món ăn không xác định';
                    $cart_display_items[] = $row;
                    $subtotal_price += (float)$row['price_at_add'] * (int)$row['quantity'];
                }
            } else {
                throw new Exception("Lỗi thực thi câu lệnh lấy giỏ hàng: " . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
        } else {
            throw new Exception("Lỗi chuẩn bị câu lệnh lấy giỏ hàng: " . mysqli_error($conn));
        }

        // 6. Calculate Shipping and Grand Total
        if ($subtotal_price > 0) {
            $shipping_cost = SHIPPING_FEE;
        }
        $grand_total_price = $subtotal_price + $shipping_cost;
    } catch (Exception $e) {
        error_log("Cart Item Fetch/Calculation Error: " . $e->getMessage());
        $error_message = "Lỗi tải chi tiết giỏ hàng.";
        $cart_display_items = [];
        $subtotal_price = 0.00;
        $shipping_cost = 0.00;
        $grand_total_price = 0.00;
    } finally {
        if ($conn) {
            mysqli_close($conn);
        }
    }
} // End DB connection block
?>


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
                <form action="cart_action.php" method="post" class="cart-form" id="cart-form">
                    <input type="hidden" name="action" value="update_quantities">
                    <div class="cart-items-list">
                        <table class="cart-table">
                            <thead>
                                <tr>
                                    <th colspan="2">Sản phẩm</th>
                                    <th>Giá</th>
                                    <th>Số lượng</th>
                                    <th>Tạm tính</th>
                                    <th>Xóa</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart_display_items as $item): ?>
                                    <?php
                                    // Get image path (still uses food_data image if available)
                                    $imageSrc = get_image_path($item['food_image'] ?? null, $uploadDir, $webUploadDir, $placeholderPath);
                                    $item_price = (float) $item['price_at_add'];
                                    $item_quantity = (int) $item['quantity'];
                                    $item_subtotal = $item_price * $item_quantity;
                                    $food_id = $item['food_id'];
                                    $order_item_id = $item['order_item_id'];
                                    // Use food_name fetched from orders table
                                    $item_name = htmlspecialchars($item['food_name']);
                                    ?>
                                    <tr data-order-item-id="<?php echo $order_item_id; ?>">
                                        <td class="cart-item-image">
                                            <a href="food_detail.php?id=<?php echo $food_id; ?>">
                                                <img src="<?php echo $imageSrc; ?>" alt="<?php echo $item_name; ?>">
                                            </a>
                                        </td>
                                        <td class="cart-item-name">
                                            <a href="food_detail.php?id=<?php echo $food_id; ?>">
                                                <?php echo $item_name; // Display name from orders 
                                                ?>
                                            </a>
                                        </td>
                                        <td class="cart-item-price">
                                            <?php echo number_format($item_price, 0, ',', '.'); ?> VNĐ
                                        </td>
                                        <td class="cart-item-quantity">
                                            <input type="number" name="quantity[<?php echo $order_item_id; ?>]"
                                                value="<?php echo $item_quantity; ?>"
                                                min="1" required class="quantity-input"
                                                data-price="<?php echo $item_price; ?>"
                                                aria-label="Số lượng cho <?php echo $item_name; ?>">
                                        </td>
                                        <td class="cart-item-subtotal">
                                            <?php echo number_format($item_subtotal, 0, ',', '.'); ?> VNĐ
                                        </td>
                                        <td class="cart-item-remove">
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
                            <form action="cart_action.php" method="post" class="clear-cart-form">
                                <input type="hidden" name="action" value="clear_cart">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa toàn bộ giỏ hàng?');">Xóa hết giỏ hàng</button>
                            </form>
                            <!-- <button type="submit" form="cart-form" class="btn btn-primary">Cập nhật giỏ hàng</button> -->
                        </div>
                        <div class="cart-totals-section">
                            <div class="cart-total">
                                <span class="cart-total-label">Tổng cộng:</span>
                                <span class="cart-total-value final-total-value">
                                    <?php echo number_format($subtotal_price, 0, ',', '.'); ?> VNĐ
                                </span>
                            </div>
                        </div>
                    </div><!-- /.cart-summary -->

                </form> <!-- End of main cart form -->

                <!-- Checkout Actions -->
                <div class="cart-checkout-actions">
                    <a href="index.php" class="btn btn-outline-secondary">Tiếp tục mua sắm</a>
                    <button type="button" id="open-checkout-sidebar-btn" class="btn btn-success btn-checkout">Tiến hành thanh toán</button>
                </div><!-- /.cart-checkout-actions -->

            <?php endif; ?>
        </section><!-- /.cart-page -->
    </main><!-- /.cart-container -->

    <!-- Checkout Sidebar Structure -->
    <!-- ... (Sidebar HTML remains the same) ... -->
    <div id="checkout-overlay"></div>
    <div id="checkout-sidebar">
        <button id="close-checkout-sidebar-btn" class="close-btn" aria-label="Đóng">×</button>
        <h2>Xác nhận Thanh toán</h2>
        <form id="checkout-form" action="process_checkout.php" method="POST">
            <!-- Order Details -->
            <div class="sidebar-section">
                <h3>Chi tiết đơn hàng</h3>
                <div id="sidebar-cart-items">
                    <p>Đang tải chi tiết...</p>
                </div>
                <div id="sidebar-summary-details">
                    <div id="sidebar-shipping-fee" class="sidebar-shipping" style="display: none;">
                        <strong>Phí vận chuyển:</strong><span class="shipping-amount"><?php echo number_format(SHIPPING_FEE, 0, ',', '.'); ?> VNĐ</span>
                    </div>
                    <div id="sidebar-cart-total" class="sidebar-total" style="display: none;">
                        <strong>Tổng cộng:</strong> <span class="total-amount">0 VNĐ</span>
                    </div>
                </div>
            </div>
            <!-- Payment Options -->
            <div class="sidebar-section">
                <h3>Phương thức thanh toán</h3>
                <div class="payment-options">
                    <label><input type="radio" name="payment_method" value="cod" checked required> Thanh toán khi nhận hàng (COD)</label>
                    <label><input type="radio" name="payment_method" value="online" required> Thanh toán Online (Chuyển khoản/QR)</label>
                </div>
                <div id="qr-code-area" style="display: none; margin-top: 15px; text-align: center;">
                    <p>Quét mã QR bằng ứng dụng ngân hàng của bạn để thanh toán <strong id="qr-amount">0 VNĐ</strong>:</p>
                    <div id="qr-image-container" style="min-height: 212px; display: flex; align-items: center; justify-content: center; background-color: #f8f9fa; border: 1px solid #6c757d; border-radius: 4px; padding: 5px; margin-bottom: 10px;">
                        <img id="vietqr-image" src="" alt="VietQR Code">
                        <p id="qr-loading" style="display: none; color: #333;">Đang tạo mã QR...</p>
                        <p id="qr-error" style="display: none; color: red;">Lỗi tạo QR</p> <!-- Added Error Placeholder -->
                    </div>
                    <p id="qr-purpose" style="font-size: 0.9em; color: #adb5bd; margin-bottom: 5px;"></p>
                    <p><small>(Sau khi chuyển khoản thành công, vui lòng nhấn "Xác nhận đặt hàng")</small></p>
                </div>
            </div>
            <!-- Shipping Information -->
            <div class="sidebar-section">
                <h3>Thông tin giao hàng</h3>
                <label for="customer_name">Họ tên:</label>
                <input type="text" id="customer_name" name="customer_name" required placeholder="Nhập họ tên của bạn">
                <label for="customer_phone">Số điện thoại:</label>
                <input type="tel" id="customer_phone" placeholder="Nhập số điện thoại của bạn" name="customer_phone" required pattern="[0-9]{10,11}" title="Nhập số điện thoại hợp lệ (10-11 chữ số)">
                <label for="customer_address">Địa chỉ:</label>
                <textarea id="customer_address" name="customer_address" placeholder="Nhập địa chỉ nhận hàng" rows="3" required></textarea>
            </div>
            <!-- Actions -->
            <div class="sidebar-actions">
                <button type="submit" class="btn btn-success btn-block">Xác nhận đặt hàng</button>
                <button type="button" id="cancel-checkout-btn" class="btn btn-secondary btn-block">Hủy</button>
            </div>
        </form>
    </div>

    <footer>
        <?php include 'parts/footer.php'; ?>
    </footer>

    <!-- Styles remain the same -->
    <style>
        /* --- Styles are identical to your previous version --- */
        /* Add style for qr-error if you didn't have one */
        #qr-error {
            color: #dc3545;
            font-weight: bold;
        }

        /* ... other styles ... */
        img {
            max-width: 100%;
            height: auto;
            display: block;
        }

        main.cart-container {
            max-width: 1140px;
            margin: 10rem auto 2rem auto;
            padding: 0 1rem;
        }

        .cart-page.card {
            background-color: #343a40;
            border: 1px solid #495057;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            margin-bottom: 2rem;
        }

        .cart-title {
            text-align: center;
            font-size: 2em;
            color: #f8f9fa;
            margin-bottom: 2rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #495057;
        }

        .alert {
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid transparent;
            border-radius: 0.25rem;
            color: #fff;
        }

        .alert-success {
            background-color: #198754;
            border-color: #198754;
        }

        .alert-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }

        .cart-empty {
            text-align: center;
            padding: 3rem 1rem;
            color: #adb5bd;
        }

        .cart-empty p {
            font-size: 1.1em;
            margin-bottom: 1.5rem;
        }

        .cart-items-list {
            overflow-x: auto;
        }

        .cart-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
            color: #dee2e6;
        }

        .cart-table thead th {
            text-align: left;
            padding: 0.75rem 1rem;
            border-bottom: 2px solid #495057;
            color: #ced4da;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9em;
            white-space: nowrap;
        }

        .cart-table thead th:nth-child(3),
        .cart-table thead th:nth-child(4),
        .cart-table thead th:nth-child(5),
        .cart-table thead th:nth-child(6) {
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
            background-color: #495057;
        }

        .cart-table tbody td {
            padding: 1rem;
            vertical-align: middle;
        }

        .cart-item-image {
            width: 100px;
        }

        .cart-item-image img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #495057;
        }

        .cart-item-name a {
            font-weight: 500;
            color: #e9ecef;
            text-decoration: none;
        }

        .cart-item-name a:hover {
            color: #fff;
        }

        .cart-item-price,
        .cart-item-subtotal {
            font-weight: 500;
            white-space: nowrap;
            width: 10em;
            vertical-align: middle;
        }

        .cart-item-price {
            text-align: left;
        }

        .cart-item-subtotal {
            text-align: center;
        }

        .cart-item-quantity {
            text-align: center;
            width: 100px;
        }

        .quantity-input {
            width: 60px;
            padding: 0.5rem;
            text-align: center;
            border: 1px solid #6c757d;
            background-color: #495057;
            color: #f8f9fa;
            border-radius: 4px;
        }

        .quantity-input::-webkit-inner-spin-button,
        .quantity-input::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .cart-item-remove {
            text-align: center;
            width: 60px;
        }

        .remove-form {
            display: inline-block;
            margin: 0;
            padding: 0;
        }

        .remove-button {
            background: none;
            border: none;
            color: #ff6b6b;
            font-size: 1.6em;
            font-weight: bold;
            cursor: pointer;
            padding: 0 0.5rem;
            line-height: 1;
            transition: color 0.2s ease;
        }

        .remove-button:hover {
            color: #e03131;
        }

        .cart-summary {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1rem;
            padding-top: 1.5rem;
            border-top: 1px solid #495057;
        }

        .cart-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            flex-basis: 50%;
            justify-content: flex-start;
        }

        .clear-cart-form {
            display: inline-block;
            margin: 0;
        }

        .cart-totals-section {
            text-align: right;
            flex-basis: 45%;
            min-width: 250px;
        }

        .cart-total {
            display: flex;
            justify-content: space-between;
            font-size: 1.2em;
        }

        .cart-total-label {
            font-weight: 500;
            color: #adb5bd;
            margin-right: 1rem;
        }

        .cart-total-value {
            font-weight: bold;
            color: #f8f9fa;
            font-size: 1.3em;
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

        .btn {
            display: inline-block;
            font-weight: 500;
            line-height: 1.5;
            color: #212529;
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

        .btn-primary {
            color: #fff;
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }

        .btn-secondary {
            color: #fff;
            background-color: #6c757d;
            border-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #5c636a;
            border-color: #565e64;
        }

        .btn-success {
            color: #fff;
            background-color: #198754;
            border-color: #198754;
        }

        .btn-success:hover {
            background-color: #157347;
            border-color: #146c43;
        }

        .btn-danger {
            color: #fff;
            background-color: #dc3545;
            border-color: #dc3545;
        }

        .btn-danger:hover {
            background-color: #bb2d3b;
            border-color: #b02a37;
        }

        .btn-outline-secondary {
            color: #adb5bd;
            border-color: #6c757d;
        }

        .btn-outline-secondary:hover {
            color: #fff;
            background-color: #6c757d;
            border-color: #6c757d;
        }

        .btn-checkout {
            padding: 0.75rem 1.5rem;
            font-size: 1.1em;
        }

        .btn-block {
            display: block;
            width: 100%;
            margin-top: 0.5rem;
        }

        /* --- Responsive Adjustments --- */
        @media (max-width: 768px) {
            /* Adjust as needed */
        }

        @media (max-width: 576px) {
            /* Adjust as needed */
        }


        /* --- Checkout Sidebar Styles --- */
        #checkout-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 1040;
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
            right: -450px;
            width: 400px;
            height: 100vh;
            background-color: #2c3034;
            color: #e9ecef;
            padding: 25px;
            box-shadow: -5px 0 15px rgba(0, 0, 0, 0.5);
            z-index: 1050;
            transition: right 0.4s ease-in-out;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        #checkout-sidebar.active {
            right: 0;
        }

        #checkout-sidebar h2 {
            margin-top: 0;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #495057;
            color: #fff;
            text-align: center;
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

        #close-checkout-sidebar-btn:hover {
            color: #fff;
        }

        .sidebar-section {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #495057;
        }

        .sidebar-section:last-of-type {
            border-bottom: none;
            margin-bottom: 0;
            flex-grow: 1;
        }

        .sidebar-section h3 {
            margin-top: 0;
            margin-bottom: 1rem;
            color: #ced4da;
            font-size: 1.1em;
        }

        #sidebar-cart-items {
            max-height: 30vh;
            overflow-y: auto;
            margin-bottom: 1rem;
            padding-right: 5px;
        }

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

        #sidebar-cart-items .item-info {
            flex-grow: 1;
            margin-right: 10px;
        }

        #sidebar-cart-items .item-name {
            display: block;
            margin-bottom: 2px;
        }

        #sidebar-cart-items .item-qty-price {
            font-size: 0.9em;
            color: #adb5bd;
        }

        #sidebar-cart-items .item-subtotal {
            font-weight: 500;
            white-space: nowrap;
        }

        /* Sidebar Summary Styling */
        #sidebar-summary-details {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #495057;
        }

        .sidebar-subtotal,
        /* Subtotal is not directly shown here anymore by default, handled by JS */
        .sidebar-shipping,
        .sidebar-total {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 1.05em;
        }

        .sidebar-shipping span,
        .sidebar-subtotal span {
            font-weight: 500;
        }

        .sidebar-total {
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid #495057;
            font-size: 1.2em;
        }

        .sidebar-total strong {
            color: #fff;
        }

        .sidebar-total .total-amount {
            font-weight: bold;
            color: #fff;
        }

        .payment-options label {
            display: block;
            margin-bottom: 0.75rem;
            cursor: pointer;
            padding: 10px;
            border: 1px solid #495057;
            border-radius: 4px;
            transition: background-color 0.2s, border-color 0.2s;
        }

        .payment-options label:hover {
            background-color: #3a3f44;
        }

        .payment-options input[type="radio"] {
            margin-right: 10px;
            accent-color: #198754;
        }

        #qr-code-area img {
            border: 1px solid #6c757d;
            padding: 5px;
            background-color: white;
            display: inline-block;
            max-width: 100%;
        }

        /* Added max-width */
        #qr-code-area p {
            margin-bottom: 10px;
        }

        #qr-code-area p small {
            color: #adb5bd;
        }

        .sidebar-actions {
            margin-top: auto;
            padding-top: 1.5rem;
            border-top: 1px solid #495057;
        }

        #checkout-sidebar label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #ced4da;
        }

        /* Ensure label is block */
        #checkout-sidebar input[type="text"],
        #checkout-sidebar input[type="tel"],
        #checkout-sidebar textarea {
            width: 100%;
            padding: 0.6rem 0.8rem;
            margin-bottom: 1rem;
            background-color: #495057;
            border: 1px solid #6c757d;
            color: #f8f9fa;
            border-radius: 4px;
            box-sizing: border-box;
            font-family: inherit;
        }

        /* Added box-sizing */
        #checkout-sidebar textarea {
            resize: vertical;
        }

        #checkout-sidebar input:focus,
        #checkout-sidebar textarea:focus {
            outline: none;
            border-color: #198754;
            box-shadow: 0 0 0 2px rgba(25, 135, 84, 0.25);
        }
    </style>


    <!-- JavaScript uses the corrected PHP variables for QR -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // --- DOM Element Selection ---
            const quantityInputs = document.querySelectorAll('.quantity-input');
            const mainFinalTotalValueElement = document.querySelector('.cart-total .final-total-value');
            const cartTableBody = document.querySelector('.cart-table tbody');

            const openCheckoutBtn = document.getElementById('open-checkout-sidebar-btn');
            const closeCheckoutBtn = document.getElementById('close-checkout-sidebar-btn');
            const cancelCheckoutBtn = document.getElementById('cancel-checkout-btn');
            const checkoutSidebar = document.getElementById('checkout-sidebar');
            const checkoutOverlay = document.getElementById('checkout-overlay');
            const sidebarItemsContainer = document.getElementById('sidebar-cart-items');
            const sidebarShippingRow = document.getElementById('sidebar-shipping-fee');
            const sidebarTotalRow = document.getElementById('sidebar-cart-total');
            const sidebarTotalAmount = sidebarTotalRow ? sidebarTotalRow.querySelector('.total-amount') : null;

            const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
            const qrCodeArea = document.getElementById('qr-code-area');
            const qrImageContainer = document.getElementById('qr-image-container');
            const vietQrImage = document.getElementById('vietqr-image');
            const qrLoading = document.getElementById('qr-loading');
            const qrError = document.getElementById('qr-error'); // Get the error placeholder
            const qrAmount = document.getElementById('qr-amount');
            const qrPurpose = document.getElementById('qr-purpose');

            const shippingFee = <?php echo SHIPPING_FEE; ?>;

            // --- Helper Functions ---
            function formatCurrency(value) {
                if (isNaN(value)) return '0 VNĐ';
                return Number(value).toLocaleString('de-DE', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                }) + ' VNĐ';
            }

            function escapeHtml(unsafe) { // Basic escaping
                if (typeof unsafe !== 'string') return '';
                return unsafe
                    .replace(/&/g, "&") // Use entities
                    .replace(/</g, "<")
                    .replace(/>/g, ">")
                    .replace(/"/g, "")
                    .replace(/'/g, "'");
            }

            // --- Update Totals ---
            function updateAllCartTotals() {
                let currentSubtotal = 0;
                if (!cartTableBody) {
                    if (mainFinalTotalValueElement) mainFinalTotalValueElement.textContent = formatCurrency(0);
                    updateSidebarTotals(0, 0);
                    return;
                }
                const rows = cartTableBody.querySelectorAll('tr');
                if (rows.length === 0) {
                    if (mainFinalTotalValueElement) mainFinalTotalValueElement.textContent = formatCurrency(0);
                    updateSidebarTotals(0, 0);
                    return;
                }

                rows.forEach(row => {
                    const input = row.querySelector('.quantity-input');
                    const price = parseFloat(input?.dataset.price);
                    const quantity = parseInt(input?.value, 10);
                    if (!isNaN(price) && price >= 0 && !isNaN(quantity) && quantity >= 1) {
                        currentSubtotal += price * quantity;
                    }
                });

                let currentShipping = (currentSubtotal > 0) ? shippingFee : 0;
                if (mainFinalTotalValueElement) {
                    mainFinalTotalValueElement.textContent = formatCurrency(currentSubtotal);
                }
                updateSidebarTotals(currentSubtotal, currentShipping);
            }

            // --- Update Sidebar Totals Display ---
            function updateSidebarTotals(subtotal, shippingCost) {
                const finalTotal = subtotal + shippingCost;
                let showDetails = subtotal > 0;
                if (sidebarShippingRow) {
                    sidebarShippingRow.style.display = (showDetails && shippingCost > 0) ? 'flex' : 'none';
                }
                if (sidebarTotalRow && sidebarTotalAmount) {
                    if (showDetails) {
                        sidebarTotalAmount.textContent = formatCurrency(finalTotal);
                        sidebarTotalRow.style.display = 'flex';
                    } else {
                        sidebarTotalRow.style.display = 'none';
                    }
                }
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
                        updateAllCartTotals();
                        return;
                    }
                    if (isNaN(quantity) || quantity < 1) {
                        quantity = 1;
                        currentInput.value = quantity;
                    }
                    if (subtotalCell) {
                        subtotalCell.textContent = formatCurrency(price * quantity);
                    }
                    updateAllCartTotals();
                });
            });

            // --- Sidebar Logic ---
            function populateSidebarItems() {
                if (!cartTableBody || !sidebarItemsContainer) {
                    if (sidebarItemsContainer) sidebarItemsContainer.innerHTML = '<p>Lỗi tải giỏ hàng.</p>';
                    updateSidebarTotals(0, 0);
                    return;
                }
                sidebarItemsContainer.innerHTML = '';
                const rows = cartTableBody.querySelectorAll('tr');
                if (rows.length === 0) {
                    sidebarItemsContainer.innerHTML = '<p>Giỏ hàng trống.</p>';
                } else {
                    rows.forEach(row => {
                        const nameElement = row.querySelector('.cart-item-name a');
                        const quantityInput = row.querySelector('.quantity-input');
                        const price = parseFloat(quantityInput?.dataset.price);
                        const quantity = parseInt(quantityInput?.value, 10);
                        if (nameElement && !isNaN(price) && !isNaN(quantity) && quantity >= 1) {
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
                updateAllCartTotals(); // Update totals after populating
            }

            function openSidebar() {
                populateSidebarItems(); // Populate first
                if (checkoutSidebar && checkoutOverlay) {
                    checkoutOverlay.classList.add('active');
                    checkoutSidebar.classList.add('active');
                    document.body.style.overflow = 'hidden';
                    // Check if online payment is selected and generate QR
                    const checkedOnline = document.querySelector('input[name="payment_method"][value="online"]:checked');
                    if (checkedOnline && qrCodeArea) {
                        generateAndDisplayQR();
                    } else if (qrCodeArea) {
                        qrCodeArea.style.display = 'none';
                    }
                }
            }

            function closeSidebar() {
                if (checkoutSidebar && checkoutOverlay) {
                    checkoutOverlay.classList.remove('active');
                    checkoutSidebar.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }

            // --- Generate VietQR ---
            function generateAndDisplayQR() {
                // Check crucial elements
                if (!qrCodeArea || !vietQrImage || !qrLoading || !qrError || !qrAmount || !qrPurpose || !sidebarTotalAmount) {
                    console.error("QR Code elements missing for generation.");
                    if (qrCodeArea) qrCodeArea.innerHTML = '<p style="color: red;">Lỗi: Thiếu thành phần QR.</p>';
                    return;
                }

                // UI updates for loading
                qrCodeArea.style.display = 'block';
                qrLoading.style.display = 'block';
                vietQrImage.style.display = 'none';
                qrError.style.display = 'none'; // Hide previous error
                vietQrImage.src = '';
                qrAmount.textContent = '...';
                qrPurpose.textContent = '';

                // Get amount from sidebar
                const totalText = sidebarTotalAmount.textContent;
                let amount = 0;
                try {
                    const cleanedTotalText = totalText.replace(/ VNĐ/g, '').replace(/\./g, '');
                    amount = parseInt(cleanedTotalText, 10);
                    if (isNaN(amount) || amount <= 0) throw new Error(`Invalid amount: ${amount}`);
                } catch (e) {
                    console.error("Error parsing amount for QR:", e);
                    qrLoading.style.display = 'none';
                    qrError.textContent = "Lỗi lấy tổng tiền.";
                    qrError.style.display = 'block';
                    return;
                }

                // Get bank details from PHP (ensure correct escaping)
                const bankBin = '<?php echo htmlspecialchars($bank_bin, ENT_QUOTES, 'UTF-8'); ?>';
                const bankNumber = '<?php echo htmlspecialchars($bank_number, ENT_QUOTES, 'UTF-8'); ?>';
                const orderPurpose = `Thanh toan FoodNow ${Date.now()}`; // Unique purpose
                const accountName = '<?php echo htmlspecialchars($account_name ?: "FoodNow", ENT_QUOTES, 'UTF-8'); ?>';

                // Validate essential bank details
                if (!bankBin || !bankNumber) {
                    console.error("Bank BIN or Number is missing in PHP variables.");
                    qrLoading.style.display = 'none';
                    qrError.textContent = "Lỗi cấu hình thanh toán.";
                    qrError.style.display = 'block';
                    return;
                }

                const qrUrl = `https://img.vietqr.io/image/${bankBin}-${bankNumber}-print.png?amount=${amount}&addInfo=${encodeURIComponent(orderPurpose)}&accountName=${encodeURIComponent(accountName)}`;
                console.log("Generating QR URL:", qrUrl); // Log for debugging

                // Set image source and handlers
                vietQrImage.onload = () => {
                    console.log("QR Image loaded.");
                    qrLoading.style.display = 'none';
                    vietQrImage.style.display = 'block'; // Show the loaded image
                    qrError.style.display = 'none';
                    qrAmount.textContent = formatCurrency(amount);
                    qrPurpose.textContent = `Nội dung: ${escapeHtml(orderPurpose)}`;
                };
                vietQrImage.onerror = () => {
                    console.error("Failed to load VietQR image from URL:", qrUrl);
                    qrLoading.style.display = 'none';
                    vietQrImage.style.display = 'none'; // Keep hidden on error
                    qrError.textContent = "Không thể tải mã QR. Vui lòng kiểm tra lại.";
                    qrError.style.display = 'block';
                    qrAmount.textContent = formatCurrency(amount); // Still show amount
                    qrPurpose.textContent = '';
                };
                vietQrImage.src = qrUrl; // Trigger image load
            }

            // --- Event Listeners ---
            if (openCheckoutBtn) openCheckoutBtn.addEventListener('click', openSidebar);
            if (closeCheckoutBtn) closeCheckoutBtn.addEventListener('click', closeSidebar);
            if (cancelCheckoutBtn) cancelCheckoutBtn.addEventListener('click', closeSidebar);
            if (checkoutOverlay) checkoutOverlay.addEventListener('click', closeSidebar);

            // Payment Radio Listener
            paymentRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (qrCodeArea) {
                        if (this.value === 'online' && this.checked) {
                            generateAndDisplayQR();
                        } else {
                            qrCodeArea.style.display = 'none';
                            if (vietQrImage) vietQrImage.src = '';
                            if (qrLoading) qrLoading.style.display = 'none';
                            if (qrError) qrError.style.display = 'none';
                        }
                    }
                });
            });

            // --- Initial Setup ---
            updateAllCartTotals(); // Initial calculation

            // Hide QR area initially if COD is selected
            const checkedPayment = document.querySelector('input[name="payment_method"]:checked');
            if (qrCodeArea && (!checkedPayment || checkedPayment.value !== 'online')) {
                qrCodeArea.style.display = 'none';
            }

            // Add error placeholder if missing
            if (qrCodeArea && !document.getElementById('qr-error')) {
                const errorP = document.createElement('p');
                errorP.id = 'qr-error';
                errorP.style.display = 'none';
                errorP.style.color = 'red'; // Or use your alert-danger color
                qrImageContainer.parentNode.insertBefore(errorP, qrImageContainer.nextSibling); // Insert after container
                // Re-assign qrError variable AFTER creating the element
                // Note: This part is tricky as the original qrError might be null initially.
                // It's better to ensure qr-error exists in the HTML from the start.
                // Let's assume it exists for now based on the code structure.
                // If it really doesn't, the check at the start of generateAndDisplayQR will fail.
            }

        });
    </script>

</body>

</html>