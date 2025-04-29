<?php
session_start();

// --- Database Configuration & Connection ---
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'foodnow');

$message = '';
$error = '';
$dishes = [];

// Kết nối CSDL để lấy danh sách món ăn (Giữ nguyên)
$conn_dishes = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn_dishes) {
    mysqli_set_charset($conn_dishes, "utf8mb4");
    $sql_dishes = "SELECT id, name FROM food_data ORDER BY name ASC"; // Thay 'food_data' nếu cần
    $result_dishes = mysqli_query($conn_dishes, $sql_dishes);
    if ($result_dishes) {
        $dishes = mysqli_fetch_all($result_dishes, MYSQLI_ASSOC);
    } else {
        error_log("Error fetching dishes: " . mysqli_error($conn_dishes));
    }
    mysqli_close($conn_dishes);
} else {
    error_log("Database Connection failed for fetching dishes: " . mysqli_connect_error());
}

// --- Xử lý khi form được gửi đi (Giữ nguyên logic PHP) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $dish_id = filter_input(INPUT_POST, 'dish_id', FILTER_VALIDATE_INT);
    $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 10]
    ]);
    $feedback_text = trim($_POST['feedback'] ?? '');

    if (empty($full_name)) {
        $error = 'Vui lòng nhập họ và tên.';
    } elseif (!$email) {
        $error = 'Vui lòng nhập địa chỉ email hợp lệ.';
    } elseif (empty($dish_id) || $dish_id <= 0) {
        $error = 'Vui lòng chọn một món ăn.';
    } elseif ($rating === false) {
        $error = 'Vui lòng chọn điểm đánh giá hợp lệ (từ 1 đến 10).';
    } elseif (empty($feedback_text) && $rating === false) {
        $error = 'Vui lòng nhập ý kiến hoặc chấm điểm món ăn.';
    } else {
        $conn_save = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
        if ($conn_save === false) {
            error_log("DB Connection failed for saving feedback: " . mysqli_connect_error());
            $error = "Lỗi kết nối. Không thể gửi ý kiến.";
        } else {
            mysqli_set_charset($conn_save, "utf8mb4");
            try {
                $sql_insert = "INSERT INTO user_feedback (full_name, email, dish_id, rating, feedback) VALUES (?, ?, ?, ?, ?)";
                $stmt_insert = mysqli_prepare($conn_save, $sql_insert);
                if ($stmt_insert) {
                    mysqli_stmt_bind_param($stmt_insert, "ssiis", $full_name, $email, $dish_id, $rating, $feedback_text);
                    if (mysqli_stmt_execute($stmt_insert)) {
                        $message = "Cảm ơn bạn đã gửi ý kiến và đánh giá! Chúng tôi rất trân trọng đóng góp của bạn.";
                        $_POST = array();
                    } else {
                        error_log("MySQLi execute failed: " . mysqli_stmt_error($stmt_insert));
                        $error = "Không thể gửi ý kiến lúc này.";
                    }
                    mysqli_stmt_close($stmt_insert);
                } else {
                    error_log("MySQLi prepare failed: " . mysqli_error($conn_save));
                    $error = "Lỗi chuẩn bị gửi ý kiến.";
                }
            } catch (Exception $e) {
                error_log("Error saving feedback: " . $e->getMessage());
                $error = "Đã xảy ra lỗi.";
            } finally {
                mysqli_close($conn_save);
            }
        }
    }
}

// --- Include Header ---
include 'parts/header.php'; // Giả sử header không set màu nền trắng mặc định cho body
?>
<title>Gửi Ý Kiến & Đánh Giá Món Ăn - FoodNow</title>
<style>
    /* === DARK THEME CSS === */
    body {
        background-color: #212529;
        /* Màu nền tối chính */
        color: #e0e0e0;
        /* Màu chữ sáng mặc định */
        line-height: 1.6;
        font-family: 'Montserrat', sans-serif;
        /* Nên định nghĩa font ở header hoặc file CSS chung */
        margin: 0;
        /* Đảm bảo không có margin mặc định */
    }

    .feedback-page {
        max-width: 700px;
        margin: 6% auto;
        padding: 30px;
        background-color: #343a40;
        /* Nền tối hơn cho form container */
        border: 1px solid #495057;
        /* Viền tối */
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
        /* Shadow tối hơn */
    }

    .feedback-page h1 {
        text-align: center;
        color: #e44d26;
        /* Giữ màu nhấn */
        margin-bottom: 30px;
    }

    .feedback-page p {
        /* Đoạn text hướng dẫn */
        color: #adb5bd;
        /* Màu xám sáng hơn */
    }


    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #ced4da;
        /* Màu label sáng hơn */
    }

    .form-control {
        /* Áp dụng cho input, select, textarea */
        display: block;
        width: 100%;
        padding: 12px;
        font-size: 1rem;
        line-height: 1.5;
        color: #e9ecef;
        /* Màu chữ trong input */
        background-color: #495057;
        /* Nền tối cho input */
        background-clip: padding-box;
        border: 1px solid #6c757d;
        /* Viền tối hơn */
        border-radius: 4px;
        transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
        box-sizing: border-box;
    }

    /* Đảm bảo select có màu nền và chữ đúng */
    select.form-control {
        height: calc(1.5em + 1.5rem + 2px);
        /* Một số trình duyệt cần set color riêng cho option, nhưng khó custom */
    }

    textarea.form-control {
        min-height: 120px;
        resize: vertical;
    }

    .form-control::placeholder {
        /* Màu placeholder */
        color: #adb5bd;
        opacity: 1;
        /* Firefox cần */
    }

    .form-control:focus {
        color: #ffffff;
        /* Chữ trắng hơn khi focus */
        background-color: #5a6268;
        /* Nền tối hơn chút khi focus */
        border-color: #e44d26;
        /* Màu viền nhấn */
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(228, 77, 38, 0.5);
        /* Shadow màu nhấn */
    }

    .btn-submit-feedback {
        display: inline-block;
        font-weight: 600;
        color: #fff;
        text-align: center;
        cursor: pointer;
        background-color: #28a745;
        /* Giữ màu xanh lá cho nút chính */
        border: 1px solid #28a745;
        padding: 12px 25px;
        font-size: 1.1em;
        line-height: 1.5;
        border-radius: 5px;
        transition: background-color .15s ease-in-out, border-color .15s ease-in-out;
        width: 100%;
    }

    .btn-submit-feedback:hover {
        background-color: #218838;
        border-color: #1e7e34;
    }

    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border: 1px solid transparent;
        border-radius: 4px;
        text-align: center;
    }

    .alert-success {
        color: #c3e6cb;
        /* Chữ sáng hơn */
        background-color: #155724;
        /* Nền xanh lá đậm */
        border-color: #1c6431;
        /* Viền tối hơn */
    }

    .alert-danger {
        color: #f5c6cb;
        /* Chữ sáng hơn */
        background-color: #721c24;
        /* Nền đỏ đậm */
        border-color: #80242c;
        /* Viền tối hơn */
    }

    /* Style cho rating (giữ nguyên) */
    .rating-stars {
        display: none;
    }

    .rating-select-label {
        font-weight: 600;
        color: #ced4da;
    }

    /* Màu label sáng */

    /* Đảm bảo navbar/footer không bị ảnh hưởng nếu chúng có style riêng */
    /* Bạn có thể cần điều chỉnh CSS cho navbar/footer nếu chúng cũng cần dark theme */
</style>

<body>
    <?php include 'parts/navbar.php'; // Navbar có thể cần CSS riêng cho dark theme 
    ?>

    <div class="feedback-page">
        <h1>Góp Ý & Đánh Giá Món Ăn</h1>
        <p style="text-align: center; margin-bottom: 30px;">
            Ý kiến và đánh giá của bạn giúp chúng tôi phục vụ tốt hơn.
        </p>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($message)): ?>
            <form action="user_feedback.php" method="POST">
                <div class="form-group">
                    <label for="full_name">Họ và Tên (*):</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ($_SESSION['full_name'] ?? '')); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email (*):</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ($_SESSION['email'] ?? '')); ?>" required>
                </div>
                <div class="form-group">
                    <label for="dish_id">Món ăn bạn muốn đánh giá (*):</label>
                    <select id="dish_id" name="dish_id" class="form-control" required>
                        <option value="" <?php echo (!isset($_POST['dish_id']) || empty($_POST['dish_id'])) ? 'selected' : ''; ?>>-- Chọn món ăn --</option>
                        <?php if (!empty($dishes)): ?>
                            <?php foreach ($dishes as $dish): ?>
                                <option value="<?php echo $dish['id']; ?>" <?php echo (isset($_POST['dish_id']) && $_POST['dish_id'] == $dish['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dish['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="">Không tải được danh sách món ăn</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="rating" class="rating-select-label">Chấm điểm món ăn (*):</label>
                    <select id="rating" name="rating" class="form-control" required>
                        <option value="" <?php echo (!isset($_POST['rating'])) ? 'selected' : ''; ?>>-- Chọn điểm (1 = Tệ, 10 = Tuyệt vời) --</option>
                        <?php for ($i = 10; $i >= 1; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo (isset($_POST['rating']) && $_POST['rating'] == $i) ? 'selected' : ''; ?>>
                                <?php echo $i; ?> điểm
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="feedback">Ý kiến của bạn (Tùy chọn):</label>
                    <textarea id="feedback" name="feedback" class="form-control"><?php echo htmlspecialchars($_POST['feedback'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="btn-submit-feedback">Gửi Đánh Giá</button>
            </form>
        <?php endif; ?>
    </div>

    <?php include 'parts/footer.php'; // Footer có thể cần CSS riêng cho dark theme 
    ?>
</body>

</html>