<header>
    <a href="index.php">
        <div class="logo">FoodNow</div>
    </a>
    <nav>
        <a href="index.php" class="active">Trang chủ</a>
        <a href="food.php">Đồ ăn</a>
        <a href="#">Khuyến Mãi</a>
        <a href="#">Chi nhánh</a>
        <a href="#">Cảm nhận</a>
        <a href="#">Liên hệ</a>
    </nav>
    <div class="header-icons">
        <span class="search-container">
            <input class="search" type="search" id="searchfoods" placeholder="search">
            <span class="search-icon">🔍</span>
            <?php
            // --- PHP Data Fetching for Autocomplete ---
            session_start();
            require_once 'config\db_connect.php'; 
            echo "<!-- DEBUG: Starting autocomplete PHP block -->";

            $autocomplete_foods = [];
            $jsonData = '[]'; // Default to empty array string

            // Check Connection FIRST
            if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
                echo "<!-- DEBUG: DB Connection OK -->";

                $sql_autocomplete = "SELECT id, name, image, price FROM food_data ORDER BY name ASC";
                $result_autocomplete = mysqli_query($conn, $sql_autocomplete);

                if ($result_autocomplete) {
                    echo "<!-- DEBUG: SQL Query Success -->";
                    $rowCount = mysqli_num_rows($result_autocomplete);
                    echo "<!-- DEBUG: Rows Found: " . $rowCount . " -->";

                    if ($rowCount > 0) {
                        while ($row = mysqli_fetch_assoc($result_autocomplete)) {
                            $image_path = !empty($row['image']) ? 'uploads/' . htmlspecialchars($row['image']) : 'image/placeholder-food.png';
                            // Quick check if file exists (optional, can slow down slightly)
                            // if (!file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $image_path)) {
                            //     $image_path = 'image/placeholder-food.png'; // Fallback if file missing
                            // }

                            $autocomplete_foods[] = [
                                'label' => htmlspecialchars($row['name']), // Used for display
                                'value' => htmlspecialchars($row['name']), // Used for input value on select (can be same as label)
                                'image' => $image_path,                    // Image path
                                'price' => number_format($row['price'], 0, ',', '.').' VNĐ', // Formatted price
                                'url'   => 'food_detail.php?id=' . $row['id'] // URL for redirection
                            ];
                        }
                    }
                    mysqli_free_result($result_autocomplete);
                } else {
                    echo "<!-- DEBUG: SQL Query FAILED: " . mysqli_error($conn) . " -->";
                    error_log("Autocomplete query failed: " . mysqli_error($conn));
                }
            } else {
                echo "<!-- DEBUG: DB Connection FAILED or NOT AVAILABLE -->";
                error_log("Database connection not available or failed for autocomplete.");
            }

            // --- Check JSON Encoding ---
            // Ensure UTF-8 encoding if necessary, especially for names
            $jsonData = json_encode($autocomplete_foods, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo "<!-- DEBUG: JSON Encode Error: " . json_last_error_msg() . " -->";
                error_log("JSON Encode Error for autocomplete: " . json_last_error_msg());
                $jsonData = '[]'; // Default to empty array on error
            } else {
                echo "<!-- DEBUG: JSON Encoding OK. Items: " . count($autocomplete_foods) . " -->";
            }

            echo "<!-- DEBUG: End autocomplete PHP block -->";
            ?>

            <!-- Define the JavaScript variable *inline* -->
            <script>
                var availableFoodsFromPHP = <?php echo $jsonData; ?>;
                // Log to browser console immediately after definition
                console.log('DEBUG: availableFoodsFromPHP defined in HTML:', availableFoodsFromPHP);
            </script>
        </span>
        <?php if (isset($_SESSION['user_name'])): ?>
            <div class="account-menu">
                <button class="account-trigger">
                    <span class="account-icon">👤</span>
                    <span class="account-text">My Account</span>
                    <span class="dropdown-arrow">▼</span>
                </button>
                <div class="dropdown-content">
                    <a href="#">Thông tin</a>
                    <a href="#">Giỏ hàng</a>
                    <a href="#">Đăng xuất</a>
                </div>
            </div>
        <?php else: ?>
            <div class="account-menu">
                <button class="account-trigger">
                    <span class="account-text"><a href="login.php">Đăng nhập</a></span>
                    <span class="account-text"><a href="register.php">Đăng ký</a></span>
                </button>

            </div>
        <?php endif; ?>
    </div>
</header>