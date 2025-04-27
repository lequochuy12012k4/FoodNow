<?php
include 'parts/header.php';
?>
<link rel="shortcut icon" href="image/foodnow_icon.png" sizes="32x32" type="image/x-icon">
<title>FoodNow</title>
<body>
    <?php
    include 'parts/navbar.php';
    include 'parts/slider.php';
    ?>
    <section id="our-story" class="content-section story-layout">
        <div class="content-card">
            <div class="image-container">
                <img src="image/my_story.jpg" alt="Food cooking on a grill with steam">
            </div>
            <div class="text-container">
                <div class="section-subtitle">Discover</div>
                <h2>Our Story</h2>
                <p>
                    Get the best steakhouse experience at the Steakhouse. Whether you're joining us for a romantic
                    dinner, a business meeting, a private party or just a drink at the bar, our Kowloon steakhouse will
                    deliver superior service and an unforgettable dining experience.
                </p>
                <a href="#" class="more-link">More About Us →</a>

            </div>
        </div>
    </section>

    <section id="best-ingredients" class="content-section image-right">
        <div class="content-wrapper">
            <div class="image-container">
                <img src="image/img1.jpg" alt="Raw steak and fresh ingredients">
            </div>
            <div class="text-container">
                <div class="section-subtitle">Discover</div>
                <h2>The Best Ingredients</h2>
                <p>
                    We take an enormous amount of pride in sourcing our ingredients carefully to ensure that the flavors
                    of our food are as delicious and authentic as possible. We're only able to achieve this level of
                    excellence by putting an extra level of care into our menu items that is difficult to find at other
                    restaurants.
                </p>
            </div>
        </div>
    </section>

    <section id="menu-sample" class="content-section image-left dark-bg">
        <div class="content-wrapper menu-layout">
            <div class="image-container main-dish">
                <img src="image/img2.jpg" alt="Appetizer dish on white plate">
            </div>
            <div class="text-container menu-text">
                <div class="section-subtitle">Dinner</div>
                <h2>Our Menu</h2>
                <p>
                    Few things come close to the joy of steak and chips - cooked simply with tender, loving care. Rest
                    assured that our chefs treat our Irish beef with the respect it deserves. The open kitchens in many
                    of our steakhouses are testimony to that.
                </p>
                <div class="subsection">
                    <h3>Appetizer</h3>
                    <p>Start with our fresh baked bread with an egg and basil on top.</p>
                </div>
            </div>
            <div class="image-container secondary-dish">
                <img src="image/img1.jpg" alt="Grilled steak dish">
            </div>
        </div>
    </section>

    <section id="upcoming-events" class="content-section image-left">
        <div class="content-wrapper">
            <div class="image-container">
                <img src="image/img1.jpg" alt="People dining and socializing">
            </div>
            <div class="text-container">
                <div class="section-subtitle">Discover</div>
                <h2>Upcoming Events</h2>
                <p>
                    Not only can you get the best steak in town, you can gather up with your old friends while enjoying
                    the food we provide.
                </p>
                <div class="event-item">
                    <h4>Barbecue Party</h4>
                    <p>December 26 | Lunch Time | Casual</p>
                </div>
                <a href="#" class="more-link">More Events →</a>
            </div>
        </div>
    </section>

    <section id="reservations" class="content-section full-width-background">
        <div class="content-wrapper centered-content">
            <div class="text-container">
                <div class="section-subtitle">Reservation</div>
                <h2>Book Your Table</h2>
                <a href="#" class="button-primary">Online Booking</a>
            </div>
        </div>
        <div class="footer-info">
            <div class="info-block">
                <h4>Location</h4>
                <p>Start with our fresh sliced shrimps and ...</p>
                <p>1221 Roosevelt, NY</p>
            </div>
            <div class="info-block">
                <h4>Working Hours</h4>
                <p>Monday - Thursday: 10 am - 9 pm</p>
                <p>Friday: 4 pm - 11 pm</p>
                <p>Saturday - Sunday: 10 am - 11 pm</p>
            </div>
            <div class="info-block">
                <h4>Contact</h4>
                <p class="logo-font">Steakhouse</p>
                <p>info@kitchen.com</p>
            </div>
        </div>
    </section>

    <footer>
    <?php
    include 'parts/footer.php'
    ?>
    </footer>
</body>

</html>