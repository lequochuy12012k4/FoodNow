<?php
// Ensure session is started
// Make sure the path to config.php is correct relative to this file
// Assuming config.php is in the same directory or an accessible path
if (file_exists('config/admin_config.php')) {
    include 'config/admin_config.php';
} elseif (file_exists('../config/admin_config.php')) {
    include '../config/admin_config.php'; // Example if structure is different
} else {
    // If config is critical for session start, start session AFTER include check
    if (session_status() == PHP_SESSION_NONE) { session_start(); }
    // Set an error message to display later, but allow page structure to load
    $_SESSION['temp_message'] = "Lỗi: Không tìm thấy tệp cấu hình admin_config.php.";
    $_SESSION['temp_msg_type'] = "danger";
    // Set default values to avoid errors later
    $admin_username = 'Admin';
    $final_orders_to_display = [];
    $total_order_count_display = 0;
    $online_order_count_display = 0;
    $offline_order_count_display = 0;
    $pending_order_count_display = 0;
    $search_term = '';
    $selected_status = '';
    $valid_filter_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    $pdo = null; // Indicate DB connection failed
}

// Start session if not already started (e.g., if config didn't start it)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Display message from session if config was missing
$message = $_SESSION['temp_message'] ?? '';
$msg_type = $_SESSION['temp_msg_type'] ?? '';
unset($_SESSION['temp_message'], $_SESSION['temp_msg_type']); // Clear after displaying

// Check if user is logged in and is an admin (only if config loaded)
if ($pdo && (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["role"]) || $_SESSION["role"] !== 'admin')) {
    header("location: login.php"); // Redirect to login page
    exit;
}
// If config didn't load, $admin_username is default, otherwise get from session
$admin_username = ($pdo) ? htmlspecialchars($_SESSION["username"]) : $admin_username;

// --- Initialize variables ---
$search_term = '';
$selected_status = '';
$order_items_list = [];
$grouped_orders = [];
$final_orders_to_display = [];
$overall_pending_count = 0;
$total_order_count_display = 0;
$online_order_count_display = 0;
$offline_order_count_display = 0;
$pending_order_count_display = 0;
$transaction_statuses = []; // To store payment statuses
$valid_filter_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled']; // Define default

// --- START: FETCH "ORDER" ITEMS WITH SEARCH AND STATUS FILTER (Only if DB connected) ---
if ($pdo) {
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search_term = trim($_GET['search']);
    }
    if (isset($_GET['status_filter']) && !empty($_GET['status_filter'])) {
        $selected_status = trim($_GET['status_filter']);
    }

    // Base SQL query - STEP 1: Added o.transaction_id
    $sql = "SELECT o.id AS item_row_id, o.user_id, o.username AS order_table_username,
                   o.food_id, o.food_name, o.quantity, o.price_at_add,
                   o.status, o.added_at,
                   o.recipient_name, o.recipient_phone, o.recipient_address, o.payment_method,
                   o.transaction_id, -- <-- Added this column
                   u.username AS user_table_username, u.full_name AS customer_full_name
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.status != 'cart'";

    $params = [];
    if (!empty($search_term)) {
        $sql .= " AND (CAST(o.id AS CHAR) LIKE :search_term_id OR o.username LIKE :search_term_order_user OR u.username LIKE :search_term_table_user OR u.full_name LIKE :search_term_fullname OR o.food_name LIKE :search_term_food OR o.recipient_name LIKE :search_term_recipient OR o.recipient_phone LIKE :search_term_phone)";
        $search_like = "%" . $search_term . "%";
        $params[':search_term_id'] = $search_like;
        $params[':search_term_order_user'] = $search_like;
        $params[':search_term_table_user'] = $search_like;
        $params[':search_term_fullname'] = $search_like;
        $params[':search_term_food'] = $search_like;
        $params[':search_term_recipient'] = $search_like;
        $params[':search_term_phone'] = $search_like;
    }

    // Add status filter (applies to individual item status)
    if (!empty($selected_status)) {
        $sql .= " AND o.status = :status_filter";
        $params[':status_filter'] = $selected_status;
    }

    // Grouping should logically happen *after* potentially filtering items by status
    // However, the original logic grouped first, then filtered the groups.
    // Let's stick to the original grouping approach for now, but be aware this might
    // filter out whole groups if *none* of their items match the $selected_status filter.
    $sql .= " ORDER BY o.user_id, o.recipient_name, o.recipient_phone, o.payment_method, o.added_at DESC";


    $stmt = $pdo->prepare($sql);


    try {
        $stmt->execute($params);
        $order_items_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- START: GROUPING LOGIC ---
        if (!empty($order_items_list)) {
            foreach ($order_items_list as $item) {
                $user_id_key = $item['user_id'] ?? 'guest';
                $recipient_name_key = strtolower(trim($item['recipient_name'] ?? ''));
                $recipient_phone_key = strtolower(trim($item['recipient_phone'] ?? ''));
                $recipient_address_key = strtolower(trim($item['recipient_address'] ?? ''));
                $payment_method_key = strtolower(trim($item['payment_method'] ?? 'unknown'));
                $group_key = $user_id_key . '_' . md5($recipient_name_key) . '_' . md5($recipient_phone_key) . '_' . md5($recipient_address_key) . '_' . $payment_method_key;

                if (!isset($grouped_orders[$group_key])) {
                    $grouped_orders[$group_key] = [
                        'user_id' => $item['user_id'], 'order_table_username' => $item['order_table_username'],
                        'user_table_username' => $item['user_table_username'], 'customer_full_name' => $item['customer_full_name'],
                        'recipient_name' => $item['recipient_name'], 'recipient_phone' => $item['recipient_phone'],
                        'recipient_address' => $item['recipient_address'], 'payment_method' => $item['payment_method'],
                        'first_added_at' => $item['added_at'], 'last_added_at' => $item['added_at'],
                        'items' => [], 'total_price' => 0, 'contains_status' => [],
                        'transaction_id' => $item['transaction_id'] // STEP 2: Store transaction_id for the group
                    ];
                }

                $grouped_orders[$group_key]['items'][] = $item;
                $quantity = is_numeric($item['quantity']) ? (int)$item['quantity'] : 0;
                $price = is_numeric($item['price_at_add']) ? (float)$item['price_at_add'] : 0;
                $grouped_orders[$group_key]['total_price'] += ($quantity * $price);
                $item_status = strtolower(trim($item['status'] ?? 'unknown'));
                if (!in_array($item_status, $grouped_orders[$group_key]['contains_status'])) {
                    $grouped_orders[$group_key]['contains_status'][] = $item_status;
                }
                if (isset($item['added_at']) && strtotime($item['added_at']) > strtotime($grouped_orders[$group_key]['last_added_at'])) {
                    $grouped_orders[$group_key]['last_added_at'] = $item['added_at'];
                }
                // Ensure transaction_id is consistent within the group (optional check)
                // if ($grouped_orders[$group_key]['transaction_id'] != $item['transaction_id']) {
                //     error_log("Warning: Inconsistent transaction ID within group key $group_key");
                // }
            }
            // Sort grouped orders
            uasort($grouped_orders, function ($a, $b) {
                $time_a = strtotime($a['last_added_at'] ?? $a['first_added_at'] ?? 0);
                $time_b = strtotime($b['last_added_at'] ?? $b['first_added_at'] ?? 0);
                return $time_b <=> $time_a;
            });

            // --- STEP 3: FETCH TRANSACTION STATUSES ---
            $transaction_ids = [];
            foreach ($grouped_orders as $group) {
                if (!empty($group['transaction_id']) && !in_array($group['transaction_id'], $transaction_ids)) {
                    $transaction_ids[] = $group['transaction_id'];
                }
            }

            if (!empty($transaction_ids)) {
                try {
                    // Create placeholders for the IN clause
                    $in_placeholders = implode(',', array_fill(0, count($transaction_ids), '?'));
                    $sql_trans = "SELECT id, payment_status FROM transactions WHERE id IN ($in_placeholders)";
                    $stmt_trans = $pdo->prepare($sql_trans);
                    // Execute with the array of IDs
                    $stmt_trans->execute($transaction_ids); // PDO handles binding IN clause values correctly

                    while ($row = $stmt_trans->fetch(PDO::FETCH_ASSOC)) {
                        $transaction_statuses[$row['id']] = $row['payment_status'];
                    }
                } catch (PDOException $e) {
                    // Log error but continue page load
                    error_log("Database Error (Fetch Transaction Statuses) in " . __FILE__ . ": " . $e->getMessage());
                    $message .= " | Lỗi khi tải trạng thái thanh toán."; // Append to existing message
                    $msg_type = "warning";
                }
            }
            // --- END: FETCH TRANSACTION STATUSES ---


            // --- CALCULATE OVERALL PENDING COUNT (Before Filtering) ---
             foreach ($grouped_orders as $order) {
                if (in_array('pending', $order['contains_status'])) {
                     $overall_pending_count++;
                }
            }

             // Apply the filter *after* grouping and fetching transaction statuses
            // This maintains the original filter behavior on item status
            if (!empty($selected_status)) {
                foreach ($grouped_orders as $group_key => $order) {
                    // Keep group if *any* item has the selected status
                    $has_selected_status = false;
                    foreach($order['items'] as $item) {
                        if (strtolower($item['status']) === strtolower($selected_status)) {
                            $has_selected_status = true;
                            break;
                        }
                    }
                    if ($has_selected_status) {
                        $final_orders_to_display[$group_key] = $order;
                    }
                }
            } else {
                // If no status filter, show all grouped orders
                $final_orders_to_display = $grouped_orders;
            }

        }
        // --- END: GROUPING & FILTERING LOGIC ---

    } catch (PDOException $e) {
        $final_orders_to_display = []; // Ensure it's empty on error
        $message = "Lỗi khi truy vấn hoặc xử lý đơn hàng: " . $e->getMessage();
        $msg_type = "danger";
        error_log("Database Error (Fetch/Group Orders) in " . __FILE__ . ": " . $e->getMessage());
    }
    // --- END: FETCH "ORDER" ITEMS ---


    // --- START: CALCULATE COUNTS for display (based on $final_orders_to_display) ---
    $total_order_count_display = count($final_orders_to_display);
    $online_order_count_display = 0;
    $offline_order_count_display = 0; // COD
    $pending_order_count_display = 0; // Pending item status shown in table

    foreach ($final_orders_to_display as $order) {
        $pm = strtolower(trim($order['payment_method'] ?? ''));
        if ($pm === 'online') $online_order_count_display++;
        elseif ($pm === 'cod') $offline_order_count_display++;
        // Count groups that contain at least one 'pending' item
        $has_pending_item = false;
        foreach($order['items'] as $item) {
             if(strtolower($item['status']) === 'pending') {
                 $has_pending_item = true;
                 break;
             }
        }
        if($has_pending_item) $pending_order_count_display++;
    }
    // --- END: CALCULATE COUNTS ---

} // End of if($pdo) block
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoodNow Admin - Quản lý Đơn hàng</title>
    <link rel="stylesheet" href="css/admin.css"> <!-- Adjust path if needed -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="shortcut icon" href="image/foodnow_icon.png" sizes="32x32" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- Existing styles from your previous code --- */
        :root {
            --primary-color: #4CAF50;
            --secondary-color: #f8f9fa;
            --text-color: #333;
            --border-color: #e0e0e0;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --success-color: #28a745;
            --purple-color: #6f42c1;
            --orange-color: #fd7e14;
            --blue-color: #0d6efd;
            --sidebar-width: 250px;
            --collapsed-sidebar-width: 70px;
        }
        /* Basic Reset */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7f6; color: var(--text-color); display: flex; min-height: 100vh; }
        /* Admin Container */
        .admin-container { display: flex; width: 100%; }
        /* Sidebar */
        .sidebar { width: var(--sidebar-width); background-color: #2c3e50; color: #ecf0f1; padding: 20px 0; height: 100vh; position: fixed; left: 0; top: 0; overflow-y: auto; transition: width 0.3s ease; z-index: 1000; }
        .sidebar-header { padding: 0 20px 20px 20px; text-align: center; border-bottom: 1px solid #34495e; }
        .sidebar-header .logo { color: #ecf0f1; text-decoration: none; font-size: 1.5em; font-weight: 600; }
        .sidebar-nav ul { list-style: none; }
        .sidebar-nav li a { display: flex; align-items: center; padding: 12px 20px; color: #bdc3c7; text-decoration: none; transition: background-color 0.3s ease, color 0.3s ease, padding-left 0.3s ease; white-space: nowrap; overflow: hidden; }
        .sidebar-nav li a i { margin-right: 15px; width: 20px; text-align: center; font-size: 1.1em; }
        .sidebar-nav li a span { opacity: 1; transition: opacity 0.2s ease 0.1s; }
        .sidebar-nav li a:hover, .sidebar-nav li.active a { background-color: #34495e; color: #ffffff; padding-left: 25px; }
        /* Main Content Area */
        .main-content { flex-grow: 1; padding: 20px; margin-left: var(--sidebar-width); transition: margin-left 0.3s ease; }
        /* Header */
        .main-header { background-color: #fff; padding: 10px 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; position: sticky; top: 0; z-index: 999; flex-wrap: wrap;}
        .header-title { display: flex; align-items: center; }
        .header-title h1 { font-size: 1.6em; margin-left: 15px; color: var(--text-color); font-weight: 600; }
        .header-menu-toggle { background: none; border: none; font-size: 1.5em; cursor: pointer; color: var(--text-color); }
        .header-user { display: flex; align-items: center; gap: 15px;}
        .header-user .filter-search-form { display: flex; align-items: center;} /* Ensure form elements line up */
        .header-user input[type="search"] { padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 20px 0 0 20px; font-size: 0.9em; outline: none; min-width: 250px; height: 36px; border-right: none; }
        .header-user .search-btn { padding: 8px 12px; border: 1px solid var(--orange-color); background-color: var(--orange-color); color: white; border-radius: 0 20px 20px 0; cursor: pointer; height: 36px; border-left: none; }
        .user-info { display: flex; align-items: center; cursor: pointer; position: relative; }
        .user-info .avatar { width: 35px; height: 35px; border-radius: 50%; margin-right: 10px; object-fit: cover; border: 2px solid var(--border-color); }
        .user-info span { font-weight: 500; margin-right: 5px; }
        .user-info i.fa-caret-down { font-size: 0.8em; }
        .user-dropdown { display: none; position: absolute; top: 110%; right: 0; background-color: #fff; border: 1px solid var(--border-color); border-radius: 4px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); min-width: 120px; z-index: 1001; }
        .user-dropdown a { display: block; padding: 10px 15px; color: var(--text-color); text-decoration: none; font-size: 0.9em; }
        .user-dropdown a:hover { background-color: #f1f1f1; }
        .user-info:hover .user-dropdown { display: block; }
        /* Collapsed Sidebar Styles */
        .admin-container.sidebar-collapsed .sidebar { width: var(--collapsed-sidebar-width); }
        .admin-container.sidebar-collapsed .main-content { margin-left: var(--collapsed-sidebar-width); }
        .admin-container.sidebar-collapsed .sidebar-header .logo { font-size: 1.2em; }
        .admin-container.sidebar-collapsed .sidebar-nav li a span { opacity: 0; }
        .admin-container.sidebar-collapsed .sidebar-nav li a i { margin-right: 0; }
        .admin-container.sidebar-collapsed .sidebar-nav li a { justify-content: center; }
        /* Message Styles */
        .message { padding: 15px; margin-bottom: 20px; border-radius: 5px; font-size: 0.95em; }
        .success-message { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error-message { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning-message { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; } /* For warnings */

        /* --- Summary Cards --- */
        .summary-cards-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .summary-card { background-color: #fff; border-radius: 8px; padding: 20px 25px; box-shadow: 0 3px 6px rgba(0, 0, 0, 0.07); display: flex; align-items: center; gap: 20px; border: 1px solid #e0e0e0; transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; }
        .summary-card:hover { transform: translateY(-3px); box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1); }
        .summary-card .card-icon { font-size: 2.2em; padding: 10px; border-radius: 50%; width: 55px; height: 55px; display: flex; justify-content: center; align-items: center; color: #fff; }
        .summary-card.total-orders .card-icon { background-color: var(--primary-color); }
        .summary-card.pending-orders .card-icon { background-color: var(--warning-color); }
        .summary-card.online-payments .card-icon { background-color: var(--blue-color); }
        .summary-card.cod-payments .card-icon { background-color: var(--orange-color); }
        .summary-card .card-content { display: flex; flex-direction: column; }
        .summary-card .card-value { font-size: 1.6em; font-weight: 600; color: var(--text-color); line-height: 1.2; transition: opacity 0.3s ease; }
        .summary-card .card-title { font-size: 0.9em; color: #6c757d; }

        /* --- Table Controls --- */
        .table-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 15px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); flex-wrap: wrap; gap: 15px; }
        .table-controls h2 { margin: 0; font-size: 1.3em; font-weight: 600; color: var(--text-color); }
        .table-controls .filter-container { display: flex; align-items: center; gap: 10px; }
        .table-controls .filter-container label { font-size: 0.9em; color: #555; font-weight: 500; white-space: nowrap; }
        .table-controls #status-filter { padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 5px; font-size: 0.9em; background-color: #fff; min-width: 180px; height: 36px; cursor: pointer;}

        /* --- Table Styles --- */
        .table-container { overflow-x: auto; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08); }
        .table-container table { width: 100%; border-collapse: collapse; }
        .table-container th, .table-container td { padding: 12px 15px; border-bottom: 1px solid var(--border-color); text-align: left; vertical-align: top; font-size: 0.9rem; }
        .table-container th { background-color: var(--secondary-color); font-weight: 600; white-space: nowrap; }
        .table-container tbody tr:hover { background-color: #f1f1f1; }
        .table-container td.address-col, .table-container td.items-col { white-space: normal; word-break: break-word; }
        .table-container td.address-col { min-width: 170px; }
        .table-container td.items-col { min-width: 300px; }
        .table-container td.total-price-row { font-weight: bold; font-size: 1em; white-space: nowrap; text-align: center; }
        .table-container td.payment-method { text-transform: uppercase; font-weight: 500; white-space: nowrap; text-align: center;}
        .pm-cod { color: var(--orange-color); }
        .pm-online { color: var(--blue-color); }
        /* Items List within Table Cell */
        .items-list { list-style: none; padding: 0; margin: 0; }
        .items-list li { border-bottom: 1px dotted #eee; padding: 8px 0; margin-bottom: 5px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .items-list li:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .item-details { flex-grow: 1; margin-right: 10px; }
        .item-name { font-weight: 500; display: block; margin-bottom: 3px; }
        .item-qty-price { font-size: 0.85em; color: #555; }
        .item-actions { white-space: nowrap; display: flex; align-items: center; gap: 5px; }
        .delete-item-btn, .order-status-select { padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.8em; vertical-align: middle; height: 28px; /* Align height */}
        .delete-item-btn { background-color: var(--danger-color); color: white; border: none; line-height: 1; }
        .delete-item-btn:hover { background-color: #c82333; }
        .order-status-select { border: 1px solid #ccc; background-color: #fff; }
        .order-status-select:focus { outline: none; border-color: var(--primary-color); }

        /* Item Status label classes */
        .status-label-pending { color: var(--warning-color); font-weight: bold; }
        .status-label-processing { color: var(--info-color); font-weight: bold; }
        .status-label-shipped { color: var(--purple-color); font-weight: bold; }
        .status-label-delivered { color: var(--success-color); font-weight: bold; }
        .status-label-cancelled { color: var(--danger-color); font-weight: bold; }
        .status-label-default { color: #6c757d; } /* Default/Unknown */
        .status-label-unknown { color: #adb5bd; font-style: italic; }

        /* Payment Status Cell */
        td.payment-status-cell { font-weight: bold; white-space: nowrap; text-align: center;}
        .payment-status-cell .tooltip { font-size: 0.8em; font-weight: normal; color: #6c757d; display: block; margin-top: 2px;}

        td.address-col[title] { cursor: help; } /* Tooltip hint */
        #loading-spinner { color: var(--primary-color); } /* Spinner styling */

        /* Responsive Adjustments */
        @media (max-width: 992px) {
            .sidebar { width: var(--collapsed-sidebar-width); }
            .main-content { margin-left: var(--collapsed-sidebar-width); }
            .sidebar-nav li a span { opacity: 0; }
            .sidebar-nav li a i { margin-right: 0; }
            .sidebar-nav li a { justify-content: center; }
            .header-title h1 { font-size: 1.3em; }
            .header-user input[type="search"] { min-width: 150px; }
            .header-menu-toggle { display: block; margin-right: 10px; }
            .sidebar:hover { width: var(--sidebar-width); }
            .sidebar:hover .sidebar-nav li a span { opacity: 1; }
            .sidebar:hover .sidebar-nav li a i { margin-right: 15px; }
            .sidebar:hover .sidebar-nav li a { justify-content: flex-start; }
        }
        @media (max-width: 768px) {
            .main-header { flex-direction: column; align-items: flex-start; padding: 10px 15px;}
            .header-title { width: 100%; justify-content: space-between; margin-bottom: 10px;}
            .header-user { width: 100%; margin-top: 0; justify-content: space-between; flex-wrap: wrap;} /* Allow wrap */
            .header-user .filter-search-form { flex-grow: 1; margin-bottom: 10px;} /* Take space, add margin */
            .table-container th, .table-container td { padding: 8px 10px; font-size: 0.85rem; }
            .header-title h1 { font-size: 1.2em; }
            .summary-cards-container { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; }
            .summary-card { padding: 15px; gap: 10px; }
            .summary-card .card-icon { font-size: 1.8em; width: 45px; height: 45px; }
            .summary-card .card-value { font-size: 1.4em; }
            .summary-card .card-title { font-size: 0.85em; }
            .table-controls { flex-direction: column; align-items: stretch; } /* Stack controls */
            .table-controls h2 { margin-bottom: 10px; text-align: center;}
            .table-controls .filter-container { justify-content: center; } /* Center filter */
            /* Hide less important columns on smaller screens */
            .table-container th:nth-child(1), .table-container td:nth-child(1), /* Customer */
            .table-container th:nth-child(3), .table-container td:nth-child(3), /* Phone */
            .table-container th:nth-child(4), .table-container td:nth-child(4), /* Address */
            .table-container th:nth-child(8), .table-container td:nth-child(8) { /* Date */
                 /* display: none; */ /* Uncomment to hide */
            }
        }
        @media (max-width: 576px) {
            body { display: block; }
            .sidebar { height: auto; position: relative; width: 100%; }
            .main-content { margin-left: 0; padding: 15px; }
            .main-header { padding: 10px 15px; }
            .header-menu-toggle {
    background: none;
    border: none;
    font-size: 1.5em;
    cursor: pointer;
    color: var(--text-color);
    /* display: none; <--- This is the line hiding the button by default */
}
            .sidebar-nav ul { display: flex; flex-wrap: wrap; justify-content: center; }
            .sidebar-nav li { flex-basis: 50%; text-align: center; }
            .sidebar-nav li a { justify-content: center; padding: 10px; }
            .sidebar-nav li a i { margin-right: 8px; }
            .sidebar-nav li a span { opacity: 1; }
            .admin-container.sidebar-collapsed .sidebar { width: 100%; }
            .admin-container.sidebar-collapsed .main-content { margin-left: 0; }
             .header-user input[type="search"] { min-width: 180px; width: auto; flex-grow: 1;} /* Adjust search */
             .header-user { gap: 10px; }
             .summary-cards-container { grid-template-columns: 1fr; } /* Single column cards */
             .table-controls { padding: 10px;}
             .item-actions { flex-direction: column; align-items: flex-end; gap: 8px;} /* Stack item actions */
             .delete-item-btn, .order-status-select { width: auto;}
             /* Hide more columns on very small screens */
             .table-container th:nth-child(2), .table-container td:nth-child(2) { /* Recipient */
                 /* display: none; */ /* Uncomment to hide */
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="admin.php" class="logo">FoodNow Admin</a>
            </div>
            <nav class="sidebar-nav">
                 <ul>
                    <li><a href="admin.php"><i class="fas fa-tachometer-alt fa-fw"></i><span>Tổng quan</span></a></li>
                    <li><a href="admin_food.php"><i class="fas fa-utensils fa-fw"></i> <span>Quản lý Món ăn</span></a></li>
                    <li class="active"><a href="admin_order.php"><i class="fas fa-receipt fa-fw"></i> <span>Quản lý Đơn hàng</span></a></li>
                    <li><a href="admin_users.php"><i class="fas fa-users fa-fw"></i> <span>Quản lý Người dùng</span></a></li>
                    <li><a href="admin_user_feedback.php"><i class="fas fa-comments"></i><span>Quản lý góp ý</span></a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt fa-fw"></i> <span>Đăng xuất</span></a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <header class="main-header">
                 <div class="header-title">
                    <button class="header-menu-toggle" aria-label="Toggle Sidebar"><i class="fas fa-bars"></i></button>
                    <h1>Quản lý Đơn hàng</h1>
                </div>
                 <div class="header-user">
                     <form action="admin_order.php" method="GET" class="filter-search-form" id="filter-search-form">
                        <input type="search" id="admin-search-order" name="search" placeholder="Tìm kiếm đơn hàng..." autocomplete="off" value="<?php echo htmlspecialchars($search_term); ?>">
                        <button type="submit" class="search-btn" aria-label="Tìm kiếm"><i class="fas fa-search"></i></button>
                    </form>
                    <?php include 'parts/admin_info.php' ?>
                 </div>
            </header>

            <!-- Display Messages -->
            <?php if ($message): ?>
                <div class="message <?php echo ($msg_type === 'danger') ? 'error-message' : (($msg_type === 'warning') ? 'warning-message' : 'success-message'); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>


            <!-- Summary Cards Section -->
            <div class="summary-cards-container">
                <div class="summary-card total-orders">
                    <div class="card-icon"><i class="fas fa-receipt fa-fw"></i></div>
                    <div class="card-content">
                        <span class="card-value" id="card-total-orders"><?php echo $total_order_count_display; ?></span>
                        <span class="card-title">Đơn hàng (Hiển thị)</span>
                    </div>
                </div>
                 <div class="summary-card pending-orders">
                    <div class="card-icon"><i class="fas fa-clock fa-fw"></i></div>
                    <div class="card-content">
                        <span class="card-value" id="card-pending-orders"><?php echo $pending_order_count_display; ?></span>
                        <span class="card-title">Chờ xử lý (Hiển thị)</span>
                    </div>
                </div>
                <div class="summary-card online-payments">
                    <div class="card-icon"><i class="fas fa-credit-card fa-fw"></i></div>
                    <div class="card-content">
                        <span class="card-value" id="card-online-payments"><?php echo $online_order_count_display; ?></span>
                        <span class="card-title">TT Online (Hiển thị)</span>
                    </div>
                </div>
                 <div class="summary-card cod-payments">
                    <div class="card-icon"><i class="fas fa-money-bill-wave fa-fw"></i></div>
                    <div class="card-content">
                        <span class="card-value" id="card-cod-payments"><?php echo $offline_order_count_display; ?></span>
                        <span class="card-title">TT COD (Hiển thị)</span>
                    </div>
                </div>
            </div>

             <!-- Table Controls (Title & Filter) -->
            <div class="table-controls">
                <h2>Danh sách Đơn hàng</h2>
                 <div class="filter-container">
                     <label for="status-filter">Lọc trạng thái:</label>
                     <select name="status_filter" id="status-filter" aria-label="Lọc theo trạng thái">
                         <option value="">Tất cả</option>
                         <?php foreach ($valid_filter_statuses as $status): ?>
                             <?php
                              $status_text = ucfirst($status);
                              switch ($status) {
                                  case 'pending': $status_text = 'Chờ xử lý'; break;
                                  case 'processing': $status_text = 'Đang xử lý'; break;
                                  case 'shipped': $status_text = 'Đã giao'; break;
                                  case 'delivered': $status_text = 'Hoàn thành'; break;
                                  case 'cancelled': $status_text = 'Đã hủy'; break;
                              }
                             ?>
                             <option value="<?php echo $status; ?>" <?php echo ($selected_status == $status) ? 'selected' : ''; ?>>
                                 <?php echo htmlspecialchars($status_text); ?>
                             </option>
                         <?php endforeach; ?>
                     </select>
                 </div>
            </div>

            <!-- Orders Table Container -->
            <div class="table-container">
                 <div id="loading-spinner" style="display: none; text-align: center; padding: 30px;">
                    <i class="fas fa-spinner fa-spin fa-2x"></i> Đang tải...
                 </div>
                <table>
                    <thead>
                        <tr>
                            <th>Khách hàng</th>
                            <th>Người nhận</th>
                            <th>SĐT Nhận</th>
                            <th>Địa chỉ Giao</th>
                            <th>Sản phẩm & Trạng thái Item</th> <!-- Clarified header -->
                            <th>Tổng tiền</th>
                            <th>Thanh toán</th>
                            <th>Trạng thái TT</th> <!-- STEP 4: Added Header -->
                            <th>Ngày đặt</th>
                        </tr>
                    </thead>
                    <tbody id="orders-table-body">
                        <?php if (!empty($final_orders_to_display)): ?>
                            <?php foreach ($final_orders_to_display as $group_key => $order): ?>
                                <?php
                                // --- Prepare data for the row ---
                                $customer_display_name = 'Khách vãng lai';
                                if (!empty($order['customer_full_name'])) $customer_display_name = $order['customer_full_name'];
                                elseif (!empty($order['user_table_username'])) $customer_display_name = $order['user_table_username'];
                                elseif (!empty($order['order_table_username'])) $customer_display_name = $order['order_table_username'];
                                elseif ($order['user_id']) $customer_display_name = 'User ID: ' . htmlspecialchars($order['user_id']);

                                $pm_class = ''; $payment_method_display = strtoupper(htmlspecialchars($order['payment_method'] ?? 'N/A'));
                                if ($payment_method_display === 'COD') $pm_class = 'pm-cod'; elseif ($payment_method_display === 'ONLINE') $pm_class = 'pm-online';
                                $display_date_str = $order['last_added_at'] ?? $order['first_added_at']; $display_date = $display_date_str ? date("d/m/y H:i", strtotime($display_date_str)) : 'N/A';
                                $full_address = htmlspecialchars($order['recipient_address'] ?? ''); $short_address = mb_strimwidth($full_address, 0, 60, "...");
                                $valid_item_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled']; // For item dropdown

                                // --- STEP 5: Get Payment Status ---
                                $current_transaction_id = $order['transaction_id'] ?? null;
                                $payment_status_raw = ($current_transaction_id && isset($transaction_statuses[$current_transaction_id]))
                                    ? $transaction_statuses[$current_transaction_id]
                                    : null; // Get status from fetched array

                                $payment_status_display = 'N/A'; // Default display
                                $payment_status_class = 'status-label-unknown'; // Default CSS class

                                if ($payment_status_raw !== null) {
                                    $status_lower = strtolower($payment_status_raw);
                                    switch ($status_lower) {
                                        case 'pending': $payment_status_display = 'Chờ TT'; $payment_status_class = 'status-label-pending'; break;
                                        case 'completed': $payment_status_display = 'Đã TT'; $payment_status_class = 'status-label-delivered'; break; // Use green
                                        case 'cod_pending': $payment_status_display = 'Chờ COD'; $payment_status_class = 'status-label-pending'; break; // Use yellow
                                        case 'failed': $payment_status_display = 'Thất bại'; $payment_status_class = 'status-label-cancelled'; break; // Use red
                                        case 'verified': $payment_status_display = 'Đã xác nhận'; $payment_status_class = 'status-label-processing'; break; // Use blue/info
                                        default: $payment_status_display = htmlspecialchars(ucfirst($payment_status_raw)); $payment_status_class = 'status-label-default'; break;
                                    }
                                } elseif ($order['payment_method'] === 'cod' && $current_transaction_id === null) {
                                     // Special case: If it's COD and no transaction exists (maybe old order or error), assume Pending COD
                                     $payment_status_display = 'Chưa thanh toán';
                                     $payment_status_class = 'status-label-pending';
                                } elseif ($current_transaction_id !== null && $payment_status_raw === null) {
                                     // Transaction ID exists, but status wasn't found (shouldn't happen often)
                                     $payment_status_display = 'Lỗi Status';
                                     $payment_status_class = 'status-label-cancelled';
                                } else {
                                     // No transaction ID at all
                                     $payment_status_display = 'Chưa Thanh toán';
                                     $payment_status_class = 'status-label-unknown';
                                }
                                // --- END: Get Payment Status ---
                                ?>
                                <tr>
                                     <td data-label="Khách hàng"><?php echo htmlspecialchars($customer_display_name); ?></td>
                                     <td data-label="Người nhận"><?php echo htmlspecialchars($order['recipient_name'] ?? '-'); ?></td>
                                     <td data-label="SĐT Nhận"><?php echo htmlspecialchars($order['recipient_phone'] ?? '-'); ?></td>
                                     <td data-label="Địa chỉ Giao" class="address-col" title="<?php echo $full_address ?: 'Không có địa chỉ'; ?>">
                                         <?php echo nl2br(htmlspecialchars($short_address ?: '-')); ?>
                                     </td>
                                     <td data-label="Sản phẩm & Trạng thái Item" class="items-col">
                                         <ul class="items-list">
                                             <?php if (!empty($order['items'])): ?>
                                                 <?php foreach ($order['items'] as $item): ?>
                                                     <?php
                                                     $current_status = htmlspecialchars(strtolower($item['status'] ?? 'unknown'));
                                                     $item_id = htmlspecialchars($item['item_row_id'] ?? '');
                                                     $item_price = $item['price_at_add'] ?? 0; // Keep numeric
                                                     $item_quantity = $item['quantity'] ?? 0; // Keep numeric
                                                     $item_name = htmlspecialchars($item['food_name'] ?? 'N/A');
                                                     ?>
                                                     <li>
                                                         <div class="item-details">
                                                             <span class="item-name"><?php echo $item_name; ?></span>
                                                             <span class="item-qty-price">
                                                                 SL: <?php echo htmlspecialchars($item_quantity); ?>
                                                                 @ <?php echo number_format($item_price, 0, ',', '.'); ?>đ
                                                                 (ID: #<?php echo $item_id; ?>)
                                                             </span>
                                                         </div>
                                                         <div class="item-actions">
                                                             <select class="order-status-select <?php /* echo 'status-label-' . $current_status; */ ?>" data-item-id="<?php echo $item_id; ?>" data-original-status="<?php echo $current_status; ?>" aria-label="Trạng thái mục <?php echo $item_id; ?>">
                                                                 <option value="pending" <?php echo $current_status == 'pending' ? 'selected' : ''; ?>>Chờ xử lý</option>
                                                                 <option value="processing" <?php echo $current_status == 'processing' ? 'selected' : ''; ?>>Đang xử lý</option>
                                                                 <option value="shipped" <?php echo $current_status == 'shipped' ? 'selected' : ''; ?>>Đã giao</option>
                                                                 <option value="delivered" <?php echo $current_status == 'delivered' ? 'selected' : ''; ?>>Hoàn thành</option>
                                                                 <option value="cancelled" <?php echo $current_status == 'cancelled' ? 'selected' : ''; ?>>Đã hủy</option>
                                                                 <?php if (!in_array($current_status, $valid_item_statuses) && $current_status !== 'cart' && $current_status !== 'unknown' && !empty($current_status)): ?>
                                                                     <option value="<?php echo $current_status; ?>" selected><?php echo htmlspecialchars(ucfirst($current_status)); ?> (Khác)</option>
                                                                 <?php endif; ?>
                                                                 <?php if ($current_status == 'unknown'): ?> <option value="unknown" selected disabled>Không rõ</option> <?php endif; ?>
                                                             </select>
                                                             <button class="delete-item-btn"
                                                                 data-item-id="<?php echo $item_id; ?>"
                                                                 data-price="<?php echo htmlspecialchars($item_price); ?>"
                                                                 data-quantity="<?php echo htmlspecialchars($item_quantity); ?>"
                                                                 title="Xóa mục #<?php echo $item_id; ?>"
                                                                 aria-label="Xóa mục #<?php echo $item_id; ?>">
                                                                 <i class="fas fa-trash"></i>
                                                             </button>
                                                         </div>
                                                     </li>
                                                 <?php endforeach; ?>
                                             <?php else: ?>
                                                 <li>Không có sản phẩm.</li>
                                             <?php endif; ?>
                                         </ul>
                                     </td>
                                     <td data-label="Tổng tiền" class="total-price-row"><?php echo number_format($order['total_price'] ?? 0, 0, ',', '.'); ?> đ</td>
                                     <td data-label="Thanh toán" class="payment-method <?php echo $pm_class; ?>"><?php echo $payment_method_display; ?></td>
                                     <!-- STEP 5 Cont: Display Payment Status -->
                                     <td data-label="Trạng thái TT" class="payment-status-cell <?php echo $payment_status_class; ?>">
                                        <?php echo $payment_status_display; ?>
                                        <?php if ($current_transaction_id): ?>
                                            <span class="tooltip">(GD: #<?php echo htmlspecialchars($current_transaction_id); ?>)</span>
                                        <?php endif; ?>
                                     </td>
                                     <td data-label="Ngày đặt" style="white-space: nowrap;"><?php echo $display_date; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <!-- STEP 6: Update Colspan -->
                                <td colspan="9" style="text-align:center; padding: 20px;">
                                    <?php
                                        if (!empty($search_term) || !empty($selected_status)) {
                                            echo "Không tìm thấy đơn hàng nào phù hợp.";
                                        } elseif (!$pdo) {
                                            echo "Không thể kết nối cơ sở dữ liệu để tải đơn hàng.";
                                        } else {
                                            echo "Không có đơn hàng nào (ngoài trạng thái 'cart').";
                                        }
                                    ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div> <!-- End table-container -->
        </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        $(document).ready(function() {
            // --- Existing JS code (Sidebar Toggle, Currency Format, Live Search, Status Update, Delete Item) ---
            // Should mostly work, but AJAX search/filter needs updating.

            // --- Sidebar Toggle ---
            const $adminContainer = $('.admin-container');
            const sidebarStateKey = 'adminSidebarState';
            function applySidebarState() {
                if (localStorage.getItem(sidebarStateKey) === 'collapsed' && $(window).width() > 992) { $adminContainer.addClass('sidebar-collapsed'); }
                else { $adminContainer.removeClass('sidebar-collapsed'); }
            }
            $('.header-menu-toggle').on('click', function() {
                $('.admin-container').toggleClass('sidebar-collapsed');
                // localStorage logic might be needed here if admin.js doesn't handle it
            });
            applySidebarState();
            $(window).on('resize', applySidebarState);

            // --- Currency Formatting ---
            function formatVietnameseCurrency(number) {
                if (isNaN(number) || number === null) return '0 đ';
                 try { return number.toLocaleString('vi-VN', { style: 'currency', currency: 'VND', minimumFractionDigits: 0, maximumFractionDigits: 0 }).replace('₫', 'đ'); }
                 catch (e) { return number.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ".") + ' đ'; }
            }

            // --- Live Search & Filter (Needs Update for AJAX) ---
            const searchInput = $('#admin-search-order');
            const statusFilter = $('#status-filter');
            const tableBody = $('#orders-table-body');
            const spinner = $('#loading-spinner');
            const cardTotalOrders = $('#card-total-orders');
            const cardPendingOrders = $('#card-pending-orders');
            const cardOnlinePayments = $('#card-online-payments');
            const cardCodPayments = $('#card-cod-payments');
            let searchDebounceTimeout = null;
            let currentSearchRequest = null;

            function performSearchOrFilter() {
                const searchTerm = searchInput.val().trim();
                const statusValue = statusFilter.val();
                spinner.show();
                tableBody.css('opacity', 0.5);
                $('.summary-card .card-value').css('opacity', 0.5);
                if (currentSearchRequest) { currentSearchRequest.abort(); }

                // STEP 8: Ensure admin_order_ajax.php is updated
                currentSearchRequest = $.ajax({
                    url: 'admin_order_ajax.php', // This PHP file MUST be updated too
                    type: 'GET',
                    data: { search: searchTerm, status_filter: statusValue },
                    dataType: 'json',
                    success: function(response) {
                        if (response && response.html !== undefined && response.counts) {
                            tableBody.html(response.html); // Assumes AJAX returns full HTML rows including the new column
                            cardTotalOrders.text(response.counts.total || 0);
                            cardPendingOrders.text(response.counts.pending || 0);
                            cardOnlinePayments.text(response.counts.online || 0);
                            cardCodPayments.text(response.counts.cod || 0);
                        } else {
                            tableBody.html('<tr class="no-results"><td colspan="9">Lỗi tải dữ liệu. Phản hồi không hợp lệ.</td></tr>'); // Updated colspan
                            cardTotalOrders.text('0'); cardPendingOrders.text('0'); cardOnlinePayments.text('0'); cardCodPayments.text('0');
                        }
                    },
                    error: function(jqXHR, textStatus) {
                        if (textStatus !== 'abort') {
                            console.error("AJAX Search/Filter Error:", textStatus, jqXHR.responseText);
                            tableBody.html('<tr class="no-results"><td colspan="9">Lỗi kết nối khi tải kết quả.</td></tr>'); // Updated colspan
                            cardTotalOrders.text('0'); cardPendingOrders.text('0'); cardOnlinePayments.text('0'); cardCodPayments.text('0');
                        }
                    },
                    complete: function() {
                        spinner.hide();
                        tableBody.css('opacity', 1);
                        $('.summary-card .card-value').css('opacity', 1);
                        currentSearchRequest = null;
                    }
                });
            }

            searchInput.on('input', function() {
                clearTimeout(searchDebounceTimeout);
                searchDebounceTimeout = setTimeout(performSearchOrFilter, 450);
            });
            statusFilter.on('change', function() {
                clearTimeout(searchDebounceTimeout);
                performSearchOrFilter();
            });
            $('#filter-search-form').on('submit', function(e) {
                 e.preventDefault();
                 clearTimeout(searchDebounceTimeout);
                 performSearchOrFilter();
            });

            // --- Status Update (Event Delegation) ---
             tableBody.on('change', 'select.order-status-select', function() {
                 var selectElement = $(this);
                 var itemId = selectElement.data('item-id');
                 var newStatus = selectElement.val();
                 var originalStatus = selectElement.data('original-status');
                 var newStatusText = selectElement.find("option:selected").text();
                 if (!itemId || newStatus === originalStatus) return; // No change or no ID

                 if (!confirm(`Cập nhật trạng thái mục #${itemId} thành "${newStatusText}"?`)) {
                     selectElement.val(originalStatus); return;
                 }
                 selectElement.prop('disabled', true);

                 $.ajax({
                     url: 'ajax_update_order_status.php', // Needs to exist
                     type: 'POST', data: { order_id: itemId, status: newStatus }, dataType: 'json', timeout: 10000,
                     success: function(response) {
                         if (response && response.success) {
                             selectElement.data('original-status', newStatus); // Update original status on success
                             selectElement.css('border-color', 'green').delay(1000).queue(function(next){ $(this).css('border-color',''); next(); });
                             // OPTIONAL: Trigger a refresh if status change might affect counts (e.g., changed to/from 'pending')
                             if (newStatus === 'pending' || originalStatus === 'pending') {
                                 clearTimeout(searchDebounceTimeout); // Avoid rapid fire
                                 searchDebounceTimeout = setTimeout(performSearchOrFilter, 200); // Refresh counts after a small delay
                             }
                         } else {
                             alert('Lỗi cập nhật: ' + (response?.message || 'Lỗi không xác định.'));
                             selectElement.val(originalStatus);
                         }
                     },
                     error: function(jqXHR, textStatus) {
                         alert(`Lỗi kết nối (${textStatus}) khi cập nhật.`);
                         selectElement.val(originalStatus);
                     },
                     complete: function() { selectElement.prop('disabled', false); }
                 });
            });


            // --- Delete Item (Event Delegation) ---
            tableBody.on('click', 'button.delete-item-btn', function(e) {
                e.preventDefault();
                var buttonElement = $(this);
                var itemId = buttonElement.data('item-id');
                var itemPrice = parseFloat(buttonElement.data('price')); // Keep numeric
                var itemQuantity = parseInt(buttonElement.data('quantity'), 10); // Keep numeric
                var listItem = buttonElement.closest('li');
                var orderRow = buttonElement.closest('tr');
                var itemsList = listItem.closest('ul');
                var itemName = listItem.find('.item-name').text().trim() || `mục #${itemId}`;

                if (!itemId) { console.error("Missing item-id for delete."); return; }
                if (isNaN(itemPrice)) itemPrice = 0;
                if (isNaN(itemQuantity)) itemQuantity = 0;
                if (!confirm(`Bạn chắc chắn muốn xóa ${itemName}? Hành động này không thể hoàn tác.`)) return;

                buttonElement.prop('disabled', true).find('i').removeClass('fa-trash').addClass('fa-spinner fa-spin');

                $.ajax({
                    url: 'ajax_delete_order.php', // Needs to exist
                    type: 'POST', data: { order_id: itemId }, dataType: 'json', timeout: 10000,
                    success: function(response) {
                        if (response && response.success) {
                            // Update Total Price in the row
                            var totalPriceCell = orderRow.find('td.total-price-row');
                            var currentTotalText = totalPriceCell.text().trim();
                            var currentTotalValue = parseFloat(currentTotalText.replace(/[.đ\s]/g, '').replace(',', '.')) || 0;
                            var priceToSubtract = itemPrice * itemQuantity;
                            var newTotalValue = Math.max(0, currentTotalValue - priceToSubtract);
                            totalPriceCell.text(formatVietnameseCurrency(newTotalValue));

                            // Remove Item List Item
                            listItem.css('background-color', '#ffebee').animate({ opacity: 0, height: 0, padding: 0, margin: 0 }, 500, function() {
                                $(this).remove();
                                // Check if it was the last item in this order group
                                if (itemsList.children('li').length === 0) {
                                    orderRow.css('background-color', '#fff0f0').fadeOut(700, function() {
                                        $(this).remove();
                                        // Refresh table & counts AFTER row is removed
                                        performSearchOrFilter();
                                    });
                                } else {
                                     // Refresh table & counts even if only item removed (pending status might change)
                                     performSearchOrFilter();
                                }
                            });
                        } else {
                            alert('Lỗi khi xóa: ' + (response?.message || 'Lỗi không xác định.'));
                            buttonElement.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-trash');
                        }
                    },
                    error: function(jqXHR, textStatus) {
                        alert(`Lỗi kết nối (${textStatus}) khi xóa.`);
                        buttonElement.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-trash');
                    }
                    // No complete needed here as button is removed or re-enabled in success/error
                });
            });


        }); // End document ready
    </script>
</body>
</html>