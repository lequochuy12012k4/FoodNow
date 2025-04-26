<?php
// Bạn vẫn có thể cần session_start() nếu header/footer sử dụng session
session_start();
// Không cần require db_config.php nữa
?>
<?php include 'parts/header.php';?>
<title>Thành tựu</title>
<body>

    <?php include 'parts/navbar.php';?>
    <style>
        /* awards_style.css */

/* --- Import Theme Variables --- */
:root {
    --bg-color-dark: #1a1a1a;
    --card-bg-color: #2b2b2b;
    --text-color-light: #e0e0e0;
    --text-color-muted: #a0a0a0;
    --accent-color: #f0ad4e;
    --border-color: #444;
    --link-color: #5bc0de; /* Màu link */
     --gold-color: #ffd700; /* Màu vàng gold cho icon */
}

/* --- Base Body Styles --- */
body {
    background-color: var(--bg-color-dark);
    color: var(--text-color-light);
    font-family: 'Montserrat', sans-serif;
    margin: 0;
    line-height: 1.6;
}

/* --- Main Container --- */
.awards-container {
    max-width: 950px; /* Giới hạn chiều rộng */
    margin: 40px auto;
    padding: 20px;
}

/* --- Title & Subtitle --- */
h1 {
    text-align: center;
    color: var(--accent-color);
    margin-bottom: 10px; /* Giảm khoảng cách dưới */
    font-size: 2.5rem;
    font-weight: 700;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px;
}
h1 i.fa-award {
    color: var(--gold-color); /* Màu vàng cho icon award */
}

.page-subtitle {
    text-align: center;
    color: var(--text-color-muted);
    font-size: 1.1rem;
    margin-bottom: 40px;
}

/* --- Awards List --- */
.awards-list {
    display: flex;
    flex-direction: column; /* Sắp xếp các card theo chiều dọc */
    gap: 30px; /* Khoảng cách giữa các card */
}

/* --- Individual Award Card --- */
.award-card {
    background-color: var(--card-bg-color);
    border-radius: 10px;
    display: flex; /* Sắp xếp ảnh và chi tiết ngang nhau */
    gap: 25px; /* Khoảng cách giữa ảnh và chi tiết */
    padding: 25px;
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.2);
    overflow: hidden; /* Đảm bảo ảnh không tràn */
    border-left: 5px solid var(--accent-color); /* Thêm điểm nhấn bên trái */
}

.award-image-wrapper {
    flex-shrink: 0; /* Không co ảnh lại */
    width: 120px; /* Kích thước cố định cho ảnh/logo */
    height: 120px;
    display: flex;
    justify-content: center;
    align-items: center;
    background-color: rgba(255, 255, 255, 0.05); /* Nền nhẹ cho ảnh */
    border-radius: 8px;
    padding: 5px; /* Padding nhỏ quanh ảnh */
}

.award-image {
    display: block;
    max-width: 100%;
    max-height: 100%;
    object-fit: contain; /* Hiển thị toàn bộ ảnh, không cắt xén */
    border-radius: 4px;
}

.award-details {
    flex-grow: 1; /* Chi tiết chiếm hết không gian còn lại */
    display: flex;
    flex-direction: column;
}

.award-title {
    font-size: 1.6rem;
    font-weight: 600;
    color: var(--text-color-light);
    margin-top: 0;
    margin-bottom: 8px;
    line-height: 1.3;
}

.award-meta {
    display: flex;
    flex-wrap: wrap; /* Cho phép xuống dòng nếu cần */
    gap: 15px; /* Khoảng cách giữa nguồn và ngày */
    margin-bottom: 15px;
    font-size: 0.9rem;
    color: var(--text-color-muted);
}
.award-meta span {
    display: inline-flex; /* Căn icon và text */
    align-items: center;
}
.award-meta i.fa-fw {
    margin-right: 5px; /* Khoảng cách nhỏ sau icon */
    color: var(--link-color); /* Màu icon meta */
    width: 1em; /* Giảm chiều rộng icon */
}

.award-description {
    font-size: 1rem;
    color: var(--text-color-light);
    line-height: 1.6;
    margin-bottom: 15px;
    flex-grow: 1; /* Đẩy link xuống dưới nếu description ngắn */
}

.award-link {
    display: inline-block; /* Link dạng nút nhỏ */
    color: var(--link-color);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    margin-top: auto; /* Đẩy link xuống cuối */
    transition: color 0.2s ease;
}
.award-link:hover {
    color: var(--accent-color);
    text-decoration: underline;
}
.award-link i.fa-xs {
    margin-left: 4px;
}

/* --- Messages --- */
.no-awards-message {
    text-align: center;
    font-size: 1.1rem;
    color: var(--text-color-muted);
    padding: 40px 20px;
    background-color: var(--card-bg-color);
    border-radius: 8px;
}
.error-notice {
    /* ... style như các trang trước ... */
     color: #f8d7da; background-color: #721c24; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; text-align: center; margin-bottom: 30px;
}


/* --- Responsive Adjustments --- */
@media (max-width: 768px) {
    h1 { font-size: 2rem; }
    .page-subtitle { font-size: 1rem; }
    .award-card {
        flex-direction: column; /* Stack ảnh lên trên chi tiết */
        gap: 15px;
        padding: 20px;
    }
    .award-image-wrapper {
        width: 100px; /* Giảm kích thước ảnh trên mobile */
        height: 100px;
        margin: 0 auto 15px auto; /* Căn giữa ảnh */
    }
    .award-title { font-size: 1.4rem; text-align: center; }
    .award-meta { justify-content: center; /* Căn giữa meta */ }
    .award-description { font-size: 0.95rem; text-align: center; }
    .award-link { align-self: center; /* Căn giữa link */ }
}
    </style>
    <main class="awards-container">
        <h1><i class="fas fa-award"></i> Giải Thưởng & Thành Tựu</h1>
        <p class="page-subtitle">Những cột mốc và sự công nhận dành cho FoodNow</p>

        <div class="awards-list">

            <!-- === BẮT ĐẦU MỘT THÀNH TỰU (VÍ DỤ 1) === -->
            <div class="award-card">
                <div class="award-image-wrapper">
                    <!-- Thay 'images/awards/top-website-logo.png' bằng đường dẫn ảnh thật -->
                    <img src="images/awards/top-website-logo.png" alt="Top Website Ẩm thực" class="award-image">
                </div>
                <div class="award-details">
                    <h2 class="award-title">Top 5 Trang Web Ẩm Thực 2024</h2>
                    <div class="award-meta">
                        <span class="award-source">
                            <i class="fas fa-certificate fa-fw"></i> Tạp chí Cuisine Master
                        </span>
                        <span class="award-date">
                            <i class="fas fa-calendar-alt fa-fw"></i> 15/03/2024
                        </span>
                    </div>
                    <p class="award-description">FoodNow vinh dự được tạp chí Cuisine Master uy tín bình chọn vào Top 5 trang web chia sẻ công thức và kiến thức ẩm thực hàng đầu năm 2024 nhờ nội dung phong phú và cộng đồng sôi nổi.</p>
                    <!-- Thay link bằng link bài viết thật (nếu có) -->
                    <a href="#" class="award-link" target="_blank" rel="noopener noreferrer">
                        Đọc thêm <i class="fas fa-external-link-alt fa-xs"></i>
                    </a>
                </div>
            </div>
            <!-- === KẾT THÚC MỘT THÀNH TỰU === -->

            <!-- === BẮT ĐẦU MỘT THÀNH TỰU (VÍ DỤ 2) === -->
            <div class="award-card">
                <div class="award-image-wrapper">
                     <!-- Thay 'images/awards/community-choice.svg' bằng đường dẫn ảnh thật -->
                    <img src="images/awards/community-choice.svg" alt="Lựa chọn Cộng đồng" class="award-image">
                </div>
                <div class="award-details">
                    <h2 class="award-title">Giải thưởng "Lựa chọn của Cộng đồng"</h2>
                    <div class="award-meta">
                        <span class="award-source">
                            <i class="fas fa-users fa-fw"></i> Diễn đàn Yêu Bếp Việt
                        </span>
                        <span class="award-date">
                            <i class="fas fa-calendar-alt fa-fw"></i> 20/12/2023
                        </span>
                    </div>
                    <p class="award-description">Giải thưởng do chính cộng đồng người yêu bếp bình chọn, ghi nhận sự đóng góp tích cực của FoodNow trong việc kết nối và chia sẻ đam mê nấu nướng.</p>
                    <!-- Bỏ link nếu không có -->
                    <!-- <a href="#" class="award-link" target="_blank" rel="noopener noreferrer"> ... </a> -->
                </div>
            </div>
            <!-- === KẾT THÚC MỘT THÀNH TỰU === -->

            <!-- === BẮT ĐẦU MỘT THÀNH TỰU (VÍ DỤ 3 - Không có ảnh) === -->
            <div class="award-card">
                <!-- Bỏ phần award-image-wrapper nếu không có ảnh -->
                <div class="award-details" style="width: 100%;"> <!-- Thêm style để chiếm đủ rộng khi không có ảnh -->
                    <h2 class="award-title">Cán mốc 10,000 Công thức Chia sẻ</h2>
                    <div class="award-meta">
                        <!-- Có thể không cần nguồn cho cột mốc nội bộ -->
                        <span class="award-date">
                            <i class="fas fa-calendar-check fa-fw"></i> 01/11/2023
                        </span>
                    </div>
                    <p class="award-description">Một cột mốc đáng nhớ! Xin chân thành cảm ơn sự đóng góp và chia sẻ của toàn thể cộng đồng thành viên FoodNow.</p>
                </div>
            </div>
            <!-- === KẾT THÚC MỘT THÀNH TỰU === -->

             <!-- === THÊM CÁC THÀNH TỰU KHÁC TẠI ĐÂY === -->
             <!-- Copy và sửa đổi khối <div class="award-card">...</div> -->

        </div>

        <!-- Thông báo nếu danh sách trống (hiện tại sẽ không bao giờ trống nếu bạn thêm ít nhất 1 card) -->
        <!-- <p class="no-awards-message">Chúng tôi đang nỗ lực... </p> -->

    </main>

    <?php include 'parts/footer.php'; // Include your site footer ?>

</body>
</html>