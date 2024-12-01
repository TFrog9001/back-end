<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cảm ơn bạn đã đặt sân</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            text-align: center;
            padding: 50px;
        }

        .container {
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
            max-width: 600px;
            margin: 0 auto;
        }

        h1 {
            color: #4CAF50;
        }

        p {
            font-size: 18px;
            color: #555;
        }

        .button {
            background-color: #4CAF50;
            color: white;
            padding: 15px 30px;
            font-size: 18px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }

        .button:hover {
            background-color: #45a049;
        }
    </style>
</head>

<body>

    <div class="container">
        <h1>Cảm ơn bạn đã đặt sân!</h1>
        <p>Chúng tôi đã nhận được thông tin đặt sân của bạn. Bạn có thể tiếp tục trò chuyện với chúng tôi nếu cần thêm
            thông tin hoặc hỗ trợ.</p>

        <a href="javascript:void(0);" class="button" onclick="continueChat()">Tiếp tục chat</a>
    </div>

    <script>
        function continueChat() {
            // Thay đổi URL của trang hiện tại
            window.location.href = "http://127.0.0.1:8000/botman/chat";

            // (Tùy chọn) Làm mới cửa sổ hiện tại trước khi điều hướng, nếu cần
            // window.location.reload();
        }
    </script>

</body>

</html>