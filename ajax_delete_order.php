<?php
// ajax_delete_order.php
include 'config/admin_config.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Yêu cầu không hợp lệ.'];

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["role"]) || $_SESSION["role"] !== 'admin') {
    $response['message'] = 'Không được phép.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    // IMPORTANT: 'order_id' sent from JS contains the item_row_id ('id' from your orders table)
    $item_row_id = intval($_POST['order_id']);

    if ($item_row_id > 0) {
        try {
            // DELETE from your 'orders' table using its primary key 'id'
            $sql = "DELETE FROM orders WHERE id = ?";
            $stmt = $pdo->prepare($sql);

            if ($stmt->execute([$item_row_id])) {
                if ($stmt->rowCount() > 0) {
                    $response['success'] = true;
                    $response['message'] = 'Xóa mục thành công.';
                } else {
                    $response['message'] = 'Không tìm thấy mục để xóa hoặc đã được xóa.';
                }
            } else {
                 $response['message'] = 'Lỗi khi xóa khỏi cơ sở dữ liệu.';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Lỗi cơ sở dữ liệu: ' . $e->getMessage();
            error_log("DB Error (Delete Item): " . $e->getMessage());
        }
    } else {
        $response['message'] = 'ID mục không hợp lệ.';
    }
}
echo json_encode($response);
?>