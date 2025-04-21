<?php
// Ensure session is started FIRST
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- CONFIGURATION & INITIALIZATION ---
$config_path = __DIR__ . '/config/admin_config.php';
if (!file_exists($config_path)) $config_path = __DIR__ . '/../config/admin_config.php';

if (file_exists($config_path)) {
    include $config_path;
} else {
    error_log("CRITICAL ERROR: Could not find admin_config.php.");
    die("Lỗi hệ thống: Không thể tải tệp cấu hình.");
}

// --- Security Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["role"]) || $_SESSION["role"] !== 'admin') {
    header("location: login.php");
    exit;
}

// --- Determine Request Type ---
$is_ajax_request = isset($_REQUEST['ajax']) && $_REQUEST['ajax'] == '1';

// --- Helper Functions (Copy from previous "Live Search" or "Design Resemble" answer) ---
// <<< PASTE get_grouped_orders HERE >>>
// <<< PASTE generate_order_table_body HERE (Ensure it uses new action styles) >>>
// <<< PASTE selected HERE >>>

// --- UPDATED generate_order_table_body with new Action Styles ---
function generate_order_table_body(array $grouped_orders, string $search_term = ''): string {
    ob_start();
    if (!empty($grouped_orders)) {
        foreach ($grouped_orders as $group_key => $order) {
            // Prepare data for the row
            $customer_display_name = 'Khách vãng lai';
            if (!empty($order['customer_full_name'])) $customer_display_name = $order['customer_full_name'];
            elseif (!empty($order['user_table_username'])) $customer_display_name = $order['user_table_username'];
            elseif (!empty($order['order_table_username'])) $customer_display_name = $order['order_table_username'];
            elseif ($order['user_id']) $customer_display_name = 'User ID: ' . htmlspecialchars($order['user_id']);

            $pm_class = '';
            $payment_method_display = strtoupper(htmlspecialchars($order['payment_method'] ?? 'N/A'));
            if ($payment_method_display === 'COD') $pm_class = 'pm-cod';
            elseif ($payment_method_display === 'ONLINE') $pm_class = 'pm-online';

            $display_date_str = $order['first_added_at'] ?? $order['last_added_at']; // Use earliest for order time
            $display_date = $display_date_str ? date("d/m/y H:i", strtotime($display_date_str)) : 'N/A';

            $full_address = htmlspecialchars($order['recipient_address'] ?? '');
            $short_address = mb_strimwidth($full_address, 0, 60, "...");
            ?>
            <tr>
                <td data-label="Khách hàng"><?php echo htmlspecialchars($customer_display_name); ?></td>
                <td data-label="Người nhận"><?php echo htmlspecialchars($order['recipient_name'] ?? '-'); ?></td>
                <td data-label="SĐT Nhận"><?php echo htmlspecialchars($order['recipient_phone'] ?? '-'); ?></td>
                <td data-label="Địa chỉ Giao" class="address-col" title="<?php echo $full_address ?: 'Không có địa chỉ'; ?>">
                    <?php echo nl2br($short_address ?: '-'); ?>
                </td>
                <td data-label="Sản phẩm & Trạng thái" class="items-col">
                    <ul class="items-list">
                        <?php if (!empty($order['items'])): ?>
                            <?php foreach ($order['items'] as $item):
                                // Prepare item data
                                $item_id = htmlspecialchars($item['item_row_id'] ?? '');
                                $item_name = htmlspecialchars($item['food_name'] ?? 'N/A');
                                $item_quantity = htmlspecialchars($item['quantity'] ?? 0);
                                $item_price = $item['price_at_add'] ?? 0;
                                $item_status = htmlspecialchars($item['status'] ?? 'unknown');
                                $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
                            ?>
                                <li>
                                    <div class="item-details">
                                        <span class="item-name"><?php echo $item_name; ?></span>
                                        <span class="item-qty-price">
                                            SL: <?php echo $item_quantity; ?>
                                            @ <?php echo number_format($item_price, 0, ',', '.'); ?> đ
                                            <span class="item-id">(ID: #<?php echo $item_id; ?>)</span>
                                        </span>
                                    </div>
                                    <div class="item-actions">
                                        <!-- Styled Select (approximates blue "Sửa" button) -->
                                         <div class="action-select-wrapper">
                                             <select class="order-status-select styled-select" data-item-id="<?php echo $item_id; ?>" data-original-status="<?php echo $item_status; ?>" aria-label="Cập nhật trạng thái cho mục <?php echo $item_name; ?>">
                                                <option value="pending" <?php selected($item_status, 'pending'); ?>>Chờ xử lý</option>
                                                <option value="processing" <?php selected($item_status, 'processing'); ?>>Đang xử lý</option>
                                                <option value="shipped" <?php selected($item_status, 'shipped'); ?>>Đã giao</option>
                                                <option value="delivered" <?php selected($item_status, 'delivered'); ?>>Hoàn thành</option>
                                                <option value="cancelled" <?php selected($item_status, 'cancelled'); ?>>Đã hủy</option>
                                                <?php if (!in_array($item_status, $valid_statuses) && $item_status !== 'cart' && !empty($item_status)): ?>
                                                    <option value="<?php echo $item_status; ?>" selected><?php echo htmlspecialchars(ucfirst($item_status)); ?> (Hiện tại)</option>
                                                <?php endif; ?>
                                            </select>
                                            <i class="fas fa-sync-alt select-icon"></i> <!-- Icon for select -->
                                         </div>
                                         <!-- Styled Delete Button -->
                                         <button class="action-btn delete-item-btn"
                                                 data-item-id="<?php echo $item_id; ?>"
                                                 data-price="<?php echo htmlspecialchars($item_price); ?>"
                                                 data-quantity="<?php echo $item_quantity; ?>"
                                                 title="Xóa mục <?php echo $item_name; ?> (ID: #<?php echo $item_id; ?>)"
                                                 aria-label="Xóa mục <?php echo $item_name; ?>">
                                             <i class="fas fa-trash fa-fw"></i> <span>Xóa</span>
                                         </button>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>Không có sản phẩm nào trong đơn hàng này.</li>
                        <?php endif; ?>
                    </ul>
                </td>
                <td data-label="Tổng tiền" class="total-price-row">
                    <?php echo number_format($order['total_price'] ?? 0, 0, ',', '.'); ?> đ
                </td>
                <td data-label="PTTT" class="payment-method <?php echo $pm_class; ?>">
                    <?php echo $payment_method_display; ?>
                </td>
                <td data-label="Ngày đặt" style="white-space: nowrap;">
                    <?php echo $display_date; ?>
                </td>
            </tr>
            <?php
        } // end foreach grouped_orders
    } else {
        // --- No Results Row ---
        ?>
        <tr class="no-results">
            <td colspan="8">
                <?php if (!empty($search_term)): ?>
                    Không tìm thấy đơn hàng nào phù hợp với tìm kiếm: "<strong><?php echo htmlspecialchars($search_term); ?></strong>"
                <?php else: ?>
                    Hiện tại không có đơn hàng nào (ngoài trạng thái 'cart').
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }
    return ob_get_clean(); // Return the buffered HTML content
}

// --- PASTE get_grouped_orders and selected functions here ---
function get_grouped_orders(PDO $pdo, string $search_term = '', ?string $status_filter = null): array {
    // Base SQL query
    $sql = "SELECT o.id AS item_row_id, o.user_id, o.username AS order_table_username,
                   o.food_id, o.food_name, o.quantity, o.price_at_add,
                   o.status, o.added_at,
                   o.recipient_name, o.recipient_phone, o.recipient_address, o.payment_method,
                   u.username AS user_table_username, u.full_name AS customer_full_name
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.status != 'cart'";
    $params = [];

    // Status Filter (Added)
    if (!empty($status_filter) && $status_filter !== 'all') {
         $sql .= " AND o.status = :status_filter";
         $params[':status_filter'] = $status_filter;
    }


    // Search Filter
    if (!empty($search_term)) {
        $sql .= " AND (CAST(o.id AS CHAR) LIKE :search_term_id
                    OR o.username LIKE :search_term_order_user
                    OR u.username LIKE :search_term_table_user
                    OR u.full_name LIKE :search_term_fullname
                    OR o.food_name LIKE :search_term_food
                    OR o.recipient_name LIKE :search_term_recipient
                    OR o.recipient_phone LIKE :search_term_phone)";
        $search_like = "%" . $search_term . "%";
        $params[':search_term_id'] = $search_like;
        $params[':search_term_order_user'] = $search_like;
        $params[':search_term_table_user'] = $search_like;
        $params[':search_term_fullname'] = $search_like;
        $params[':search_term_food'] = $search_like;
        $params[':search_term_recipient'] = $search_like;
        $params[':search_term_phone'] = $search_like;
    }

     $sql .= " ORDER BY o.added_at DESC"; // Order primarily by date

    $grouped_orders = [];
    $order_keys_processed = [];

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $order_items_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($order_items_list)) {
            // --- GROUPING LOGIC ---
            $temp_grouped = [];
            foreach ($order_items_list as $item) {
                // Grouping Key
                $user_id_key = $item['user_id'] ?? 'guest_' . md5(strtolower(trim($item['recipient_name'] ?? '')).strtolower(trim($item['recipient_phone'] ?? '')));
                $recipient_name_key = strtolower(trim($item['recipient_name'] ?? ''));
                $recipient_phone_key = strtolower(trim($item['recipient_phone'] ?? ''));
                $recipient_address_key = strtolower(trim($item['recipient_address'] ?? ''));
                $payment_method_key = strtolower(trim($item['payment_method'] ?? 'unknown'));
                $order_date_hour = date('Y-m-d-H', strtotime($item['added_at'] ?? time())); // Group by hour to separate similar orders

                $group_key = $user_id_key . '_' . md5($recipient_name_key) . '_' . md5($recipient_phone_key) . '_' . md5($recipient_address_key) . '_' . $payment_method_key . '_' . $order_date_hour;


                if (!isset($temp_grouped[$group_key])) {
                     $temp_grouped[$group_key] = [
                        'order_key' => $group_key, 'user_id' => $item['user_id'], 'order_table_username' => $item['order_table_username'],
                        'user_table_username' => $item['user_table_username'], 'customer_full_name' => $item['customer_full_name'],
                        'recipient_name' => $item['recipient_name'], 'recipient_phone' => $item['recipient_phone'],
                        'recipient_address' => $item['recipient_address'], 'payment_method' => $item['payment_method'],
                        'first_added_at' => $item['added_at'], 'last_added_at' => $item['added_at'],
                        'items' => [], 'total_price' => 0, 'status_counts' => [],
                    ];
                }
                $temp_grouped[$group_key]['items'][] = $item;
                $quantity = is_numeric($item['quantity']) ? (int)$item['quantity'] : 0;
                $price = is_numeric($item['price_at_add']) ? (float)$item['price_at_add'] : 0;
                $temp_grouped[$group_key]['total_price'] += ($quantity * $price);
                $current_item_status = $item['status'] ?? 'unknown';
                if (!isset($temp_grouped[$group_key]['status_counts'][$current_item_status])) { $temp_grouped[$group_key]['status_counts'][$current_item_status] = 0; }
                 $temp_grouped[$group_key]['status_counts'][$current_item_status]++;
                if (isset($item['added_at']) && strtotime($item['added_at']) < strtotime($temp_grouped[$group_key]['first_added_at'])) { $temp_grouped[$group_key]['first_added_at'] = $item['added_at']; }
                if (isset($item['added_at']) && strtotime($item['added_at']) > strtotime($temp_grouped[$group_key]['last_added_at'])) { $temp_grouped[$group_key]['last_added_at'] = $item['added_at']; }
            }
             uasort($temp_grouped, function($a, $b) { $time_a = strtotime($a['last_added_at'] ?? $a['first_added_at'] ?? 0); $time_b = strtotime($b['last_added_at'] ?? $b['first_added_at'] ?? 0); return $time_b <=> $time_a; });
             $grouped_orders = $temp_grouped;
        }
    } catch (PDOException $e) { error_log("Database Error in get_grouped_orders(): " . $e->getMessage()); return []; }
    return $grouped_orders;
}

function selected($current, $value) {
    if ($current === $value) { echo 'selected'; }
}

// --- Main Logic (Continued) ---
$admin_username = isset($_SESSION["username"]) ? htmlspecialchars($_SESSION["username"]) : 'Admin';
$message = ''; $msg_type = '';

$search_term = ''; if (isset($_REQUEST['search']) && is_string($_REQUEST['search'])) { $search_term = trim($_REQUEST['search']); }
$status_filter = null; if (isset($_REQUEST['status_filter']) && is_string($_REQUEST['status_filter'])) { $status_filter = trim($_REQUEST['status_filter']); }

// --- AJAX Request Handling ---
if ($is_ajax_request) {
    $grouped_orders_data = get_grouped_orders($pdo, $search_term, $status_filter);
    $table_body_html = generate_order_table_body($grouped_orders_data, $search_term);
    header('Content-Type: text/html; charset=utf-8');
    echo $table_body_html;
    exit;
}

// --- Full Page Load Handling ---
$total_orders_count = 0; $pending_orders_count = 0; $todays_revenue = 0;
try {
    $initial_orders_for_stats = get_grouped_orders($pdo);
    $total_orders_count = count($initial_orders_for_stats);
    foreach ($initial_orders_for_stats as $order) { if (isset($order['status_counts']['pending']) && $order['status_counts']['pending'] > 0) { $pending_orders_count++; } }
    $stmt_revenue = $pdo->prepare("SELECT SUM(price_at_add * quantity) as total_revenue FROM orders WHERE status != 'cart' AND DATE(added_at) = CURDATE()");
    $stmt_revenue->execute(); $revenue_result = $stmt_revenue->fetch(PDO::FETCH_ASSOC); $todays_revenue = $revenue_result['total_revenue'] ?? 0;
} catch (PDOException $e) { error_log("Error calculating summary stats: " . $e->getMessage()); }

$grouped_orders_data = get_grouped_orders($pdo, $search_term, $status_filter);
$table_body_html = generate_order_table_body($grouped_orders_data, $search_term);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Quản lý Đơn hàng</title>
    <!-- Use Poppins font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Link to external CSS OR use inline styles -->
    <!-- <link rel="stylesheet" href="css/admin_orders_theme.css"> -->

    <style>
        /* --- TARGETED THEME STYLES --- */
        :root {
            --sidebar-bg: #2c3e50;
            --sidebar-text: #bdc3c7;
            --sidebar-hover-bg: #34495e;
            --sidebar-hover-text: #ffffff;
            --sidebar-active-bg: #e74c3c; /* Matching active state from image */
            --sidebar-active-border: #ffffff; /* White left border on active */
            --sidebar-width: 250px;
            --collapsed-sidebar-width: 70px;

            --header-bg: #ffffff;
            --header-text: #2c3e50;
            --header-shadow: 0 2px 4px rgba(0,0,0,0.05);
            --search-border: #ced4da;
            --search-focus-border: #f39c12; /* Orange focus */
            --search-button-bg: #fd7e14; /* Orange button */
            --search-button-text: #ffffff;

            --body-bg: #f4f7f6;
            --card-bg: #ffffff;
            --card-border: #e0e0e0;
            --card-shadow: 0 2px 5px rgba(0, 0, 0, 0.06);
            --text-color: #34495e; /* Dark blue-gray text */
            --text-muted: #7f8c8d; /* Muted gray */
            --heading-color: #2c3e50; /* Darker heading */

            --stat-orders-icon-bg: #e7f3ff;
            --stat-orders-icon-color: #3498db; /* Blue */
            --stat-pending-icon-bg: #fff8e1;
            --stat-pending-icon-color: #f39c12; /* Orange/Yellow */
            --stat-revenue-icon-bg: #e8f5e9;
            --stat-revenue-icon-color: #2ecc71; /* Green */
            --stat-customer-icon-bg: #fff3e0; /* Light orange */
            --stat-customer-icon-color: #e67e22; /* Darker orange */

            --table-header-bg: #f8f9fa;
            --table-header-text: #34495e;
            --table-border: #e0e0e0;
            --table-hover-bg: #f1f1f1;

            --action-edit-bg: #eaf2fb; /* Light blue */
            --action-edit-text: #3498db; /* Blue */
            --action-edit-hover-bg: #3498db;
            --action-edit-hover-text: #ffffff;
            --action-delete-bg: #fce8e6; /* Light red */
            --action-delete-text: #e74c3c; /* Red */
            --action-delete-hover-bg: #e74c3c;
            --action-delete-hover-text: #ffffff;

            --primary-button-bg: #e67e22; /* Orange "Thêm" button */
            --primary-button-text: #ffffff;

             /* Payment Methods */
            --pm-cod-color: #fd7e14; /* Orange */
            --pm-online-color: #3498db; /* Blue */

             /* Total Price */
            --total-price-color: #2980b9; /* Slightly darker blue */
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--body-bg); color: var(--text-color); display: flex; min-height: 100vh; font-size: 14px; }
        .admin-container { display: flex; width: 100%; }

        /* Sidebar */
        .sidebar { width: var(--sidebar-width); background-color: var(--sidebar-bg); color: var(--sidebar-text); padding-top: 20px; height: 100vh; position: fixed; left: 0; top: 0; overflow-y: auto; transition: width 0.3s ease; z-index: 1000; }
        .sidebar-header { padding: 0 20px 20px 20px; text-align: center; border-bottom: 1px solid #495057; }
        .sidebar-header .logo { color: #fff; text-decoration: none; font-size: 1.6em; font-weight: 600; display: block; }
        .sidebar-nav ul { list-style: none; margin-top: 20px; padding: 0; }
        .sidebar-nav li a { display: flex; align-items: center; padding: 13px 20px; color: var(--sidebar-text); text-decoration: none; transition: all 0.2s ease; white-space: nowrap; overflow: hidden; border-left: 4px solid transparent; }
        .sidebar-nav li a i.fa-fw { width: 22px; text-align: center; margin-right: 16px; font-size: 1.05em; }
        .sidebar-nav li a span { opacity: 1; transition: opacity 0.2s ease 0.1s; font-size: 0.95em; }
        .sidebar-nav li a:hover { background-color: var(--sidebar-hover-bg); color: var(--sidebar-hover-text); border-left-color: var(--sidebar-active-bg); }
        .sidebar-nav li.active a { background-color: var(--sidebar-active-bg); color: var(--sidebar-active-text); font-weight: 500; border-left-color: var(--sidebar-active-border); }

        /* Main Content */
        .main-content { flex-grow: 1; padding: 25px; margin-left: var(--sidebar-width); transition: margin-left 0.3s ease; }
        .main-header { background-color: var(--header-bg); padding: 12px 25px; border-radius: 8px; box-shadow: var(--header-shadow); display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; position: sticky; top: 0; z-index: 999; }
        .header-title { display: flex; align-items: center; }
        .header-title h1 { font-size: 1.6em; margin-left: 15px; color: var(--heading-color); font-weight: 600; }
        .header-menu-toggle { background: none; border: none; font-size: 1.5em; cursor: pointer; color: var(--header-text); display: none; }
        .header-user { display: flex; align-items: center; gap: 15px; }
        .header-user form { display: flex; align-items: center; position: relative; }
        .header-user input[type="search"] { padding: 9px 40px 9px 18px; border: 1px solid var(--search-border); border-radius: 25px; /* Fully rounded */ font-size: 0.9em; outline: none; min-width: 250px; height: 40px; transition: border-color 0.2s ease; }
        .header-user input[type="search"]:focus { border-color: var(--search-focus-border); }
        /* Use orange button if needed */
        .header-user .search-btn-visible { padding: 0 15px; border: none; background-color: var(--search-button-bg); color: var(--search-button-text); border-radius: 0 25px 25px 0; cursor: pointer; height: 40px; display: flex; align-items: center; justify-content: center; margin-left: -25px; /* Overlap */ z-index: 2; }
        .header-user .search-btn { display: none; } /* Hides default submit */
        .search-loading { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); font-size: 1em; color: var(--text-muted); display: none; }
        .search-loading.active { display: block; }
        .user-info { display: flex; align-items: center; cursor: pointer; position: relative; }
        .user-info .avatar { width: 40px; height: 40px; border-radius: 50%; margin-right: 10px; object-fit: cover; }
        .user-info span { font-weight: 500; margin-right: 5px; color: var(--header-text); }
        .user-info i.fa-caret-down { font-size: 0.8em; color: var(--text-muted); }
        .user-dropdown { display: none; position: absolute; top: 115%; right: 0; background-color: var(--card-bg); border: 1px solid var(--card-border); border-radius: 6px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); min-width: 140px; z-index: 1001; overflow: hidden; }
        .user-dropdown a { display: block; padding: 10px 15px; color: var(--text-color); text-decoration: none; font-size: 0.9em; white-space: nowrap; }
        .user-dropdown a:hover { background-color: #f1f1f1; }
        .user-info:hover .user-dropdown { display: block; }

        /* Stats Cards */
        .stats-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .stats-card { background-color: var(--card-bg); border-radius: 8px; padding: 20px 25px; display: flex; align-items: center; box-shadow: var(--card-shadow); border: 1px solid var(--card-border); }
        .stats-icon { font-size: 2.2em; margin-right: 20px; width: 55px; height: 55px; display: flex; align-items: center; justify-content: center; border-radius: 50%; flex-shrink: 0; }
        .stats-icon.icon-orders { background-color: var(--stat-orders-icon-bg); color: var(--stat-orders-icon-color); }
        .stats-icon.icon-pending { background-color: var(--stat-pending-icon-bg); color: var(--stat-pending-icon-color); }
        .stats-icon.icon-revenue { background-color: var(--stat-revenue-icon-bg); color: var(--stat-revenue-icon-color); }
        .stats-icon.icon-customer { background-color: var(--stat-customer-icon-bg); color: var(--stat-customer-icon-color); }
        .stats-info .stats-value { font-size: 1.7em; font-weight: 600; color: var(--heading-color); display: block; line-height: 1.2; }
        .stats-info .stats-label { font-size: 0.9em; color: var(--text-muted); }

        /* Table Controls */
        .table-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; background-color: var(--card-bg); padding: 15px 20px; border-radius: 8px; box-shadow: var(--card-shadow); border: 1px solid var(--card-border); }
        .table-controls h2 { font-size: 1.25em; font-weight: 600; color: var(--heading-color); margin: 0; }
        .table-filters { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; } /* Allow wrapping */
        .table-filters label { font-weight: 500; color: var(--text-muted); font-size: 0.9em; }
        .table-filters select, .table-filters input { padding: 8px 12px; border: 1px solid var(--card-border); border-radius: 6px; font-size: 0.9em; height: 38px; background-color: #fff; color: var(--text-color); }
        .table-filters select { min-width: 180px; } /* Give select some width */
        .btn-primary-action { background-color: var(--primary-button-bg); color: var(--primary-button-text); border: none; padding: 9px 18px; border-radius: 6px; font-weight: 500; font-size: 0.9em; cursor: pointer; transition: background-color 0.2s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;}
        .btn-primary-action:hover { background-color: #d35400; } /* Darker orange */

        /* Table */
        .table-container { overflow-x: auto; background-color: var(--card-bg); padding: 0; border-radius: 8px; box-shadow: var(--card-shadow); border: 1px solid var(--card-border); }
        .table-container table { width: 100%; border-collapse: collapse; }
        .table-container th, .table-container td { padding: 14px 18px; border-bottom: 1px solid var(--table-border); text-align: left; vertical-align: middle; /* Align middle like target */ font-size: 0.9em; }
        .table-container th { background-color: var(--table-header-bg); font-weight: 600; white-space: nowrap; color: var(--table-header-text); border-top: 1px solid var(--table-border); border-bottom-width: 1px; text-transform: uppercase; font-size: 0.85em; letter-spacing: 0.5px; }
        .table-container tbody tr:hover { background-color: var(--table-hover-bg); }
        .table-container tbody tr td { color: var(--text-color); }
        .table-container tbody tr:last-child td { border-bottom: none; }

        /* Specific Columns */
        td.address-col { max-width: 200px; white-space: normal; }
        td.items-col { min-width: 300px; vertical-align: top; } /* Keep top align for items */
        td.total-price-row { font-weight: 600; font-size: 0.95em; white-space: nowrap; color: var(--total-price-color); }
        td.payment-method { text-transform: uppercase; font-weight: 500; white-space: nowrap; }
        .pm-cod { color: var(--pm-cod-color); }
        .pm-online { color: var(--pm-online-color); }

        /* Item List Inside Cell */
        .items-list { list-style: none; padding: 5px 0; margin: 0; }
        .items-list li { border-bottom: 1px dashed #e9ecef; padding: 8px 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .items-list li:last-child { border-bottom: none; padding-bottom: 0; }
        .item-details { flex-grow: 1; margin-right: 10px; }
        .item-name { font-weight: 500; display: block; margin-bottom: 3px; color: var(--heading-color); }
        .item-qty-price { font-size: 0.9em; color: var(--text-muted); }
        .item-qty-price .item-id { font-size: 0.9em; color: #bdc3c7; margin-left: 5px; }
        .item-actions { white-space: nowrap; display: flex; align-items: center; gap: 6px; }

        /* Action Button/Select Styling */
        .action-btn, .styled-select { border: none; border-radius: 5px; /* Match target */ padding: 5px 10px; /* Match target */ font-size: 0.85em; font-weight: 500; cursor: pointer; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 5px; line-height: 1.4; }
        .action-btn i, .select-icon { font-size: 0.9em; }
        /* Delete Button */
        .action-btn.delete-item-btn { background-color: var(--action-delete-bg); color: var(--action-delete-text); }
        .action-btn.delete-item-btn:hover { background-color: var(--action-delete-hover-bg); color: var(--action-delete-hover-text); }
        .action-btn.delete-item-btn span { display: inline; /* Show text */ }
         .action-btn.delete-item-btn:disabled { background-color: #f5c6cb; color: #721c24; cursor: not-allowed; }
         .action-btn.delete-item-btn:disabled i { color: #721c24; }
        /* Select Wrapper */
        .action-select-wrapper { position: relative; display: inline-block; }
        .styled-select { appearance: none; -webkit-appearance: none; -moz-appearance: none; background-color: var(--action-edit-bg); color: var(--action-edit-text); padding-right: 28px; border: 1px solid transparent; /* No border initially */ height: 29px; /* Match button height */ min-width: 100px; /* Give some base width */ }
        .styled-select:hover { background-color: var(--action-edit-hover-bg); color: var(--action-edit-hover-text); }
         .styled-select:hover + .select-icon { color: var(--action-edit-hover-text); } /* Change icon color on hover */
        .styled-select:disabled { background-color: #e9ecef; color: #adb5bd; cursor: not-allowed; opacity: 0.7; }
        .select-icon { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--action-edit-text); font-size: 0.8em; transition: color 0.2s ease; }
        .styled-select:focus { outline: none; box-shadow: 0 0 0 2px rgba(0,123,255,.25); /* Standard focus */ }

        /* No Results */
        .no-results td { text-align: center !important; padding: 40px 15px !important; color: var(--text-muted); font-style: italic; }

        /* Responsive */
        /* Keep previous responsive rules, adjust values if needed */
        @media (max-width: 992px) {
            .admin-container:not(.sidebar-force-open) .sidebar { width: var(--collapsed-sidebar-width); }
            .admin-container:not(.sidebar-force-open) .main-content { margin-left: var(--collapsed-sidebar-width); }
            .admin-container:not(.sidebar-force-open) .sidebar-nav li a span { opacity: 0; width: 0; }
            .admin-container:not(.sidebar-force-open) .sidebar-nav li a i.fa-fw { margin-right: 0; }
            .admin-container:not(.sidebar-force-open) .sidebar-nav li a { justify-content: center; }
            .header-title h1 { font-size: 1.5em; }
            .header-user input[type="search"] { min-width: 200px; }
            .header-menu-toggle { display: block; margin-right: 10px; }
            .stats-card { flex-direction: column; align-items: flex-start; text-align: left; }
            .stats-icon { margin-right: 0; margin-bottom: 10px; }
        }
        @media (max-width: 768px) {
            body { font-size: 13px;}
            .main-header { flex-direction: column; align-items: stretch; gap: 10px; padding: 15px; }
            .header-title { justify-content: space-between;}
            .header-user { width: 100%; flex-wrap: wrap; justify-content: space-between; gap: 10px;}
            .header-user form { flex-grow: 1; min-width: 100%; margin-right: 0;}
             .header-user input[type="search"] { min-width: unset; width: 100%;}
            .table-controls { flex-direction: column; align-items: stretch; }
            .table-filters { flex-direction: column; align-items: stretch;}
            .table-container th, .table-container td { padding: 10px 12px; font-size: 0.9em; }
             .stats-container { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
        }
         @media (max-width: 576px) {
             .main-content { padding: 15px; }
             .main-header { padding: 10px 15px; }
             .header-title h1 { font-size: 1.3em; }
             .items-list li { flex-direction: column; align-items: stretch; } /* Stack item parts */
             .item-actions { width: 100%; justify-content: flex-end; margin-top: 8px;}
             .action-btn, .styled-select { padding: 5px 10px; font-size: 0.85em;}
             .stats-container { grid-template-columns: 1fr; gap: 15px; } /* Stack stats cards */
             .stats-card { padding: 15px; flex-direction: row; align-items: center;} /* Revert card direction */
             .stats-icon { margin-right: 15px; margin-bottom: 0; font-size: 1.8em; width: 45px; height: 45px;}
             .stats-info .stats-value { font-size: 1.4em; }
             .table-container th, .table-container td { padding: 8px 10px; }
         }

    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
         <aside class="sidebar">
            <div class="sidebar-header">
                <a href="admin.php" class="logo">FoodNow</a>
            </div>
            <nav class="sidebar-nav">
                 <ul>
                    <li><a href="admin.php"><i class="fas fa-tachometer-alt fa-fw"></i><span>Tổng quan</span></a></li>
                    <li><a href="admin_food.php"><i class="fas fa-utensils fa-fw"></i> <span>Món ăn</span></a></li>
                    <li class="active"><a href="admin_order.php"><i class="fas fa-receipt fa-fw"></i> <span>Đơn hàng</span></a></li>
                    <li><a href="admin_users.php"><i class="fas fa-users fa-fw"></i> <span>Người dùng</span></a></li>
                    <li><a href="admin_transfer.php"><i class="fas fa-money-check-dollar fa-fw"></i> <span>Giao dịch</span></a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt fa-fw"></i> <span>Đăng xuất</span></a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="main-header">
                 <div class="header-title">
                    <button class="header-menu-toggle" aria-label="Toggle Sidebar"><i class="fas fa-bars"></i></button>
                    <h1>Quản lý Đơn hàng</h1>
                </div>
                <div class="header-user">
                    <form action="admin_order.php" method="GET" role="search" id="order-search-form">
                        <input type="search" id="admin-search-order" name="search" placeholder="Tìm kiếm đơn hàng..." aria-label="Tìm kiếm đơn hàng" autocomplete="off" value="<?php echo htmlspecialchars($search_term); ?>">
                        <span class="search-loading" id="search-spinner"><i class="fas fa-spinner fa-spin"></i></span>
                         <!-- Optional: Visible Search Button -->
                         <!-- <button type="submit" class="search-btn-visible" aria-label="Tìm kiếm"><i class="fas fa-search"></i></button> -->
                         <button type="submit" class="search-btn" aria-label="Tìm kiếm"><i class="fas fa-search"></i></button> <!-- Hidden via CSS -->
                    </form>
                    <div class="user-info">
                        <img src="images/placeholder-avatar.png" alt="Admin Avatar" class="avatar"> <!-- Update path -->
                        <span><?php echo $admin_username; ?></span> <i class="fas fa-caret-down"></i>
                        <div class="user-dropdown">
                            <a href="#">Hồ sơ</a>
                            <a href="logout.php">Đăng xuất</a>
                        </div>
                    </div>
                </div>
            </header>

             <!-- Summary Cards -->
            <section class="stats-container">
                <div class="stats-card">
                    <div class="stats-icon icon-orders"><i class="fas fa-receipt"></i></div>
                    <div class="stats-info"><span class="stats-value"><?php echo $total_orders_count; ?></span><span class="stats-label">Tổng số đơn</span></div>
                </div>
                <div class="stats-card">
                    <div class="stats-icon icon-pending"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stats-info"><span class="stats-value"><?php echo $pending_orders_count; ?></span><span class="stats-label">Đơn chờ xử lý</span></div>
                </div>
                <div class="stats-card">
                    <div class="stats-icon icon-revenue"><i class="fas fa-coins"></i></div>
                    <div class="stats-info"><span class="stats-value"><?php echo number_format($todays_revenue ?? 0, 0, ',', '.'); ?> <small>VNĐ</small></span><span class="stats-label">Doanh thu hôm nay</span></div>
                </div>
                 <div class="stats-card">
                    <div class="stats-icon icon-customer"><i class="fas fa-star"></i></div>
                    <div class="stats-info"><span class="stats-value">N/A</span><span class="stats-label">Top Khách</span></div>
                </div>
            </section>

             <!-- Table Controls -->
             <section class="table-controls">
                 <h2>Danh sách Đơn hàng</h2>
                 <div class="table-filters">
                    <form action="admin_order.php" method="get" id="filter-form" style="display:contents;"> <!-- Make form inline -->
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                        <label for="status_filter">Lọc:</label>
                        <select name="status_filter" id="status_filter" onchange="this.form.submit()">
                            <option value="all" <?php selected($status_filter ?? 'all', 'all'); ?>>Tất cả trạng thái</option>
                            <option value="pending" <?php selected($status_filter, 'pending'); ?>>Chờ xử lý</option>
                            <option value="processing" <?php selected($status_filter, 'processing'); ?>>Đang xử lý</option>
                            <option value="shipped" <?php selected($status_filter, 'shipped'); ?>>Đã giao</option>
                            <option value="delivered" <?php selected($status_filter, 'delivered'); ?>>Hoàn thành</option>
                            <option value="cancelled" <?php selected($status_filter, 'cancelled'); ?>>Đã hủy</option>
                        </select>
                    </form>
                 </div>
                 <!-- Example Action Button -->
                 <!-- <div class="table-actions">
                    <a href="#" class="btn-primary-action"><i class="fas fa-plus"></i> Thêm Đơn hàng</a>
                 </div> -->
             </section>

            <!-- Orders Table -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Khách hàng</th>
                            <th>Người nhận</th>
                            <th>SĐT Nhận</th>
                            <th>Địa chỉ Giao</th>
                            <th>Sản phẩm & Trạng thái</th>
                            <th>Tổng tiền</th>
                            <th>PTTT</th>
                            <th>Ngày đặt</th>
                        </tr>
                    </thead>
                    <tbody id="order-table-body">
                        <?php echo $table_body_html; // Initial content ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>

    <!-- JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        $(document).ready(function() {
            // --- Sidebar Toggle ---
            // (Same JS as previous answer)
            const $adminContainer = $('.admin-container'); const sidebarStateKey = 'adminSidebarState'; function applySidebarState() { if (localStorage.getItem(sidebarStateKey) === 'collapsed' && $(window).width() > 992) { $adminContainer.addClass('sidebar-collapsed'); } else { $adminContainer.removeClass('sidebar-collapsed'); } } $('.header-menu-toggle').on('click', function() { $adminContainer.toggleClass('sidebar-collapsed'); if ($(window).width() > 992) { if ($adminContainer.hasClass('sidebar-collapsed')) { localStorage.setItem(sidebarStateKey, 'collapsed'); } else { localStorage.setItem(sidebarStateKey, 'expanded'); } } else { localStorage.removeItem(sidebarStateKey); } }); applySidebarState(); $(window).on('resize', applySidebarState);


            // --- Currency Formatting ---
            // (Same JS as previous answer)
             function formatVietnameseCurrency(number) { if (isNaN(number) || number === null) return '0 đ'; try { return number.toLocaleString('vi-VN', { style: 'currency', currency: 'VND', minimumFractionDigits: 0, maximumFractionDigits: 0 }).replace('₫', 'đ'); } catch (e) { console.error("Currency formatting error:", e); return number.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ".") + ' đ'; } }


            // --- Live Search (Instant with Abort) ---
             // (Same JS as previous answer)
            const searchInput = $('#admin-search-order'); const tableBody = $('#order-table-body'); const spinner = $('#search-spinner'); const searchForm = $('#order-search-form'); const statusFilterSelect = $('#status_filter'); let currentSearchRequest = null;
            function performSearch() {
                const searchTerm = searchInput.val().trim();
                const statusFilter = statusFilterSelect.val(); spinner.addClass('active');
                if (currentSearchRequest) { currentSearchRequest.abort(); }
                currentSearchRequest = $.ajax({ url: 'admin_order.php', type: 'GET', data: { search: searchTerm, status_filter: statusFilter, ajax: '1' }, dataType: 'html',
                    success: function(responseHtml) { tableBody.html(responseHtml); },
                    error: function(jqXHR, textStatus) { if (textStatus !== 'abort') { console.error("Live Search AJAX Error:", textStatus); tableBody.html('<tr class="no-results"><td colspan="8">Lỗi khi tải kết quả tìm kiếm.</td></tr>'); } },
                    complete: function() { spinner.removeClass('active'); currentSearchRequest = null; } });
            }
            searchInput.on('input', performSearch);
            searchForm.on('submit', function(e) { if (searchInput.is(':focus')) { e.preventDefault(); } });


            // --- Status Update & Delete Actions (Using Event Delegation on tbody) ---
            // (Same JS as previous answer - Ensure AJAX URLs are correct)
            tableBody.on('change', 'select.order-status-select', function() {
                var selectElement = $(this); var itemId = selectElement.data('item-id'); var newStatus = selectElement.val(); var originalStatus = selectElement.data('original-status'); var newStatusText = selectElement.find("option:selected").text(); if (!itemId) return;
                if (confirm(`Cập nhật trạng thái mục #${itemId} thành "${newStatusText}"?`)) {
                    selectElement.prop('disabled', true); $.ajax({ url: 'ajax_update_order_status.php', type: 'POST', data: { order_id: itemId, status: newStatus }, dataType: 'json', timeout: 10000,
                        success: function(response) { if (response && response.success) { selectElement.data('original-status', newStatus); /* Optional: Visual feedback */ } else { alert('Lỗi cập nhật: '+(response?.message||'Lỗi không xác định.')); selectElement.val(originalStatus); } },
                        error: function(jqXHR, textStatus) { console.error("AJAX Status Update Error:", textStatus); alert(`Lỗi kết nối (${textStatus}).`); selectElement.val(originalStatus); },
                        complete: function() { selectElement.prop('disabled', false); } });
                } else { selectElement.val(originalStatus); } });
            tableBody.on('click', 'button.delete-item-btn', function(e) {
                e.preventDefault(); var buttonElement = $(this); var itemId = buttonElement.data('item-id'); var itemPrice = parseFloat(buttonElement.data('price')); var itemQuantity = parseInt(buttonElement.data('quantity'), 10); var listItem = buttonElement.closest('li'); var orderRow = buttonElement.closest('tr'); var itemsList = listItem.closest('ul'); var itemName = listItem.find('.item-name').text() || `mục #${itemId}`; if (!itemId) return; if (isNaN(itemPrice)||isNaN(itemQuantity)) { itemPrice=0; itemQuantity=0; }
                if (confirm(`Bạn có chắc muốn xóa vĩnh viễn ${itemName}?`)) {
                    buttonElement.prop('disabled', true).find('i').removeClass('fa-trash').addClass('fa-spinner fa-spin');
                    $.ajax({ url: 'ajax_delete_order.php', type: 'POST', data: { order_id: itemId }, dataType: 'json', timeout: 10000,
                        success: function(response) { if (response && response.success) { var totalPriceCell = orderRow.find('td.total-price-row'); var currentTotalText = totalPriceCell.text().trim(); var currentTotalValue = 0; if (currentTotalText) { try { currentTotalValue = parseFloat(currentTotalText.replace(/[.đ\s]/g, '').replace(',', '.')); } catch (e) { console.error(e); } } if (isNaN(currentTotalValue)) currentTotalValue = 0; var priceToSubtract = itemPrice * itemQuantity; var newTotalValue = Math.max(0, currentTotalValue - priceToSubtract); totalPriceCell.text(formatVietnameseCurrency(newTotalValue)); listItem.css('background-color','#ffebee').animate({ opacity: 0, height: 0, padding: 0, margin: 0 }, 500, function() { $(this).remove(); if (itemsList.children('li').length === 0) { orderRow.css('background-color','#fff0f0').fadeOut(700, function() { $(this).remove(); if ($('#order-table-body tr:not(.no-results)').length === 0) { const currentSearchVal = searchInput.val().trim(); const noResultMessage = !currentSearchVal ? 'Hiện tại không có đơn hàng nào (ngoài trạng thái \'cart\').' : 'Không tìm thấy đơn hàng nào phù hợp với tìm kiếm: "<strong>' + $('<div>').text(currentSearchVal).html() + '</strong>"'; $('#order-table-body').html('<tr class="no-results"><td colspan="8">' + noResultMessage + '</td></tr>'); } }); } }); } else { alert('Lỗi khi xóa: '+(response?.message||'Lỗi không xác định.')); buttonElement.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-trash'); } },
                        error: function(jqXHR, textStatus) { console.error("AJAX Delete Error:", textStatus); alert(`Lỗi kết nối (${textStatus}) khi xóa.`); buttonElement.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-trash'); } }); } });

        }); // End document ready
    </script>

</body>
</html>