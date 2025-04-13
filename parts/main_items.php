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