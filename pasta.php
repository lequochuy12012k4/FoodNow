<?php
include 'parts/header.php'
?>
<body>
    <?php
    include 'parts/main_items.php'
    ?>
    
    <section class="food-detail">
        <img src="image/img1.jpg" alt="Spicy Tomato Pasta">
        <h1>Spicy Tomato Pasta</h1>
        <p class="price">$12.99</p>
        <p class="description">A classic pasta dish with a rich tomato sauce, fresh basil, and a hint of chili spice.</p>
        <div class="rating">★★★★☆</div>
        <div class="quantity-selector">
            <label for="quantity">Số lượng:</label>
            <div class="quantity-controls">
                <button class="quantity-button" data-action="decrease">-</button>
                <input type="number" id="quantity" name="quantity" value="1" min="1">
                <button class="quantity-button" data-action="increase">+</button>
            </div>
        </div>
        <button class="order-button" id="order-now-button">Order Now</button>
    </section>
    
    <section class="related-food">
        <h2>You Might Also Like</h2>
        <div class="food-grid related-food-grid"> <!-- Thêm class 'related-food-grid' -->
            <div class="food-item" data-category="main">
                <a href="pasta.html">
                    <img src="image/img1.jpg"
                        alt="Spicy Tomato Pasta">
                    <h3>Spicy Tomato Pasta</h3>
                    <p class="food-description">A classic pasta dish with a rich tomato sauce, fresh basil, and a hint
                        of chili spice.</p>
                    <div class="food-details">
                        <span class="food-price">$12.99</span>
                        <div class="food-rating">★★★★☆</div>
                    </div>
                    <button class="order-button">Order Now</button>
                </a>
            </div>
            <div class="food-item" data-category="main">
                <a href="pasta.html">
                    <img src="image/img1.jpg"
                        alt="Spicy Tomato Pasta">
                    <h3>Spicy Tomato Pasta</h3>
                    <p class="food-description">A classic pasta dish with a rich tomato sauce, fresh basil, and a hint
                        of chili spice.</p>
                    <div class="food-details">
                        <span class="food-price">$12.99</span>
                        <div class="food-rating">★★★★☆</div>
                    </div>
                    <button class="order-button">Order Now</button>
                </a>
            </div>
            <div class="food-item" data-category="main">
                <a href="pasta.html">
                    <img src="image/img1.jpg"
                        alt="Spicy Tomato Pasta">
                    <h3>Spicy Tomato Pasta</h3>
                    <p class="food-description">A classic pasta dish with a rich tomato sauce, fresh basil, and a hint
                        of chili spice.</p>
                    <div class="food-details">
                        <span class="food-price">$12.99</span>
                        <div class="food-rating">★★★★☆</div>
                    </div>
                    <button class="order-button">Order Now</button>
                </a>
            </div>
            <div class="food-item" data-category="main">
                <a href="pasta.html">
                    <img src="image/img1.jpg"
                        alt="Spicy Tomato Pasta">
                    <h3>Spicy Tomato Pasta</h3>
                    <p class="food-description">A classic pasta dish with a rich tomato sauce, fresh basil, and a hint
                        of chili spice.</p>
                    <div class="food-details">
                        <span class="food-price">$12.99</span>
                        <div class="food-rating">★★★★☆</div>
                    </div>
                    <button class="order-button">Order Now</button>
                </a>
            </div>
            <div class="food-item" data-category="main">
                <a href="pasta.html">
                    <img src="image/img1.jpg"
                        alt="Spicy Tomato Pasta">
                    <h3>Spicy Tomato Pasta</h3>
                    <p class="food-description">A classic pasta dish with a rich tomato sauce, fresh basil, and a hint
                        of chili spice.</p>
                    <div class="food-details">
                        <span class="food-price">$12.99</span>
                        <div class="food-rating">★★★★☆</div>
                    </div>
                    <button class="order-button">Order Now</button>
                </a>
            </div>
            <div class="food-item" data-category="main">
                <a href="pasta.html">
                    <img src="image/img1.jpg"
                        alt="Spicy Tomato Pasta">
                    <h3>Spicy Tomato Pasta</h3>
                    <p class="food-description">A classic pasta dish with a rich tomato sauce, fresh basil, and a hint
                        of chili spice.</p>
                    <div class="food-details">
                        <span class="food-price">$12.99</span>
                        <div class="food-rating">★★★★☆</div>
                    </div>
                    <button class="order-button">Order Now</button>
                </a>
            </div>
            <div class="food-item" data-category="main">
                <a href="pasta.html">
                    <img src="image/img1.jpg"
                        alt="Spicy Tomato Pasta">
                    <h3>Spicy Tomato Pasta</h3>
                    <p class="food-description">A classic pasta dish with a rich tomato sauce, fresh basil, and a hint
                        of chili spice.</p>
                    <div class="food-details">
                        <span class="food-price">$12.99</span>
                        <div class="food-rating">★★★★☆</div>
                    </div>
                    <button class="order-button">Order Now</button>
                </a>
            </div>
            <!-- Thêm các food-item khác vào đây -->
            <div class="food-item" data-category="main">
                <a href="pasta.html">
                    <img src="image/img1.jpg"
                        alt="Spicy Tomato Pasta">
                    <h3>Spicy Tomato Pasta</h3>
                    <p class="food-description">A classic pasta dish with a rich tomato sauce, fresh basil, and a hint
                        of chili spice.</p>
                    <div class="food-details">
                        <span class="food-price">$12.99</span>
                        <div class="food-rating">★★★★☆</div>
                    </div>
                    <button class="order-button">Order Now</button>
                </a>
            </div>
        </div>
    </section>

    <!-- Include jQuery -->
    <footer>
    <?php
    include 'parts/footer.php'
    ?>
    </footer>
</body>

</html>