<?php
// =========================================================================
// PHP BACKEND LOGIC - START
// =========================================================================
session_start();
require_once 'db_connect.php'; // Needs correct path & db name 'foodnow'

$uploadDir = 'uploads/'; // Directory for image uploads
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$message = ''; // For flash messages displayed on the page
$msg_type = ''; // 'success' or 'danger'

// --- Handle Actions (POST requests: Add/Edit) ---

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {

    // Common data retrieval & sanitization
    $action = $_POST['action'];
    $name = mysqli_real_escape_string($conn, trim($_POST['name'] ?? ''));
    $type = mysqli_real_escape_string($conn, trim($_POST['type'] ?? ''));
    // Price: Remove any non-digit characters (like commas or currency symbols) before converting
    $price_str = preg_replace('/[^\d]/', '', $_POST['price'] ?? '0');
    $price = floatval($price_str);
    $rate = intval($_POST['rate'] ?? 0); // Rate comes from select, cast to int
    $description = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0; // For edits
    $current_image = isset($_POST['current_image']) ? mysqli_real_escape_string($conn, $_POST['current_image']) : ''; // For edits


    // Basic Validation (Server-side)
    if (empty($name)) {
        $_SESSION['message'] = 'Tên món ăn không được để trống.';
        $_SESSION['msg_type'] = 'danger';
    } elseif (empty($type)) {
        $_SESSION['message'] = 'Vui lòng chọn loại món ăn.';
        $_SESSION['msg_type'] = 'danger';
    } elseif ($price <= 0 && $action == 'add') { // Price can be 0 for free items? Adjust if needed. Check >0 for new items.
        $_SESSION['message'] = 'Giá phải là một số dương.';
        $_SESSION['msg_type'] = 'danger';
    } elseif ($rate < 0 || $rate > 5) {
        $_SESSION['message'] = 'Đánh giá không hợp lệ.';
        $_SESSION['msg_type'] = 'danger';
    }
    // Add more validation as needed (e.g., description length)


    // Proceed if no validation errors so far
    if (!isset($_SESSION['message'])) {
        $image_name_to_db = ($action == 'edit') ? $current_image : ''; // Default for edit, empty for add

        // --- Handle Image Upload ---
        if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
            $img_tmp_name = $_FILES['image']['tmp_name'];
            $img_error = $_FILES['image']['error'];
            $img_size = $_FILES['image']['size'];
            $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_file_size = 5 * 1024 * 1024; // 5 MB

            $image_info = getimagesize($img_tmp_name);
            $mime_type = $image_info['mime'] ?? null;

            if (!$image_info || !in_array($mime_type, $allowed_mime_types)) {
                $_SESSION['message'] = 'Chỉ cho phép ảnh JPG, PNG, GIF.';
                $_SESSION['msg_type'] = 'danger';
            } elseif ($img_size > $max_file_size) {
                $_SESSION['message'] = 'Kích thước ảnh không được vượt quá 5MB.';
                $_SESSION['msg_type'] = 'danger';
            } else {
                // Generate unique name and move file
                $new_image_name = uniqid('food_') . '_' . basename($_FILES['image']['name']);
                $target_file = $uploadDir . $new_image_name;

                if (move_uploaded_file($img_tmp_name, $target_file)) {
                    // Success! Update image name for DB
                    $image_name_to_db = $new_image_name;

                    // If editing and upload successful, delete old image (if it exists and is different)
                    if ($action == 'edit' && !empty($current_image) && $current_image != $new_image_name && file_exists($uploadDir . $current_image)) {
                        @unlink($uploadDir . $current_image); // Use @ to suppress errors if file not found
                    }
                } else {
                    $_SESSION['message'] = 'Lỗi khi tải ảnh lên.';
                    $_SESSION['msg_type'] = 'danger';
                    $image_name_to_db = ($action == 'edit') ? $current_image : ''; // Revert to old image on failure
                }
            }
        } // End image upload handling

        // --- Database Operation (Only if no critical errors like validation or upload failure) ---
        if (!isset($_SESSION['message']) || $_SESSION['msg_type'] !== 'danger') {
            if ($action == 'add') {
                $sql = "INSERT INTO food_data (name, type, price, rate, description, image) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                // Note: price is decimal/float in DB, rate is integer
                mysqli_stmt_bind_param($stmt, "ssdiss", $name, $type, $price, $rate, $description, $image_name_to_db);
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['message'] = 'Thêm món ăn thành công!';
                    $_SESSION['msg_type'] = 'success';
                } else {
                    $_SESSION['message'] = 'Lỗi thêm món ăn: ' . mysqli_error($conn);
                    $_SESSION['msg_type'] = 'danger';
                    // Delete uploaded image if DB insert failed
                    if (!empty($image_name_to_db) && file_exists($uploadDir . $image_name_to_db)) {
                        @unlink($uploadDir . $image_name_to_db);
                    }
                }
                mysqli_stmt_close($stmt);
            } elseif ($action == 'edit' && $id > 0) {
                $sql = "UPDATE food_data SET name=?, type=?, price=?, rate=?, description=?, image=? WHERE id=?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ssdissi", $name, $type, $price, $rate, $description, $image_name_to_db, $id);
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['message'] = 'Cập nhật món ăn thành công!';
                    $_SESSION['msg_type'] = 'success';
                } else {
                    $_SESSION['message'] = 'Lỗi cập nhật món ăn: ' . mysqli_error($conn);
                    $_SESSION['msg_type'] = 'danger';
                    // Don't delete the *new* image if update failed, maybe DB issue is temporary
                }
                mysqli_stmt_close($stmt);
            }
        }
    }

    // Redirect after POST to prevent resubmission
    header("Location: admin_food.php");
    exit();
}

// --- Handle Actions (GET requests: Delete) ---

if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // First, get the image filename to delete it from the server
    $sql_select = "SELECT image FROM food_data WHERE id = ?";
    $stmt_select = mysqli_prepare($conn, $sql_select);
    mysqli_stmt_bind_param($stmt_select, "i", $id);
    mysqli_stmt_execute($stmt_select);
    $result_select = mysqli_stmt_get_result($stmt_select);
    $food_item = mysqli_fetch_assoc($result_select);
    mysqli_stmt_close($stmt_select);

    if ($food_item) {
        $image_to_delete = $food_item['image'];

        // Now, delete the database record
        $sql_delete = "DELETE FROM food_data WHERE id = ?";
        $stmt_delete = mysqli_prepare($conn, $sql_delete);
        mysqli_stmt_bind_param($stmt_delete, "i", $id);

        if (mysqli_stmt_execute($stmt_delete)) {
            $_SESSION['message'] = 'Xóa món ăn thành công!';
            $_SESSION['msg_type'] = 'success';
            // Delete the image file if it exists
            if (!empty($image_to_delete) && file_exists($uploadDir . $image_to_delete)) {
                @unlink($uploadDir . $image_to_delete);
            }
        } else {
            $_SESSION['message'] = 'Lỗi xóa món ăn: ' . mysqli_error($conn);
            $_SESSION['msg_type'] = 'danger';
        }
        mysqli_stmt_close($stmt_delete);
    } else {
        $_SESSION['message'] = 'Không tìm thấy món ăn để xóa.';
        $_SESSION['msg_type'] = 'danger';
    }
    header("Location: admin_food.php"); // Redirect after delete
    exit();
}


// --- Fetch Data for Display ---
$foods = [];
$sql = "SELECT * FROM food_data ORDER BY created_at DESC"; // Use correct table name
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Ensure rate is integer (it should be from DB, but good practice)
        $row['rate'] = intval($row['rate']);
        $foods[] = $row;
    }
    mysqli_free_result($result);
} else {
    // Set a message to display if fetching fails, but don't use session here
    // as it's not a redirect scenario
    $message = "Lỗi tải danh sách món ăn: " . mysqli_error($conn);
    $msg_type = 'danger';
}

// --- Get Session Flash Message (if any) ---
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $msg_type = $_SESSION['msg_type'];
    unset($_SESSION['message']); // Clear message after getting it
    unset($_SESSION['msg_type']);
}

mysqli_close($conn);
$db_host = 'localhost';      // Or your database host
$db_name = 'foodnow';        // Your database name
$db_user = 'root';  // Your database username
$db_pass = '';  // Your database password
$charset = 'utf8mb4';

// PDO connection options
// IMPORTANT: Set error mode to exceptions for easier error handling
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Default fetch mode as associative array
    PDO::ATTR_EMULATE_PREPARES   => false, // Use native prepared statements
];

// Data Source Name (DSN)
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$charset";

try {
    // Create the PDO instance (the connection object)
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (\PDOException $e) {
    // Log the error properly in a production environment
    error_log("Database Connection Error: " . $e->getMessage());
    // Display a generic error message to the user and stop script execution
    // Avoid echoing $e->getMessage() directly in production!
    die("Database connection failed. Please check configuration or contact support.");
}
?>