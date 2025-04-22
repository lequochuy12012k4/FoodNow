<?php
session_start(); // Bắt đầu session nếu cần
require 'config/db_config.php'; // Kết nối CSDL

$recipe = null;
$error_message = '';
$image_folder = 'recipes/'; // Thư mục ảnh công thức

// Function để trích xuất ID YouTube từ URL
function get_youtube_id($url) {
    $youtube_id = null;
    $patterns = [
        '/youtu\.be\/([^\s&\?]+)/',             // youtu.be/{id}
        '/v=([^\s&\?]+)/',                      // ?v={id}
        '/embed\/([^\s&\?]+)/',                 // embed/{id}
        '/vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/([^\/]*)\/videos\/|album\/(\d+)\/video\/|)(\d+)/' // Basic Vimeo (optional)
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
             // Đối với YouTube, ID thường là group 1. Đối với Vimeo là group 3.
             if (strpos($url, 'youtu') !== false) {
                 $youtube_id = $matches[1];
                 break; // Tìm thấy ID YouTube, dừng lại
             }
             // Có thể thêm logic cho Vimeo hoặc các nền tảng khác tại đây
             // elseif (strpos($url, 'vimeo') !== false) {
             //    $vimeo_id = $matches[3]; // Vimeo ID
             //    // Bạn sẽ cần iframe embed code khác cho Vimeo
             //    break;
             // }
        }
    }
    return $youtube_id;
}


// --- Lấy Recipe ID từ URL và Fetch Data ---
$recipe_id = null;
if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $recipe_id = (int)$_GET['id'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM recipes WHERE id = ?");
        $stmt->execute([$recipe_id]);
        $recipe = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$recipe) {
            $error_message = "Công thức không tồn tại.";
        }

    } catch (PDOException $e) {
        $error_message = "Lỗi khi tải dữ liệu công thức.";
        // error_log("Recipe Fetch Error: " . $e->getMessage()); // Log lỗi chi tiết
    }

} else {
    $error_message = "ID công thức không hợp lệ.";
}

// --- Lấy các công thức liên quan (Ví dụ: 4 công thức ngẫu nhiên khác) ---
$related_recipes = [];
if ($recipe && empty($error_message)) { // Chỉ lấy liên quan nếu tìm thấy công thức chính
     try {
         $stmt_related = $pdo->prepare("
            SELECT id, title, image
            FROM recipes
            WHERE id != ? -- Loại trừ công thức hiện tại
            ORDER BY RAND() -- Lấy ngẫu nhiên (tùy chọn, có thể ORDER BY created_at DESC)
            LIMIT 4
         ");
         $stmt_related->execute([$recipe_id]);
         $related_recipes = $stmt_related->fetchAll(PDO::FETCH_ASSOC);
     } catch (PDOException $e) {
         // Lỗi lấy liên quan không nghiêm trọng, chỉ cần log
         error_log("Failed to fetch related recipes: " . $e->getMessage());
     }
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $recipe ? htmlspecialchars($recipe['title']) : 'Chi tiết công thức'; ?> - FoodNow</title>
    <link rel="stylesheet" href="style.css"> <!-- Your main stylesheet -->
    <link rel="stylesheet" href="recipe_style.css"> <!-- Specific styles for this page -->
</head>
<body>
    <?php include 'parts/header.php'; // Include your site header ?>

    <main class="recipe-detail-container">
        <?php if ($error_message): ?>
            <p class="error-notice"><?php echo htmlspecialchars($error_message); ?></p>
        <?php elseif ($recipe): ?>
            <div class="recipe-content-card">
                <h1 class="recipe-title"><?php echo htmlspecialchars($recipe['title']); ?></h1>

                <?php if (!empty($recipe['image'])): ?>
                    <div class="recipe-image-wrapper">
                        <img src="<?php echo $image_folder . htmlspecialchars($recipe['image']); ?>" alt="<?php echo htmlspecialchars($recipe['title']); ?>" class="recipe-image">
                    </div>
                <?php endif; ?>

                <?php if (!empty($recipe['description'])): ?>
                    <p class="recipe-description"><?php echo nl2br(htmlspecialchars($recipe['description'])); ?></p>
                <?php endif; ?>

                <!-- Ingredients Section -->
                <?php if (!empty($recipe['ingredients'])): ?>
                    <section class="recipe-section ingredients-section">
                        <h2 class="section-title">Nguyên liệu</h2>
                        <ul class="ingredients-list">
                            <?php
                            // Tách các nguyên liệu theo dòng mới và hiển thị dạng list
                            $ingredients_array = explode("\n", trim($recipe['ingredients']));
                            foreach ($ingredients_array as $ingredient):
                                $ingredient_trimmed = trim($ingredient);
                                if (!empty($ingredient_trimmed)):
                                    ?>
                                    <li><?php echo htmlspecialchars($ingredient_trimmed); ?></li>
                                    <?php
                                endif;
                            endforeach;
                            ?>
                        </ul>
                    </section>
                <?php endif; ?>

                <!-- Instructions Section -->
                 <?php if (!empty($recipe['instructions'])): ?>
                    <section class="recipe-section instructions-section">
                        <h2 class="section-title">Cách làm</h2>
                        <ol class="instructions-list">
                             <?php
                            // Tách các bước theo dòng mới và hiển thị dạng list có số thứ tự
                            $instructions_array = explode("\n", trim($recipe['instructions']));
                             foreach ($instructions_array as $instruction):
                                $instruction_trimmed = trim($instruction);
                                if (!empty($instruction_trimmed)):
                                    ?>
                                    <li><?php echo nl2br(htmlspecialchars($instruction_trimmed)); ?></li>
                                    <?php
                                endif;
                            endforeach;
                            ?>
                        </ol>
                    </section>
                <?php endif; ?>


                <!-- Video Section -->
                <?php
                $youtube_id = null;
                if (!empty($recipe['video_url'])) {
                     $youtube_id = get_youtube_id($recipe['video_url']);
                }
                ?>
                <?php if ($youtube_id): ?>
                    <section class="recipe-section video-section">
                        <h2 class="section-title">Video Hướng Dẫn</h2>
                        <div class="video-responsive">
                            <!-- Sử dụng iframe của YouTube Embed -->
                            <iframe src="https://www.youtube.com/embed/<?php echo htmlspecialchars($youtube_id); ?>"
                                    frameborder="0"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen></iframe>
                        </div>
                    </section>
                <?php elseif (!empty($recipe['video_url'])): ?>
                     <!-- Optional: Show original link if ID couldn't be extracted -->
                      <section class="recipe-section video-section">
                         <h2 class="section-title">Video Hướng Dẫn</h2>
                         <p>Link video: <a href="<?php echo htmlspecialchars($recipe['video_url']); ?>" target="_blank"><?php echo htmlspecialchars($recipe['video_url']); ?></a></p>
                      </section>
                <?php endif; ?>


            </div> <!-- End recipe-content-card -->

            <!-- Related Recipes Section -->
            <?php if (!empty($related_recipes)): ?>
                <section class="related-items">
                    <h2 class="related-title">Công Thức Khác</h2>
                    <div class="related-grid">
                        <?php foreach ($related_recipes as $related_recipe): ?>
                            <a href="recipe_detail.php?id=<?php echo $related_recipe['id']; ?>" class="related-item-card">
                                <?php if (!empty($related_recipe['image'])): ?>
                                    <img src="<?php echo $image_folder . htmlspecialchars($related_recipe['image']); ?>" alt="<?php echo htmlspecialchars($related_recipe['title']); ?>" class="related-item-image">
                                <?php endif; ?>
                                <h3 class="related-item-name"><?php echo htmlspecialchars($related_recipe['title']); ?></h3>
                                <!-- Có thể thêm mô tả ngắn hoặc thông tin khác tại đây -->
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

        <?php endif; // End check if $recipe exists ?>
    </main>

    <?php include 'parts/footer.php';?>

</body>
</html>