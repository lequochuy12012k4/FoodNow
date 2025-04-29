<?php


include 'config/admin_config.php'; // Include database connection

// Check if user is logged in and is an admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["role"]) || $_SESSION["role"] !== 'admin') {
    // If not logged in or not an admin, redirect to login page
    header("location: login.php");
    exit;
}

// Get admin username for display
$admin_username = htmlspecialchars($_SESSION["username"]);
$current_admin_id = $_SESSION["id"]; // Store current admin ID for checks

// --- User Management Logic ---
$message = '';
$msg_type = '';

// ---- HANDLE POST REQUESTS FIRST (e.g., Edit Role) ----
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'edit_role') {
    $user_id_to_edit = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $new_role = filter_input(INPUT_POST, 'new_role', FILTER_SANITIZE_SPECIAL_CHARS); // Sanitize role input
    $valid_roles = ['customer', 'admin']; // Define valid roles

    if ($user_id_to_edit && $new_role && in_array($new_role, $valid_roles)) {

        // --- Security Check: Prevent demoting the last admin ---
        $is_last_admin = false;
        if ($new_role !== 'admin') { // Only check if demoting an admin
            try {
                // Check current role of the user being edited
                $stmt_check_role = $pdo->prepare("SELECT role FROM users WHERE id = :id");
                $stmt_check_role->bindParam(':id', $user_id_to_edit, PDO::PARAM_INT);
                $stmt_check_role->execute();
                $user_to_edit = $stmt_check_role->fetch(PDO::FETCH_ASSOC);

                if ($user_to_edit && $user_to_edit['role'] === 'admin') {
                    // If the user being edited is currently an admin, check if they are the last one
                    $stmt_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
                    $admin_count = $stmt_count->fetchColumn();
                    if ($admin_count <= 1) {
                        $is_last_admin = true;
                    }
                }
            } catch (PDOException $e) {
                 error_log("Admin Count Check Error: " . $e->getMessage());
                 $_SESSION['message'] = "Lỗi khi kiểm tra số lượng admin.";
                 $_SESSION['msg_type'] = "danger";
                 header("location: admin_users.php"); // Redirect to avoid double submission
                 exit;
            }
        }

        if ($is_last_admin) {
            $_SESSION['message'] = "Không thể thay đổi vai trò của admin cuối cùng.";
            $_SESSION['msg_type'] = "danger";
        } else {
             // Proceed with update
            try {
                $sql = "UPDATE users SET role = :new_role WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':new_role', $new_role, PDO::PARAM_STR);
                $stmt->bindParam(':id', $user_id_to_edit, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    $_SESSION['message'] = "Đã cập nhật vai trò người dùng thành công.";
                    $_SESSION['msg_type'] = "success";
                    // Special case: If admin demoted themselves, log them out or redirect
                    if ($user_id_to_edit == $current_admin_id && $new_role !== 'admin') {
                         $_SESSION['message'] .= " Bạn đã thay đổi vai trò của chính mình và không còn là admin. Đang đăng xuất...";
                         // Optionally destroy session and redirect to login after a delay
                         // header("Refresh: 3; url=logout.php"); // Example delay
                         // For immediate effect:
                         header("location: logout.php");
                         exit;
                    }

                } else {
                    $_SESSION['message'] = "Lỗi khi cập nhật vai trò người dùng.";
                    $_SESSION['msg_type'] = "danger";
                }
            } catch (PDOException $e) {
                error_log("User Role Update Error: " . $e->getMessage());
                $_SESSION['message'] = "Lỗi cơ sở dữ liệu khi cập nhật vai trò.";
                $_SESSION['msg_type'] = "danger";
            }
        }
        // Redirect back to the user page after processing POST to prevent resubmission
        header("location: admin_users.php");
        exit;

    } else {
         $_SESSION['message'] = "Dữ liệu không hợp lệ để cập nhật vai trò.";
         $_SESSION['msg_type'] = "warning";
         header("location: admin_users.php"); // Redirect
         exit;
    }
}


// ---- HANDLE GET REQUESTS (e.g., Delete) ----
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $user_id_to_delete = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($user_id_to_delete) {
        // Prevent deleting the currently logged-in admin
        if ($user_id_to_delete == $current_admin_id) {
             $_SESSION['message'] = "Bạn không thể xóa tài khoản admin đang đăng nhập!";
             $_SESSION['msg_type'] = "danger";
        } else {
            // --- Security Check: Prevent deleting the last admin ---
            $can_delete_user = true;
            try {
                // Check the role of the user being deleted
                $stmt_check = $pdo->prepare("SELECT role FROM users WHERE id = :id");
                $stmt_check->bindParam(':id', $user_id_to_delete, PDO::PARAM_INT);
                $stmt_check->execute();
                $user_to_delete = $stmt_check->fetch(PDO::FETCH_ASSOC);

                if ($user_to_delete && $user_to_delete['role'] === 'admin') {
                    // If the user is an admin, check if they are the last one
                    $stmt_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
                    $admin_count = $stmt_count->fetchColumn();
                    if ($admin_count <= 1) {
                        $can_delete_user = false;
                        $_SESSION['message'] = "Không thể xóa admin cuối cùng.";
                        $_SESSION['msg_type'] = "danger";
                    }
                }

            } catch (PDOException $e) {
                error_log("Admin Count Check Error (Delete): " . $e->getMessage());
                $can_delete_user = false; // Prevent deletion on error
                $_SESSION['message'] = "Lỗi khi kiểm tra vai trò người dùng trước khi xóa.";
                $_SESSION['msg_type'] = "danger";
            }

            // Proceed with deletion only if checks pass
            if ($can_delete_user) {
                try {
                    $sql = "DELETE FROM users WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':id', $user_id_to_delete, PDO::PARAM_INT);

                    if ($stmt->execute()) {
                        $_SESSION['message'] = "Đã xóa người dùng thành công.";
                        $_SESSION['msg_type'] = "success";
                    } else {
                        $_SESSION['message'] = "Lỗi khi xóa người dùng.";
                        $_SESSION['msg_type'] = "danger";
                    }
                } catch (PDOException $e) {
                    error_log("User Deletion Error: " . $e->getMessage());
                     $_SESSION['message'] = "Lỗi cơ sở dữ liệu khi xóa người dùng.";
                     $_SESSION['msg_type'] = "danger";
                }
            }
        }
        // Redirect back to the user page without GET parameters to avoid re-deletion on refresh
        header("location: admin_users.php");
        exit;
    } else {
        $_SESSION['message'] = "ID người dùng không hợp lệ.";
        $_SESSION['msg_type'] = "warning";
        header("location: admin_users.php");
        exit;
    }
}

// --- Display messages from session ---
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $msg_type = $_SESSION['msg_type'];
    unset($_SESSION['message']);
    unset($_SESSION['msg_type']);
}


// --- Fetch all users from the database ---
$users = [];
$last_admin_check_needed = false; // Flag to check if we need the last admin count later

try {
    $stmt = $pdo->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC");
    if ($stmt) {
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Check if we need to determine the last admin (only if there are users)
        if (!empty($users)) {
            $admin_count_stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
            $admin_count = $admin_count_stmt->fetchColumn();
            if ($admin_count <= 1) {
                $last_admin_check_needed = true;
            }
        }

    } else {
        $message = "Lỗi khi chuẩn bị truy vấn người dùng.";
        $msg_type = "danger";
        error_log("User Fetch Error: Failed to prepare or execute query.");
    }
} catch (PDOException $e) {
    error_log("User Fetch Error: " . $e->getMessage());
    $message = "Lỗi cơ sở dữ liệu khi tải danh sách người dùng.";
    $msg_type = "danger";
    // Ensure users array is empty on error
    $users = [];
}
$users = [];
$last_admin_check_needed = false; // Flag to check if we need the last admin count later
// ADD THIS LINE: Initialize total users count
$total_users = 0;

try {
    $stmt = $pdo->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC");
    if ($stmt) {
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // ADD THIS LINE: Calculate the count after fetching
        $total_users = count($users);

        // Check if we need to determine the last admin (only if there are users)
        if (!empty($users)) {
            // ... (rest of your admin count check logic) ...
        }

    } else {
        // ... (your existing error handling) ...
    }
} catch (PDOException $e) {
    // ... (your existing error handling) ...
    $users = []; // Ensure users array is empty on error
    $total_users = 0; // Ensure count is 0 on error
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoodNow Admin - Quản lý Người dùng</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="shortcut icon" href="image/foodnow_icon.png" sizes="32x32" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <style>
        /* Basic Modal Styling (add to your admin.css) */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1000; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.6); /* Black w/ opacity */
            align-items: center;
            justify-content: center;
        }

        .modal-overlay { /* Optional: For clicking outside to close */
             position: absolute;
             top: 0;
             left: 0;
             width: 100%;
             height: 100%;
             cursor: pointer;
        }

        .modal-content {
            position: relative; /* Needed for overlay */
            background-color: #fefefe;
            margin: auto; /* Center vertically & horizontally */
            padding: 30px;
            border: 1px solid #888;
            width: 80%; /* Could be more specific */
            max-width: 500px; /* Maximum width */
            border-radius: 8px;
            box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2),0 6px 20px 0 rgba(0,0,0,0.19);
            z-index: 1001; /* Above overlay */
             animation-name: animatetop;
             animation-duration: 0.4s
        }
        /* Add Animation */
        @keyframes animatetop {
          from {top: -300px; opacity: 0}
          to {top: 0; opacity: 1}
        }


        .modal-close {
            color: #aaa;
            position: absolute;
            top: 10px;
            right: 20px;
            font-size: 28px;
            font-weight: bold;
            border: none;
            background: none;
            cursor: pointer;
        }

        .modal-close:hover,
        .modal-close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        .modal h2 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.5em;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .modal .form-group {
            margin-bottom: 15px;
        }

        .modal label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
             color: #555;
        }

        .modal input[type="text"], /* Adjust if needed */
        .modal select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box; /* Include padding and border in element's total width/height */
        }
        .modal select {
             cursor: pointer;
        }

        .modal button.modal-submit-btn {
            background-color: #5cb85c; /* Green */
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%; /* Or adjust as needed */
            font-size: 1em;
            margin-top: 10px;
            transition: background-color 0.3s ease;
        }

        .modal button.modal-submit-btn:hover {
            background-color: #4cae4c;
        }
         .modal .alert { /* Style for messages inside modal */
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .modal .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .modal .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }


        /* Alert Styles (if not already in admin.css) */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
            position: relative; /* For close button */
        }
        .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .alert-warning { color: #856404; background-color: #fff3cd; border-color: #ffeeba; }
        .alert-info { color: #0c5460; background-color: #d1ecf1; border-color: #bee5eb; }
        .close-alert {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 20px;
            font-weight: bold;
            color: inherit; /* Use alert text color */
            opacity: 0.6;
            cursor: pointer;
        }
        .close-alert:hover { opacity: 1; }

        /* Style disabled buttons */
        .btn[disabled] {
            cursor: not-allowed;
            opacity: 0.65;
            background-color: #ccc; /* Or keep original color and just change opacity */
            border-color: #ccc;
        }
         .btn-edit { background-color: #f0ad4e; border-color: #eea236; color: white; }
         .btn-edit:hover { background-color: #ec971f; border-color: #d58512; }
         .btn-delete { background-color: #d9534f; border-color: #d43f3a; color: white; }
         .btn-delete:hover { background-color: #c9302c; border-color: #ac2925; }
         td.actions .btn { margin-right: 5px; } /* Add spacing between buttons */
    </style>
</head>

<body>
    <div class="admin-container">
        <aside class="sidebar">
            <!-- Sidebar content remains the same -->
             <div class="sidebar-header">
                <a href="admin.php" class="logo">FoodNow Admin</a>
            </div>
            <nav class="sidebar-nav">
                 <ul>
                    <li><a href="admin.php"><i class="fas fa-tachometer-alt"></i> <span>Tổng quan</span></a></li>
                    <li><a href="admin_food.php"><i class="fas fa-utensils"></i> <span>Quản lý Món ăn</span></a></li>
                    <li><a href="admin_order.php"><i class="fas fa-receipt"></i> <span>Quản lý Đơn hàng</span></a></li>
                    <li class="active"><a href="admin_users.php"><i class="fas fa-users"></i> <span>Quản lý Người dùng</span></a></li>
                    <li><a href="admin_user_feedback.php"><i class="fas fa-comments"></i><span>Quản lý góp ý</span></a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Đăng xuất</span></a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <header class="main-header">
                 <!-- Header content remains the same -->
                 <div class="header-title">
                    <button class="header-menu-toggle" aria-label="Toggle Sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1>Quản lý Người dùng</h1>
                </div>
                <div class="header-user">
                    <input type="search" id="admin-search-user" placeholder="Tìm kiếm người dùng..." autocomplete="off">
                    <button class="search-btn"><i class="fas fa-search"></i></button>
                    <?php include 'parts/admin_info.php' ?>
                </div>
            </header>

            <section class="content-area">
                 <!-- Display Messages -->
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($msg_type); ?>" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                         <button type="button" class="close-alert" onclick="this.parentElement.style.display='none';">×</button>
                    </div>
                <?php endif; ?>

                <!-- START: Dashboard Cards for Users -->
                <div class="dashboard-cards">
                    <div class="card">
                        <div class="card-icon"><i class="fas fa-users"></i></div>
                        <div class="card-info">
                            <h3><?php echo $total_users; ?></h3>
                            <p>Tổng số người dùng</p>
                        </div>
                    </div>
                    <!-- You could add more relevant cards here later if needed -->
                    <!-- Example: Admins vs Customers counts -->
                    <?php
                        // Optional: Calculate admin/customer counts if needed for more cards
                        $admin_user_count = 0;
                        $customer_user_count = 0;
                        foreach ($users as $user) {
                            if ($user['role'] === 'admin') $admin_user_count++;
                            else if ($user['role'] === 'customer') $customer_user_count++;
                        }
                    ?>
                    
                    <div class="card">
                        <div class="card-icon"><i class="fas fa-user-shield"></i></div>
                        <div class="card-info">
                            <h3><?php echo $admin_user_count; ?></h3>
                            <p>Quản trị viên</p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-icon"><i class="fas fa-user"></i></div>
                        <div class="card-info">
                            <h3><?php echo $customer_user_count; ?></h3>
                            <p>Khách hàng</p>
                        </div>
                    </div>
                    
                </div>
                <!-- END: Dashboard Cards for Users -->


                <!-- User Data Table -->
                <div class="data-table-container">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2>Danh sách Người dùng</h2>
                        <!-- Add button remains optional -->
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên đăng nhập</th>
                                <th>Email</th>
                                <th>Vai trò</th>
                                <th>Ngày tạo</th>
                            </tr>
                        </thead>
                        <tbody id="user-table-body">
                            <?php if (empty($users)): ?>
                                <tr>
                                    <!-- Update colspan to 6 -->
                                    <td colspan="6" style="text-align:center; padding: 20px;">Không tìm thấy người dùng nào.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user):
                                    // Determine if edit/delete should be disabled for this user
                                    $is_current_user = ($user['id'] == $current_admin_id);
                                    $is_last_admin = ($last_admin_check_needed && $user['role'] === 'admin');
                                    $disable_delete = $is_current_user || $is_last_admin;
                                    // Prepare data for the edit modal function call
                                    $edit_modal_data = htmlspecialchars(json_encode([
                                        'id' => $user['id'],
                                        'username' => $user['username'],
                                        'role' => $user['role']
                                    ]), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($user['role'])); ?></td>
                                    <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($user['created_at']))); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <!-- Edit Role Modal HTML -->
    <div id="edit-role-modal" class="modal" style="display: none; /* Initially hidden */">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <button class="modal-close" id="close-role-modal-btn">×</button>
            <h2 id="edit-role-modal-title">Thay đổi vai trò người dùng</h2>
            <div id="edit-role-form-message" class="alert" style="display: none; margin-bottom: 15px;"></div>

            <form id="edit-role-form" action="admin_users.php" method="post">
                <!-- Important: Hidden fields to send necessary data -->
                <input type="hidden" name="action" value="edit_role">
                <input type="hidden" id="edit-role-user-id" name="user_id" value="">

                <div class="form-group">
                    <label>Tên đăng nhập:</label>
                    <!-- Display username, not editable -->
                    <p id="edit-role-username" style="font-weight: bold; margin-top: 5px; padding: 8px; background-color: #f0f0f0; border-radius: 4px;"></p>
                </div>

                <div class="form-group">
                    <label for="edit-role-select">Vai trò mới:</label>
                    <select id="edit-role-select" name="new_role" required>
                        <option value="" disabled>-- Chọn vai trò mới --</option>
                        <option value="customer">Customer</option>
                        <option value="admin">Admin</option>
                        <!-- Add other roles if you have them, e.g., <option value="staff">Staff</option> -->
                    </select>
                    <small id="last-admin-warning" style="color: red; display: none; margin-top: 5px;">Không thể thay đổi vai trò của admin cuối cùng.</small>
                </div>

                <button type="submit" class="btn btn-primary modal-submit-btn" id="edit-role-submit-btn">
                    <i class="fas fa-save"></i> Lưu thay đổi
                </button>
            </form>
        </div>
    </div>

    <script src="js/admin.js"></script> <!-- Your existing admin JS -->
    <script>
        $(document).ready(function() { // Use jQuery document ready

            // --- User Search ---
            const userSearchInput = $('#admin-search-user');
            if (userSearchInput.length) {
                userSearchInput.on('keyup', function() {
                    const searchTerm = $(this).val().toLowerCase();
                    $('#user-table-body tr').each(function() {
                        const row = $(this);
                        const username = row.find('td:nth-child(2)').text().toLowerCase();
                        const email = row.find('td:nth-child(3)').text().toLowerCase();
                        if (username.includes(searchTerm) || email.includes(searchTerm)) {
                            row.show();
                        } else {
                            row.hide();
                        }
                    });
                });
            }

            // --- Edit Role Modal JS ---
            const editRoleModal = $('#edit-role-modal');
            const closeRoleModalBtn = $('#close-role-modal-btn');
            const roleModalOverlay = editRoleModal.find('.modal-overlay');
            const editRoleUserId = $('#edit-role-user-id');
            const editRoleUsername = $('#edit-role-username');
            const editRoleSelect = $('#edit-role-select');
            const editRoleFormMessage = $('#edit-role-form-message');
            const editRoleModalTitle = $('#edit-role-modal-title');
            const lastAdminWarning = $('#last-admin-warning');
            const editRoleSubmitBtn = $('#edit-role-submit-btn');

            // Global scope function to be callable from inline onclick
            window.openEditRoleModal = function(userId, username, currentRole, isLastAdmin) {
                if (!editRoleModal.length) return; // Prevent errors if modal not found

                editRoleUserId.val(userId);
                editRoleUsername.text(username); // Display username
                editRoleSelect.val(currentRole); // Pre-select current role
                editRoleModalTitle.text(`Thay đổi vai trò cho: ${username}`); // Set title
                editRoleFormMessage.hide().removeClass('alert-danger alert-success').text(''); // Clear previous messages

                // --- Handle Last Admin Case ---
                lastAdminWarning.hide();
                editRoleSubmitBtn.prop('disabled', false); // Enable submit button by default
                editRoleSelect.find('option').prop('disabled', false); // Enable all options by default

                if (isLastAdmin && currentRole === 'admin') {
                    // If this is the last admin, disable changing role *away* from admin
                    editRoleSelect.find('option[value!="admin"]').prop('disabled', true);
                    lastAdminWarning.show();
                    // Optionally disable submit if the only available option is already selected
                    // editRoleSubmitBtn.prop('disabled', true); // Maybe too restrictive, user might want to "save" the admin role explicitly
                }

                editRoleModal.css('display', 'flex').hide().fadeIn(300); // Use display:flex for centering
            }

            // Close Modal Logic
            function closeRoleModal() {
                if (editRoleModal.length) {
                    editRoleModal.fadeOut(300);
                }
            }
            closeRoleModalBtn.on('click', closeRoleModal);
            roleModalOverlay.on('click', closeRoleModal);

            // Optional: Close modal on ESC key
            $(document).on('keydown', function(event) {
                if (event.key === "Escape" && editRoleModal.is(':visible')) {
                    closeRoleModal();
                }
            });

            // --- Form Submission (Using standard POST for now, no AJAX) ---
            // The form action="admin_users.php" method="post" handles the submission.
            // PHP handles the logic and redirects back with session messages.

             // --- Sidebar Toggle (from your admin.js or add here) ---
             const sidebar = $('.sidebar');
             const mainContent = $('.main-content');
             const menuToggle = $('.header-menu-toggle');

             if (menuToggle.length && sidebar.length && mainContent.length) {
                 menuToggle.on('click', function() {
                     sidebar.toggleClass('collapsed');
                     mainContent.toggleClass('expanded');
                     // Save state in localStorage (optional)
                     if (sidebar.hasClass('collapsed')) {
                         localStorage.setItem('sidebarState', 'collapsed');
                     } else {
                         localStorage.removeItem('sidebarState');
                     }
                 });

                 // Check localStorage on page load (optional)
                 if (localStorage.getItem('sidebarState') === 'collapsed') {
                     sidebar.addClass('collapsed');
                     mainContent.addClass('expanded');
                 }
             }

             // --- User Dropdown (from your admin.js or add here) ---
            $('.user-info').on('click', function(e) {
                e.stopPropagation(); // Prevent click from bubbling up to document
                $('.user-dropdown').toggle();
            });

            // Close dropdown if clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.user-info').length) {
                    $('.user-dropdown').hide();
                }
            });
            

        }); // End document ready
    </script>

</body>
</html>