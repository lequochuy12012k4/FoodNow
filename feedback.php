<?php include 'parts/header.php' ?>
<link rel="stylesheet" href="css/feedback.css">
<title>Liên hệ</title>
<link rel="shortcut icon" href="image/foodnow_icon.png" sizes="32x32" type="image/x-icon">
<body>
    <?php include 'parts/navbar.php' ?>

    <main class="feedback-display-container">
        <h1 class="page-title">Phản hồi & Cảm nhận</h1>
        <p class="page-subtitle">Lắng nghe chia sẻ và đánh giá từ những khách hàng đã trải nghiệm FoodNow</p>

        <ul class="feedback-list">

            <!-- Feedback Item 1 -->
            <li class="feedback-item">
                <div class="item-image">
                    <!-- Use a placeholder image or icon -->
                    <!-- <img src="images/avatars/avatar1.png" alt="Avatar Trần Văn Mạnh"> -->
                     <div class="icon-placeholder"><i class="fas fa-user"></i></div>
                </div>
                <div class="item-content">
                    <h3 class="item-name">Trần Văn Mạnh</h3>
                    <div class="item-meta">
                        <span class="rating-stars" title="5/5">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </span>
                        <span class="item-date">27/10/2023</span>
                    </div>
                    <p class="item-text">
                        Phở bò ở đây ngon tuyệt vời! Nước dùng đậm đà, thịt bò mềm. Sẽ quay lại thường xuyên!
                    </p>
                     <!-- Optional Read More
                     <a href="#" class="read-more-link">Đọc thêm</a>
                     -->
                </div>
            </li>

            <!-- Feedback Item 2 -->
            <li class="feedback-item">
                <div class="item-image">
                     <div class="icon-placeholder"><i class="fas fa-user"></i></div>
                </div>
                <div class="item-content">
                    <h3 class="item-name">Nguyễn Thị Lan Anh</h3>
                    <div class="item-meta">
                        <span class="rating-stars" title="4/5">
                             <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="far fa-star"></i>
                        </span>
                        <span class="item-date">26/10/2023</span>
                    </div>
                    <p class="item-text">
                        Bún chả rất ổn, chả nướng thơm, nước chấm vừa miệng. Chỉ có điều đợi hơi lâu một chút vào giờ cao điểm. Mong quán cải thiện thời gian phục vụ.
                    </p>
                </div>
            </li>

            <!-- Feedback Item 3 (No Rating) -->
            <li class="feedback-item">
                <div class="item-image">
                    <div class="icon-placeholder"><i class="fas fa-comment-dots"></i></div> <!-- Different Icon -->
                </div>
                <div class="item-content">
                    <h3 class="item-name">Lê Hoàng Phúc</h3>
                    <div class="item-meta">
                        <span class="rating-stars"><span class="no-rating">(Không đánh giá)</span></span>
                        <span class="item-date">25/10/2023</span>
                    </div>
                    <p class="item-text">
                        Cơm rang dưa bò bình thường, hơi khô. Giá cả hợp lý. Giao hàng nhanh.
                    </p>
                </div>
            </li>

             <!-- Feedback Item 4 -->
             <li class="feedback-item">
                <div class="item-image">
                     <div class="icon-placeholder"><i class="fas fa-user"></i></div>
                </div>
                <div class="item-content">
                    <h3 class="item-name">Phạm Thu Hà</h3>
                    <div class="item-meta">
                        <span class="rating-stars" title="5/5">
                             <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </span>
                        <span class="item-date">23/10/2023</span>
                    </div>
                    <p class="item-text">
                        Gà rán giòn tan, không bị dầu mỡ nhiều. Rất thích hợp cho bữa trưa nhanh gọn. Sốt chấm cũng ngon nữa! Highly recommend!
                    </p>
                </div>
            </li>

            <!-- Add more static feedback items here as needed -->

        </ul>
    </main>

    <?php include 'parts/footer.php'; ?>

    <!-- Include jQuery if needed by other scripts -->
    <!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script> -->
    <!-- Your site's main JS -->
    <!-- <script src="js/script.js"></script> -->

</body>
</html>