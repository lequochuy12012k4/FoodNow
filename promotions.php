<?php
    // Start session if needed for other parts
    // session_start();

    // --- 1. Include DB Config & Connect ---
    require_once 'config/db_config.php'; // Adjust path if needed

    // Establish PDO connection
    $pdo = null;
    $db_error = '';
    try {
        $dsn = "mysql:host=$servername;dbname=$databaseName;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, $username, $password, $options);
    } catch (\PDOException $e) {
        $db_error = "Lỗi kết nối CSDL: Không thể tải danh sách khuyến mãi.";
        error_log("Database Connection Error in promotions.php: " . $e->getMessage());
    }

    // --- 2. Fetch ONLY Promoted Food Items ---
    $promo_foods = []; // Array to store foods with active promotions
    if ($pdo) {
        try {
            // SQL Query to select items with either a discount price OR a discount percent > 0
            $sql = "SELECT id, name, description, price, image, discount_price, discount_percent
                    FROM food_data
                    WHERE (discount_price IS NOT NULL AND discount_price > 0)
                       OR (discount_percent IS NOT NULL AND discount_percent > 0)
                    ORDER BY name ASC"; // Or order by discount type, etc.

            $stmt = $pdo->query($sql); // Execute the query
            $promo_foods = $stmt->fetchAll(); // Fetch all matching rows

        } catch (\PDOException $e) {
            $db_error = "Lỗi truy vấn danh sách khuyến mãi: " . $e->getMessage();
            error_log("Database Query Error (Promo Fetch) in promotions.php: " . $e->getMessage());
            $promo_foods = []; // Ensure array is empty on error
        }
    }

    // --- 3. Helper Function for Image Path (Same as before) ---
    $uploadDirWeb = 'uploads/';
    $placeholderImage = 'images/placeholder-food.png';

    function get_promo_image_path($foodName, $imageFilename, $uploadDir, $placeholder) {
        // Note: This simpler version doesn't need the imageMap anymore
        if (!empty($imageFilename)) {
            $fullPath = rtrim($uploadDir, '/') . '/' . $imageFilename;
             return htmlspecialchars($fullPath);
        }
        return htmlspecialchars($placeholder);
    }

    // Include static parts AFTER fetching data
    include 'parts/header.php';
    include 'parts/navbar.php';

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khuyến Mãi Hấp Dẫn - FoodNow</title>
    <!-- CSS Includes from header.php -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Paste the same CSS styles from the previous "card layout" example here */
        /* It defines the layout for .promo-card, .promotions-grid etc. */
         body { background-color: #212529; color: #e9ecef; font-family: 'Montserrat', sans-serif; margin: 0; padding: 0; }
        main.promotions-container { max-width: 1200px; margin: 10rem auto 2rem auto; padding: 1rem 1.5rem; }
        .page-title { text-align: center; font-size: 2.5em; font-weight: 700; color: #ffc107; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 1px; }
        .page-subtitle { text-align: center; font-size: 1.1em; color: #adb5bd; margin-bottom: 3rem; }
        .promotions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; }
        .promo-card { background-color: #343a40; border-radius: 8px; overflow: hidden; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3); display: flex; flex-direction: column; position: relative; transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; }
        .promo-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4); }
        .card-image { position: relative; width: 100%; height: 200px; overflow: hidden; background-color: #495057; }
        .card-image img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.3s ease; }
        .promo-card:hover .card-image img { transform: scale(1.05); }
        .discount-badge { position: absolute; top: 10px; left: 10px; background-color: #dc3545; color: #fff; padding: 5px 10px; font-size: 0.9em; font-weight: 600; border-radius: 4px; z-index: 1; }
        .card-content { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
        .card-name { font-size: 1.25em; font-weight: 600; color: #f8f9fa; margin: 0 0 10px 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .card-description { font-size: 0.9em; color: #ced4da; margin-bottom: 15px; line-height: 1.5; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; flex-grow: 1; }
        .card-price { margin-top: auto; margin-bottom: 15px; display: flex; align-items: baseline; gap: 10px; }
        .original-price { font-size: 0.95em; color: #adb5bd; text-decoration: line-through; }
        .discounted-price { font-size: 1.2em; font-weight: 700; color: #ffc107; }
        .card-actions { padding: 0 20px 20px 20px; }
        .add-to-cart-btn { display: block; width: 100%; padding: 10px 15px; background-color: #198754; color: #fff; text-align: center; border: none; border-radius: 5px; font-weight: 500; font-size: 1em; cursor: pointer; transition: background-color 0.2s ease; }
        .add-to-cart-btn i { margin-right: 8px; }
        .add-to-cart-btn:hover { background-color: #157347; }
        .db-error-message, .no-promotions-message { background-color: #343a40; color: #adb5bd; border: 1px dashed #495057; padding: 20px; margin-bottom: 20px; border-radius: 4px; text-align: center; font-size: 1em;}
        @media (max-width: 992px) { .promotions-grid { grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); } }
        @media (max-width: 768px) { main.promotions-container { margin-top: 8rem; } .page-title { font-size: 2em; } .promotions-grid { gap: 20px; } .card-name { font-size: 1.15em; } .discounted-price { font-size: 1.1em; } }
        @media (max-width: 576px) { main.promotions-container { padding: 1rem; } .promotions-grid { grid-template-columns: 1fr; } .card-image { height: 180px; } }
    </style>
</head>
<body>

    <?php // Navbar included above ?>

    <main class="promotions-container">
        <h1 class="page-title">Khuyến Mãi Hấp Dẫn</h1>
        <p class="page-subtitle">Đừng bỏ lỡ cơ hội thưởng thức những món ăn ngon với giá cực ưu đãi!</p>

        <!-- Display Database Error if Connection Failed -->
        <?php if ($db_error): ?>
            <p class="db-error-message"><?php echo htmlspecialchars($db_error); ?></p>
        <?php endif; ?>

        <div class="promotions-grid">

            <?php if (!$db_error && !empty($promo_foods)): ?>
                <?php foreach ($promo_foods as $food): ?>
                    <?php
                        // Calculate final price and badge text
                        $original_price = (float)($food['price'] ?? 0);
                        $discount_price = isset($food['discount_price']) ? (float)$food['discount_price'] : null;
                        $discount_percent = isset($food['discount_percent']) ? (int)$food['discount_percent'] : null;
                        $final_price = $original_price; // Default to original
                        $badge_text = '';

                        if ($discount_price !== null && $discount_price > 0) {
                            $final_price = $discount_price;
                            $discount_amount = $original_price - $discount_price;
                            if ($discount_amount > 0) {
                                $badge_text = '-' . number_format($discount_amount, 0, ',', '.') . 'đ';
                            }
                        } elseif ($discount_percent !== null && $discount_percent > 0) {
                            $final_price = $original_price * (1 - ($discount_percent / 100));
                            $badge_text = '-' . $discount_percent . '%';
                        } else {
                            // This case shouldn't happen due to SQL WHERE clause,
                            // but handle it just in case
                            continue; // Skip items that somehow got through without a valid discount
                        }

                        $image_path = get_promo_image_path($food['name'], $food['image'] ?? null, $uploadDirWeb, $placeholderImage);
                        $food_name = htmlspecialchars($food['name']);
                        $description = htmlspecialchars($food['description'] ?? 'Chưa có mô tả.'); // Add description if available
                        $food_id = htmlspecialchars($food['id']); // For Add to Cart button

                    ?>
                    <div class="promo-card">
                        <div class="card-image">
                            <img src="<?php echo $image_path; ?>" alt="<?php echo $food_name; ?>">
                            <?php if ($badge_text): ?>
                                <span class="discount-badge"><?php echo $badge_text; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="card-content">
                            <h3 class="card-name"><?php echo $food_name; ?></h3>
                            <p class="card-description"><?php echo $description; ?></p>
                             <div class="card-price">
                                <?php if ($final_price < $original_price): // Only show original if there's a discount ?>
                                    <span class="original-price"><?php echo number_format($original_price, 0, ',', '.'); ?>đ</span>
                                <?php endif; ?>
                                <span class="discounted-price"><?php echo number_format($final_price, 0, ',', '.'); ?>đ</span>
                            </div>
                        </div>
                        <div class="card-actions">
                             <!-- Add data attributes for JS cart functionality later -->
                             <button class="add-to-cart-btn"
                                     data-food-id="<?php echo $food_id; ?>"
                                     data-food-name="<?php echo $food_name; ?>"
                                     data-final-price="<?php echo $final_price; ?>">
                                 <i class="fas fa-cart-plus"></i> Thêm vào giỏ
                             </button>
                        </div>
                    </div>
                <?php endforeach; ?>

            <?php elseif (!$db_error): ?>
                <p class="no-promotions-message">Hiện tại chưa có món ăn nào đang được khuyến mãi. Vui lòng quay lại sau!</p>
            <?php endif; ?>
            <?php // DB error message is handled above the grid ?>

        </div> <!-- /.promotions-grid -->
    </main>

    <?php include 'parts/footer.php'; ?>

    <!-- JS Includes -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="js/script.js"></script> <!-- Your main site script -->
    <script>
        // Add JS specifically for this page, e.g., Add to Cart handling
        $(document).ready(function() {
            $('.add-to-cart-btn').on('click', function() {
                const foodId = $(this).data('food-id');
                const foodName = $(this).data('food-name');
                const finalPrice = $(this).data('final-price');
                const quantity = 1; // Default quantity

                console.log(`Adding to cart: ID=${foodId}, Name=${foodName}, Price=${finalPrice}, Qty=${quantity}`);

                // --- AJAX Call to Add to Cart ---
                // This requires a backend script (e.g., cart_action.php)
                $.ajax({
                    url: 'cart_action.php', // Your backend cart handler
                    type: 'POST',
                    data: {
                        action: 'add_item', // Define an action name
                        food_id: foodId,
                        quantity: quantity
                        // Price is usually determined server-side based on food_id
                        // to prevent manipulation, but you *could* send finalPrice
                        // if your backend verifies it. For now, sending only ID is safer.
                    },
                    dataType: 'json', // Expect JSON response { success: true/false, message: '...', cart_count: X }
                    success: function(response) {
                        if (response.success) {
                            // Update cart icon/count in navbar (requires navbar JS)
                            if (typeof updateCartCount === 'function') { // Check if function exists
                                updateCartCount(response.cart_count);
                            }
                            // Optionally show a brief success message near the button
                            alert(foodName + ' đã được thêm vào giỏ hàng!'); // Simple alert for now
                        } else {
                             alert('Lỗi thêm vào giỏ hàng: ' + (response.message || 'Vui lòng thử lại.'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Add to cart AJAX error:", status, error);
                        alert('Đã xảy ra lỗi kết nối. Không thể thêm vào giỏ hàng.');
                    }
                });
                // --- End AJAX Call ---
            });
        });
    </script>

</body>
</html>