    document.addEventListener('DOMContentLoaded', () => {
    const nextButton = document.getElementById('next');
    const prevButton = document.getElementById('prev');
    const carouselList = document.querySelector('.carousel .list');
    const thumbnail = document.querySelector('.carousel .thumbnail');
    const progressBarFill = document.querySelector('.progress-fill');
    const currentSlideEl = document.querySelector('.current-slide');
    const totalSlidesEl = document.querySelector('.total-slides');
    const carousel = document.querySelector('.carousel');

    if (nextButton && prevButton && carouselList && thumbnail && progressBarFill && currentSlideEl && totalSlidesEl && carousel) {
        const listItems = carouselList.querySelectorAll('.item');
        const thumbnailItems = thumbnail.querySelectorAll('.item');
        let initialActiveIndex = 0;
        listItems.forEach((item, index) => {
            if (item.classList.contains('active')) { initialActiveIndex = index; }
        });
        let currentIndex = initialActiveIndex;
        const actualTotalSlides = listItems.length;

        if (actualTotalSlides === 0 || thumbnailItems.length === 0 || actualTotalSlides !== thumbnailItems.length) {
            console.error(`Mismatch or missing slides/thumbnails! List items: ${actualTotalSlides}, Thumbnails: ${thumbnailItems.length}`);
            if (totalSlidesEl && actualTotalSlides > 0) {
                totalSlidesEl.textContent = String(actualTotalSlides).padStart(2, '0');
            }
            if (actualTotalSlides !== thumbnailItems.length) {
                console.warn("Thumbnail count doesn't match slide count.");
            }
            if (actualTotalSlides === 0) return;
        }

        let autoPlayInterval;
        const autoPlayDelay = 2000;
        const transitionTime = 1000;

        function initializeSlider() {
            if (totalSlidesEl) totalSlidesEl.textContent = String(actualTotalSlides).padStart(2, '0');
            updateSlideCounter();
            updateProgressBar();
            setActiveClasses();
            startAutoPlay();
        }

        function updateSlideCounter() {
            if (currentSlideEl) currentSlideEl.textContent = String(currentIndex + 1).padStart(2, '0');
        }

        function updateProgressBar() {
            if (progressBarFill && actualTotalSlides > 0) {
                const progressPercentage = ((currentIndex + 1) / actualTotalSlides) * 100;
                progressBarFill.style.width = `${progressPercentage}%`;
            }
        }

        function setActiveClasses() {
            listItems.forEach((item, index) => item.classList.toggle('active', index === currentIndex));
            if (actualTotalSlides === thumbnailItems.length) {
                thumbnailItems.forEach((item, index) => item.classList.toggle('active', index === currentIndex));
                const activeThumbnail = thumbnailItems[currentIndex];
                if (activeThumbnail) {
                    const scrollLeft = activeThumbnail.offsetLeft + (activeThumbnail.offsetWidth / 2) - (thumbnail.offsetWidth / 2);
                    thumbnail.scrollTo({ left: scrollLeft, behavior: 'smooth' });
                }
            } else {
                thumbnailItems.forEach(item => item.classList.remove('active'));
            }
        }

        function showSlide(newIndex) {
            if (carouselList.classList.contains('processing') || actualTotalSlides === 0) return;
            carouselList.classList.add('processing');
            if (newIndex >= actualTotalSlides) { newIndex = 0; }
            else if (newIndex < 0) { newIndex = actualTotalSlides - 1; }
            currentIndex = newIndex;
            setActiveClasses();
            updateSlideCounter();
            updateProgressBar();
            setTimeout(() => carouselList.classList.remove('processing'), transitionTime);
            resetAutoPlay();
        }

        function startAutoPlay() {
            stopAutoPlay();
            if (actualTotalSlides > 1) {
                autoPlayInterval = setInterval(() => showSlide(currentIndex + 1), autoPlayDelay);
            }
        }

        function stopAutoPlay() { clearInterval(autoPlayInterval); }
        function resetAutoPlay() { stopAutoPlay(); startAutoPlay(); }

        nextButton.onclick = () => showSlide(currentIndex + 1);
        prevButton.onclick = () => showSlide(currentIndex - 1);

        if (actualTotalSlides === thumbnailItems.length) {
            thumbnailItems.forEach((thumb, index) => {
                thumb.addEventListener('click', () => { if (index !== currentIndex) { showSlide(index); } });
            });
        }

        carousel.addEventListener('mouseenter', stopAutoPlay);
        carousel.addEventListener('mouseleave', startAutoPlay);

        initializeSlider(); // Initialize Carousel

    } else {
        console.error("Essential carousel elements not found! Carousel functionality disabled.");
    }

    const foodSection = document.getElementById('food-section');
    function checkScroll() {
        if (!foodSection) return;
        const sectionTop = foodSection.getBoundingClientRect().top;
        const triggerPoint = window.innerHeight * 0.85;
        if (sectionTop < triggerPoint) {
            foodSection.classList.add('visible');
        }
    }
    window.addEventListener('scroll', checkScroll);
    checkScroll();

    const tabButtons = document.querySelectorAll('.food-category-tabs .tab-button');
    const foodItems = document.querySelectorAll('.food-grid .food-item');

    if (tabButtons.length > 0 && foodItems.length > 0) {
        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                // 1. Update active tab button
                tabButtons.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');

                // 2. Get selected category
                const selectedCategory = button.dataset.category; // Get 'data-category' value

                foodItems.forEach(item => {
                    const itemCategory = item.dataset.category;

                    // Check if item should be visible
                    const shouldShow = (selectedCategory === 'all' || itemCategory === selectedCategory);

                    // Apply styles (using classes for potential transitions)
                    if (shouldShow) {
                        // If using transitions, remove hidden first, then set display
                        item.classList.remove('hidden');
                        setTimeout(() => { item.style.display = 'flex'; }, 50); // Small delay
                        item.style.display = 'flex'; // Set display back to flex
                    } else {
                        item.classList.add('hidden');
                        setTimeout(() => { item.style.display = 'none'; }, 400);
                    }
                });
            });
        });

    } else {
        console.warn("Food tab buttons or food items not found. Filtering disabled.");
    }
    
    const contentSections = document.querySelectorAll('.content-section');

    if (contentSections.length > 0) {
        const revealSection = (entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    // Optional: Stop observing once revealed
                    observer.unobserve(entry.target);
                }
            });
        };

        const sectionObserver = new IntersectionObserver(revealSection, {
            root: null, // relative to the viewport
            threshold: 0.1, // Trigger when 10% of the section is visible
            rootMargin: '0px'
        });

        contentSections.forEach(section => {
            sectionObserver.observe(section);
        });
    } else {
        console.log("No '.content-section' elements found for scroll reveal.");
    }
    const accountMenu = document.querySelector('.account-menu');
    const accountTrigger = accountMenu?.querySelector('.account-trigger'); // Use optional chaining
    const dropdownContent = accountMenu?.querySelector('.dropdown-content');

    if (accountTrigger && dropdownContent) {
        accountTrigger.addEventListener('click', (event) => {
            event.stopPropagation(); // Prevent click from immediately closing dropdown
            dropdownContent.classList.toggle('show');
            accountMenu.classList.toggle('active'); // Toggle active class on parent
        });

        // Close dropdown if clicked outside
        window.addEventListener('click', (event) => {
            if (!accountMenu.contains(event.target)) {
                if (dropdownContent.classList.contains('show')) {
                    dropdownContent.classList.remove('show');
                    accountMenu.classList.remove('active');
                }
            }
        });

        // Optional: Close dropdown if Escape key is pressed
        window.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && dropdownContent.classList.contains('show')) {
                dropdownContent.classList.remove('show');
                accountMenu.classList.remove('active');
            }
        });

    } else {
        console.warn("Account dropdown elements not found.");
    }
    const navLinks = document.querySelectorAll('header nav a');

    navLinks.forEach(link => {
        if (link.href === window.location.href) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });

    const quantityInput = document.getElementById('quantity');
    const quantityButtons = document.querySelectorAll('.quantity-button');
    const orderNowButton = document.getElementById('order-now-button'); // Get the order button

    quantityButtons.forEach(button => {
        button.addEventListener('click', function() {
            const action = this.dataset.action;
            let currentValue = parseInt(quantityInput.value);

            if (action === 'increase') {
                quantityInput.value = currentValue + 1;
            } else if (action === 'decrease' && currentValue > 1) {
                quantityInput.value = currentValue - 1;
            }
        });
    });

    // Add event listener to the order button
    orderNowButton.addEventListener('click', function() {
        const quantity = parseInt(quantityInput.value); // Get the quantity

        // Do something with the quantity (e.g., display an alert)
        alert(`Bạn muốn mua ${quantity} phần!`);

        // OR, instead of the alert, you might do something like:
        // sendQuantityToServer(quantity);  // Call a function to send it to the server
        // OR
        //  let cart = getCartFromLocalStorage(); // Get cart data
        //  cart.push({ productId: 'pasta', quantity: quantity }); // Add the item
        //  saveCartToLocalStorage(cart); // Save the cart
    });

}); 


function sendQuantityToServer(quantity) {
    // Replace with your actual API endpoint and logic
    fetch('/api/add-to-cart', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            productId: 'pasta', // Or get the actual product ID from the page
            quantity: quantity
        })
    })
    .then(response => {
        if (response.ok) {
            console.log('Quantity sent to server');
        } else {
            console.error('Error sending quantity to server');
        }
    })
    .catch(error => {
        console.error('Network error:', error);
    });
}
$(function () {
    var availableFoods = [
        { label: "Spicy Tomato Pasta", value: "Spicy Tomato Pasta", image: "image/img1.jpg", price: "$14.50" },
        { label: "Cheeseburger Deluxe", value: "Cheeseburger Deluxe", image: "image/img2.jpg", price: "$14.50" },
        { label: "Grilled Steak", value: "Grilled Steak", image: "image/steak.jpg", price: "$14.50" },
        { label: "Sushi Platter", value: "Sushi Platter", image: "image/sushi.jpg", price: "$14.50" },
        { label: "Chocolate Cake", value: "Chocolate Cake", image: "image/chocolatecake.jpg", price: "$14.50" },
        { label: "Ice Cream Sundae", value: "Ice Cream Sundae", image: "image/icecream.jpg", price: "$14.50" },
        { label: "Fresh Lemonade", value: "Fresh Lemonade", image: "image/lemonade.jpg", price: "$14.50" },
        { label: "Iced Coffee", value: "Iced Coffee", image: "image/coffee.jpg", price: "$14.50" },
        { label: "Vanilla Cupcake", value: "Vanilla Cupcake", image: "image/cupcake.jpg", price: "$14.50" },
        { label: "Cheesecake", value: "Cheesecake", image: "image/cheesecake.jpg", price: "$14.50" },
        { label: "Strawberries", value: "Strawberries", image: "image/strawberries.jpg", price: "$14.50" },
        { label: "Watermelon", value: "Watermelon", image: "image/watermelon.jpg", price: "$14.50" },
        { label: "Fries", value: "Fries", image: "image/fries.jpg", price: "$14.50" },
        { label: "Salad", value: "Salad", image: "image/salad.jpg", price: "$14.50" }
    ];

    $("#searchfoods").autocomplete({
        source: availableFoods,
        select: function (event, ui) {
            var selectedFood = ui.item.value;
            if (selectedFood === "Spicy Tomato Pasta") {
                window.location.href = "pasta.html";
            } else if (selectedFood === "Cheeseburger Deluxe") {
                window.location.href = "cheeseburger.html";
            }
            else {
                alert("Bạn đã chọn: " + selectedFood);
            }
        }
    }).autocomplete("instance")._renderItem = function (ul, item) {
        return $("<li>")
            .append("<div style='background:none; border:none; color: black;font-weight: bold; display: flex; align-items: center;'>" +
                "<img src='" + item.image + "'style='width:50px; height:50px; margin-right:10px;'/>" +
                "<div style='display: flex; flex-direction: column;'>" +
                "<div>" + item.label + "</div>" +
                "<div style='font-weight: normal; font-style: italic; font-size: 1em;'>" + item.price + "</div>" +
                "</div>" +
                "</div>")
            .appendTo(ul);
    };
});