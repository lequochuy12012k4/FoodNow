<?php
// MUST be the very first line
session_start();

// --- Database Configuration ---
$servername = "localhost";
$username_db = "root"; // Renamed DB user variable
$password_db = "";     // Renamed DB password variable
$databaseName = "foodnow";
$orderTableName = 'orders'; // Your table name

// --- Configuration ---
$default_redirect = 'cart.php'; // Default redirect back to cart page

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

// --- Determine User/Session Identifier ---
// ** Using username from session, ensure login script sets $_SESSION['username'] **
$session_username = isset($_SESSION['username']) ? trim($_SESSION['username']) : null;
$session_id = session_id(); // Get current session ID for guests

// --- Database Connection (Connect once at the beginning) ---
$conn = mysqli_connect($servername, $username_db, $password_db, $databaseName);

// Check connection
if (!$conn) {
    // Log serious error, inform user via session message and redirect
    error_log("Database Connection failed in cart_action.php: " . mysqli_connect_error());
    $_SESSION['cart_error'] = 'Lỗi kết nối cơ sở dữ liệu. Không thể thực hiện hành động.';
    header('Location: ' . $default_redirect);
    exit();
}
mysqli_set_charset($conn, "utf8mb4"); // Set charset

// --- Process Action ---
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $redirect_url = $default_redirect; // Default to cart page unless changed

    try {
        switch ($action) {

            // ACTION: Update quantities for items in the cart
            // Renamed from 'update' to match cart.php form
            case 'update_quantities':
                if (isset($_POST['quantity']) && is_array($_POST['quantity'])) {
                    $updated_count = 0;
                    $removed_count = 0;
                    $errors = [];

                    foreach ($_POST['quantity'] as $order_item_id => $quantity) {
                        // Sanitize inputs
                        $order_item_id = filter_var($order_item_id, FILTER_VALIDATE_INT);
                        $quantity = filter_var($quantity, FILTER_VALIDATE_INT);

                        if ($order_item_id === false || $quantity === false) {
                            $errors[] = "Dữ liệu không hợp lệ cho một món hàng.";
                            error_log("Invalid order_item_id or quantity received during cart update. ID: '{$_POST['order_item_id']}', Qty: '{$_POST['quantity']}'");
                            continue; // Skip this item
                        }

                        if ($quantity > 0) {
                            // --- UPDATE quantity in DB ---
                            $sql_update = "UPDATE `{$orderTableName}` SET quantity = ?, updated_at = NOW() WHERE id = ? AND status = 'cart'";
                            $stmt_update = null;

                            if ($session_username !== null) {
                                // Logged-in user: Check using username
                                $sql_update .= " AND username = ?";
                                $stmt_update = mysqli_prepare($conn, $sql_update);
                                if ($stmt_update) mysqli_stmt_bind_param($stmt_update, "iis", $quantity, $order_item_id, $session_username);
                            } else {
                                // Guest user: Check using session_id and ensure username is NULL
                                $sql_update .= " AND session_id = ? AND username IS NULL";
                                $stmt_update = mysqli_prepare($conn, $sql_update);
                                if ($stmt_update) mysqli_stmt_bind_param($stmt_update, "iis", $quantity, $order_item_id, $session_id);
                            }

                            if ($stmt_update && mysqli_stmt_execute($stmt_update)) {
                                if (mysqli_stmt_affected_rows($stmt_update) > 0) {
                                    $updated_count++;
                                } // else: quantity might not have changed, or item didn't belong to user
                            } else {
                                $errors[] = "Lỗi cập nhật số lượng cho món hàng ID: {$order_item_id}.";
                                error_log("Error updating quantity for order_item_id {$order_item_id}: " . ($stmt_update ? mysqli_stmt_error($stmt_update) : mysqli_error($conn)));
                            }
                             if ($stmt_update) mysqli_stmt_close($stmt_update);

                        } else {
                            // --- REMOVE item if quantity is 0 or less ---
                            $sql_delete = "DELETE FROM `{$orderTableName}` WHERE id = ? AND status = 'cart'";
                             $stmt_delete = null;

                            if ($session_username !== null) {
                                $sql_delete .= " AND username = ?";
                                $stmt_delete = mysqli_prepare($conn, $sql_delete);
                                if ($stmt_delete) mysqli_stmt_bind_param($stmt_delete, "is", $order_item_id, $session_username);
                            } else {
                                $sql_delete .= " AND session_id = ? AND username IS NULL";
                                $stmt_delete = mysqli_prepare($conn, $sql_delete);
                                if ($stmt_delete) mysqli_stmt_bind_param($stmt_delete, "is", $order_item_id, $session_id);
                            }

                             if ($stmt_delete && mysqli_stmt_execute($stmt_delete)) {
                                 if (mysqli_stmt_affected_rows($stmt_delete) > 0) {
                                     $removed_count++;
                                 } // else: item already removed or didn't belong to user
                             } else {
                                 $errors[] = "Lỗi xóa món hàng (số lượng <= 0) ID: {$order_item_id}.";
                                 error_log("Error deleting order_item_id {$order_item_id} (qty<=0): " . ($stmt_delete ? mysqli_stmt_error($stmt_delete) : mysqli_error($conn)));
                             }
                              if ($stmt_delete) mysqli_stmt_close($stmt_delete);
                        }
                    } // end foreach

                    // --- Set feedback messages ---
                    if (!empty($errors)) {
                        $_SESSION['cart_error'] = "Đã xảy ra lỗi khi cập nhật giỏ hàng: " . implode(' ', $errors);
                    } elseif ($updated_count > 0 || $removed_count > 0) {
                         $_SESSION['cart_message'] = "Giỏ hàng đã được cập nhật.";
                         if ($removed_count > 0) {
                             $_SESSION['cart_message'] .= " ({$removed_count} món đã được xóa.)";
                         }
                    } else {
                         // No effective changes or only non-owned items targeted
                         // $_SESSION['cart_message'] = "Không có thay đổi nào được thực hiện trong giỏ hàng.";
                    }

                } else {
                     $_SESSION['cart_error'] = "Không nhận được dữ liệu số lượng để cập nhật.";
                }
                break; // End case 'update_quantities'

            // ACTION: Remove a specific item from the cart
            // Renamed from 'remove' to match cart.php form
            case 'remove_item':
                if (isset($_POST['order_item_id'])) { // Expecting order_item_id now
                    $order_item_id = filter_var($_POST['order_item_id'], FILTER_VALIDATE_INT);

                    if ($order_item_id !== false) {
                        // --- DELETE item from DB ---
                        $sql_delete = "DELETE FROM `{$orderTableName}` WHERE id = ? AND status = 'cart'";
                        $stmt_delete = null;

                        if ($session_username !== null) {
                            $sql_delete .= " AND username = ?";
                            $stmt_delete = mysqli_prepare($conn, $sql_delete);
                            if ($stmt_delete) mysqli_stmt_bind_param($stmt_delete, "is", $order_item_id, $session_username);
                        } else {
                            $sql_delete .= " AND session_id = ? AND username IS NULL";
                            $stmt_delete = mysqli_prepare($conn, $sql_delete);
                             if ($stmt_delete) mysqli_stmt_bind_param($stmt_delete, "is", $order_item_id, $session_id);
                        }

                        if ($stmt_delete && mysqli_stmt_execute($stmt_delete)) {
                            if (mysqli_stmt_affected_rows($stmt_delete) > 0) {
                                $_SESSION['cart_message'] = "Món ăn đã được xóa khỏi giỏ hàng.";
                            } else {
                                // Item not found for this user/session or already deleted
                                $_SESSION['cart_error'] = "Không tìm thấy món ăn để xóa hoặc bạn không có quyền.";
                                error_log("Attempted to remove non-existent/unowned order_item_id '{$order_item_id}' from cart. User: {$session_username}, Session: {$session_id}");
                            }
                        } else {
                            $_SESSION['cart_error'] = "Lỗi khi xóa món ăn.";
                             error_log("Error deleting order_item_id {$order_item_id}: " . ($stmt_delete ? mysqli_stmt_error($stmt_delete) : mysqli_error($conn)));
                        }
                        if ($stmt_delete) mysqli_stmt_close($stmt_delete);

                    } else {
                        $_SESSION['cart_error'] = "ID món ăn không hợp lệ để xóa.";
                    }
                } else {
                     $_SESSION['cart_error'] = "Không có ID món ăn nào được cung cấp để xóa.";
                }
                break; // End case 'remove_item'

            // ACTION: Clear the entire cart for the current user/session
            // Renamed from 'clear' to match cart.php form
            case 'clear_cart':
                 $sql_clear = "DELETE FROM `{$orderTableName}` WHERE status = 'cart'";
                 $stmt_clear = null;

                 if ($session_username !== null) {
                     $sql_clear .= " AND username = ?";
                     $stmt_clear = mysqli_prepare($conn, $sql_clear);
                     if ($stmt_clear) mysqli_stmt_bind_param($stmt_clear, "s", $session_username);
                 } else {
                     $sql_clear .= " AND session_id = ? AND username IS NULL";
                     $stmt_clear = mysqli_prepare($conn, $sql_clear);
                      if ($stmt_clear) mysqli_stmt_bind_param($stmt_clear, "s", $session_id);
                 }

                 if ($stmt_clear && mysqli_stmt_execute($stmt_clear)) {
                     // Check affected rows to see if anything was actually deleted
                     if (mysqli_stmt_affected_rows($stmt_clear) > 0) {
                         $_SESSION['cart_message'] = "Giỏ hàng đã được xóa sạch.";
                     } else {
                          $_SESSION['cart_message'] = "Giỏ hàng đã trống.";
                     }
                 } else {
                     $_SESSION['cart_error'] = "Lỗi khi xóa giỏ hàng.";
                      error_log("Error clearing cart: " . ($stmt_clear ? mysqli_stmt_error($stmt_clear) : mysqli_error($conn)));
                 }
                 if ($stmt_clear) mysqli_stmt_close($stmt_clear);
                 break; // End case 'clear_cart'

            // REMOVED 'add' action - should be handled by cart_add.php

            default:
                 $_SESSION['cart_error'] = "Hành động không hợp lệ.";
                 error_log("Invalid cart action received: " . $action);
        }
    } catch (Exception $e) {
         // Catch any unexpected errors during processing
         $_SESSION['cart_error'] = "Đã xảy ra lỗi hệ thống khi xử lý giỏ hàng.";
         error_log("Exception in cart_action.php: " . $e->getMessage());
    } finally {
        // Ensure connection is closed
        if ($conn) {
            mysqli_close($conn);
        }
    }

} else {
    // No action specified
    $_SESSION['cart_error'] = "Không có hành động nào được chỉ định.";
    error_log("cart_action.php called without a POST action.");
}

// Redirect back to the cart page
header('Location: ' . $redirect_url);
exit();
?>