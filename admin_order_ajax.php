<?php
// Ensure session is started (assuming config.php handles this)
if (file_exists('config/admin_config.php')) {
    include 'config/admin_config.php';
} elseif (file_exists('../config/admin_config.php')) {
    include '../config/admin_config.php';
} else {
    header('Content-Type: application/json');
    echo json_encode(['html' => '<tr class="no-results"><td colspan="8">Lỗi: Không tìm thấy tệp cấu hình.</td></tr>', 'counts' => ['total' => 0, 'online' => 0, 'cod' => 0, 'pending' => 0]]);
    exit;
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Default response structure
$response = ['html' => '', 'counts' => ['total' => 0, 'online' => 0, 'cod' => 0, 'pending' => 0]];

// --- Security Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["role"]) || $_SESSION["role"] !== 'admin') {
     $response['html'] = '<tr class="no-results"><td colspan="8">Lỗi: Phiên đăng nhập không hợp lệ.</td></tr>';
     header('Content-Type: application/json');
     echo json_encode($response);
     exit;
}

// --- Database Connection Check (assuming $pdo is set in config.php) ---
if (!$pdo) {
    $response['html'] = '<tr class="no-results"><td colspan="8">Lỗi: Không thể kết nối cơ sở dữ liệu.</td></tr>';
     header('Content-Type: application/json');
     echo json_encode($response);
     exit;
}

// --- Get Parameters ---
$search_term = trim($_GET['search'] ?? '');
$selected_status = trim($_GET['status_filter'] ?? '');

// --- Replicate Database Fetch Logic ---
$sql = "SELECT o.id AS item_row_id, o.user_id, o.username AS order_table_username,
               o.food_id, o.food_name, o.quantity, o.price_at_add,
               o.status, o.added_at,
               o.recipient_name, o.recipient_phone, o.recipient_address, o.payment_method,
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

$sql .= " ORDER BY o.user_id, o.recipient_name, o.recipient_phone, o.payment_method, o.added_at DESC";

// --- Execute Query and Fetch Items ---
$order_items_list = [];
$grouped_orders = [];
$final_orders_to_display = [];
$output_html = '';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $order_items_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Replicate Grouping Logic ---
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
                     'items' => [], 'total_price' => 0, 'contains_status' => []
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
        }
        uasort($grouped_orders, function ($a, $b) {
             $time_a = strtotime($a['last_added_at'] ?? $a['first_added_at'] ?? 0);
             $time_b = strtotime($b['last_added_at'] ?? $b['first_added_at'] ?? 0);
             return $time_b <=> $time_a;
        });

        // --- Replicate Status Filtering ---
        if (!empty($selected_status)) {
            foreach ($grouped_orders as $group_key => $order) {
                 if (in_array(strtolower($selected_status), $order['contains_status'])) {
                    $final_orders_to_display[$group_key] = $order;
                }
            }
        } else {
            $final_orders_to_display = $grouped_orders;
        }
    }

    // --- Calculate Counts for the Filtered Results ---
    $response['counts']['total'] = count($final_orders_to_display);
    foreach ($final_orders_to_display as $order) {
        $pm = strtolower(trim($order['payment_method'] ?? ''));
        if ($pm === 'online') $response['counts']['online']++;
        elseif ($pm === 'cod') $response['counts']['cod']++;
        if (in_array('pending', $order['contains_status'])) $response['counts']['pending']++;
    }

    // --- Generate HTML Table Rows ---
    if (!empty($final_orders_to_display)) {
        $valid_item_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

        foreach ($final_orders_to_display as $group_key => $order) {
            // Prepare display variables
            $customer_display_name = 'Khách vãng lai';
            if (!empty($order['customer_full_name'])) $customer_display_name = $order['customer_full_name'];
            elseif (!empty($order['user_table_username'])) $customer_display_name = $order['user_table_username'];
            elseif (!empty($order['order_table_username'])) $customer_display_name = $order['order_table_username'];
            elseif ($order['user_id']) $customer_display_name = 'User ID: ' . htmlspecialchars($order['user_id']);

            $pm_class = ''; $payment_method_display = strtoupper(htmlspecialchars($order['payment_method'] ?? 'N/A'));
            if ($payment_method_display === 'COD') $pm_class = 'pm-cod'; elseif ($payment_method_display === 'ONLINE') $pm_class = 'pm-online';
            $display_date_str = $order['last_added_at'] ?? $order['first_added_at']; $display_date = $display_date_str ? date("d/m/y H:i", strtotime($display_date_str)) : 'N/A';
            $full_address = htmlspecialchars($order['recipient_address'] ?? ''); $short_address = mb_strimwidth($full_address, 0, 60, "...");

            // Start building the row HTML
            $output_html .= '<tr>';
            $output_html .= '<td data-label="Khách hàng">' . htmlspecialchars($customer_display_name) . '</td>';
            $output_html .= '<td data-label="Người nhận">' . htmlspecialchars($order['recipient_name'] ?? '-') . '</td>';
            $output_html .= '<td data-label="SĐT Nhận">' . htmlspecialchars($order['recipient_phone'] ?? '-') . '</td>';
            $output_html .= '<td data-label="Địa chỉ Giao" class="address-col" title="' . ($full_address ?: 'Không có địa chỉ') . '">' . nl2br(htmlspecialchars($short_address ?: '-')) . '</td>';

            // Items list cell
            $output_html .= '<td data-label="Sản phẩm & Trạng thái" class="items-col"><ul class="items-list">';
            if (!empty($order['items'])) {
                foreach ($order['items'] as $item) {
                    $current_status = htmlspecialchars(strtolower($item['status'] ?? 'unknown'));
                    $item_id = htmlspecialchars($item['item_row_id'] ?? '');
                    $item_price = $item['price_at_add'] ?? 0;
                    $item_quantity = $item['quantity'] ?? 0;
                    $item_name = htmlspecialchars($item['food_name'] ?? 'N/A');

                    $output_html .= '<li>';
                    $output_html .= '<div class="item-details">';
                    $output_html .= '<span class="item-name">' . $item_name . '</span>';
                    $output_html .= '<span class="item-qty-price">SL: ' . htmlspecialchars($item_quantity) . ' @ ' . number_format($item_price, 0, ',', '.') . 'đ (ID: #' . $item_id . ')</span>';
                    $output_html .= '</div>';
                    $output_html .= '<div class="item-actions">';
                    $output_html .= '<select class="order-status-select" data-item-id="' . $item_id . '" data-original-status="' . $current_status . '" aria-label="Trạng thái mục ' . $item_id . '">';
                    $output_html .= '<option value="pending" ' . ($current_status == 'pending' ? 'selected' : '') . '>Chờ xử lý</option>';
                    $output_html .= '<option value="processing" ' . ($current_status == 'processing' ? 'selected' : '') . '>Đang xử lý</option>';
                    $output_html .= '<option value="shipped" ' . ($current_status == 'shipped' ? 'selected' : '') . '>Đã giao</option>';
                    $output_html .= '<option value="delivered" ' . ($current_status == 'delivered' ? 'selected' : '') . '>Hoàn thành</option>';
                    $output_html .= '<option value="cancelled" ' . ($current_status == 'cancelled' ? 'selected' : '') . '>Đã hủy</option>';
                    if (!in_array($current_status, $valid_item_statuses) && $current_status !== 'cart' && $current_status !== 'unknown' && !empty($current_status)) {
                        $output_html .= '<option value="' . $current_status . '" selected>' . htmlspecialchars(ucfirst($current_status)) . ' (Khác)</option>';
                    }
                    if ($current_status == 'unknown') { $output_html .= '<option value="unknown" selected disabled>Không rõ</option>'; }
                    $output_html .= '</select>';
                    $output_html .= '<button class="delete-item-btn" data-item-id="' . $item_id . '" data-price="' . htmlspecialchars($item_price) . '" data-quantity="' . htmlspecialchars($item_quantity) . '" title="Xóa mục #' . $item_id . '" aria-label="Xóa mục #' . $item_id . '"><i class="fas fa-trash"></i></button>';
                    $output_html .= '</div>'; // end item-actions
                    $output_html .= '</li>';
                }
            } else {
                $output_html .= '<li>Không có sản phẩm.</li>';
            }
            $output_html .= '</ul></td>'; // end items-col

            // Remaining cells
            $output_html .= '<td data-label="Tổng tiền" class="total-price-row">' . number_format($order['total_price'] ?? 0, 0, ',', '.') . ' đ</td>';
            $output_html .= '<td data-label="Thanh toán" class="payment-method ' . $pm_class . '">' . $payment_method_display . '</td>';
            $output_html .= '<td data-label="Ngày đặt" style="white-space: nowrap;">' . $display_date . '</td>';
            $output_html .= '</tr>'; // End row
        }
    } else {
        // No results message
        $no_results_message = "Không có đơn hàng nào (ngoài trạng thái 'cart').";
        if (!empty($search_term) || !empty($selected_status)) {
             $no_results_message = "Không tìm thấy đơn hàng nào phù hợp với tiêu chí lọc/tìm kiếm.";
        }
        $output_html = '<tr class="no-results"><td colspan="8">' . $no_results_message . '</td></tr>';
    }
    $response['html'] = $output_html;

} catch (PDOException $e) {
    error_log("AJAX Order Fetch Error in " . __FILE__ . ": " . $e->getMessage());
    $response['html'] = '<tr class="no-results"><td colspan="8">Lỗi khi truy vấn dữ liệu đơn hàng. Vui lòng thử lại.</td></tr>';
    // Counts remain 0
} catch (Exception $e) {
     error_log("AJAX Order Processing Error in " . __FILE__ . ": " . $e->getMessage());
    $response['html'] = '<tr class="no-results"><td colspan="8">Lỗi xử lý dữ liệu đơn hàng.</td></tr>';
     // Counts remain 0
}

// --- Output JSON ---
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>