<?php
session_start();

// 清除所有 Session
$_SESSION = [];
session_unset();
session_destroy();

// 跳转到登录页面
header("Location: index.php");
exit;
