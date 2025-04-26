<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liên Hệ - FoodNow</title>
    <link rel="stylesheet" href="css/contact.css">
    <!-- <link rel="icon" href="favicon.ico" type="image/x-icon"> -->
</head>
<body>
    <?php
    include 'parts/navbar.php'; // Include your standard header
    ?>
    <!-- Main Content -->
    <main>
        <div class="container">
            <h1 class="page-title">Liên Hệ Với Chúng Tôi</h1>
            <p class="page-subtitle">Chúng tôi luôn sẵn lòng lắng nghe ý kiến đóng góp hoặc giải đáp thắc mắc của bạn.</p>

            <form action="#" method="post" class="contact-form">
                <div class="form-group">
                    <label for="name">Họ và Tên:</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="email">Địa chỉ Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="subject">Chủ đề:</label>
                    <input type="text" id="subject" name="subject">
                </div>
                <div class="form-group">
                    <label for="message">Nội dung tin nhắn:</label>
                    <textarea id="message" name="message" required></textarea>
                </div>
                <button type="submit" class="submit-btn">Gửi Tin Nhắn</button>
            </form>

           
            <div class="contact-details" style="text-align: center; margin-top: 40px; color: var(--text-secondary);">
                <p><strong>Địa chỉ:</strong> 123 Đường ABC, Quận XYZ, Thành phố HCM</p>
                <p><strong>Điện thoại:</strong> (028) 1234 5678</p>
                <p><strong>Email:</strong> contact@foodnow.com</p>
            </div>
        </div>
    </main>

    <!-- Footer (Optional) -->
    <footer>
        <div class="container">
            <p>© 2024 FoodNow. Bảo lưu mọi quyền.</p>
        </div>
    </footer>

</body>
</html>