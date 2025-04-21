<?php
// ajax_update_order_status.php
include 'config/admin_config.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Yêu cầu không hợp lệ.'];

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["role"]) || $_SESSION["role"] !== 'admin') {
    $response['message'] = 'Không được phép.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id']) && isset($_POST['status'])) {
    // IMPORTANT: 'order_id' sent from JS actually contains the item_row_id (the 'id' from your orders table)
    $item_row_id = intval($_POST['order_id']);
    $status = trim($_POST['status']);
    // Add more valid statuses if needed, but exclude 'cart' generally for updates here
    $allowed_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled' /* Add any other valid 'placed order' statuses */ ];

    if ($item_row_id > 0 && in_array($status, $allowed_statuses)) {
        try {
            // UPDATE your 'orders' table using its primary key 'id'
            $sql = "UPDATE orders SET status = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$status, $item_row_id])) {
                if ($stmt->rowCount() > 0) {
                    $response['success'] = true;
                    $response['message'] = 'Cập nhật trạng thái mục thành công.';
                } else {
                    // It's possible the status was already set to the new value
                    $response['success'] = true; // Treat as success even if no row changed
                    $response['message'] = 'Không có thay đổi trạng thái (có thể đã ở trạng thái này).';
                     // Check if the row actually exists
                     $checkSql = "SELECT COUNT(*) FROM orders WHERE id = ?";
                     $checkStmt = $pdo->prepare($checkSql);
                     $checkStmt->execute([$item_row_id]);
                     if ($checkStmt->fetchColumn() == 0) {
                         $response['success'] = false;
                          $response['message'] = 'ID mục không tồn tại.';
                     }
                }
            } else {
                $response['message'] = 'Lỗi khi cập nhật cơ sở dữ liệu.';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Lỗi cơ sở dữ liệu: ' . $e->getMessage();
            error_log("DB Error (Update Item Status): " . $e->getMessage());
        }
    } else {
        $response['message'] = 'Dữ liệu không hợp lệ (ID hoặc trạng thái).';
    }
}
echo json_encode($response);
?>