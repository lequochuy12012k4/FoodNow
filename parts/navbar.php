<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<header>
    <a href="index.php">
        <div class="logo">FoodNow</div>
    </a>
    <nav>
        <a href="index.php" class="active">Trang chủ</a>
        <a href="food.php">Đồ ăn</a>
        <a href="#">Sách Công thức</a>
        <a href="achievements.php">Thành tựu</a>
        <a href="#">Cảm nhận</a>
        <a href="#">Liên hệ</a>
    </nav>
    <div class="header-icons">
        <span class="search-container">
            <input class="search" type="search" id="searchfoods" placeholder="search">
            <span class="search-icon">🔍</span>
            <?php
            require_once 'config/db_connect.php'; 
            $autocomplete_foods = [];
            $jsonData = '[]'; // Default

            if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
                $sql_autocomplete = "SELECT id, name, image, price FROM food_data ORDER BY name ASC";
                $result_autocomplete = mysqli_query($conn, $sql_autocomplete);
                if ($result_autocomplete && mysqli_num_rows($result_autocomplete) > 0) {
                    while ($row = mysqli_fetch_assoc($result_autocomplete)) {
                        $image_path = !empty($row['image']) ? 'uploads/' . htmlspecialchars($row['image']) : 'image/placeholder-food.png';
                        $autocomplete_foods[] = [
                            'label' => htmlspecialchars($row['name']),
                            'value' => htmlspecialchars($row['name']),
                            'image' => $image_path,
                            'price' => number_format($row['price'], 0, ',', '.') . ' VNĐ',
                            'url'   => 'food_detail.php?id=' . $row['id']
                        ];
                    }
                    mysqli_free_result($result_autocomplete);
                } else if (!$result_autocomplete) {
                    error_log("Autocomplete query failed: " . mysqli_error($conn));
                }
            } else {
                error_log("Database connection not available or failed for autocomplete header.");
            }
            $jsonData = json_encode($autocomplete_foods, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON Encode Error for autocomplete header: " . json_last_error_msg());
                $jsonData = '[]';
            }
            ?>
            <script>
                var availableFoodsFromPHP = <?php echo $jsonData; ?>;
            </script>
        </span>

        <?php ?>
        <?php if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
            <?php
            $displayName = $_SESSION['full_name'];
            ?>
            <div class="account-menu">
                <button class="account-trigger">
                    <span class="account-icon">👤</span>
                    <span class="account-text"><?php echo htmlspecialchars($displayName); ?></span>
                    <span class="dropdown-arrow">▼</span>
                </button>
                <div class="dropdown-content">
                    <a href="profile.php">Thông tin</a>
                    <a href="cart.php">Giỏ hàng</a>
                    <a href="logout.php">Đăng xuất</a>
                </div>
            </div>
        <?php else: ?>
            <div class="account-menu">
                <a href="login.php" class="header-link" style="color: white;">Đăng nhập</a>
                <span style="color: black;"> | </span> 
                <a href="register.php" class="header-link" style="color: white;">Đăng ký</a>
            </div>
        <?php endif; ?>

    </div>
</header>