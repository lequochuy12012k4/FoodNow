<?php
// Ensure session is started
// Make sure this path is correct for your setup
require_once 'config/admin_config.php'; // Use require_once for essential files

// Check if user is logged in and is an admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["role"]) || $_SESSION["role"] !== 'admin') {
    // If not logged in or not an admin, redirect to login page
    header("location: login.php");
    exit;
}

// Get admin username for display
$admin_username = htmlspecialchars($_SESSION["username"] ?? 'Admin'); // Default if not set

// Define upload directory (adjust path as needed)
$uploadDir = 'uploads/';
if (!is_dir($uploadDir)) {
    // Attempt to create directory recursively with appropriate permissions
    if (!mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
         // Log error or display a message if directory creation fails
         error_log("Failed to create upload directory: " . $uploadDir);
         // Optionally display an error message to the admin, but maybe not die
    }
}

$message = '';
$msg_type = '';

// --- START: FETCH FOOD STATS (Existing Logic) ---
$sql_food = "SELECT id, name, type, price, rate, description, image FROM food_data ORDER BY id DESC";
$foods = [];
$total_food_items = 0;
$most_frequent_type = 'N/A';
$highest_rated_food_name = 'N/A';
$total_food_value = 0; // Represents the sum of *prices* of all food items listed, not inventory value
$highest_rate_so_far = -1;

try {
    $stmt_food = $pdo->query($sql_food); // Simple query, no parameters needed here
    $foods = $stmt_food->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($foods)) {
        $total_food_items = count($foods);
        $type_counts = [];
        $current_total_value = 0;

        foreach ($foods as $food) {
            // Count Types
            $type = $food['type'] ?? 'Unknown';
            $type_counts[$type] = ($type_counts[$type] ?? 0) + 1;

            // Find Highest Rated Food
            $current_rate = isset($food['rate']) ? (int)$food['rate'] : -1;
            if ($current_rate > $highest_rate_so_far) {
                $highest_rate_so_far = $current_rate;
                $highest_rated_food_name = $food['name'] ?? 'Unknown Name';
            } elseif ($current_rate === $highest_rate_so_far && $highest_rated_food_name === 'N/A') {
                 $highest_rated_food_name = $food['name'] ?? 'Unknown Name';
            }

            // Calculate Total Value (Sum of listed prices)
            $price = isset($food['price']) ? filter_var($food['price'], FILTER_VALIDATE_FLOAT) : 0;
            if ($price !== false) {
                 $current_total_value += $price;
            }
        }

        if (!empty($type_counts)) {
            arsort($type_counts);
            $most_frequent_type = key($type_counts);
        }

        $total_food_value = $current_total_value;

        if ($highest_rate_so_far === -1 && $total_food_items > 0) {
            $highest_rated_food_name = 'Chưa có đánh giá';
        } elseif ($total_food_items === 0) {
             $highest_rated_food_name = 'Không có món ăn';
        }
    }

} catch (PDOException $e) {
    $message = "Lỗi khi truy vấn món ăn: " . $e->getMessage();
    $msg_type = "danger";
    error_log("Database Error (Food Fetch): " . $e->getMessage());
}
// --- END: FETCH FOOD STATS ---


// --- START: FETCH MONTHLY STATS ---
$monthly_revenue = 0;
$total_items_ordered_month = 0;
$monthly_cost_estimated = 0;
$monthly_profit_estimated = 0;

// Define the start and end dates for the current month
$start_date = date('Y-m-01 00:00:00');
$end_date = date('Y-m-t 23:59:59'); // 't' gives the last day of the month

// **IMPORTANT**: Define the status that counts towards revenue (e.g., 'completed', 'delivered')
$revenue_generating_status = 'completed'; // Adjust as needed

// 1. Calculate Monthly Revenue (from completed orders)
$sql_revenue = "SELECT SUM(total_amount) as total_revenue
                FROM orders
                WHERE order_date BETWEEN :start_date AND :end_date
                  AND status = :status"; // Filter by status

try {
    $stmt_revenue = $pdo->prepare($sql_revenue);
    $stmt_revenue->bindParam(':start_date', $start_date, PDO::PARAM_STR);
    $stmt_revenue->bindParam(':end_date', $end_date, PDO::PARAM_STR);
    $stmt_revenue->bindParam(':status', $revenue_generating_status, PDO::PARAM_STR);
    $stmt_revenue->execute();
    $result_revenue = $stmt_revenue->fetch(PDO::FETCH_ASSOC);
    if ($result_revenue && $result_revenue['total_revenue'] !== null) {
        $monthly_revenue = (float)$result_revenue['total_revenue'];
    }
} catch (PDOException $e) {
    $message .= "<br>Lỗi khi tính doanh thu tháng: " . $e->getMessage(); // Append message
    $msg_type = "danger";
    error_log("Database Error (Monthly Revenue): " . $e->getMessage());
    // monthly_revenue remains 0
}

// 2. Calculate Total Items Ordered This Month (from completed orders)
// Assumes an 'order_items' table exists with 'order_id' and 'quantity'
$sql_items = "SELECT SUM(oi.quantity) as total_items
              FROM order_items oi
              JOIN orders o ON oi.order_id = o.id
              WHERE o.order_date BETWEEN :start_date AND :end_date
                AND o.status = :status"; // Ensure items are from completed orders

try {
    $stmt_items = $pdo->prepare($sql_items);
    $stmt_items->bindParam(':start_date', $start_date, PDO::PARAM_STR);
    $stmt_items->bindParam(':end_date', $end_date, PDO::PARAM_STR);
    $stmt_items->bindParam(':status', $revenue_generating_status, PDO::PARAM_STR);
    $stmt_items->execute();
    $result_items = $stmt_items->fetch(PDO::FETCH_ASSOC);
    if ($result_items && $result_items['total_items'] !== null) {
        $total_items_ordered_month = (int)$result_items['total_items'];
    }
} catch (PDOException $e) {
    // Check if the error is due to the table not existing
    if (strpos($e->getMessage(), 'order_items') !== false) {
         $message .= "<br>Lưu ý: Bảng 'order_items' không tồn tại hoặc có lỗi. Không thể tính số lượng món đã đặt.";
         error_log("Database Warning/Error (Monthly Items): Table 'order_items' likely missing or query failed - " . $e->getMessage());
    } else {
        $message .= "<br>Lỗi khi tính số lượng món đã đặt: " . $e->getMessage(); // Append message
        error_log("Database Error (Monthly Items): " . $e->getMessage());
    }
    $msg_type = "warning"; // Use warning if it might be a missing table
     // total_items_ordered_month remains 0
}


// 3. Estimate Monthly Cost and Profit
// **ESTIMATION**: Assume cost is 40% of revenue. Adjust this factor as needed.
$cost_factor = 0.40;
if ($monthly_revenue > 0) {
    $monthly_cost_estimated = $monthly_revenue * $cost_factor;
    $monthly_profit_estimated = $monthly_revenue - $monthly_cost_estimated;
}
// --- END: FETCH MONTHLY STATS ---

?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoodNow Admin - Tổng quan</title>
    <!-- Link to your CSS file -->
    <link rel="stylesheet" href="css/admin.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- jQuery (ensure it's loaded before your script) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <style>
        /* Add some basic styling for the stat cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); /* Responsive grid */
            gap: 20px; /* Space between cards */
            margin-bottom: 30px; /* Space below the grid */
        }

        .stat-card {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 15px; /* Space between icon and text */
        }

        .stat-card .icon {
            font-size: 2rem; /* Adjust icon size */
            color: #5c67f2; /* Example icon color */
            padding: 10px;
             background-color: rgba(92, 103, 242, 0.1); /* Light background for icon */
            border-radius: 50%;
             width: 50px; /* Fixed width */
            height: 50px; /* Fixed height */
            display: flex;
            justify-content: center;
            align-items: center;
        }
         .stat-card .icon.revenue { color: #28a745; background-color: rgba(40, 167, 69, 0.1); }
         .stat-card .icon.cost { color: #dc3545; background-color: rgba(220, 53, 69, 0.1); }
         .stat-card .icon.profit { color: #17a2b8; background-color: rgba(23, 162, 184, 0.1); }
         .stat-card .icon.items-ordered { color: #ffc107; background-color: rgba(255, 193, 7, 0.1); }
         .stat-card .icon.total-food { color: #6f42c1; background-color: rgba(111, 66, 193, 0.1); }
         .stat-card .icon.frequent-type { color: #fd7e14; background-color: rgba(253, 126, 20, 0.1); }
         .stat-card .icon.highest-rated { color: #e83e8c; background-color: rgba(232, 62, 140, 0.1); }


        .stat-card .info h3 {
            margin: 0 0 5px 0;
            font-size: 1.5rem; /* Main stat value size */
            font-weight: 600;
            color: #333;
        }

        .stat-card .info p {
            margin: 0;
            font-size: 0.9rem;
            color: #666;
        }

        /* Alert Message Styling */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        .alert-warning {
             color: #856404;
             background-color: #fff3cd;
             border-color: #ffeeba;
        }
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .alert-info {
            color: #0c5460;
            background-color: #d1ecf1;
            border-color: #bee5eb;
        }


    </style>
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
                    <!-- Highlight the current page -->
                    <li class="active"><a href="admin.php"><i class="fas fa-tachometer-alt"></i><span>Tổng quan</span></a></li>
                    <li><a href="admin_food.php"><i class="fas fa-utensils"></i> <span>Quản lý Món ăn</span></a></li>
                    <li><a href="admin_order.php"><i class="fas fa-receipt"></i> <span>Quản lý Đơn hàng</span></a></li>
                    <li><a href="admin_users.php"><i class="fas fa-users"></i> <span>Quản lý Người dùng</span></a></li>
                     <li><a href="admin_transfer.php"><i class="fas fa-money-check-dollar"></i> <span>Quản lý Giao dịch</span></a></li>
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
                    <h1>Tổng quan</h1>
                </div>
                <div class="header-user">
                    <div class="user-info">
                        <!-- Placeholder - replace with dynamic avatar if available -->
                        <img src="placeholder-avatar.png" alt="Admin Avatar" class="avatar">
                        <span><?php echo $admin_username; ?></span> <i class="fas fa-caret-down"></i>
                        <div class="user-dropdown">
                            <a href="#">Hồ sơ</a> <!-- Link to profile page if exists -->
                            <a href="logout.php">Đăng xuất</a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Display messages if any -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $msg_type ?: 'info'; ?>">
                    <?php echo nl2br($message); // Use nl2br to respect newlines in messages ?>
                </div>
            <?php endif; ?>


            <!-- Statistics Section -->
            <section class="stats-section">
                <h2>Thống kê tháng này (<?php echo date('m/Y'); ?>)</h2>
                <div class="stats-grid">
                    <!-- Monthly Revenue -->
                    <div class="stat-card">
                        <div class="icon revenue"><i class="fas fa-dollar-sign"></i></div>
                        <div class="info">
                             <h3><span class="currency"><?php echo number_format($monthly_revenue, 0, ',', '.'); ?> đ</span></h3>
                            <p>Doanh thu (Đơn hoàn thành)</p>
                        </div>
                    </div>

                    <!-- Estimated Monthly Cost -->
                    <div class="stat-card">
                        <div class="icon cost"><i class="fas fa-file-invoice-dollar"></i></div>
                        <div class="info">
                             <h3><span class="currency"><?php echo number_format($monthly_cost_estimated, 0, ',', '.'); ?> đ</span></h3>
                             <p>Chi phí (Ước tính <?php echo ($cost_factor * 100); ?>%)</p>
                        </div>
                    </div>

                     <!-- Estimated Monthly Profit -->
                    <div class="stat-card">
                        <div class="icon profit"><i class="fas fa-chart-line"></i></div>
                        <div class="info">
                             <h3><span class="currency"><?php echo number_format($monthly_profit_estimated, 0, ',', '.'); ?> đ</span></h3>
                            <p>Lợi nhuận (Ước tính)</p>
                        </div>
                    </div>

                     <!-- Total Items Ordered -->
                    <div class="stat-card">
                        <div class="icon items-ordered"><i class="fas fa-shopping-basket"></i></div>
                        <div class="info">
                            <h3><?php echo number_format($total_items_ordered_month); ?></h3>
                            <p>Món đã đặt (Đơn hoàn thành)</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="stats-section">
                 <h2>Thống kê Món ăn Chung</h2>
                 <div class="stats-grid">
                    <!-- Total Food Items -->
                    <div class="stat-card">
                        <div class="icon total-food"><i class="fas fa-utensils"></i></div>
                        <div class="info">
                            <h3><?php echo $total_food_items; ?></h3>
                            <p>Tổng số món ăn</p>
                        </div>
                    </div>

                    <!-- Most Frequent Type -->
                     <div class="stat-card">
                        <div class="icon frequent-type"><i class="fas fa-tags"></i></div>
                        <div class="info">
                             <h3><?php echo htmlspecialchars($most_frequent_type); ?></h3>
                            <p>Loại phổ biến nhất</p>
                        </div>
                    </div>

                     <!-- Highest Rated Food -->
                     <div class="stat-card">
                        <div class="icon highest-rated"><i class="fas fa-star"></i></div>
                        <div class="info">
                            <h3><?php echo htmlspecialchars($highest_rated_food_name); ?></h3>
                            <p>Đánh giá cao nhất (<?php echo $highest_rate_so_far > 0 ? $highest_rate_so_far . '/5' : 'N/A'; ?>)</p>
                        </div>
                    </div>

                     <!-- Total Food Value (Sum of Prices) -->
                     <!-- Optional: You might want this or not -->
                     <!--
                     <div class="stat-card">
                         <div class="icon"><i class="fas fa-coins"></i></div>
                         <div class="info">
                              <h3><span class="currency"><?php echo number_format($total_food_value, 0, ',', '.'); ?> đ</span></h3>
                             <p>Tổng giá trị món ăn (Giá bán)</p>
                         </div>
                     </div>
                     -->
                 </div>
            </section>

             <!-- Maybe add more content sections here like recent orders, user activity etc. -->

        </main>
    </div>
    <script src="js/admin.js"></script> <!-- Your existing JS -->
    <script>
        // Add specific JS for this page if needed, e.g., handling the user dropdown
        $(document).ready(function() {
            $('.user-info').on('click', function(e) {
                 // Prevent dropdown closing if clicking inside it (optional)
                // if ($(e.target).closest('.user-dropdown').length) {
                //     return;
                // }
                $('.user-dropdown').toggle();
            });

            // Close dropdown if clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.user-info').length) {
                    $('.user-dropdown').hide();
                }
            });

            // Sidebar toggle logic (if not already in admin.js)
            $('.header-menu-toggle').on('click', function() {
                 $('.admin-container').toggleClass('sidebar-collapsed');
            });
        });
    </script>

</body>
</html>