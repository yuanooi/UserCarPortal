<?php
// 简单的数据库测试脚本
include 'includes/db.php';

echo "<h2>数据库连接测试</h2>";

if ($conn->connect_error) {
    echo "❌ 数据库连接失败: " . $conn->connect_error;
} else {
    echo "✅ 数据库连接成功<br>";
    
    // 测试用户表结构
    echo "<h3>用户表结构:</h3>";
    $result = $conn->query("DESCRIBE users");
    if ($result) {
        echo "<table border='1'>";
        echo "<tr><th>字段</th><th>类型</th><th>空值</th><th>键</th><th>默认值</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 测试用户数据
    echo "<h3>用户数据:</h3>";
    $result = $conn->query("SELECT id, username, email, role FROM users");
    if ($result) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>用户名</th><th>邮箱</th><th>角色</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['username'] . "</td>";
            echo "<td>" . $row['email'] . "</td>";
            echo "<td>" . $row['role'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

$conn->close();
?>
