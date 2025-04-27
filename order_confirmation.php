<?php
session_start();
include 'parts/header.php'; // Include your standard header

$success_message = $_SESSION['checkout_success'] ?? 'Đặt hàng thành công!';
$notice_message = $_SESSION['checkout_notice'] ?? null;

// Clear the session messages after displaying
unset($_SESSION['checkout_success']);
unset($_SESSION['checkout_notice']);
// Optionally clear the cart session variable if you use one
// unset($_SESSION['cart']);

?>
<title>Đặt hàng thành công</title>
<link rel="shortcut icon" href="image/foodnow_icon.png" sizes="32x32" type="image/x-icon">
<body>
    <?php include 'parts/navbar.php'; ?>

    <main class="confirmation-container" style="max-width: 800px; margin: 10rem auto 2rem auto; padding: 2rem; background-color: #343a40; border-radius: 8px; text-align: center; color: #f8f9fa;">
        <h1><i class="fas fa-check-circle" style="color: #198754;"></i> <?php echo htmlspecialchars($success_message); ?></h1>
        <p>Cảm ơn bạn đã đặt hàng tại FoodNow. Chúng tôi sẽ xử lý đơn hàng của bạn sớm nhất có thể.</p>
        <?php if ($notice_message): ?>
            <p style="font-style: italic; color: #adb5bd;"><?php echo htmlspecialchars($notice_message); ?></p>
        <?php endif; ?>
        <p>Bạn có thể xem lại lịch sử đơn hàng trong tài khoản (nếu đăng nhập) hoặc liên hệ với chúng tôi nếu có bất kỳ câu hỏi nào.</p>
        <div style="margin-top: 2rem;">
            <a href="index.php" class="btn btn-primary" style="margin-right: 10px;">Về Trang Chủ</a>
            <a href="food.php" class="btn btn-outline-secondary">Tiếp tục mua sắm</a>
            <!-- Maybe add a link to order history if applicable -->
            <!-- <a href="order_history.php" class="btn btn-info">Xem đơn hàng</a> -->
        </div>
    </main>

    <footer>
        <?php include 'parts/footer.php'; ?>
    </footer>
</body>
</html>