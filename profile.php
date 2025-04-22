<?php
session_start();

if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}

require 'config/db_config.php';

$userId = $_SESSION['id'];
$fullName = $email = $phoneNumber = $address = '';
$currentHashedPassword = ''; // Biến để lưu mật khẩu hash hiện tại
$successMessage = '';
$errorMessage = '';
$errors = [];

// --- Lấy thông tin người dùng hiện tại (bao gồm cả mật khẩu hash) NGAY TỪ ĐẦU ---
try {
    // Thêm 'password' vào câu SELECT
    $stmt = $pdo->prepare("SELECT full_name, email, phone_number, address, password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $fullName = $user['full_name'] ?? '';
        $email = $user['email'];
        $phoneNumber = $user['phone_number'] ?? '';
        $address = $user['address'] ?? '';
        $currentHashedPassword = $user['password']; // Lưu mật khẩu hash hiện tại
    } else {
        // User không tồn tại? Xử lý lỗi session
        session_destroy();
        header('Location: login.php');
        exit;
    }
} catch (PDOException $e) {
    $errorMessage = "Lỗi nghiêm trọng khi tải thông tin người dùng.";
}
// --- Kết thúc lấy thông tin người dùng ban đầu ---


// --- Xử lý POST Request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Lấy dữ liệu từ form
    $submittedOldPassword = $_POST['old_password'] ?? ''; // Lấy mật khẩu cũ
    $newFullName = trim($_POST['full_name'] ?? '');
    $newEmail = trim($_POST['email'] ?? '');
    $newPhoneNumber = trim($_POST['phone_number'] ?? '');
    $newPassword = $_POST['password'] ?? '';
    $newConfirmPassword = $_POST['confirm_password'] ?? '';
    $newAddress = trim($_POST['address'] ?? '');

    // --- Validation ---

    // 1. Kiểm tra mật khẩu cũ (QUAN TRỌNG)
    if (empty($submittedOldPassword)) {
        $errors['old_password'] = "Bạn phải nhập mật khẩu hiện tại để cập nhật.";
    } elseif (empty($currentHashedPassword)) {

        $errors['old_password'] = "Tài khoản của bạn chưa có mật khẩu. Vui lòng liên hệ hỗ trợ hoặc thiết lập mật khẩu.";
    }
    // Chỉ gọi password_verify nếu $currentHashedPassword không rỗng
    elseif (!empty($currentHashedPassword) && !password_verify($submittedOldPassword, $currentHashedPassword)) {
        $errors['old_password'] = "Mật khẩu hiện tại không chính xác.";
    }


    // 2. Validation các trường khác (chỉ thực hiện nếu mật khẩu cũ ổn - tùy chọn, nhưng có thể tiết kiệm xử lý)
    // Hoặc cứ validate hết rồi kiểm tra $errors ở cuối
    if (empty($newFullName)) {
        $errors['full_name'] = "Họ và Tên không được để trống.";
    }
    if (empty($newEmail)) {
        $errors['email'] = "Email không được để trống.";
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Định dạng email không hợp lệ.";
    }

    // 3. Validation mật khẩu mới (chỉ khi có nhập)
    if (!empty($newPassword)) {
        if (strlen($newPassword) < 6) {
            $errors['password'] = "Mật khẩu mới phải có ít nhất 6 ký tự.";
        } elseif ($newPassword !== $newConfirmPassword) {
            $errors['confirm_password'] = "Mật khẩu xác nhận không khớp.";
        }
    }
    // --- Kết thúc Validation ---


    // --- Tiến hành cập nhật nếu không có lỗi ---
    if (empty($errors)) {
        try {
            $sql = "UPDATE users SET full_name = :full_name, email = :email, phone_number = :phone_number, address = :address";
            $params = [
                ':full_name' => $newFullName,
                ':email' => $newEmail, // Cân nhắc: có nên yêu cầu mk cũ khi đổi email không?
                ':phone_number' => $newPhoneNumber ?: null,
                ':address' => $newAddress ?: null,
            ];

            // Chỉ cập nhật mật khẩu nếu người dùng nhập mật khẩu mới *và* mật khẩu cũ đã đúng
            if (!empty($newPassword)) {
                $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $sql .= ", password = :password";
                $params[':password'] = $hashedNewPassword;
            }

            $sql .= " WHERE id = :id";
            $params[':id'] = $userId;

            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                $successMessage = "Cập nhật thông tin thành công!";
                // Cập nhật lại biến hiển thị sau khi thành công
                $fullName = $newFullName;
                $email = $newEmail;
                $phoneNumber = $newPhoneNumber;
                $address = $newAddress;
                // Nếu mật khẩu đã thay đổi, cập nhật biến hash hiện tại (dù không hiển thị)
                if (!empty($newPassword)) {
                    $currentHashedPassword = $hashedNewPassword;
                }
            } else {
                $errorMessage = "Lỗi cập nhật thông tin. Vui lòng thử lại.";
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Lỗi duplicate email
                $errorMessage = "Email này đã được sử dụng bởi tài khoản khác.";
                $errors['email'] = "Email đã tồn tại.";
            } else {
                $errorMessage = "Lỗi cơ sở dữ liệu. Vui lòng thử lại sau.";
                // error_log("Profile Update Error: " . $e->getMessage());
            }
        }
    } else {
        // Có lỗi validation, giữ lại giá trị người dùng đã nhập (ngoại trừ password)
        $fullName = $newFullName; // Giữ lại để hiển thị
        $email = $newEmail;
        $phoneNumber = $newPhoneNumber;
        $address = $newAddress;
        if (!isset($errors['old_password'])) { // Chỉ báo lỗi chung nếu không phải do mk cũ sai
            $errorMessage = $errorMessage ?: "Vui lòng kiểm tra lại các thông tin đã nhập.";
        } else {
            $errorMessage = $errorMessage ?: "Vui lòng kiểm tra lại mật khẩu và các thông tin khác.";
        }
    }
} // --- Kết thúc xử lý POST ---
?>
<?php
include 'parts/header.php';
?>
<body>
    <style>
        :root {
            --bg-color-dark: #1a1a1a;
            /* Dark background */
            --card-bg-color: #2b2b2b;
            /* Slightly lighter card background */
            --text-color-light: #e0e0e0;
            /* Light text */
            --text-color-muted: #a0a0a0;
            /* Muted text */
            --accent-color: #f0ad4e;
            /* Yellow/Orange accent */
            --accent-color-darker: #ec971f;
            --border-color: #444;
            --error-color: #dc3545;
            /* Red for errors */
            --error-bg-color: #f8d7da;
            /* Light background for error messages */
            --error-text-color: #721c24;
            /* Dark text for error messages */
            --success-color: #28a745;
            /* Green for success */
            --success-bg-color: #d4edda;
            /* Light background for success messages */
            --success-text-color: #155724;
            /* Dark text for success messages */
            --input-bg-color: #3a3a3a;
            /* Background for inputs */
        }

        /* --- Base Body Styles --- */
        body {
            background-color: var(--bg-color-dark);
            color: var(--text-color-light);
            font-family: 'Montserrat', sans-serif;
            /* Or your preferred font */
            margin: 0;
            line-height: 1.6;
            padding: 20px;
            /* Add some padding around the container */
        }

        /* --- Profile Container (similar to food detail card) --- */
        .container {
            /* Using the existing class from profile.php */
            max-width: 750px;
            /* Adjust width as needed */
            margin: 100px auto;
            background-color: var(--card-bg-color);
            padding: 30px 40px;
            /* More padding */
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        h1 {
            text-align: center;
            color: var(--accent-color);
            /* Use accent color for the main title */
            margin-bottom: 30px;
            font-size: 2.2rem;
            font-weight: 700;
        }

        /* --- Form Styling --- */
        .form-group {
            margin-bottom: 20px;
            /* Increase spacing */
        }

        label {
            display: block;
            margin-bottom: 8px;
            /* Space between label and input */
            font-weight: 600;
            /* Slightly bolder labels */
            color: var(--text-color-light);
            font-size: 0.95rem;
        }

        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="password"],
        textarea {
            width: 100%;
            padding: 12px 15px;
            /* More padding */
            border: 1px solid var(--border-color);
            border-radius: 5px;
            box-sizing: border-box;
            background-color: var(--input-bg-color);
            /* Darker input background */
            color: var(--text-color-light);
            font-size: 1rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="tel"]:focus,
        input[type="password"]:focus,
        textarea:focus {
            outline: none;
            border-color: var(--accent-color);
            /* Highlight border on focus */
            box-shadow: 0 0 0 3px rgba(240, 173, 78, 0.3);
            /* Subtle glow on focus */
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* --- Submit Button (style like 'Add to Cart') --- */
        button[type="submit"] {
            display: block;
            width: 100%;
            background-color: var(--accent-color);
            color: var(--bg-color-dark);
            /* Dark text on light button */
            border: none;
            padding: 14px 20px;
            /* Slightly larger padding */
            font-size: 1.1rem;
            font-weight: 600;
            text-transform: uppercase;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.2s ease, transform 0.1s ease;
            margin-top: 10px;
            /* Space above button */
        }

        button[type="submit"]:hover {
            background-color: var(--accent-color-darker);
        }

        button[type="submit"]:active {
            transform: scale(0.98);
        }

        /* --- Messages --- */
        .message {
            padding: 15px;
            margin-bottom: 25px;
            /* More space */
            border-radius: 5px;
            text-align: center;
            font-weight: 500;
            border: 1px solid transparent;
        }

        /* Use light backgrounds with dark text for better readability */
        .success {
            background-color: var(--success-bg-color);
            color: var(--success-text-color);
            border-color: #c3e6cb;
        }

        .error {
            background-color: var(--error-bg-color);
            color: var(--error-text-color);
            border-color: #f5c6cb;
        }

        /* Inline validation error text */
        .error-text {
            color: var(--error-color);
            /* Use a distinct error color */
            font-size: 0.875em;
            margin-top: 5px;
            /* Space below input */
            font-weight: 500;
        }

        /* --- Other Elements --- */
        hr {
            border: none;
            border-top: 1px solid var(--border-color);
            margin: 30px 0;
            /* More space around the separator */
        }

        p {
            color: var(--text-color-muted);
            /* Muted color for paragraphs */
            line-height: 1.6;
        }

        p strong,
        p b {
            /* Make bold text in paragraphs lighter */
            color: var(--text-color-light);
            font-weight: 600;
        }

        /* Style the "Change Password" text */
        p.password-section-title {
            /* Add this class to the <p> tag */
            font-weight: bold;
            color: var(--text-color-light);
            /* Lighter color */
            font-size: 1.1rem;
            margin-bottom: 10px;
        }

        /* Style the 'Back to home' link */
        .container p a {
            color: var(--accent-color);
            /* Use accent color for links */
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .container p a:hover {
            color: var(--accent-color-darker);
            text-decoration: underline;
        }

        /* --- Responsive Adjustments --- */
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }

            h1 {
                font-size: 1.8rem;
            }

            button[type="submit"] {
                padding: 12px 15px;
                font-size: 1rem;
            }
        }
    </style>
    <?php include 'parts/navbar.php'; ?>
    <div class="container">
        <h1>Hồ sơ của bạn</h1>

        <?php if ($successMessage): ?>
            <div class="message success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>

        <?php if ($errorMessage): // Hiển thị lỗi tổng quát hoặc lỗi validation 
        ?>
            <div class="message error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <form action="profile.php" method="post">
            <div class="form-group">
                <label for="full_name">Họ và Tên:</label>
                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($fullName); ?>" required>
                <?php if (isset($errors['full_name'])): ?>
                    <div class="error-text"><?php echo $errors['full_name']; ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                <?php if (isset($errors['email'])): ?>
                    <div class="error-text"><?php echo $errors['email']; ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="phone_number">Số điện thoại:</label>
                <input type="tel" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($phoneNumber); ?>" placeholder="Nhập số điện thoại (tùy chọn)">
                <?php if (isset($errors['phone_number'])): ?>
                    <div class="error-text"><?php echo $errors['phone_number']; ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="address">Địa chỉ:</label>
                <textarea id="address" name="address" placeholder="Nhập địa chỉ giao hàng (tùy chọn)"><?php echo htmlspecialchars($address); ?></textarea>
                <?php if (isset($errors['address'])): ?>
                    <div class="error-text"><?php echo $errors['address']; ?></div>
                <?php endif; ?>
            </div>

            <hr>

            <p class="password-section-title">Thay đổi mật khẩu (Để trống nếu không muốn đổi)</p>
            <div class="form-group">
                <label for="old_password">Xác nhận mật khẩu hiện tại:</label>
                <input type="password" id="old_password" name="old_password" required placeholder="Nhập mật khẩu hiện tại để lưu thay đổi">
                <?php if (isset($errors['old_password'])): ?>
                    <div class="error-text"><?php echo $errors['old_password']; ?></div>
                <?php endif; ?>
                <small style="color: var(--text-color-muted); display: block; margin-top: 5px;">Bắt buộc để lưu bất kỳ thay đổi nào.</small>
            </div>
            <div class="form-group">
                <label for="password">Mật khẩu mới:</label>
                <input type="password" id="password" name="password" placeholder="Nhập mật khẩu mới (ít nhất 6 ký tự)">
                <?php if (isset($errors['password'])): ?>
                    <div class="error-text"><?php echo $errors['password']; ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="confirm_password">Xác nhận mật khẩu mới:</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Nhập lại mật khẩu mới">
                <?php if (isset($errors['confirm_password'])): ?>
                    <div class="error-text"><?php echo $errors['confirm_password']; ?></div>
                <?php endif; ?>
            </div>

            <hr> <!-- Thêm một đường kẻ nữa để tách biệt -->
            <div class="form-group" style="margin-top: 25px;"> <!-- Thêm khoảng cách trước nút submit -->
                <button type="submit">Cập nhật thông tin</button>
            </div>
        </form>
        <p style="text-align: center; margin-top: 20px;">
            <a href="index.php">Quay lại trang chủ</a>
        </p>
    </div>

    <?php include 'parts/footer.php'; ?>
</body>

</html>