<?php
// Ensure session is started
include 'config/admin_config.php'; // Include database connection

// Check if user is logged in and is an admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["role"]) || $_SESSION["role"] !== 'admin') {
    // If not logged in or not an admin, redirect to login page
    header("location: login.php");
    exit;
}

// Get admin username for display
$admin_username = htmlspecialchars($_SESSION["username"]);

// --- START: FILTER LOGIC ---
$filter_type = ''; // Default: show all types
if (isset($_GET['filter_type']) && !empty($_GET['filter_type'])) {
    $filter_type = trim($_GET['filter_type']);
}

// --- END: FILTER LOGIC ---


// Define upload directory (adjust path as needed)
$uploadDir = 'uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true); // Create directory if it doesn't exist
}


$message = '';
$msg_type = '';

// --- START: FETCH FOODS WITH FILTER ---
// Base SQL query
$sql = "SELECT id, name, type, price, rate, description, image FROM food_data";

// Add filter condition if a type is selected
if (!empty($filter_type)) {
    $sql .= " WHERE type = ?"; // Use prepared statement placeholder
}

$sql .= " ORDER BY id DESC"; // Or any other ordering you prefer

$stmt = $pdo->prepare($sql);

// Bind the filter parameter if it exists
if (!empty($filter_type)) {
    $stmt->bindParam(1, $filter_type, PDO::PARAM_STR);
}

// Execute the query
try {
    $stmt->execute();
    $foods = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle potential database errors
    $foods = []; // Set to empty array on error
    $message = "Lỗi khi truy vấn món ăn: " . $e->getMessage();
    $msg_type = "danger";
    // Log the error for debugging: error_log("Database Error: " . $e->getMessage());
}
// --- END: FETCH FOODS WITH FILTER ---
$total_food_items = 0;
$most_frequent_type = 'N/A';
$highest_rated_food_name = 'N/A';
$total_food_value = 0;
$highest_rate_so_far = -1; // Initialize lower than any possible rate

if (!empty($foods)) {
    // 1. Total Food Items
    $total_food_items = count($foods);

    // Initialize arrays/variables for calculations
    $type_counts = [];
    $current_total_value = 0; // Use a temporary variable for summing

    foreach ($foods as $food) {
        // 2. Count Types
        $type = $food['type'] ?? 'Unknown'; // Handle cases where type might be null/missing
        if (!isset($type_counts[$type])) {
            $type_counts[$type] = 0;
        }
        $type_counts[$type]++;

        // 3. Find Highest Rated Food
        $current_rate = isset($food['rate']) ? (int)$food['rate'] : -1; // Default to -1 if no rate
        if ($current_rate > $highest_rate_so_far) {
            $highest_rate_so_far = $current_rate;
            $highest_rated_food_name = $food['name'] ?? 'Unknown Name';
        } elseif ($current_rate === $highest_rate_so_far && $highest_rated_food_name === 'N/A') {
            // Handle cases where the very first item might be the highest rated
             $highest_rated_food_name = $food['name'] ?? 'Unknown Name';
        }


        // 4. Calculate Total Value
        $price = isset($food['price']) ? filter_var($food['price'], FILTER_VALIDATE_FLOAT) : 0;
        if ($price !== false) { // Check if price is a valid number
             $current_total_value += $price;
        }
    }

    // Find the most frequent type after looping
    if (!empty($type_counts)) {
        arsort($type_counts); // Sort types by count descending
        $most_frequent_type = key($type_counts); // Get the type name with the highest count
    }

    // Assign the final total value
    $total_food_value = $current_total_value;

    // Refine highest rated name if no rated items were found
    if ($highest_rate_so_far === -1 && $total_food_items > 0) {
        $highest_rated_food_name = 'Chưa có đánh giá';
    }


}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoodNow Admin - Quản lý Món ăn</title>
    <!-- Link to your CSS file -->
    <link rel="stylesheet" href="css/admin.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="shortcut icon" href="image/foodnow_icon.png" sizes="32x32" type="image/x-icon">
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
                <a href="admin.php" class="logo">FoodNow Admin</a>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="admin.php"><i class="fas fa-tachometer-alt"></i><span>Tổng quan</span></a></li>
                    <!-- Make the current page active -->
                    <li class="active"><a href="admin_food.php"><i class="fas fa-utensils"></i> <span>Quản lý Món ăn</span></a></li>
                    <li><a href="admin_order.php"><i class="fas fa-receipt"></i> <span>Quản lý Đơn hàng</span></a></li>
                    <li><a href="admin_users.php"><i class="fas fa-users"></i> <span>Quản lý Người dùng</span></a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Đăng xuất</span></a></li>
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
                    <input type="search" id="admin-search-food" placeholder="Tìm kiếm món ăn..." autocomplete="off">
                    <button class="search-btn"><i class="fas fa-search"></i></button>
                    <?php include 'parts/admin_info.php' ?>
                </div>
            </header>

            <section class="content-area">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($msg_type); ?>" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="dashboard-cards">
     <!-- Card 1: Total Food Items -->
     <div class="card">
         <div class="card-icon"><i class="fas fa-utensils"></i></div>
         <div class="card-info">
             <!-- Display total food items count -->
             <h3><?php echo $total_food_items; ?></h3>
             <p>Tổng số món ăn</p>
         </div>
     </div>

     <!-- Card 2: Most Frequent Food Type -->
     <div class="card">
         <div class="card-icon"><i class="fas fa-tags"></i></div>
         <div class="card-info">
             <!-- Display the most frequent type -->
             <h3 style="font-size: 1.2em; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($most_frequent_type); ?>">
                 <?php echo htmlspecialchars($most_frequent_type); ?>
             </h3>
             <p>Loại phổ biến nhất</p>
         </div>
     </div>

     <!-- Card 3: Highest Rated Food Item -->
     <div class="card">
         <div class="card-icon"><i class="fas fa-star"></i></div>
         <div class="card-info">
             <!-- Display the name of the highest-rated food -->
              <h3 style="font-size: 1.2em; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($highest_rated_food_name); ?>">
                 <?php echo htmlspecialchars($highest_rated_food_name); ?>
             </h3>
             <p>Đánh giá cao nhất</p>
         </div>
     </div>

     <!-- Card 4: Total Value of All Food Items -->
     <div class="card">
         <div class="card-icon"><i class="fas fa-coins"></i></div>
         <div class="card-info">
             <!-- Display the total value, formatted as VNĐ -->
             <h3><?php echo number_format($total_food_value, 0, ',', '.'); ?> <span style="font-size: 0.7em;">VNĐ</span></h3>
             <p>Tổng giá trị Menu</p>
         </div>
     </div>
 </div>

                <!-- Data Table -->
                <div class="data-table-container">
                    <div class="table-controls" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                        <h2>Danh sách Món ăn</h2>

                        <!-- --- START: FILTER FORM --- -->
                        <form action="admin_food.php" method="get" id="filter-form" style="display: flex; align-items: center; gap: 10px;">
                            <label for="food-type-filter" style="margin-bottom: 0;">Lọc theo loại:</label>
                            <select name="filter_type" id="food-type-filter" onchange="this.form.submit()" style="padding: 5px 8px; border-radius: 4px; border: 1px solid #ccc;">
                                <option value="">-- Tất cả loại --</option>
                                <?php
                                // Define food types (same as in the modal)
                                $food_types = ["Món khai vị", "Món chính", "Tráng miệng", "Nước uống", "Bánh ngọt", "Đồ ăn nhanh", "Đồ ăn chay", "Trái cây"];
                                foreach ($food_types as $type_option) {
                                    // Check if this option should be selected
                                    $selected = ($filter_type === $type_option) ? ' selected' : '';
                                    echo '<option value="' . htmlspecialchars($type_option) . '"' . $selected . '>' . htmlspecialchars($type_option) . '</option>';
                                }
                                ?>
                            </select>
                            <?php /* Optional: Keep a hidden submit button for non-JS users or remove if relying solely on onchange
                            <button type="submit" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.9em;">
                                <i class="fas fa-filter"></i> Lọc
                            </button>
                             */ ?>
                        </form>
                        <!-- --- END: FILTER FORM --- -->


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
                                    <td colspan="7" style="text-align:center; padding: 20px;">
                                        <?php echo !empty($filter_type) ? 'Không tìm thấy món ăn nào thuộc loại "' . htmlspecialchars($filter_type) . '".' : 'Chưa có món ăn nào.'; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($foods as $food): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($food['id']); ?></td>
                                        <td><?php echo htmlspecialchars($food['name']); ?></td>
                                        <td><?php echo htmlspecialchars($food['type']); ?></td>
                                        <td><?php echo number_format($food['price'], 0, ',', '.'); ?></td>
                                        <td style="text-align: center; white-space: nowrap; padding: 25px 0px;">
                                            <?php
                                            $rate = isset($food['rate']) ? (int)$food['rate'] : 0; // Ensure rate is numeric
                                            $rate = max(0, min(5, $rate)); // Clamp between 0 and 5
                                            echo str_repeat('⭐', $rate) . str_repeat('', 5 - $rate); // Use filled and empty stars
                                            ?>
                                            (<?php echo htmlspecialchars($rate); ?>)
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
                                            <?php
                                                // Append filter type to delete URL if active
                                                $delete_url = 'admin_food.php?action=delete&id=' . $food['id'];
                                                if (!empty($filter_type)) {
                                                    $delete_url .= '&filter_type=' . urlencode($filter_type);
                                                }
                                            ?>
                                            <a href="<?php echo $delete_url; ?>"
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

             <!-- Make sure the form submits back preserving filter if needed -->
             <form id="add-item-form" action="admin_food.php<?php echo !empty($filter_type) ? '?filter_type=' . urlencode($filter_type) : ''; ?>" method="post" enctype="multipart/form-data">
                <input type="hidden" id="form-action" name="action" value="add">
                <input type="hidden" id="edit-item-id" name="id" value="">
                <input type="hidden" id="current-image-filename" name="current_image" value="">
                <!-- Optional: Add hidden field to preserve filter on form submission if JS fails or for complex cases -->
                <?php if (!empty($filter_type)): ?>
                    <input type="hidden" name="filter_type" value="<?php echo htmlspecialchars($filter_type); ?>">
                <?php endif; ?>


                <div class="form-group">
                    <label for="item-name">Tên món ăn:</label>
                    <input type="text" id="item-name" name="name" placeholder="Ví dụ: Phở Bò Tái" required>
                </div>

                <div class="form-group">
                    <label for="item-type">Loại món ăn:</label>
                    <select id="item-type" name="type" required>
                        <option value="" disabled selected>-- Chọn loại món ăn --</option>
                        <?php
                         // Re-use the food types array
                        foreach ($food_types as $type_option) {
                            echo '<option value="' . htmlspecialchars($type_option) . '">' . htmlspecialchars($type_option) . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="item-price">Giá (VNĐ):</label>
                    <!-- Using type="number" is better for numeric input, but text with pattern allows easier formatting potentially -->
                    <input type="number" id="item-price" name="price" placeholder="Ví dụ: 50000" required min="0" step="1000">
                    <!-- OR -->
                    <!-- <input type="text" id="item-price" name="price" placeholder="Ví dụ: 50000" required inputmode="numeric" pattern="[0-9]*"> -->
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
                    <input type="file" id="item-image" name="image" accept="image/png, image/jpeg, image/gif, image/webp"> <!-- Added webp -->
                    <div id="current-image-preview" style="margin-top: 10px; font-size: 0.9em; color: #555;"></div>
                </div>

                <button type="submit" class="btn btn-primary modal-submit-btn" id="modal-submit-button">
                    <i class="fas fa-check"></i> <span id="modal-submit-button-text">Thêm món ăn</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Link to your JS file -->
    <script src="js/admin.js"></script>
    <script>
       // Handle sidebar toggle persistence if needed (using localStorage)
       const sidebar = document.querySelector('.sidebar');
       const mainContent = document.querySelector('.main-content');
       const toggleButton = document.querySelector('.header-menu-toggle');

       const isSidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';

       if (isSidebarCollapsed) {
           document.body.classList.add('sidebar-collapsed');
       }

       toggleButton.addEventListener('click', () => {
           document.body.classList.toggle('sidebar-collapsed');
           localStorage.setItem('sidebarCollapsed', document.body.classList.contains('sidebar-collapsed'));
       });


       // Make dropdown work better on click/touch
       const userInfo = document.querySelector('.header-user .user-info');
       if (userInfo) {
           userInfo.addEventListener('click', function(event) {
               // Prevent dropdown from closing if click is inside dropdown
               if (event.target.closest('.user-dropdown')) {
                   return;
               }
               this.classList.toggle('active');
           });
           // Close dropdown if clicked outside
           document.addEventListener('click', function(event) {
               if (!userInfo.contains(event.target)) {
                   userInfo.classList.remove('active');
               }
           });
       }


        // --- Add/Edit Modal Logic (from admin.js or similar) ---
        const modal = document.getElementById('add-item-modal');
        const showModalBtn = document.getElementById('show-add-modal-btn');
        const closeModalBtn = document.getElementById('close-modal-btn');
        const overlay = modal.querySelector('.modal-overlay');
        const form = document.getElementById('add-item-form');
        const modalTitle = document.getElementById('modal-title');
        const formActionInput = document.getElementById('form-action');
        const editItemIdInput = document.getElementById('edit-item-id');
        const modalSubmitButtonText = document.getElementById('modal-submit-button-text');
        const currentImagePreview = document.getElementById('current-image-preview');
        const currentImageFilenameInput = document.getElementById('current-image-filename');
        const formMessageDiv = document.getElementById('add-item-form-message');


        function openModal() {
            form.reset(); // Clear previous data
            modalTitle.textContent = 'Thêm Món ăn mới';
            formActionInput.value = 'add';
            editItemIdInput.value = '';
            modalSubmitButtonText.textContent = 'Thêm món ăn';
            currentImagePreview.innerHTML = ''; // Clear image preview
            currentImageFilenameInput.value = '';
            formMessageDiv.style.display = 'none'; // Hide message div
            modal.style.display = 'flex';
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        function openEditModal(foodItem) {
            form.reset(); // Clear previous data first
            modalTitle.textContent = 'Sửa thông tin Món ăn';
            formActionInput.value = 'edit';
            editItemIdInput.value = foodItem.id;
            modalSubmitButtonText.textContent = 'Cập nhật món ăn';
            formMessageDiv.style.display = 'none'; // Hide message div

            // Populate form fields
            document.getElementById('item-name').value = foodItem.name || '';
            document.getElementById('item-type').value = foodItem.type || '';
            document.getElementById('item-price').value = foodItem.price || '';
            document.getElementById('item-rating').value = foodItem.rate !== null ? foodItem.rate : ''; // Handle null rate
            document.getElementById('item-description').value = foodItem.description || '';
             currentImageFilenameInput.value = foodItem.image || ''; // Store current image filename

            // Display current image info
            if (foodItem.image) {
                // Adjust the path prefix as needed for display
                const imagePath = '<?php echo $uploadDir; ?>' + foodItem.image;
                currentImagePreview.innerHTML = `Ảnh hiện tại: <img src="${imagePath}" alt="Current Image" style="max-height: 50px; vertical-align: middle; margin-left: 10px;">`;
            } else {
                currentImagePreview.innerHTML = 'Chưa có ảnh.';
            }


            modal.style.display = 'flex';
        }

        // Event Listeners
        if (showModalBtn) {
            showModalBtn.addEventListener('click', openModal);
        }
        if(closeModalBtn) {
            closeModalBtn.addEventListener('click', closeModal);
        }
        if (overlay) {
            overlay.addEventListener('click', closeModal);
        }

        // Close modal with Escape key
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal.style.display === 'flex') {
                closeModal();
            }
        });


    </script>

</body>
</html>