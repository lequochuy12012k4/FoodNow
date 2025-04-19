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
                <a href="admin.php" class="logo">FoodNow Admin</a>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="admin.php"><i class="fas fa-tachometer-alt"></i><span>Tổng quan</span></a></li>
                    <!-- Make the current page active -->
                    <li><a href="admin_food.php"><i class="fas fa-utensils"></i> <span>Quản lý Món ăn</span></a></li>
                    <li><a href="admin_order.php"><i class="fas fa-receipt"></i> <span>Quản lý Đơn hàng</span></a></li>
                    <li><a href="admin_users.php"><i class="fas fa-users"></i> <span>Quản lý Người dùng</span></a></li>
                    <li class="active"><a href="admin_transfer.php"><i class="fas fa-money-check-dollar"></i> <span>Quản lý Giao dịch</span></a></li>
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
                    <h1>Quản lý Giao dịch</h1>
                </div>
                <div class="header-user">
                <input type="search" id="admin-search-user" placeholder="Tìm kiếm giao dịch..." autocomplete="off">
                <button class="search-btn"><i class="fas fa-search"></i></button>
                    <div class="user-info">
                        <img src="placeholder-avatar.png" alt="Admin Avatar" class="avatar">
                        <span><?php echo $admin_username; ?></span> <i class="fas fa-caret-down"></i>
                        <div class="user-dropdown">
                            <a href="#">Hồ sơ</a>
                            <a href="logout.php">Đăng xuất</a>
                        </div>
                    </div>
                </div>
            </header>
        </main>
    </div>
    <script src="js/admin.js"></script>
    <script>

    </script>

</body>
</html>