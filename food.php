<?php
include 'parts/header.php';
?>
<title>Đồ ăn</title>
<link rel="shortcut icon" href="image/foodnow_icon.png" sizes="32x32" type="image/x-icon">
<body>
    <?php
    include 'parts/navbar.php';
    include 'parts/slider.php';
    ?>

    <section id="food-section" class="food-section">
        <h2>Our Delicious Menu</h2>

        <!-- Tab Buttons Container -->
        <div class="food-category-tabs">
            <button class="tab-button active" data-category="all">Tất cả</button>
            <button class="tab-button" data-category="Món khai vị">Món khai vị</button>
            <button class="tab-button" data-category="Món chính">Món chính</button>
            <button class="tab-button" data-category="Tráng miệng">Tráng miệng</button>
            <button class="tab-button" data-category="Đồ ăn chay">Đồ ăn chay</button>
            <button class="tab-button" data-category="Nước uống">Nước uống</button>
            <button class="tab-button" data-category="Bánh ngọt">Bánh ngọt</button>
            <button class="tab-button" data-category="Trái cây">Trái cây</button>
            <button class="tab-button" data-category="Đồ ăn nhanh">Đồ ăn nhanh</button>
        </div>  
        <form action="" method="post">
            <div class="food-grid">
            <?php
            // --- PHP ĐỂ LẤY TẤT CẢ MÓN ĂN (KHÔNG THAY ĐỔI TỪ LẦN TRƯỚC) ---
            $servername = "localhost";
            $username = "root";
            $password = "";
            $databaseName = "foodnow";

            $conn = mysqli_connect($servername, $username, $password, $databaseName);

            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }

            $sql = "SELECT id, name, type, price, rate, description, image FROM food_data ORDER BY id DESC"; // Lấy tất cả
            $result = $conn->query($sql);

            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $category = !empty($row["type"]) ? htmlspecialchars($row["type"]) : 'main';
                    $detailPageLink = "food_detail.php?id=" . htmlspecialchars($row["id"]);
                    
                    echo "<div class='food-item' data-category='" . $category . "'>"; // Không cần class 'food-item-all' nữa
                    echo "<a href='" . $detailPageLink . "'>";
                    // --- IMAGE PATH CORRECTION (Giữ nguyên) ---
                    $imageFilename = $row["image"];
                    $placeholderPath = 'image/placeholder_food.png';
                    $imageDisplay = $placeholderPath;
                    if (!empty($imageFilename)) {
                        $webImagePath = 'uploads/' . $imageFilename;
                        $serverCheckPath = 'uploads/' . $imageFilename;
                        if (file_exists($serverCheckPath)) {
                            $imageDisplay = htmlspecialchars($webImagePath);
                        } else {
                             error_log("Image file not found on server: " . $serverCheckPath);
                        }
                    }
                    // --- END IMAGE PATH CORRECTION ---


                    echo "<img src='" . $imageDisplay . "' alt='" . htmlspecialchars($row["name"]) . "'>";
                    echo "<h3>" . htmlspecialchars($row["name"]) . "</h3>";
                    echo "<h5>" . htmlspecialchars($row["type"]) . "</h5>";
                    echo "<p class='food-description'>" . htmlspecialchars($row["description"]) . "</p>";
                    echo "<div class='food-details'>";
                    echo "<span class='food-price'>" . number_format($row['price']) . "đ</span>";

                    // Rating display (Giữ nguyên)
                    $ratingStars = '';
                    $rate = floatval($row["rate"]);
                    for ($i = 1; $i <= 5; $i++) {
                        if ($rate >= $i) $ratingStars .= '★';
                        elseif ($rate >= ($i - 0.7)) $ratingStars .= '⯪';
                        else $ratingStars .= '☆';
                    }
                    echo "<div class='food-rating'>" . $ratingStars . "</div>";
                    echo "</div>"; // end food-details
                    echo "<button type='button' class='order-button' onclick='window.location.href=\"" . $detailPageLink . "\"'>Xem chi tiết</button>";
                    echo "</a>";
                    echo "</div>"; // end food-item
                }
            } else {
                 if (!$result) {
                    echo "<p style='color: red;'>Error executing query: " . $conn->error . "</p>";
                 } else {
                    echo "<p>Hiện tại chưa có món ăn nào để hiển thị.</p>";
                 }
            }
            $conn->close();
            ?>
            </div>
        </form>
    </section>

    <footer>
        <?php
        include 'parts/footer.php'
        ?>
    </footer>

    <!-- === ADD/MODIFY JAVASCRIPT HERE === -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-button');
            const foodItems = document.querySelectorAll('.food-item'); // NodeList của tất cả item
            const itemsToShowInAllTab = 16; // <-- Số lượng món ăn ngẫu nhiên hiển thị ở tab "Tất cả"

            // --- Hàm Fisher-Yates (Knuth) Shuffle ---
            // Dùng để xáo trộn một mảng
            function shuffleArray(array) {
                for (let i = array.length - 1; i > 0; i--) {
                    // Chọn một index ngẫu nhiên từ 0 đến i
                    const j = Math.floor(Math.random() * (i + 1));
                    // Hoán đổi phần tử tại i và j
                    [array[i], array[j]] = [array[j], array[i]];
                }
            }

            function filterAndDisplayItems(selectedCategory) {
                if (selectedCategory === 'all') {
                    // 1. Chuyển NodeList thành Array để có thể shuffle
                    const allItemsArray = Array.from(foodItems);

                    // 2. Xáo trộn (shuffle) mảng các item
                    shuffleArray(allItemsArray);

                    // 3. Ẩn tất cả các item trước khi hiển thị những cái được chọn
                    foodItems.forEach(item => item.style.display = 'none');

                    // 4. Hiển thị số lượng item giới hạn từ mảng đã xáo trộn
                    const limit = Math.min(itemsToShowInAllTab, allItemsArray.length); // Đảm bảo không vượt quá số lượng item thực tế
                    for (let i = 0; i < limit; i++) {
                        // Sử dụng 'block' hoặc 'grid-item', 'flex-item' tùy thuộc vào CSS của bạn
                        allItemsArray[i].style.display = 'block';
                    }
                } else {
                    // Logic cho các category cụ thể (hiển thị tất cả các món khớp)
                    foodItems.forEach(item => {
                        const itemCategory = item.dataset.category;
                        if (itemCategory === selectedCategory) {
                            item.style.display = 'block'; // Hiển thị item khớp
                        } else {
                            item.style.display = 'none'; // Ẩn item không khớp
                        }
                    });
                }
            }

            // --- Event Listeners cho các Tab Button ---
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Bỏ active khỏi tất cả button, thêm active vào button được click
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    // Lấy category từ data-attribute
                    const selectedCategory = this.dataset.category;

                    // Lọc và hiển thị dựa trên category đã chọn
                    filterAndDisplayItems(selectedCategory);
                });
            });

            // --- Initial Load ---
            // Lọc và hiển thị NGẪU NHIÊN ban đầu cho tab "Tất cả" khi trang tải xong
            filterAndDisplayItems('all');
        });

        // Hàm chuyển hướng đến trang chi tiết (nếu nút là Xem chi tiết)
        // function viewDetails(foodId) {
        //     window.location.href = 'food_detail.php?id=' + foodId;
        // }
        // Lưu ý: Đã đổi nút thành onclick='window.location.href=...' trực tiếp trong PHP
    </script>
    <!-- === END JAVASCRIPT === -->

</body>
</html>