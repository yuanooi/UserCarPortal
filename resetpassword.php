<?php
session_start();
include 'includes/db.php';

// 初始化变量
$message = '';
$error = '';
$token = $_GET['token'] ?? '';

// 如果没有 token，直接拒绝
if (empty($token)) {
    die("Invalid password reset link.");
}

try {
    // 检查 token 是否存在且未过期
    $check_stmt = $conn->prepare("SELECT email, expires_at FROM password_resets WHERE token = ?");
    if (!$check_stmt) {
        throw new Exception("Failed to prepare token check query: " . $conn->error);
    }
    
    $check_stmt->bind_param("s", $token);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Invalid or expired reset link.");
    }
    
    $row = $result->fetch_assoc();
    $email = $row['email'];
    $expires_at = $row['expires_at'];
    
    if (strtotime($expires_at) < time()) {
        // 删除过期 token
        $delete_expired_stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
        if ($delete_expired_stmt) {
            $delete_expired_stmt->bind_param("s", $token);
            $delete_expired_stmt->execute();
            $delete_expired_stmt->close();
        }
        throw new Exception("This password reset link has expired.");
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // 密码验证
        if (empty($password) || empty($confirm_password)) {
            $error = "Please fill in all fields.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/", $password)) {
            $error = "Password must be at least 8 characters long and include at least one uppercase letter, one lowercase letter, one number, and one special character.";
        } else {
            // 更新用户密码
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            if (!$update_stmt) {
                throw new Exception("Failed to prepare password update query: " . $conn->error);
            }
            
            $update_stmt->bind_param("ss", $hashedPassword, $email);
            if (!$update_stmt->execute()) {
                throw new Exception("Failed to update password: " . $update_stmt->error);
            }
            
            // 删除使用过的 token
            $delete_stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
            if ($delete_stmt) {
                $delete_stmt->bind_param("s", $token);
                $delete_stmt->execute();
                $delete_stmt->close();
            }
            
            $message = "✅ Your password has been reset successfully. You will be redirected to the <a href='login.php'>login page</a> in 5 seconds.";
        }
    }
    
} catch (Exception $e) {
    // 记录详细错误（仅开发环境）
    if (defined('DEBUG') && DEBUG) {
        error_log("Password reset error for token $token: " . $e->getMessage());
    }
    
    // 用户友好的错误消息
    if (strpos($e->getMessage(), "Invalid or expired") !== false || strpos($e->getMessage(), "link has expired") !== false) {
        $error = $e->getMessage();
    } else {
        $error = "An error occurred. Please try again or request a new reset link.";
    }
} finally {
    // 清理资源
    $resources_to_close = ['result', 'check_stmt', 'update_stmt', 'delete_stmt', 'delete_expired_stmt'];
    foreach ($resources_to_close as $resource) {
        if (isset($$resource) && $$resource instanceof mysqli_result) {
            $$resource->close();
        } elseif (isset($$resource) && $$resource instanceof mysqli_stmt) {
            $$resource->close();
        }
        unset($$resource);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Reset your password for User Car Portal">
    <title>Reset Password - User Car Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --bg-light: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1),
                          0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --border-radius: 12px;
            --transition: all 0.3s ease;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, var(--bg-light) 0%, #e2e8f0 100%);
            min-height: 100vh;
            line-height: 1.6;
        }
        .reset-section {
            max-width: 480px;
            margin: 2rem auto;
            padding: 2.5rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
        }
        .reset-section h2 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 1.75rem;
        }
        .form-control {
            border-radius: 50px;
            border: 2px solid #e2e8f0;
            padding: 0.75rem 1.5rem;
            transition: var(--transition);
            font-size: 1rem;
        }
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
        }
        .btn-primary {
            border-radius: 50px;
            padding: 0.875rem 2rem;
            font-weight: 600;
            background: var(--primary-color);
            border: none;
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: var(--transition);
        }
        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
        }
        .btn-primary:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .back-to-login {
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        .back-to-login:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }
        .password-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary-color);
            z-index: 1;
        }
        .form-floating > .form-control {
            padding-left: 3rem;
        }
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.25rem;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        .password-strength {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 0.5rem;
        }
        .password-strength span {
            color: #ef4444;
        }
        .password-strength.valid span {
            color: #10b981;
        }
        @media (max-width: 576px) {
            .reset-section {
                margin: 1rem;
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="container">
        <section class="reset-section">
            <div class="text-center mb-4">
                <i class="fas fa-lock fa-3x text-primary mb-3" style="color: var(--primary-color);"></i>
                <h2>Set a New Password</h2>
                <p class="text-muted">Enter your new password for your User Car Portal account.</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success d-flex align-items-center">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (!$message): ?>
            <form method="post" id="resetForm">
                <div class="form-floating mb-4 position-relative">
                    <i class="fas fa-lock password-icon"></i>
                    <input type="password" 
                           class="form-control" 
                           id="password" 
                           name="password" 
                           placeholder="New Password" 
                           required>
                    <label for="password">New Password</label>
                </div>
                <div class="form-floating mb-4 position-relative">
                    <i class="fas fa-lock password-icon"></i>
                    <input type="password" 
                           class="form-control" 
                           id="confirm_password" 
                           name="confirm_password" 
                           placeholder="Confirm Password" 
                           required>
                    <label for="confirm_password">Confirm Password</label>
                    <div id="passwordStrength" class="password-strength">
                        Password must include:
                        <ul>
                            <li>At least 8 characters</li>
                            <li>One uppercase letter</li>
                            <li>One lowercase letter</li>
                            <li>One number</li>
                            <li>One special character (@$!%*?&)</li>
                        </ul>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mb-3" id="submitBtn">
                    Reset Password
                    <i class="fas fa-check ms-2"></i>
                </button>
            </form>
            <?php endif; ?>

            <div class="text-center">
                <a href="login.php" class="back-to-login">
                    <i class="fas fa-arrow-left me-1"></i>
                    Back to Login
                </a>
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>

    <?php if ($message): ?>
    <script>
        // 5秒后自动跳转到登录页面
        setTimeout(function() {
            window.location.href = 'login.php';
        }, 5000);
        
        // 禁用表单
        document.getElementById('resetForm').style.opacity = '0.7';
    </script>
    <?php endif; ?>

    <script>
        // 客户端密码强度验证
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const passwordStrength = document.getElementById('passwordStrength');
        const submitBtn = document.getElementById('submitBtn');

        function validatePassword() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
            
            if (regex.test(password) && password === confirmPassword) {
                passwordStrength.classList.add('valid');
                submitBtn.disabled = false;
            } else {
                passwordStrength.classList.remove('valid');
                submitBtn.disabled = true;
            }
        }

        passwordInput.addEventListener('input', validatePassword);
        confirmPasswordInput.addEventListener('input', validatePassword);
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// 安全关闭数据库连接
if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
    $conn->close();
}
?>