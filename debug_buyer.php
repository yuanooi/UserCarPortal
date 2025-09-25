<?php
session_start();

echo "<h1>Buyer Debug Page</h1>";
echo "<h2>Current Session Data:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>Condition Checks:</h2>";
echo "isset(\$_SESSION['user_id']): " . (isset($_SESSION['user_id']) ? 'TRUE' : 'FALSE') . "<br>";
echo "isset(\$_SESSION['role']): " . (isset($_SESSION['role']) ? 'TRUE' : 'FALSE') . "<br>";
echo "isset(\$_SESSION['user_type']): " . (isset($_SESSION['user_type']) ? 'TRUE' : 'FALSE') . "<br>";
echo "\$_SESSION['role'] === 'user': " . ($_SESSION['role'] === 'user' ? 'TRUE' : 'FALSE') . "<br>";
echo "\$_SESSION['user_type'] === 'buyer': " . ($_SESSION['user_type'] === 'buyer' ? 'TRUE' : 'FALSE') . "<br>";

echo "<h2>Header Test:</h2>";
if (isset($_SESSION['user_id'])) {
    echo "✓ User logged in<br>";
    if (isset($_SESSION['role'])) {
        echo "✓ Role set<br>";
        if ($_SESSION['role'] === 'user') {
            echo "✓ Role is 'user'<br>";
            if ($_SESSION['user_type'] === 'buyer') {
                echo "✓ User type is 'buyer' - SHOULD SHOW BUYER MENUS<br>";
            } else {
                echo "✗ User type is NOT 'buyer' (it's: " . ($_SESSION['user_type'] ?? 'not set') . ")<br>";
            }
        } else {
            echo "✗ Role is NOT 'user' (it's: " . ($_SESSION['role'] ?? 'not set') . ")<br>";
        }
    } else {
        echo "✗ Role NOT set<br>";
    }
} else {
    echo "✗ User NOT logged in<br>";
}
?>

