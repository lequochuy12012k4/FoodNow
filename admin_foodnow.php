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
// =========================================================================
// PHP BACKEND LOGIC - END
// =========================================================================
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoodNow Admin - Quản lý Món ăn</title>
    <!-- Link to your CSS file -->
    <link rel="stylesheet" href="css/admin_foodnow.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
</head>

<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="admin_food.php" class="logo">FoodNow Admin</a>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="#"><i class="fas fa-tachometer-alt"></i> <span>Tổng quan</span></a></li>
                    <!-- Make the current page active -->
                    <li class="active"><a href="admin_food.php"><i class="fas fa-utensils"></i> <span>Quản lý Món ăn</span></a></li>
                    <li><a href="#"><i class="fas fa-receipt"></i> <span>Quản lý Đơn hàng</span></a></li>
                    <li><a href="#"><i class="fas fa-users"></i> <span>Quản lý Người dùng</span></a></li>
                    <li><a href="#"><i class="fas fa-cog"></i> <span>Cài đặt</span></a></li>
                    <li><a href="#"><i class="fas fa-sign-out-alt"></i> <span>Đăng xuất</span></a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
            <header class="main-header">
                <div class="header-title">
                    <button class="header-menu-toggle" aria-label="Toggle Sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1>Quản lý Món ăn</h1>
                </div>
                <div class="header-user">
                    <input type="search" placeholder="Tìm kiếm món ăn...">
                    <button class="search-btn"><i class="fas fa-search"></i></button>
                    <div class="user-info">
                        <img src="placeholder-avatar.png" alt="Admin Avatar" class="avatar">
                        <span>Admin</span> <i class="fas fa-caret-down"></i>
                        <div class="user-dropdown">
                            <a href="#">Hồ sơ</a>
                            <a href="#">Đăng xuất</a>
                        </div>
                    </div>
                </div>
            </header>

            <section class="content-area">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($msg_type); ?>" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="dashboard-cards">
                    <div class="card">
                        <div class="card-icon"><i class="fas fa-utensils"></i></div>
                        <div class="card-info">
                            <h3>150</h3>
                            <p>Món ăn</p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-icon"><i class="fas fa-receipt"></i></div>
                        <div class="card-info">
                            <h3>58</h3>
                            <p>Đơn hàng hôm nay</p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-icon"><i class="fas fa-users"></i></div>
                        <div class="card-info">
                            <h3>1200</h3>
                            <p>Người dùng</p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-icon"><i class="fas fa-dollar-sign"></i></div>
                        <div class="card-info">
                            <h3>$5,678</h3>
                            <p>Doanh thu (Tháng)</p>
                        </div>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="data-table-container">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2>Danh sách Món ăn</h2>
                        <button class="btn btn-primary add-button" id="show-add-modal-btn">
                            <i class="fas fa-plus"></i> Thêm Món ăn
                        </button>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên món ăn</th>
                                <th>Loại</th>
                                <th>Giá (VNĐ)</th>
                                <th>Đánh giá</th>
                                <th style="text-align: center;">Ảnh</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody id="food-table-body">
                            <?php if (empty($foods)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding: 20px;">Chưa có món ăn nào.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($foods as $food): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($food['id']); ?></td>
                                        <td><?php echo htmlspecialchars($food['name']); ?></td>
                                        <td><?php echo htmlspecialchars($food['type']); ?></td>
                                        <td><?php echo number_format($food['price'], 0, ',', '.'); ?></td>
                                        <td style="text-align: center; white-space: nowrap;">
                                            <?php echo str_repeat('⭐', $food['rate']) . str_repeat('☆', 5 - $food['rate']); ?>
                                            (<?php echo htmlspecialchars($food['rate']); ?>)
                                        </td>
                                        <td style="text-align: center; vertical-align: middle;">
                                            <?php if (!empty($food['image']) && file_exists($uploadDir . $food['image'])): ?>
                                                <img src="<?php echo $uploadDir . htmlspecialchars($food['image']); ?>"
                                                    alt="<?php echo htmlspecialchars($food['name']); ?>"
                                                    class="table-food-image">
                                            <?php else: ?>
                                                <span style="font-size: 0.8em; color: #888;">(Chưa có ảnh)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="actions" style="white-space: nowrap;">
                                            <button class="btn btn-edit"
                                                onclick="openEditModal(<?php echo htmlspecialchars(json_encode($food), ENT_QUOTES, 'UTF-8'); ?>)">
                                                <i class="fas fa-edit"></i> Sửa
                                            </button>
                                            <a href="admin_food.php?action=delete&id=<?php echo $food['id']; ?>"
                                                class="btn btn-delete"
                                                onclick="return confirm('Bạn có chắc chắn muốn xóa món ăn: \'<?php echo htmlspecialchars(addslashes($food['name']), ENT_QUOTES); ?>\'?\nHành động này không thể hoàn tác.');">
                                                <i class="fas fa-trash-alt"></i> Xóa
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </section>
        </main>
    </div>

    <!-- Add/Edit Item Modal -->
    <div id="add-item-modal" class="modal">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <button class="modal-close" id="close-modal-btn">×</button>
            <h2 id="modal-title">Thêm Món ăn mới</h2>
            <div id="add-item-form-message" class="alert" style="display: none; margin-bottom: 15px;"></div>

            <form id="add-item-form" action="admin_food.php" method="post" enctype="multipart/form-data">
                <input type="hidden" id="form-action" name="action" value="add">
                <input type="hidden" id="edit-item-id" name="id" value="">
                <input type="hidden" id="current-image-filename" name="current_image" value="">


                <div class="form-group">
                    <label for="item-name">Tên món ăn:</label>
                    <input type="text" id="item-name" name="name" placeholder="Ví dụ: Phở Bò Tái" required>
                </div>

                <div class="form-group">
                    <label for="item-type">Loại món ăn:</label>
                    <select id="item-type" name="type" required>
                        <option value="" disabled selected>-- Chọn loại món ăn --</option>
                        <option value="Món khai vị">Món khai vị</option>
                        <option value="Món chính">Món chính</option>
                        <option value="Tráng miệng">Tráng miệng</option>
                        <option value="Nước uống">Nước uống</option>
                        <option value="Bánh ngọt">Bánh ngọt</option>
                        <option value="Đồ ăn nhanh">Đồ ăn nhanh</option>
                        <option value="Món chay">Món chay</option>
                        <option value="Trái cây">Trái cây</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="item-price">Giá (VNĐ):</label>
                    <input type="text" id="item-price" name="price" placeholder="Ví dụ: 50000" required inputmode="numeric" pattern="[0-9]*">
                </div>

                <div class="form-group">
                    <label for="item-rating">Đánh giá (0-5):</label>
                    <select id="item-rating" name="rate" required>
                        <option value="" disabled selected>-- Chọn đánh giá --</option>
                        <option value="5">5 ⭐⭐⭐⭐⭐</option>
                        <option value="4">4 ⭐⭐⭐⭐</option>
                        <option value="3">3 ⭐⭐⭐</option>
                        <option value="2">2 ⭐⭐</option>
                        <option value="1">1 ⭐</option>
                        <option value="0">0 (Chưa đánh giá)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="item-description">Miêu tả:</label>
                    <textarea id="item-description" name="description" placeholder="Miêu tả ngắn gọn về món ăn..."></textarea>
                </div>

                <div class="form-group">
                    <label for="item-image">Ảnh món ăn (Để trống nếu không đổi ảnh khi sửa):</label>
                    <input type="file" id="item-image" name="image" accept="image/png, image/jpeg, image/gif">
                    <div id="current-image-preview" style="margin-top: 10px; font-size: 0.9em; color: #555;"></div>
                </div>

                <button type="submit" class="btn btn-primary modal-submit-btn" id="modal-submit-button">
                    <i class="fas fa-check"></i> <span id="modal-submit-button-text">Thêm món ăn</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Link to your JS file -->
    <script src="js/admin_foodnow.js"></script>

</body>

</html>