<?php
    // You might still include header/navbar if they are static HTML parts
    // session_start(); // Probably not needed if no dynamic data
    include 'parts/header.php'; // Assuming this has basic HTML head, meta, CSS links
    include 'parts/navbar.php'; // Assuming this has the navigation menu
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cảm nhận</title>
    <!-- Link to your main CSS file (should be in header.php) -->
    <!-- <link rel="stylesheet" href="css/style.css"> -->
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- Base Styles (Adapt from your existing theme) --- */
        body {
            background-color: #212529; /* Dark background */
            color: #e9ecef; /* Light text */
            font-family: 'Montserrat', sans-serif; /* Assuming you use Poppins */
            margin: 0;
            padding: 0;
        }

        /* --- Container & Titles --- */
        main.feedback-display-container {
            max-width: 960px; /* Adjust max width */
            margin: 10rem auto 2rem auto; /* Adjust top margin based on your header height */
            padding: 1rem 1.5rem;
        }

        .page-title {
            text-align: center;
            font-size: 2.5em; /* Large title */
            font-weight: 700;
            color: #ffc107; /* Gold/Yellow accent */
            margin-bottom: 0.5rem;
            text-transform: uppercase;
        }

        .page-subtitle {
            text-align: center;
            font-size: 1.1em;
            color: #adb5bd; /* Lighter gray */
            margin-bottom: 3rem;
        }

        /* --- Feedback List --- */
        .feedback-list {
            list-style: none;
            padding: 0;
            margin: 0;
            position: relative; /* For potential timeline elements */
        }

        /* --- Individual Feedback Item Card --- */
        .feedback-item {
            background-color: #343a40; /* Slightly lighter dark card */
            border-radius: 8px;
            padding: 25px 30px;
            margin-bottom: 2.5rem; /* Space between cards */
            display: flex;
            gap: 25px; /* Space between image and text */
            align-items: flex-start; /* Align items to top */
            border-left: 4px solid #ffc107; /* Accent border like achievement */
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            position: relative;
        }

        /* Image/Avatar Section */
        .item-image {
            flex-shrink: 0; /* Prevent image from shrinking */
            width: 80px; /* Adjust size */
            height: 80px;
        }

        .item-image img {
            width: 100%;
            height: 100%;
            border-radius: 50%; /* Circular avatar */
            object-fit: cover;
            border: 2px solid #495057;
        }
         /* Placeholder icon if no image */
         .item-image .icon-placeholder {
             width: 100%;
             height: 100%;
             border-radius: 50%;
             background-color: #495057;
             display: flex;
             justify-content: center;
             align-items: center;
             font-size: 2.5em; /* Icon size */
             color: #6c757d;
         }


        /* Text Content Section */
        .item-content {
            flex-grow: 1; /* Allow text to take remaining space */
        }

        .item-content h3.item-name {
            margin: 0 0 5px 0;
            font-size: 1.4em;
            font-weight: 600;
            color: #f8f9fa; /* White name */
        }

        .item-meta {
            display: flex;
            align-items: center;
            gap: 15px; /* Space between rating and date */
            margin-bottom: 10px;
            font-size: 0.9em;
            color: #adb5bd;
        }

        .rating-stars {
            color: #ffc107; /* Filled star color */
            font-size: 1.1em; /* Slightly larger stars */
        }
        .rating-stars .fa-regular { /* Empty star */
            color: #6c757d; /* Darker gray for empty */
        }
        .rating-stars .no-rating {
             font-style: italic;
             color: #6c757d;
        }

        .item-date {
             /* Style for date if needed */
        }

        .item-text {
            margin: 0;
            line-height: 1.7;
            color: #ced4da; /* Lighter text color */
            font-size: 1em;
            white-space: pre-wrap; /* Respect line breaks */
            word-wrap: break-word;
        }

        /* --- Optional: Read More Link --- */
        .read-more-link {
            display: inline-block;
            margin-top: 15px;
            color: #ffc107;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        .read-more-link:hover {
            color: #e6a700; /* Darker gold */
        }

        /* --- Responsive --- */
         @media (max-width: 768px) {
            main.feedback-display-container { margin-top: 8rem; }
            .page-title { font-size: 2em; }
            .page-subtitle { font-size: 1em; }
            .feedback-item { padding: 20px; }
            .item-meta { flex-direction: column; align-items: flex-start; gap: 5px; }
         }
         @media (max-width: 576px) {
            .feedback-item { flex-direction: column; gap: 15px; align-items: center; text-align: center; }
            .item-image { width: 70px; height: 70px; margin-bottom: 10px;}
            .item-content { width: 100%; }
            .item-meta { align-items: center; justify-content: center;}
             .item-text { font-size: 0.95em; }
         }

    </style>
</head>
<body>

    <?php // Navbar included above ?>

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