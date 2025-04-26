<?php
// Ensure session is started (usually done in config)
// Use require_once for essential configuration files
require_once 'config/admin_config.php'; // Assumes $pdo is created here and session is started

// Check if user is logged in and is an admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["role"]) || $_SESSION["role"] !== 'admin') {
    header("location: login.php");
    exit;
}

// Get admin username for display
$admin_username = htmlspecialchars($_SESSION["username"] ?? 'Admin');

// Define upload directory (adjust path as needed)
$uploadDir = 'uploads/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        error_log("Failed to create upload directory: " . $uploadDir);
        $_SESSION['admin_message'] = "Lỗi: Không thể tạo thư mục tải lên.";
        $_SESSION['admin_msg_type'] = "danger";
    }
}

$message = $_SESSION['admin_message'] ?? '';
$msg_type = $_SESSION['admin_msg_type'] ?? 'info';
unset($_SESSION['admin_message'], $_SESSION['admin_msg_type']); // Clear after retrieving

// --- Initialize Stats Variables ---
$total_food_items = 0;
$most_frequent_type = 'N/A';
$highest_rated_food_name = 'N/A';
$highest_rate_so_far = -1;
$monthly_revenue = 0;
$total_items_ordered_month = 0;
$monthly_cost_estimated = 0;
$monthly_profit_estimated = 0;
$chart_labels = []; // For revenue chart
$chart_data = [];   // For revenue chart

// --- START: FETCH FOOD STATS ---
try {
    $sql_food = "SELECT name, type, price, rate FROM food_data ORDER BY id DESC";
    $stmt_food = $pdo->query($sql_food);
    $foods = $stmt_food->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($foods)) {
        $total_food_items = count($foods);
        $type_counts = [];

        foreach ($foods as $food) {
            $type = $food['type'] ?? 'Unknown';
            $type_counts[$type] = ($type_counts[$type] ?? 0) + 1;

            $current_rate = isset($food['rate']) ? (int)$food['rate'] : -1;
            if ($current_rate > $highest_rate_so_far) {
                $highest_rate_so_far = $current_rate;
                $highest_rated_food_name = $food['name'] ?? 'Unknown Name';
            } elseif ($current_rate === $highest_rate_so_far && $highest_rated_food_name === 'N/A') {
                $highest_rated_food_name = $food['name'] ?? 'Unknown Name';
            }
        }

        if (!empty($type_counts)) {
            arsort($type_counts);
            $most_frequent_type = key($type_counts);
        }

        if ($highest_rate_so_far === -1 && $total_food_items > 0) {
            $highest_rated_food_name = 'Chưa có đánh giá';
        } elseif ($total_food_items === 0) {
            $highest_rated_food_name = 'Không có món ăn';
        }
    }
} catch (PDOException $e) {
    $message .= ($message ? "<br>" : "") . "Lỗi khi truy vấn món ăn: " . $e->getMessage();
    $msg_type = "danger";
    error_log("Database Error (Food Fetch in admin.php): " . $e->getMessage());
}
// --- END: FETCH FOOD STATS ---


// --- START: FETCH MONTHLY STATS ---
$start_date = date('Y-m-01 00:00:00');
$end_date = date('Y-m-t 23:59:59');
$revenue_generating_statuses = ['delivered', 'completed']; // *** ADJUST THIS ARRAY ***
$status_placeholders = implode(',', array_fill(0, count($revenue_generating_statuses), '?'));

// 1. Monthly Revenue
$sql_revenue = "SELECT SUM(price_at_add * quantity) as total_revenue
                FROM orders
                WHERE added_at BETWEEN ? AND ?
                AND status IN ($status_placeholders)";
try {
    $stmt_revenue = $pdo->prepare($sql_revenue);
    $params_revenue = array_merge([$start_date, $end_date], $revenue_generating_statuses);
    $stmt_revenue->execute($params_revenue);
    $result_revenue = $stmt_revenue->fetch(PDO::FETCH_ASSOC);
    if ($result_revenue && $result_revenue['total_revenue'] !== null) {
        $monthly_revenue = (float)$result_revenue['total_revenue'];
    }
} catch (PDOException $e) {
    $message .= ($message ? "<br>" : "") . "Lỗi khi tính doanh thu tháng: " . $e->getMessage();
    $msg_type = "danger";
    error_log("Database Error (Monthly Revenue in admin.php): " . $e->getMessage());
}

// 2. Monthly Items Ordered
$sql_items = "SELECT SUM(quantity) as total_items
              FROM orders
              WHERE added_at BETWEEN ? AND ?
              AND status IN ($status_placeholders)";
try {
    $stmt_items = $pdo->prepare($sql_items);
    $params_items = array_merge([$start_date, $end_date], $revenue_generating_statuses);
    $stmt_items->execute($params_items);
    $result_items = $stmt_items->fetch(PDO::FETCH_ASSOC);
    if ($result_items && $result_items['total_items'] !== null) {
        $total_items_ordered_month = (int)$result_items['total_items'];
    }
} catch (PDOException $e) {
    $message .= ($message ? "<br>" : "") . "Lỗi khi tính số lượng món đã đặt: " . $e->getMessage();
    $msg_type = "danger";
    error_log("Database Error (Monthly Items in admin.php): " . $e->getMessage());
}

// 3. Estimate Cost and Profit
$cost_factor = 0.40; // *** ADJUST COST FACTOR (e.g., 0.40 = 40%) ***
if ($monthly_revenue > 0) {
    $monthly_cost_estimated = $monthly_revenue * $cost_factor;
    $monthly_profit_estimated = $monthly_revenue - $monthly_cost_estimated;
}
// --- END: FETCH MONTHLY STATS ---


// --- START: FETCH DATA FOR REVENUE CHART (Last 30 days) ---
$daily_revenue_data = [];
try {
    // Calculate date range
    $chart_end_date = date('Y-m-d'); // Today
    $chart_start_date = date('Y-m-d', strtotime('-29 days', strtotime($chart_end_date))); // 30 days total

    // $revenue_generating_statuses and $status_placeholders are already defined above

    // CORRECTED SQL: Use only positional placeholders (?)
    $sql_daily_revenue = "SELECT DATE(added_at) as order_day, SUM(price_at_add * quantity) as daily_total
                          FROM orders
                          WHERE added_at BETWEEN ? AND ?   -- Changed to positional
                          AND status IN ($status_placeholders) -- Kept positional
                          GROUP BY DATE(added_at)
                          ORDER BY order_day ASC";

    $stmt_daily = $pdo->prepare($sql_daily_revenue);

    // Prepare the parameters array in the correct order for positional binding
    $chart_start_datetime = $chart_start_date . ' 00:00:00';
    $chart_end_datetime = $chart_end_date . ' 23:59:59';
    // Order must match the '?': start_date, end_date, status1, status2, ...
    $params_daily = array_merge(
        [$chart_start_datetime, $chart_end_datetime],
        $revenue_generating_statuses
    );

    // Execute with the correctly ordered array for positional placeholders
    $stmt_daily->execute($params_daily);
    $results_daily = $stmt_daily->fetchAll(PDO::FETCH_ASSOC);

    // Convert results to an associative array [date => revenue]
    foreach ($results_daily as $row) {
        $daily_revenue_data[$row['order_day']] = (float)$row['daily_total'];
    }

    // Generate labels and data for all days in the range
    $current_date_ts = strtotime($chart_start_date);
    $end_date_ts = strtotime($chart_end_date);

    while ($current_date_ts <= $end_date_ts) {
        $date_key = date('Y-m-d', $current_date_ts);
        $chart_labels[] = date('d M', $current_date_ts); // Format label e.g., '28 Oct'
        $chart_data[] = $daily_revenue_data[$date_key] ?? 0; // Use fetched data or 0
        $current_date_ts = strtotime('+1 day', $current_date_ts);
    }

} catch (PDOException $e) {
    $message .= ($message ? "<br>" : "") . "Lỗi khi lấy dữ liệu biểu đồ doanh thu: " . $e->getMessage();
    $msg_type = "danger";
    error_log("Database Error (Revenue Chart Data in admin.php): " . $e->getMessage());
    // Leave $chart_labels and $chart_data as empty arrays
}
// --- END: FETCH DATA FOR REVENUE CHART ---
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoodNow Admin - Tổng quan</title>
    <link rel="stylesheet" href="css/admin.css"> <!-- Ensure path is correct -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        /* Base styles should be in admin.css */
        :root {
            --primary-color: #4CAF50;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --warning-color: #ffc107;
            --purple-color: #6f42c1;
            --orange-color: #fd7e14;
            --pink-color: #e83e8c;
            --teal-color: #20c997; /* Added teal for chart */
        }
        /* Alert Message Styling */
        .alert { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 4px; font-size: 0.95em; }
        .alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .alert-warning { color: #856404; background-color: #fff3cd; border-color: #ffeeba; }
        .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .alert-info { color: #0c5460; background-color: #d1ecf1; border-color: #bee5eb; }

        /* Statistics Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background-color: #fff; padding: 20px 25px; border-radius: 8px; box-shadow: 0 3px 6px rgba(0, 0, 0, 0.07); display: flex; align-items: center; gap: 20px; border: 1px solid #e0e0e0; transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1); }
        .stat-card .icon { font-size: 2rem; padding: 12px; border-radius: 50%; width: 55px; height: 55px; display: flex; justify-content: center; align-items: center; color: #fff; }
        .stat-card .icon.revenue { background-color: var(--success-color); }
        .stat-card .icon.cost { background-color: var(--danger-color); }
        .stat-card .icon.profit { background-color: var(--info-color); }
        .stat-card .icon.items-ordered { background-color: var(--warning-color); }
        .stat-card .icon.total-food { background-color: var(--purple-color); }
        .stat-card .icon.frequent-type { background-color: var(--orange-color); }
        .stat-card .icon.highest-rated { background-color: var(--pink-color); }
        .stat-card .info h3 { margin: 0 0 5px 0; font-size: 1.6em; font-weight: 600; color: #333; line-height: 1.2; }
        .stat-card .info p { margin: 0; font-size: 0.9em; color: #6c757d; }
        .currency { white-space: nowrap; }

         /* Chart Section Styles */
        .chart-section { margin-top: 30px; background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 3px 6px rgba(0, 0, 0, 0.07); border: 1px solid #e0e0e0; }
        .chart-section h2 { margin-bottom: 20px; font-size: 1.4em; font-weight: 600; color: #333; text-align: center; }
        .chart-container { position: relative; height: 350px; width: 100%; }
        @media (max-width: 768px) { .chart-container { height: 300px; } }
        @media (max-width: 576px) { .chart-container { height: 250px; } }

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
                    <li class="active"><a href="admin.php"><i class="fas fa-tachometer-alt fa-fw"></i><span>Tổng quan</span></a></li>
                    <li><a href="admin_food.php"><i class="fas fa-utensils fa-fw"></i> <span>Quản lý Món ăn</span></a></li>
                    <li><a href="admin_order.php"><i class="fas fa-receipt fa-fw"></i> <span>Quản lý Đơn hàng</span></a></li>
                    <li><a href="admin_users.php"><i class="fas fa-users fa-fw"></i> <span>Quản lý Người dùng</span></a></li>
                    <li><a href="admin_promotions.php"><i class="fas fa-tags fa-fw"></i> <span>Quản lý Khuyến mãi</span></a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt fa-fw"></i> <span>Đăng xuất</span></a></li>
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
                <?php include 'parts/admin_info.php' ?>
                </div>
            </header>

            <!-- Display messages -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $msg_type ?: 'info'; ?>">
                    <?php echo nl2br(htmlspecialchars($message)); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Section: Monthly -->
            <section class="stats-section">
                <h2>Thống kê tháng này (<?php echo date('m/Y'); ?>)</h2>
                <div class="stats-grid">
                    <!-- Monthly Revenue -->
                    <div class="stat-card">
                        <div class="icon revenue"><i class="fas fa-dollar-sign fa-fw"></i></div>
                        <div class="info">
                            <h3><span class="currency"><?php echo number_format($monthly_revenue, 0, ',', '.'); ?> đ</span></h3>
                            <p>Doanh thu (<?php echo htmlspecialchars(implode('/', $revenue_generating_statuses)); ?>)</p>
                        </div>
                    </div>
                    <!-- Estimated Monthly Cost -->
                    <div class="stat-card">
                        <div class="icon cost"><i class="fas fa-file-invoice-dollar fa-fw"></i></div>
                        <div class="info">
                            <h3><span class="currency"><?php echo number_format($monthly_cost_estimated, 0, ',', '.'); ?> đ</span></h3>
                            <p>Chi phí (Ước tính <?php echo ($cost_factor * 100); ?>%)</p>
                        </div>
                    </div>
                    <!-- Estimated Monthly Profit -->
                    <div class="stat-card">
                        <div class="icon profit"><i class="fas fa-chart-line fa-fw"></i></div>
                        <div class="info">
                            <h3><span class="currency"><?php echo number_format($monthly_profit_estimated, 0, ',', '.'); ?> đ</span></h3>
                            <p>Lợi nhuận (Ước tính)</p>
                        </div>
                    </div>
                    <!-- Total Items Ordered -->
                    <div class="stat-card">
                        <div class="icon items-ordered"><i class="fas fa-shopping-basket fa-fw"></i></div>
                        <div class="info">
                            <h3><?php echo number_format($total_items_ordered_month); ?></h3>
                            <p>Món đã đặt (<?php echo htmlspecialchars(implode('/', $revenue_generating_statuses)); ?>)</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Statistics Section: Food -->
            <section class="stats-section">
                <h2>Thống kê Món ăn Chung</h2>
                <div class="stats-grid">
                    <!-- Total Food Items -->
                    <div class="stat-card">
                        <div class="icon total-food"><i class="fas fa-utensils fa-fw"></i></div>
                        <div class="info">
                            <h3><?php echo number_format($total_food_items); ?></h3>
                            <p>Tổng số món ăn</p>
                        </div>
                    </div>
                    <!-- Most Frequent Type -->
                    <div class="stat-card">
                        <div class="icon frequent-type"><i class="fas fa-tags fa-fw"></i></div>
                        <div class="info">
                            <h3><?php echo htmlspecialchars($most_frequent_type); ?></h3>
                            <p>Loại phổ biến nhất</p>
                        </div>
                    </div>
                    <!-- Highest Rated Food -->
                    <div class="stat-card">
                        <div class="icon highest-rated"><i class="fas fa-star fa-fw"></i></div>
                        <div class="info">
                            <h3><?php echo htmlspecialchars($highest_rated_food_name); ?></h3>
                            <p>Đánh giá cao nhất (<?php echo $highest_rate_so_far > 0 ? $highest_rate_so_far . '/5' : 'N/A'; ?>)</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Revenue Chart Section -->
            <section class="chart-section">
                 <h2>Doanh thu 30 ngày qua (Trạng thái: <?php echo htmlspecialchars(implode('/', $revenue_generating_statuses)); ?>)</h2>
                 <div class="chart-container">
                      <canvas id="revenueChart"></canvas>
                 </div>
            </section>

        </main>
    </div>

    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <!-- Your custom admin JS (Handles sidebar, dropdown etc.) -->
    <script src="js/admin.js"></script>
    <script>
        $(document).ready(function() {
            // Specific JS for this page: Chart Initialization

            // --- Initialize Revenue Chart ---
            const ctxRevenue = document.getElementById('revenueChart');
            if (ctxRevenue) {
                // Get data passed from PHP
                const revenueLabels = <?php echo json_encode($chart_labels); ?>;
                const revenueData = <?php echo json_encode($chart_data); ?>;

                // Check if data is available before rendering chart
                if (revenueLabels && revenueLabels.length > 0 && revenueData && revenueData.length > 0) {
                    // Function to format currency for tooltips
                    function formatCurrencyTooltip(value) {
                        if (isNaN(value)) return '0 đ';
                        return Number(value).toLocaleString('vi-VN') + ' đ';
                    }

                    new Chart(ctxRevenue.getContext('2d'), {
                        type: 'line', // Type of chart
                        data: {
                            labels: revenueLabels,
                            datasets: [{
                                label: 'Doanh thu hàng ngày',
                                data: revenueData,
                                borderColor: 'var(--teal-color, rgb(32, 201, 151))', // Use CSS variable or fallback
                                backgroundColor: 'rgba(32, 201, 151, 0.2)', // Lighter fill
                                tension: 0.1, // Slight curve
                                fill: true,   // Fill area under line
                                borderWidth: 2, // Line thickness
                                pointBackgroundColor: 'var(--teal-color, rgb(32, 201, 151))',
                                pointRadius: 4,
                                pointHoverRadius: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false, // Allow height control via CSS
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        // Format Y-axis labels as currency
                                        callback: function(value, index, ticks) {
                                            // Show simplified currency (e.g., 100K, 1M) for larger numbers if desired
                                            if (value >= 1000000) return (value / 1000000).toFixed(1) + ' Tr đ';
                                            if (value >= 1000) return (value / 1000).toFixed(0) + ' K đ';
                                            return Number(value).toLocaleString('vi-VN') + ' đ';
                                        }
                                    }
                                },
                                x: {
                                    ticks: {
                                        maxRotation: 0,
                                        autoSkip: true,
                                        maxTicksLimit: 15 // Adjust based on screen width if needed
                                    }
                                }
                            },
                            plugins: {
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.dataset.label || '';
                                            if (label) { label += ': '; }
                                            if (context.parsed.y !== null) {
                                                label += formatCurrencyTooltip(context.parsed.y);
                                            }
                                            return label;
                                        }
                                    }
                                },
                                legend: {
                                    display: false // Hide legend for a single dataset chart
                                }
                            },
                            interaction: {
                                mode: 'index',
                                intersect: false,
                            },
                        }
                    });
                } else {
                    // Optional: Display a message if no chart data is available
                     $('#revenueChart').parent().html('<p style="text-align:center; padding: 20px; color: #6c757d;">Không có đủ dữ liệu doanh thu để vẽ biểu đồ.</p>');
                     console.log("No data available for revenue chart.");
                }
            } else {
                console.error("Canvas element #revenueChart not found.");
            }

            // --- Add back other essential JS from admin.js if needed ---
            // e.g., Dropdown toggle, sidebar toggle if not handled by included admin.js
            $('.user-info').on('click', function(e) {
                $('.user-dropdown').toggle();
            });
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.user-info').length) {
                    $('.user-dropdown').hide();
                }
            });
            $('.header-menu-toggle').on('click', function() {
                $('.admin-container').toggleClass('sidebar-collapsed');
                // localStorage logic might be needed here if admin.js doesn't handle it
            });


        }); // End document ready
    </script>

</body>

</html>