<?php include 'parts/header.php' ?>
<link rel="stylesheet" href="css/contact.css">
<title>Liên hệ</title>
<link rel="shortcut icon" href="image/foodnow_icon.png" sizes="32x32" type="image/x-icon">
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

    <?php include 'parts/footer.php' ?>
    <footer>
        <div class="container">
            <p>© 2024 FoodNow. Bảo lưu mọi quyền.</p>
        </div>
    </footer>

</body>
</html>