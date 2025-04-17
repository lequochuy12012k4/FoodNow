<?php
include 'config/admin_config.php'; // Include database connection
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoodNow Admin - Quản lý Món ăn</title>
    <!-- Link to your CSS file -->
    <link rel="stylesheet" href="css/admin.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
</head>

<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="admin.php" class="logo">FoodNow Admin</a>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="#"><i class="fas fa-tachometer-alt"></i> <span>Tổng quan</span></a></li>
                    <!-- Make the current page active -->
                    <li class="active"><a href="admin.php"><i class="fas fa-utensils"></i> <span>Quản lý Món ăn</span></a></li>
                    <li><a href="#"><i class="fas fa-receipt"></i> <span>Quản lý Đơn hàng</span></a></li>
                    <li><a href="#"><i class="fas fa-users"></i> <span>Quản lý Người dùng</span></a></li>
                    <li><a href="#"><i class="fas fa-cog"></i> <span>Cài đặt</span></a></li>
                    <li><a href="#"><i class="fas fa-sign-out-alt"></i> <span>Đăng xuất</span></a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
        <header class="main-header">
    <div class="header-title">
        <button class="header-menu-toggle" aria-label="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>
        <h1>Quản lý Món ăn</h1>
    </div>
    <div class="header-user">
        <!-- **** ADD AN ID HERE **** -->
        <input type="search" id="admin-search-food" placeholder="Tìm kiếm món ăn..." autocomplete="off">
        <button class="search-btn"><i class="fas fa-search"></i></button>
        <div class="user-info">
            <img src="placeholder-avatar.png" alt="Admin Avatar" class="avatar">
            <span>Admin</span> <i class="fas fa-caret-down"></i>
            <div class="user-dropdown">
                <a href="#">Hồ sơ</a>
                <a href="#">Đăng xuất</a>
            </div>
        </div>
    </div>
</header>

            <section class="content-area">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($msg_type); ?>" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="dashboard-cards">
                    <div class="card">
                        <div class="card-icon"><i class="fas fa-utensils"></i></div>
                        <div class="card-info">
                            <h3>150</h3>
                            <p>Món ăn</p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-icon"><i class="fas fa-receipt"></i></div>
                        <div class="card-info">
                            <h3>58</h3>
                            <p>Đơn hàng hôm nay</p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-icon"><i class="fas fa-users"></i></div>
                        <div class="card-info">
                            <h3>1200</h3>
                            <p>Người dùng</p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-icon"><i class="fas fa-dollar-sign"></i></div>
                        <div class="card-info">
                            <h3>$5,678</h3>
                            <p>Doanh thu (Tháng)</p>
                        </div>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="data-table-container">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2>Danh sách Món ăn</h2>
                        <button class="btn btn-primary add-button" id="show-add-modal-btn">
                            <i class="fas fa-plus"></i> Thêm Món ăn
                        </button>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên món ăn</th>
                                <th>Loại</th>
                                <th>Giá (VNĐ)</th>
                                <th>Đánh giá</th>
                                <th style="text-align: center;">Ảnh</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody id="food-table-body">
                            <?php if (empty($foods)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding: 20px;">Chưa có món ăn nào.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($foods as $food): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($food['id']); ?></td>
                                        <td><?php echo htmlspecialchars($food['name']); ?></td>
                                        <td><?php echo htmlspecialchars($food['type']); ?></td>
                                        <td><?php echo number_format($food['price'], 0, ',', '.'); ?></td>
                                        <td style="text-align: center; white-space: nowrap; padding: 25px 0px;">
                                            <?php echo str_repeat('⭐', $food['rate']) . str_repeat('', 5 - $food['rate']); ?>
                                            (<?php echo htmlspecialchars($food['rate']); ?>)
                                        </td>
                                        <td style="text-align: center; vertical-align: middle;">
                                            <?php if (!empty($food['image']) && file_exists($uploadDir . $food['image'])): ?>
                                                <img src="<?php echo $uploadDir . htmlspecialchars($food['image']); ?>"
                                                    alt="<?php echo htmlspecialchars($food['name']); ?>"
                                                    class="table-food-image">
                                            <?php else: ?>
                                                <span style="font-size: 0.8em; color: #888;">(Chưa có ảnh)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="actions" style="white-space: nowrap;">
                                            <button class="btn btn-edit"
                                                onclick="openEditModal(<?php echo htmlspecialchars(json_encode($food), ENT_QUOTES, 'UTF-8'); ?>)">
                                                <i class="fas fa-edit"></i> Sửa
                                            </button>
                                            <a href="admin.php?action=delete&id=<?php echo $food['id']; ?>"
                                                class="btn btn-delete"
                                                onclick="return confirm('Bạn có chắc chắn muốn xóa món ăn: \'<?php echo htmlspecialchars(addslashes($food['name']), ENT_QUOTES); ?>\'?\nHành động này không thể hoàn tác.');">
                                                <i class="fas fa-trash-alt"></i> Xóa
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </section>
        </main>
    </div>

    <!-- Add/Edit Item Modal -->
    <div id="add-item-modal" class="modal">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <button class="modal-close" id="close-modal-btn">×</button>
            <h2 id="modal-title">Thêm Món ăn mới</h2>
            <div id="add-item-form-message" class="alert" style="display: none; margin-bottom: 15px;"></div>

            <form id="add-item-form" action="admin.php" method="post" enctype="multipart/form-data">
                <input type="hidden" id="form-action" name="action" value="add">
                <input type="hidden" id="edit-item-id" name="id" value="">
                <input type="hidden" id="current-image-filename" name="current_image" value="">


                <div class="form-group">
                    <label for="item-name">Tên món ăn:</label>
                    <input type="text" id="item-name" name="name" placeholder="Ví dụ: Phở Bò Tái" required>
                </div>

                <div class="form-group">
                    <label for="item-type">Loại món ăn:</label>
                    <select id="item-type" name="type" required>
                        <option value="" disabled selected>-- Chọn loại món ăn --</option>
                        <option value="Món khai vị">Món khai vị</option>
                        <option value="Món chính">Món chính</option>
                        <option value="Tráng miệng">Tráng miệng</option>
                        <option value="Nước uống">Nước uống</option>
                        <option value="Bánh ngọt">Bánh ngọt</option>
                        <option value="Đồ ăn nhanh">Đồ ăn nhanh</option>
                        <option value="Món chay">Món chay</option>
                        <option value="Trái cây">Trái cây</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="item-price">Giá (VNĐ):</label>
                    <input type="text" id="item-price" name="price" placeholder="Ví dụ: 50000" required inputmode="numeric" pattern="[0-9]*">
                </div>

                <div class="form-group">
                    <label for="item-rating">Đánh giá (0-5):</label>
                    <select id="item-rating" name="rate" required>
                        <option value="" disabled selected>-- Chọn đánh giá --</option>
                        <option value="5">5 ⭐⭐⭐⭐⭐</option>
                        <option value="4">4 ⭐⭐⭐⭐</option>
                        <option value="3">3 ⭐⭐⭐</option>
                        <option value="2">2 ⭐⭐</option>
                        <option value="1">1 ⭐</option>
                        <option value="0">0 (Chưa đánh giá)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="item-description">Miêu tả:</label>
                    <textarea id="item-description" name="description" placeholder="Miêu tả ngắn gọn về món ăn..."></textarea>
                </div>

                <div class="form-group">
                    <label for="item-image">Ảnh món ăn (Để trống nếu không đổi ảnh khi sửa):</label>
                    <input type="file" id="item-image" name="image" accept="image/png, image/jpeg, image/gif">
                    <div id="current-image-preview" style="margin-top: 10px; font-size: 0.9em; color: #555;"></div>
                </div>

                <button type="submit" class="btn btn-primary modal-submit-btn" id="modal-submit-button">
                    <i class="fas fa-check"></i> <span id="modal-submit-button-text">Thêm món ăn</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Link to your JS file -->
    <script src="js/admin.js"></script>

</body>

</html>