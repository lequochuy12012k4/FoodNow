<?php
session_start();

// Default redirect location
$redirect_url = 'cart.php';

// Ensure the cart exists in the session
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Check if an action is specified
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        switch ($action) {
            // ACTION: Update quantities for items in the cart
            case 'update':
                if (isset($_POST['quantity']) && is_array($_POST['quantity'])) {
                    $updated_count = 0;
                    $removed_count = 0;
                    foreach ($_POST['quantity'] as $food_id => $quantity) {
                        // Sanitize inputs
                        $food_id = filter_var($food_id, FILTER_VALIDATE_INT);
                        $quantity = filter_var($quantity, FILTER_VALIDATE_INT);

                        if ($food_id !== false && $quantity !== false) {
                            if (isset($_SESSION['cart'][$food_id])) {
                                if ($quantity > 0) {
                                    // Update quantity only if it changed
                                    if ($_SESSION['cart'][$food_id] != $quantity) {
                                         $_SESSION['cart'][$food_id] = $quantity;
                                         $updated_count++;
                                    }
                                } else {
                                    // Remove item if quantity is 0 or less
                                    unset($_SESSION['cart'][$food_id]);
                                    $removed_count++;
                                }
                            }
                        } else {
                             // Log invalid input if necessary
                             error_log("Invalid food_id or quantity received during cart update. ID: '{$_POST['food_id']}', Qty: '{$_POST['quantity']}'");
                        }
                    }
                    if ($updated_count > 0 || $removed_count > 0) {
                         $_SESSION['cart_message'] = "Giỏ hàng đã được cập nhật.";
                         if ($removed_count > 0) {
                            $_SESSION['cart_message'] .= " ({$removed_count} món đã được xóa do số lượng bằng 0.)";
                         }
                    } else {
                         // No actual changes were made
                         // $_SESSION['cart_message'] = "Không có thay đổi nào trong giỏ hàng.";
                    }

                } else {
                    // No quantity data submitted for update
                     $_SESSION['cart_error'] = "Không nhận được dữ liệu số lượng để cập nhật.";
                }
                break;

            // ACTION: Remove a specific item from the cart
            case 'remove':
                if (isset($_POST['food_id'])) {
                    $food_id = filter_var($_POST['food_id'], FILTER_VALIDATE_INT);
                    if ($food_id !== false && isset($_SESSION['cart'][$food_id])) {
                        unset($_SESSION['cart'][$food_id]);
                        $_SESSION['cart_message'] = "Món ăn đã được xóa khỏi giỏ hàng.";
                    } else {
                        // Item not found or invalid ID
                        $_SESSION['cart_error'] = "Không thể xóa món ăn. ID không hợp lệ hoặc món ăn không có trong giỏ.";
                         error_log("Attempted to remove non-existent/invalid food_id '{$_POST['food_id']}' from cart.");
                    }
                } else {
                     $_SESSION['cart_error'] = "Không có ID món ăn nào được cung cấp để xóa.";
                }
                break;

            // ACTION: Clear the entire cart
            case 'clear':
                $_SESSION['cart'] = []; // Reset cart to empty array
                $_SESSION['cart_message'] = "Giỏ hàng đã được xóa sạch.";
                break;

             // ACTION: Add an item (Though you likely have cart_add.php, good to handle here too for consistency or if merging)
             case 'add':
                if (isset($_POST['food_id']) && isset($_POST['quantity'])) {
                     $food_id = filter_var($_POST['food_id'], FILTER_VALIDATE_INT);
                     $quantity = filter_var($_POST['quantity'], FILTER_VALIDATE_INT);

                     if ($food_id !== false && $quantity !== false && $quantity > 0) {
                         // Add to cart or update quantity if already exists
                         if (isset($_SESSION['cart'][$food_id])) {
                             $_SESSION['cart'][$food_id] += $quantity; // Add to existing quantity
                             $_SESSION['cart_message'] = "Số lượng món ăn đã được cập nhật trong giỏ hàng.";
                         } else {
                             $_SESSION['cart'][$food_id] = $quantity; // Add new item
                              $_SESSION['cart_message'] = "Món ăn đã được thêm vào giỏ hàng.";
                         }
                         // Redirect back to the *previous* page (usually food detail) after adding
                         $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'index.php';
                     } else {
                          $_SESSION['cart_error'] = "Không thể thêm vào giỏ. Dữ liệu không hợp lệ.";
                           error_log("Invalid data received for cart 'add' action. ID: '{$_POST['food_id']}', Qty: '{$_POST['quantity']}'");
                           $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'index.php'; // Redirect back on error too
                     }
                } else {
                      $_SESSION['cart_error'] = "Thiếu thông tin món ăn hoặc số lượng để thêm vào giỏ.";
                      $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'index.php'; // Redirect back
                }
                 break;

            default:
                 $_SESSION['cart_error'] = "Hành động không hợp lệ.";
                 error_log("Invalid cart action received: " . $action);
        }
    } catch (Exception $e) {
         // Catch any unexpected errors during processing
         $_SESSION['cart_error'] = "Đã xảy ra lỗi khi xử lý giỏ hàng.";
         error_log("Exception in cart_action.php: " . $e->getMessage());
    }

} else {
    // No action specified
    $_SESSION['cart_error'] = "Không có hành động nào được chỉ định.";
    error_log("cart_action.php called without a POST action.");
}

// Redirect back to the cart page (or previous page for 'add')
header('Location: ' . $redirect_url);
exit(); // Important to prevent further script execution after redirect
?>
<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['food_id']) && isset($_POST['quantity'])) {
    $food_id = filter_input(INPUT_POST, 'food_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

    if ($food_id && $quantity && $quantity > 0) {
        // Initialize cart if it doesn't exist
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        // If item already in cart, add to quantity; otherwise, set quantity
        if (isset($_SESSION['cart'][$food_id])) {
            $_SESSION['cart'][$food_id] += $quantity;
             $_SESSION['cart_message'] = "Số lượng món ăn đã được cập nhật trong giỏ hàng.";
        } else {
            $_SESSION['cart'][$food_id] = $quantity;
             $_SESSION['cart_message'] = "Món ăn đã được thêm vào giỏ hàng.";
        }
        // Redirect back to the food detail page (or wherever user came from)
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    } else {
         $_SESSION['cart_error'] = "Dữ liệu không hợp lệ để thêm vào giỏ.";
         // Redirect back with error
         header('Location: ' . $_SERVER['HTTP_REFERER']);
         exit;
    }
} else {
    // Redirect if accessed directly or invalid request
    header('Location: index.php');
    exit;
}
?>