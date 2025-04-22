<?php
// config/db_config.php
$servername = "localhost";
$username = "root";
$password = "";
$databaseName = "foodnow";
$foodTableName = 'food_data';
$orderTableName = 'orders'; // Your existing orders table name
$charset = 'utf8mb4';

$dsn = "mysql:host=$servername;dbname=$databaseName;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Báo lỗi dạng Exception
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Trả về dạng mảng associative
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Sử dụng native prepared statements
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (\PDOException $e) {
    // Trong môi trường production, bạn nên log lỗi thay vì hiển thị ra màn hình
    // die("Không thể kết nối đến cơ sở dữ liệu. Vui lòng thử lại sau.");
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>