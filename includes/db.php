<?php
$host = "localhost";
$user = "root";   // 你的数据库用户名
$pass = "06060116ocy";       // 你的数据库密码
$db   = "car_portal"; // 数据库名称

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("数据库连接失败: " . $conn->connect_error);
}
?>
