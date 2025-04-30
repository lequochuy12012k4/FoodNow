<?php
include 'parts/header.php';
?>
<title>Khuyến Mãi</title>
<link rel="shortcut icon" href="image/foodnow_icon.png" sizes="32x32" type="image/x-icon">
<body>
    <?php
    include 'parts/navbar.php';
    include 'parts/slider.php'; // Keep the slider if desired
    ?>

    <!-- Promotion Section 1: Sizzling Steak Deal -->
    <section id="promo-steak" class="content-section image-left">
        <div class="content-wrapper">
            <div class="image-container">
                <img src="uploads/food_6805128b9c918_Gado-gado.jpg" alt="Sizzling Ribeye Steak Special"> <?php // CHANGE IMAGE PATH ?>
            </div>
            <div class="text-container">
                <div class="section-subtitle">Limited Time Offer</div>
                <h2>Sizzling Ribeye Special</h2>
                <p>
                    Indulge in our perfectly grilled 12oz Ribeye steak, served with garlic mashed potatoes and seasonal vegetables. Cooked just the way you like it! A taste of luxury at a promotional price.
                </p>
                <p class="promo-price"><strong>Special Price: $24.99</strong> <del>(Reg. $29.99)</del></p> <?php // Added price ?>
                <a href="food_detail.php?id=10" class="button-primary">Order Now →</a> <?php // Example link, adjust as needed ?>

            </div>
        </div>
    </section>

    <!-- Promotion Section 2: Gourmet Burger Combo -->
    <section id="promo-burger" class="content-section image-right dark-bg"> <?php // Alternating image side and dark background ?>
        <div class="content-wrapper">
             <div class="image-container">
                <img src="uploads/food_6805118d2eefa_Sò huyết Tứ Xuyên.jpg" alt="Gourmet Burger Combo Deal"> <?php // CHANGE IMAGE PATH ?>
            </div>
            <div class="text-container">
                <div class="section-subtitle">Weekend Deal</div>
                <h2>Ultimate Steakhouse Burger Combo</h2>
                <p>
                    Sink your teeth into our signature 8oz beef patty, topped with aged cheddar, crispy bacon, caramelized onions, and our secret sauce, all on a brioche bun. Comes with a side of our famous crispy fries!
                </p>
                 <p class="promo-price"><strong>Combo Price: $15.95</strong><del>(Reg. $29.99)</del></p> <?php // Added price ?>
                <a href="food_detail.php?id=5" class="button-primary">Grab the Deal →</a> <?php // Example link, adjust as needed ?>
            </div>
        </div>
    </section>

    <!-- Promotion Section 3: Seafood Pasta Delight -->
    <section id="promo-pasta" class="content-section image-left"> <?php // Alternating image side ?>
        <div class="content-wrapper">
            <div class="image-container">
                <img src="uploads/food_68051177cc1ed_Càng cua bách hoa .jpg" alt="Seafood Pasta Promotion"> <?php // CHANGE IMAGE PATH ?>
            </div>
            <div class="text-container">
                <div class="section-subtitle">Chef's Recommendation</div>
                <h2>Creamy Seafood Linguine</h2>
                <p>
                   A delightful mix of fresh shrimp, mussels, and calamari tossed in a creamy white wine sauce with linguine pasta. Served with garlic bread. A perfect taste of the sea.
                </p>
                <p class="promo-price"><strong>Featured Dish: $19.50</strong><del>(Reg. $29.99)</del></p> <?php // Added price ?>
                <a href="food_detail.php?id=4" class="button-primary">View Details →</a> <?php // Example link and button style, adjust as needed ?>
            </div>
        </div>
    </section>

     <!-- Promotion Section 4: Appetizer Sampler Platter -->
    <section id="promo-appetizer" class="content-section image-right"> <?php // Alternating image side ?>
        <div class="content-wrapper">
             <div class="image-container">
                <img src="uploads/food_68051275f3de3_satay.jpg" alt="Appetizer Sampler Platter Offer"> <?php // CHANGE IMAGE PATH ?>
            </div>
            <div class="text-container">
                <div class="section-subtitle">Share & Enjoy</div>
                <h2>Appetizer Sampler Platter</h2>
                <p>
                    Perfect for sharing! Enjoy a selection of our most popular starters: crispy calamari, buffalo wings, mozzarella sticks, and loaded potato skins. Comes with dipping sauces.
                </p>
                 <p class="promo-price"><strong>Shareable Price: $18.00</strong><del>(Reg. $29.99)</del></p> <?php // Added price ?>
                <a href="food_detail.php?id=9" class="button-primary">Add to Order →</a> <?php // Example link, adjust as needed ?>
            </div>
        </div>
    </section>

    <!-- Optional: Call to Action Section -->
    <section id="view-menu" class="content-section full-width-background" style="background-image: url('image/img6.jpg');"> <?php // Keep or change background image ?>
        <div class="content-wrapper centered-content">
            <div class="text-container">
                <div class="section-subtitle">Explore More</div>
                <h2>Check Out Our Full Menu</h2>
                <p>Discover all the delicious dishes we have to offer, from classic steaks to fresh salads and decadent desserts.</p>
                <a href="food.php" class="button-primary">View Full Menu</a> <?php // Link to your main menu page ?>
            </div>
        </div>
        <?php /* Removed the footer-info blocks from here, they belong in the main footer */ ?>
    </section>

    <footer>
    <?php
    // Ensure your footer.php contains the necessary contact/location info
    include 'parts/footer.php';
    ?>
    </footer>
</body>

</html>