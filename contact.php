<?php
// Bắt đầu session nếu cần cho navbar hoặc các chức năng khác
session_start();

// --- Include Header and Navbar ---
include 'parts/header.php'; // Bao gồm thẻ <head> và các link CSS chung
?>
<title>Liên Hệ - FoodNow</title>
<style>
    /* CSS specific for the contact page */
    .contact-page {
        max-width: 1100px;
        margin: 40px auto;
        padding: 20px;
    }

    .contact-page h1 {
        text-align: center;
        margin-bottom: 40px;
        color: #ffc107;
        font-weight: 600;
    }

    .branch-container {
        display: grid;
        /* grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); */ /* Hiển thị cạnh nhau nếu đủ rộng */
        grid-template-columns: 1fr; /* Mặc định hiển thị trên dưới */
        gap: 40px; /* Khoảng cách giữa các chi nhánh */
    }

     @media (min-width: 992px) { /* Trên màn hình lớn hơn, hiển thị 2 cột */
        .branch-container {
            grid-template-columns: 1fr 1fr;
        }
     }


    .branch-card {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        background-color: #fff;
        display: flex;
        flex-direction: column;
    }

    .branch-image {
        width: 100%;
        height: 250px; /* Chiều cao cố định cho ảnh */
        object-fit: cover; /* Đảm bảo ảnh vừa khít */
        display: block;
    }

    .branch-info {
        padding: 25px;
        flex-grow: 1; /* Đẩy nội dung xuống dưới nếu cần */
        display: flex;
        flex-direction: column;
    }

    .branch-info h2 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #e44d26; /* Màu cam chủ đạo */
        font-size: 1.6em;
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 10px;
    }

    .branch-info p {
        margin-bottom: 12px;
        line-height: 1.6;
        color: #555;
        display: flex; /* Sắp xếp icon và text */
        align-items: center; /* Căn giữa icon và text */
        gap: 10px; /* Khoảng cách giữa icon và text */
    }

     .branch-info p strong {
         color: #333;
         min-width: 80px; /* Đảm bảo các label thẳng hàng */
         display: inline-block;
     }

    .branch-info a {
        color: #007bff;
        text-decoration: none;
    }

    .branch-info a:hover {
        text-decoration: underline;
    }

    /* CSS cho icon (ví dụ dùng Font Awesome) */
    .branch-info p i {
        color: #e44d26; /* Màu icon */
        font-size: 1.1em;
        width: 20px; /* Chiều rộng cố định cho icon */
        text-align: center;
    }

    /* Optional: Embedded Map */
    .map-container {
        margin-top: 20px;
        width: 100%;
        height: 250px; /* Chiều cao bản đồ */
        border-radius: 5px;
        overflow: hidden; /* Đảm bảo iframe không tràn ra */
        border: 1px solid #eee;
    }

    .map-container iframe {
        width: 100%;
        height: 100%;
        border: 0;
    }

</style>
<!-- Tùy chọn: Link đến Font Awesome nếu bạn muốn dùng icon -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<body>
    <?php include 'parts/navbar.php'; // Bao gồm thanh điều hướng ?>

    <div class="contact-page">
        <h1>Liên Hệ Với FoodNow</h1>

        <div class="branch-container">

            <!-- Chi nhánh Hà Nội -->
            <div class="branch-card">
                <img src="image/img2.jpg" alt="Chi nhánh FoodNow Hà Nội" class="branch-image">
                <div class="branch-info">
                    <h2><i class="fas fa-map-marker-alt"></i> Chi Nhánh Hà Nội</h2>
                    <p><i class="fas fa-location-dot"></i><strong>Địa chỉ:</strong> Số 123, Đường ABC, Quận Hoàn Kiếm, Hà Nội</p>
                    <p><i class="fas fa-phone"></i><strong>Điện thoại:</strong> <a href="tel:02412345678">024 1234 5678</a></p>
                    <p><i class="fas fa-envelope"></i><strong>Email:</strong> <a href="mailto:hanoi@foodnow.com">hanoi@foodnow.com</a></p>
                    <p><i class="fas fa-clock"></i><strong>Giờ mở cửa:</strong> 08:00 - 22:00 (Thứ 2 - Chủ Nhật)</p>

                    <!-- Tùy chọn: Bản đồ nhúng cho Hà Nội -->
                    <div class="map-container">
                        <!-- Thay thế bằng mã nhúng Google Maps của chi nhánh HN -->
                        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3724.676986488331!2d105.8411490154001!3d21.00561199395683!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3135ac76cc46aea7%3A0x1ff1b43a5c605544!2zSG_DoG4gS2nhur9tIExpbmsgUGjhu5EgTOG7nWcgUGjhuqFuZywgSMOgbmcgVHLhu5FuZywgSMOgbiBO4buZaSwgVmnhu4d0IE5hbQ!5e0!3m2!1svi!2s!4v1678886655443!5m2!1svi!2s" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                </div>
            </div>

            <!-- Chi nhánh TP. Hồ Chí Minh -->
            <div class="branch-card">
                 <img src="image/img1.jpg" alt="Chi nhánh FoodNow TP. Hồ Chí Minh" class="branch-image">
                 <div class="branch-info">
                    <h2><i class="fas fa-map-marker-alt"></i> Chi Nhánh TP. Hồ Chí Minh</h2>
                    <p><i class="fas fa-location-dot"></i><strong>Địa chỉ:</strong> Số 456, Đường XYZ, Quận 1, TP. Hồ Chí Minh</p>
                    <p><i class="fas fa-phone"></i><strong>Điện thoại:</strong> <a href="tel:02898765432">028 9876 5432</a></p>
                    <p><i class="fas fa-envelope"></i><strong>Email:</strong> <a href="mailto:hcm@foodnow.com">hcm@foodnow.com</a></p>
                    <p><i class="fas fa-clock"></i><strong>Giờ mở cửa:</strong> 09:00 - 23:00 (Thứ 2 - Chủ Nhật)</p>

                     <!-- Tùy chọn: Bản đồ nhúng cho TP. HCM -->
                     <div class="map-container">
                         <!-- Thay thế bằng mã nhúng Google Maps của chi nhánh HCM -->
                         <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3919.44864086031!2d106.6951770153842!3d10.77695926215129!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31752f3a716f1b8f%3A0x8e3a3c4a6b265bf7!2zQ2jhu6MgQuG6v24gVGjDoG5oLCBMw6ogTOG7ợiwgQuG6v24gVGjDoG5oLCBRdeG6rW4gMSwgVGjDoG5oIHBo4buRIEjhu5MgQ2jDrSBNaW5oLCBWaeG7h3QgTmFt!5e0!3m2!1svi!2s!4v1678886788990!5m2!1svi!2s" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                     </div>
                 </div>
            </div>

        </div> <!-- /.branch-container -->
    </div>

    <?php include 'parts/footer.php'; // Bao gồm chân trang ?>
</body>
</html>