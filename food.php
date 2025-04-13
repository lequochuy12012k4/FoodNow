<?php
include 'parts/header.php'
?>

<body>
    <?php
    include 'parts/main_items.php';
    include 'parts/slider.php'
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
            $servername = "localhost";
            $username = "root";
            $password = "";
            $databaseName = "foodnow";

            // Create connection
            $conn = mysqli_connect($servername, $username, $password, $databaseName);

            // Check connection
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }

            // --- MODIFICATION START ---
            // Define how many items you want to display
            $numberOfItemsToShow = 8; // <--- CHANGE THIS NUMBER AS NEEDED

            // SQL query to retrieve data from the food_data table WITH A LIMIT
            // You might also want to add ORDER BY to control *which* items are shown
            // e.g., ORDER BY id DESC (newest first) or ORDER BY rate DESC (highest rated first)
            $sql = "SELECT id, name,type, price, rate, description, image FROM food_data ORDER BY id DESC LIMIT " . $numberOfItemsToShow;
            // --- MODIFICATION END ---

            $result = $conn->query($sql);

            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $category = !empty($row["type"]) ? htmlspecialchars($row["type"]) : 'main';
                    $detailPageLink = "food_detail.php?id=" . htmlspecialchars($row["id"]);
    
                    echo "<div class='food-item' data-category='" . $category . "'>";
    
                    // --- IMAGE PATH CORRECTION ---
                    $imageFilename = $row["image"]; // Get filename from DB (e.g., "my_image.jpg")
                    $placeholderPath = 'image/placeholder_food.png'; // Define placeholder path
                    $imageDisplay = $placeholderPath; // Default to placeholder
    
                    if (!empty($imageFilename)) {
                        // Construct the path RELATIVE TO THE WEB ROOT or THIS SCRIPT
                        // Assuming 'uploads/' is a folder accessible by the browser from the same level as this script or from the root
                        $webImagePath = 'uploads/' . $imageFilename; // e.g., "uploads/my_image.jpg"
    
                        // Construct the path for the SERVER-SIDE file_exists check
                        // This path might be the same as $webImagePath if 'uploads' is relative to this script
                        // If 'uploads' is elsewhere, adjust this path accordingly.
                        // Example: $serverCheckPath = __DIR__ . '/uploads/' . $imageFilename; // If uploads is next to this script
                        // Example: $serverCheckPath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $imageFilename; // If uploads is in the web root
                        $serverCheckPath = 'uploads/' . $imageFilename; // Assuming uploads is relative to this script
    
                        // Check if the actual file exists on the server
                        if (file_exists($serverCheckPath)) {
                            $imageDisplay = htmlspecialchars($webImagePath); // Use the correct web path
                        } else {
                             // Optional: Log an error if the file is expected but not found
                             error_log("Image file not found on server: " . $serverCheckPath);
                             // Keep $imageDisplay as the placeholder
                        }
                    }
                    // --- END IMAGE PATH CORRECTION ---
    
                    echo "<a href='" . $detailPageLink . "'>";
                    // Use the determined $imageDisplay path
                    echo "<img src='" . $imageDisplay . "' alt='" . htmlspecialchars($row["name"]) . "'>";
                    echo "<h3>" . htmlspecialchars($row["name"]) . "</h3>";
                    echo "<h5>" . htmlspecialchars($row["type"]) . "</h5>";
                    echo "<p class='food-description'>" . htmlspecialchars($row["description"]) . "</p>";
                    echo "<div class='food-details'>";
                    echo "<span class='food-price'>" . number_format($row['price']) . "đ</span>";
    
                    // Rating display (Keep as is)
                    $ratingStars = '';
                    $rate = floatval($row["rate"]);
                    for ($i = 1; $i <= 5; $i++) {
                        if ($rate >= $i) $ratingStars .= '★';
                        elseif ($rate >= ($i - 0.7)) $ratingStars .= '⯪';
                        else $ratingStars .= '☆';
                    }
                    echo "<div class='food-rating'>" . $ratingStars . "</div>";
    
                    echo "</div>"; // end food-details
                    echo "<button type='button' class='order-button' onclick='addToCart(" . htmlspecialchars($row["id"]) . ")'>Order Now</button>";
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
    
            // Close connection
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
</body>
</html>