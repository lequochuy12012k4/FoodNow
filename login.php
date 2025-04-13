<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoodNow - Đăng ký</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 0;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            display: flex;
            width: 90%;
            max-width: 1000px;
        }

        .image-container {
            flex: 0 0 45%; /* Chiếm 45% chiều rộng */
        }

        .image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .form-container {
            flex: 1; /* Chiếm phần còn lại */
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: stretch; /* Kéo dài các phần tử con theo chiều ngang */
        }

        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-size: 2.5em;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: bold;
            font-size: 1.1em;
        }

        input[type="text"],
        input[type="password"],
        input[type="email"] {
            width: calc(100% - 24px);
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 1em;
        }

        button {
            background-color: #ff6b6b;
            color: white;
            padding: 14px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1.2em;
            transition: background-color 0.3s ease;
            margin-top: 30px;
        }

        button:hover {
            background-color: #e65a5a;
        }

        .form-footer {
            margin-top: 30px;
            text-align: center;
            color: #777;
            font-size: 0.9em;
        }

        .form-footer a {
            color: #ff6b6b;
            text-decoration: none;
            font-weight: bold;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        /* Tiêu đề FoodNow */
        .foodnow-title {
            text-align: center;
            color: #ff6b6b;
            font-size: 3em;
            font-weight: bold;
            margin-bottom: 20px;
        }

        /* Ẩn tiêu đề form (chúng ta đã có tiêu đề FoodNow lớn hơn) */
        .form-container h2 {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="image-container">
            <img src="image/img1.jpg" alt="FoodNow Image">
        </div>
        <div class="form-container">
            <a href="index.php" style="text-decoration: none;"><h1 class="foodnow-title">FoodNow</h1></a>
            <form action="#" method="post">
                <div class="form-group">
                    <label for="new_username">Tên đăng nhập:</label>
                    <input type="text" id="new_username" name="new_username" required>
                </div>
                <div class="form-group">
                    <label for="new_password">Mật khẩu:</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                <button type="submit">Đăng nhập</button>
            </form>
            <div class="form-footer">
                Đã có tài khoản? <a href="register.php">Đăng ký</a>
            </div>
        </div>
    </div>
</body>
</html>