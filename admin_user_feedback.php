<?php

// --- Database Configuration & Connection (Sử dụng PDO như trong file admin_users.php) ---
include 'config/admin_config.php'; // Đảm bảo file này chứa kết nối PDO ($pdo)

// Check if user is logged in and is an admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["role"]) || $_SESSION["role"] !== 'admin') {
    header("location: login.php");
    exit;
}

// Get admin username for display
$admin_username = htmlspecialchars($_SESSION["username"]);

// --- Feedback Management Logic ---
$message = '';
$msg_type = '';
$feedback_list = [];
$stats = ['total' => 0, 'unread' => 0, 'approved' => 0]; // Thêm stats approved

// ---- HANDLE POST/GET REQUESTS (Toggle Read, Toggle Approve, Delete) ----

// Function to set session message and redirect
function set_message_and_redirect($msg, $type) {
    $_SESSION['message'] = $msg;
    $_SESSION['msg_type'] = $type;
    header("location: admin_feedback.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $feedback_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($feedback_id) {
        try {
            if ($action === 'toggle_read') {
                $sql = "UPDATE user_feedback SET is_read = NOT is_read WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $feedback_id, PDO::PARAM_INT);
                $stmt->execute();
                set_message_and_redirect("Đã cập nhật trạng thái đọc.", "success");
            }
            elseif ($action === 'toggle_approve') {
                $sql = "UPDATE user_feedback SET is_approved = NOT is_approved WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $feedback_id, PDO::PARAM_INT);
                $stmt->execute();
                set_message_and_redirect("Đã cập nhật trạng thái duyệt.", "success");
            }
            elseif ($action === 'delete') {
                // Optional: Add confirmation step here if desired
                $sql = "DELETE FROM user_feedback WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $feedback_id, PDO::PARAM_INT);
                if ($stmt->execute()) {
                    set_message_and_redirect("Đã xóa ý kiến thành công.", "success");
                } else {
                     set_message_and_redirect("Lỗi khi xóa ý kiến.", "danger");
                }
            } else {
                 set_message_and_redirect("Hành động không hợp lệ.", "warning");
            }
        } catch (PDOException $e) {
            error_log("Feedback Action Error (ID: {$feedback_id}, Action: {$action}): " . $e->getMessage());
            set_message_and_redirect("Lỗi cơ sở dữ liệu khi thực hiện hành động.", "danger");
        }
    } else {
        set_message_and_redirect("ID ý kiến không hợp lệ.", "warning");
    }
}

// --- Display messages from session ---
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $msg_type = $_SESSION['msg_type'];
    unset($_SESSION['message']);
    unset($_SESSION['msg_type']);
}

// --- Fetch Statistics and Feedback List ---
try {
    // Fetch Stats
    $stmt_stats = $pdo->query("SELECT
                                COUNT(*) as total,
                                SUM(CASE WHEN is_read = FALSE THEN 1 ELSE 0 END) as unread,
                                SUM(CASE WHEN is_approved = TRUE THEN 1 ELSE 0 END) as approved
                              FROM user_feedback");
    $stats_row = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    if($stats_row){
        $stats['total'] = $stats_row['total'] ?? 0;
        $stats['unread'] = $stats_row['unread'] ?? 0;
        $stats['approved'] = $stats_row['approved'] ?? 0;
    }


    // Fetch Feedback List (JOIN with food_data)
    $stmt_list = $pdo->query("SELECT uf.id, uf.full_name, uf.email, uf.rating, uf.feedback, uf.submitted_at,
                                     uf.is_read, uf.is_approved, fd.name AS dish_name
                              FROM user_feedback uf
                              LEFT JOIN food_data fd ON uf.dish_id = fd.id -- Use correct table and column names
                              ORDER BY uf.is_read ASC, uf.submitted_at DESC"); // Unread first, then newest
    if ($stmt_list) {
        $feedback_list = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $message = "Lỗi khi tải danh sách ý kiến.";
        $msg_type = "danger";
        error_log("Feedback Fetch Error: Failed to prepare or execute query.");
    }

} catch (PDOException $e) {
    error_log("Feedback Fetch Error: " . $e->getMessage());
    $message = "Lỗi cơ sở dữ liệu khi tải dữ liệu.";
    $msg_type = "danger";
    $feedback_list = []; // Ensure empty array on error
}

$page_title = "Quản lý Ý kiến & Đánh giá"; // Set page title
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Đặt tiêu đề động -->
    <title>FoodNow Admin - <?php echo $page_title; ?></title>
    <!-- Sử dụng CSS từ file admin_users.php -->
    <link rel="stylesheet" href="css/admin.css">
    <link rel="shortcut icon" href="image/foodnow_icon.png" sizes="32x32" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Thêm CSS tùy chỉnh cho bảng Feedback nếu cần -->
    <style>
         /* Thêm các style đã có trong ví dụ admin_feedback trước nếu chưa có trong admin.css */
        .stats-card {
            background-color: #fff; padding: 20px; border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.08); display: flex;
            align-items: center; gap: 15px; margin-bottom: 20px;
        }
        .stats-card .icon {
            font-size: 2.5em; color: #ffc107; width: 60px; height: 60px;
            display: inline-flex; align-items: center; justify-content: center;
            background-color: #fff3cd; border-radius: 50%;
        }
        .stats-card .icon.unread { color: #dc3545; background-color: #f8d7da; }
        .stats-card .icon.approved { color: #198754; background-color: #d1e7dd; } /* Style cho icon approved */
        .stats-card .info h3 { font-size: 1.8em; margin: 0 0 5px 0; color: #343a40; }
        .stats-card .info p { margin: 0; color: #6c757d; font-size: 0.9em; }

        .data-table-container {
            background-color: #fff; padding: 25px; border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.08); overflow-x: auto;
        }
        .data-table-container h2 { margin-top: 0; margin-bottom: 20px; font-size: 1.4em; color: #495057; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; color: #212529; /* table-layout: fixed; */ /* Cân nhắc dùng nếu cần cố định cột */}
        .table thead th { vertical-align: bottom; border-bottom: 2px solid #dee2e6; background-color: #f8f9fa; padding: 0.9rem 0.75rem; text-align: left; font-weight: 600; font-size: 0.85em; text-transform: uppercase; color: #495057; }
        .table tbody tr { border-top: 1px solid #dee2e6; }
        .table td { padding: 0.8rem 0.75rem; vertical-align: middle; font-size: 0.9em; word-wrap: break-word; }
        .table td.feedback-col { max-width: 350px; white-space: normal; line-height: 1.5; text-align: center; }
        .table th.col-rating, .table td.col-rating { text-align: center; width: 60px; } /* Thu nhỏ cột rating */
        .table th.col-status, .table td.col-status { text-align: center; width: 60px; } /* Thu nhỏ cột status */
        .table th.col-actions, .table td.actions-col { width: 120px; text-align: center;} /* Điều chỉnh độ rộng actions */
        .table td.actions-col a, .table td.actions-col button { margin: 2px; padding: 4px 8px; font-size: 0.8em; border-radius: 4px; text-decoration: none; cursor: pointer; border: 1px solid transparent; vertical-align: middle; display: inline-block; }
        /* Kiểu nút outline */
        .btn-primary-outline { color: #0d6efd; border-color: #0d6efd; background-color: transparent; } .btn-primary-outline:hover { color: #fff; background-color: #0d6efd; }
        .btn-warning-outline { color: #ffc107; border-color: #ffc107; background-color: transparent; } .btn-warning-outline:hover { color: #000; background-color: #ffc107; }
        .btn-success-outline { color: #198754; border-color: #198754; background-color: transparent; } .btn-success-outline:hover { color: #fff; background-color: #198754; }
        .btn-danger-outline { color: #dc3545; border-color: #dc3545; background-color: transparent; } .btn-danger-outline:hover { color: #fff; background-color: #dc3545; }
        .status-read i { color: green; } .status-unread i { color: orange; } .status-approved i { color: green; } .status-pending i { color: grey; }
        .rating-value { font-weight: bold; } .rating-value.high { color: #198754; } .rating-value.medium { color: #ffc107; } .rating-value.low { color: #dc3545; }
        .unread-row { background-color: #fff9e6 !important; font-weight: 500; }
        .alert { padding: 1rem; margin-bottom: 1rem; border: 1px solid transparent; border-radius: .25rem; position: relative; }
        .alert-success { color: #0f5132; background-color: #d1e7dd; border-color: #badbcc; }
        .alert-danger { color: #842029; background-color: #f8d7da; border-color: #f5c2c7; }
        .alert-warning { color: #664d03; background-color: #fff3cd; border-color: #ffecb5; }
         .close-alert { position: absolute; top: 50%; right: 15px; transform: translateY(-50%); background: none; border: none; font-size: 20px; font-weight: bold; color: inherit; opacity: 0.6; cursor: pointer; }
         .close-alert:hover { opacity: 1; }
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
                 <?php $current_page_sidebar = basename($_SERVER['PHP_SELF']); // Biến riêng cho sidebar ?>
                 <ul>
                    <li><a href="admin.php"><i class="fas fa-tachometer-alt fa-fw"></i><span>Tổng quan</span></a></li>
                    <li><a href="admin_food.php"><i class="fas fa-utensils fa-fw"></i> <span>Quản lý Món ăn</span></a></li>
                    <li><a href="admin_order.php"><i class="fas fa-receipt fa-fw"></i> <span>Quản lý Đơn hàng</span></a></li>
                    <li><a href="admin_users.php"><i class="fas fa-users fa-fw"></i> <span>Quản lý Người dùng</span></a></li>
                    <li class="active"><a href="admin_user_feedback.php"><i class="fas fa-comments"></i><span>Quản lý góp ý</span></a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt fa-fw"></i> <span>Đăng xuất</span></a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
             <!-- Header -->
            <header class="main-header">
                <div class="header-title">
                    <button class="header-menu-toggle" aria-label="Toggle Sidebar"><i class="fas fa-bars"></i></button>
                    <h1><?php echo $page_title; // Hiển thị tiêu đề động ?></h1>
                </div>
                <div class="header-user">
                     <input type="search" id="admin-search-feedback" placeholder="Tìm theo tên, email, ý kiến..." autocomplete="off">
                     <button class="search-btn" onclick="searchFeedbackTable()"><i class="fas fa-search"></i></button> <?php // Thêm onclick ?>
                     <?php include 'parts/admin_info.php' // Include info admin ?>
                </div>
            </header>

             <!-- Content Area -->
            <section class="content-area">
                 <!-- Display Messages -->
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($msg_type); ?>" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                         <button type="button" class="close-alert" onclick="this.parentElement.style.display='none';">×</button>
                    </div>
                <?php endif; ?>
                 <!-- Hiển thị lỗi riêng nếu có, ngay cả khi có message thành công từ action trước đó -->
                 <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                         <button type="button" class="close-alert" onclick="this.parentElement.style.display='none';">×</button>
                    </div>
                <?php endif; ?>


                 <!-- START: Dashboard Cards for Feedback -->
                <div class="dashboard-cards">
                    <div class="card">
                        <div class="card-icon"><i class="fas fa-comment-dots"></i></div>
                        <div class="card-info">
                            <h3><?php echo $stats['total']; ?></h3>
                            <p>Tổng số ý kiến</p>
                        </div>
                    </div>
                </div>
                <!-- END: Dashboard Cards -->

                 <!-- Feedback Data Table -->
                <div class="data-table-container">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2>Danh sách Ý kiến & Đánh giá</h2>
                        <!-- Optional: Add Filters (e.g., by read status, approval status) -->
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="col-id">ID</th>
                                <th>Người gửi</th>
                                <th>Email</th>
                                <th>Món ăn</th>
                                <th class="col-rating">Điểm</th>
                                <th class="feedback-col">Nội dung ý kiến</th>
                                <th>Ngày gửi</th>
                            </tr>
                        </thead>
                        <tbody id="feedback-table-body"> <?php // Thêm ID cho tbody ?>
                            <?php if (empty($feedback_list)): ?>
                                <tr>
                                    <td colspan="10" style="text-align:center; padding: 20px;">Không có ý kiến hoặc đánh giá nào.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($feedback_list as $fb): ?>
                                <tr class="<?php echo !$fb['is_read'] ? 'unread-row' : ''; ?>">
                                   <td class="col-id"><?php echo $fb['id']; ?></td>
                                   <td><?php echo htmlspecialchars($fb['full_name']); ?></td>
                                   <td><a href="mailto:<?php echo htmlspecialchars($fb['email']); ?>" title="Gửi email"><?php echo htmlspecialchars($fb['email']); ?></a></td>
                                   <td><?php echo htmlspecialchars($fb['dish_name'] ?? 'N/A'); ?></td>
                                   <td class="col-rating">
                                       <?php $rating_val = $fb['rating']; $rating_class = 'medium'; if ($rating_val !== null) { if ($rating_val >= 8) $rating_class = 'high'; elseif ($rating_val <= 4) $rating_class = 'low'; echo '<span class="rating-value ' . $rating_class . '">' . htmlspecialchars($rating_val) . '</span>'; } else { echo '-'; } ?>
                                   </td>
                                   <td class="feedback-col"><?php echo nl2br(htmlspecialchars($fb['feedback'])); ?></td>
                                   <td><?php echo date('d/m/y H:i', strtotime($fb['submitted_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <!-- Sử dụng JS từ file admin_users.php nếu có logic chung -->
    <script src="js/admin.js"></script>
    <script>
         // Thêm JS tìm kiếm riêng cho bảng feedback
         function searchFeedbackTable() {
             const searchTerm = $('#admin-search-feedback').val().toLowerCase();
             $('#feedback-table-body tr').each(function() {
                 const row = $(this);
                 const name = row.find('td:nth-child(2)').text().toLowerCase();
                 const email = row.find('td:nth-child(3)').text().toLowerCase();
                 const dish = row.find('td:nth-child(4)').text().toLowerCase();
                 const feedback = row.find('td.feedback-col').text().toLowerCase(); // Tìm cả trong feedback

                 if (name.includes(searchTerm) || email.includes(searchTerm) || dish.includes(searchTerm) || feedback.includes(searchTerm)) {
                     row.show();
                 } else {
                     row.hide();
                 }
             });
         }

         // Trigger search on keyup as well
         $('#admin-search-feedback').on('keyup', searchFeedbackTable);

         // --- Sidebar Toggle (from your admin.js or add here) ---
         const sidebar = $('.sidebar');
         const mainContent = $('.main-content'); // Đổi thành class của main content area
         const menuToggle = $('.header-menu-toggle');

         if (menuToggle.length && sidebar.length && mainContent.length) {
             menuToggle.on('click', function() {
                 sidebar.toggleClass('collapsed');
                 // Không cần toggle class 'expanded' trên main-content nếu CSS chỉ dựa vào sidebar collapsed
                  if (sidebar.hasClass('collapsed')) { localStorage.setItem('sidebarState', 'collapsed'); }
                  else { localStorage.removeItem('sidebarState'); }
             });
              if (localStorage.getItem('sidebarState') === 'collapsed') {
                  sidebar.addClass('collapsed');
              }
         }

        // --- Close Alert Button ---
         $('.close-alert').on('click', function() {
             $(this).parent('.alert').fadeOut('fast');
         });

         // --- User Dropdown (từ admin_users.php) ---
        $('.user-info').on('click', function(e) { // Giả sử có class .user-info bao quanh avatar và tên
            e.stopPropagation();
            $('.user-dropdown').toggle(); // Giả sử có div .user-dropdown
        });
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.user-info').length) {
                $('.user-dropdown').hide();
            }
        });

    </script>

</body>
</html>