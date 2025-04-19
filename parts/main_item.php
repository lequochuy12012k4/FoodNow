<?php
// Assume this file is included where $conn (database connection) is available
// Assume you have fetched food items into an array called $foods

// Example fetching $foods if not already done:
// include 'config/db_config.php'; // Your DB connection file
// $sql = "SELECT id, name, description, price, image, rate, type FROM foods WHERE some_condition ORDER BY ..."; // Adjust query as needed
// $result = mysqli_query($conn, $sql);
// $foods = mysqli_fetch_all($result, MYSQLI_ASSOC);

$uploadDir = 'uploads/foods/'; // Make sure this matches your admin upload path

?>

<section class="food-menu">
    <h2>Our Menu</h2>
    <div class="filter-buttons">
        <button class="filter-button active" data-category="all">All</button>
        <button class="filter-button" data-category="Món chính">Main Course</button>
        <button class="filter-button" data-category="Tráng miệng">Dessert</button>
        <button class="filter-button" data-category="Nước uống">Drinks</button>
        <!-- Add more categories as needed -->
    </div>

    <div class="food-grid" id="food-grid">
        <?php if (empty($foods)): ?>
            <p>No food items found.</p>
        <?php else: ?>
            <?php foreach ($foods as $food): ?>
                <?php
                    // Determine image path with placeholder fallback
                    $imagePath = $uploadDir . ($food['image'] ?? '');
                    $imageUrl = (!empty($food['image']) && file_exists($imagePath)) ? $imagePath : 'image/placeholder-food.png'; // Default placeholder
                ?>
                <div class="food-item" data-category="<?php echo htmlspecialchars($food['type'] ?? 'Unknown'); ?>">
                    <!-- **** THIS IS THE IMPORTANT CHANGE **** -->
                    <a href="food_detail.php?id=<?php echo htmlspecialchars($food['id']); ?>" class="food-item-link">
                        <img src="<?php echo htmlspecialchars($imageUrl); ?>"
                             alt="<?php echo htmlspecialchars($food['name']); ?>"
                             onerror="this.onerror=null; this.src='image/placeholder-food.png';"> <!-- Fallback if image fails -->
                        <h3><?php echo htmlspecialchars($food['name']); ?></h3>
                        <p class="food-description"><?php echo htmlspecialchars(substr($food['description'] ?? '', 0, 80)) . (strlen($food['description'] ?? '') > 80 ? '...' : ''); // Truncate description ?></p>
                        <div class="food-details">
                            <span class="food-price"><?php echo number_format($food['price'] ?? 0, 0, ',', '.'); ?> VNĐ</span>
                            <div class="food-rating">
                                <?php echo str_repeat('⭐', (int)($food['rate'] ?? 0)); ?><?php echo str_repeat('☆', 5 - (int)($food['rate'] ?? 0)); ?>
                            </div>
                        </div>
                         <!-- You might remove the order button here if the link takes them to the detail page -->
                         <!-- <button class="order-button">Order Now</button> -->
                         <span class="view-details-prompt">View Details</span> <!-- Optional prompt -->
                    </a>
                     <!-- Keep the separate order button if you want direct ordering from grid -->
                    <button class="order-button add-to-cart-button" data-id="<?php echo htmlspecialchars($food['id']); ?>" data-name="<?php echo htmlspecialchars($food['name']); ?>" data-price="<?php echo htmlspecialchars($food['price']); ?>">Order Now</button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>