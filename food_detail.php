<?php
session_start(); // Start session if needed for cart/login status

// --- Database Configuration ---
$servername = "localhost";
$username = "root";
$password = "";
$databaseName = "foodnow"; // Make sure this is correct

// *** DEFINE CORRECT PATHS HERE ***
// IMPORTANT: Make sure these paths are correct relative to THIS PHP file
// or use absolute paths if necessary.
$uploadDir = 'uploads/';      // Path FROM this PHP file TO the uploads folder
$webUploadDir = 'uploads/'; // Path used in the <img src="..."> (relative to web root usually best)
$placeholderPath = 'image/placeholder-food.png'; // Path used in <img src="...">

// --- Include Header ---
include 'parts/header.php'; // Include header first

$food_id = null;
$food = null;
$related_foods = [];
$error_message = '';

// --- Get Food ID from URL ---
if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) { // Validate ID as integer
    $food_id = (int)$_GET['id'];
} else {
    $error_message = "Mã món ăn không hợp lệ.";
}

// --- Database Connection ---
$conn = mysqli_connect($servername, $username, $password, $databaseName);

// Check connection
if (!$conn) {
    error_log("Database Connection failed: " . mysqli_connect_error());
    // Use a generic message for the user, the specific error is logged
    $error_message = "Lỗi kết nối cơ sở dữ liệu. Vui lòng thử lại sau.";
} else {
    mysqli_set_charset($conn, "utf8mb4"); // Set charset

    // --- Fetch Food Details if ID is valid and connection is okay ---
    if ($food_id && empty($error_message)) {
        try {
            // *** DOUBLE-CHECK YOUR TABLE NAME HERE: 'foods' or 'food_data'? ***
            $tableName = 'food_data'; // CHANGE THIS if necessary

            $sql = "SELECT id, name, description, price, image, rate, type FROM {$tableName} WHERE id = ? LIMIT 1";
            $stmt = mysqli_prepare($conn, $sql);

            if ($stmt === false) {
                error_log("MySQLi prepare failed for main food '{$tableName}': " . mysqli_error($conn));
                throw new Exception("Lỗi chuẩn bị câu lệnh.");
            }

            mysqli_stmt_bind_param($stmt, "i", $food_id);

            if (!mysqli_stmt_execute($stmt)) {
                error_log("MySQLi execute failed for main food: " . mysqli_stmt_error($stmt));
                throw new Exception("Lỗi thực thi câu lệnh.");
            }

            $result = mysqli_stmt_get_result($stmt);
            $food = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt); // Close statement promptly

            if (!$food) {
                $error_message = "Không tìm thấy món ăn này (ID: " . htmlspecialchars($food_id) . ").";
                // Optional: Set 404 status code if item truly not found
                // http_response_code(404);
            } else {
                // --- Fetch Related Items (Only if main food was found) ---
                $current_type = $food['type'];
                $related_sql = "SELECT id, name, description, price, image, rate, type FROM {$tableName}
                WHERE type = ? AND id != ? -- This is the key part
                ORDER BY RAND()
                LIMIT 4";
                $related_stmt = mysqli_prepare($conn, $related_sql);
                if ($related_stmt) {
                    mysqli_stmt_bind_param($related_stmt, "si", $current_type, $food_id); // Binds the type and the ID to exclude
                    if (mysqli_stmt_execute($related_stmt)) {
                        $related_result = mysqli_stmt_get_result($related_stmt);
                        $related_foods = mysqli_fetch_all($related_result, MYSQLI_ASSOC); // Fetches the results
                    } else {
                        error_log("MySQLi execute failed for related items: " . mysqli_stmt_error($related_stmt));
                        // Don't halt execution, just means no related items will show
                    }
                    mysqli_stmt_close($related_stmt);
                } else {
                    error_log("MySQLi prepare failed for related items: " . mysqli_error($conn));
                }
            }
        } catch (Exception $e) {
            // Catch exceptions from prepare/execute failures
            $error_message = "Đã xảy ra lỗi khi tải chi tiết món ăn. Vui lòng thử lại sau.";
            // Specific error was already logged where it occurred
        }
    }
    // Close connection if it was successfully opened
    if ($conn) {
        mysqli_close($conn);
    }
}

/**
 * Helper function to generate rating stars
 * @param int $rating The numerical rating (e.g., 0-5)
 * @param int $maxRating The maximum possible rating (e.g., 5)
 * @return string HTML string of stars
 */
function generate_stars(int $rating, int $maxRating = 5): string
{
    $rating = max(0, min($maxRating, $rating)); // Clamp rating between 0 and maxRating
    $filledStars = str_repeat('★', $rating);
    $emptyStars = str_repeat('☆', $maxRating - $rating);
    return htmlspecialchars($filledStars . $emptyStars); // Use htmlspecialchars for safety
}

/**
 * Helper function to get image path, checking existence and using placeholder
 * @param ?string $imageFilename The image filename from DB (can be null/empty)
 * @param string $uploadDir Base directory where uploads are stored (relative to server filesystem)
 * @param string $webUploadDir Base directory for web path (used in src attribute)
 * @param string $placeholderPath Path to the placeholder image (used in src attribute)
 * @return string The final image path for the src attribute
 */
function get_image_path(?string $imageFilename, string $uploadDir, string $webUploadDir, string $placeholderPath): string
{
    if (!empty($imageFilename)) {
        // Check if file exists on the server *using the filesystem path*
        $fullFilePath = rtrim($uploadDir, '/') . '/' . $imageFilename;
        if (file_exists($fullFilePath) && is_file($fullFilePath)) {
            // Use the *web path* for the src attribute
            return rtrim($webUploadDir, '/') . '/' . htmlspecialchars($imageFilename);
        }
    }
    // Return placeholder if image is null, empty, or file doesn't exist
    return htmlspecialchars($placeholderPath);
}

?>

<body>
    <?php
    include 'parts/navbar.php';
    ?>
    <?php if (!empty($error_message)): ?>
        <section class="error-message">
            <p><?php echo htmlspecialchars($error_message); ?></p>
            <p><a href="index.php">Quay lại trang chủ</a></p> <!-- Link back home -->
        </section>
    <?php elseif ($food): // Only display if food was found and no other errors 
    ?>
        <section class="food-detail">
            <?php
            // Get the correct image path
            $imageSrc = get_image_path($food['image'], $uploadDir, $webUploadDir, $placeholderPath);
            ?>
            <img src="<?php echo $imageSrc; ?>" alt="<?php echo htmlspecialchars($food['name']); ?>">
            <h1><?php echo htmlspecialchars($food['name']); ?></h1>
            <!-- Format price - adjust currency symbol/formatting as needed -->
            <p class="price"><?php echo number_format($food['price'], 0, ',', '.'); ?> VNĐ</p>
            <p class="description"><?php echo nl2br(htmlspecialchars($food['description'])); // Use nl2br to respect newlines 
                                    ?></p>
            <div class="rating"><?php echo generate_stars($food['rate'] ?? 0); ?></div>
            <form action="cart_add.php" method="post" class="add-to-cart-form">
                <input type="hidden" name="food_id" value="<?php echo $food['id']; ?>">
                <div class="quantity-selector">
                    <label for="quantity-<?php echo $food['id']; ?>">Số lượng:</label>
                    <div class="quantity-controls">
                        <button type="button" class="quantity-button" data-action="decrease" data-target="quantity-<?php echo $food['id']; ?>">-</button>
                        <input type="number" id="quantity-<?php echo $food['id']; ?>" name="quantity" value="1" min="1" required>
                        <button type="button" class="quantity-button" data-action="increase" data-target="quantity-<?php echo $food['id']; ?>">+</button>
                    </div>
                </div>
                <button type="submit" class="order-button" data-food-id="<?php echo $food['id']; ?>">Thêm vào giỏ hàng</button>
            </form>
        </section>

        <?php if (!empty($related_foods)): ?>
            <section class="related-food">
                <h2>Món ăn liên quan</h2>
                <div class="food-grid related-food-grid">
                    <?php foreach ($related_foods as $related_item): ?>
                        <?php
                        // Get the correct image path for related item
                        $relatedImageSrc = get_image_path($related_item['image'], $uploadDir, $webUploadDir, $placeholderPath);
                        ?>
                        <div class="food-item" data-category="<?php echo htmlspecialchars($related_item['type'] ?? 'unknown'); ?>">
                            <a href="food_detail.php?id=<?php echo $related_item['id']; ?>">
                                <img src="<?php echo $relatedImageSrc; ?>" alt="<?php echo htmlspecialchars($related_item['name']); ?>">
                                <h3><?php echo htmlspecialchars($related_item['name']); ?></h3>
                                <p class="food-description"><?php echo htmlspecialchars(substr($related_item['description'], 0, 100)) . (strlen($related_item['description']) > 100 ? '...' : ''); ?></p>
                                <div class="food-details">
                                    <span class="food-price"><?php echo number_format($related_item['price'], 0, ',', '.'); ?> VNĐ</span>
                                    <div class="food-rating"><?php echo generate_stars($related_item['rate'] ?? 0); ?></div>
                                </div>
                                <button type="button" class="order-button related-order-button" onclick="window.location.href='food-detail.php?id=<?php echo $related_item['id']; ?>'">Xem chi tiết</button>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php else: ?>
            <section class="related-food">
                <h2>Món ăn liên quan</h2>
                <p>Không tìm thấy món ăn nào liên quan.</p>
            </section>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Include jQuery -->
    <footer>
        <?php
        include 'parts/footer.php'
        ?>
    </footer>
</body>

</html>