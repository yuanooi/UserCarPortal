<?php
// ========== 开发环境调试模式 ==========
define('DEBUG', true); // 设置为 false 上线时隐藏调试信息
// ======================================

session_start();
include 'includes/db.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 初始化变量
$message = '';
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    // 验证邮箱
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        // 密码重置逻辑
        try {
            // 1. 检查邮箱是否存在
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            if (!$check_stmt) {
                throw new Exception("Failed to prepare email check query: " . $conn->error);
            }
            
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("No account found with that email address.");
            }
            
            // 2. 生成安全 token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // 3. 删除旧的 token
            $delete_stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            if ($delete_stmt) {
                $delete_stmt->bind_param("s", $email);
                $delete_stmt->execute();
                $delete_stmt->close();
                $delete_stmt = null;
            }
            
            // 4. 插入新的 token
            $insert_stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            if (!$insert_stmt) {
                throw new Exception("Failed to prepare token insert query: " . $conn->error);
            }
            
            $insert_stmt->bind_param("sss", $email, $token, $expires);
            if (!$insert_stmt->execute()) {
                throw new Exception("Failed to insert reset token: " . $insert_stmt->error);
            }
            
            // 5. 生成重置链接
            $reset_link = "http://localhost/user_car_portal/resetpassword.php?token=" . urlencode($token);
            
            // ========== 调试模式：直接显示链接（跳过邮件发送） ==========
            if (DEBUG) {
                $message = "
                    <div class='alert alert-success'>
                        <h4><i class='fas fa-check-circle'></i> Token Generated Successfully!</h4>
                        <p><strong>Reset Link (Click to Test):</strong></p>
                        <div class='debug-link-box'>
                            <a href='$reset_link' target='_blank' class='btn btn-primary mb-3'>
                                <i class='fas fa-external-link-alt'></i> Open Reset Page
                            </a>
                            <div class='link-display'>
                                <strong>Direct Link:</strong><br>
                                <code style='word-break: break-all; background: #f8f9fa; padding: 0.5rem; border-radius: 4px; display: block;'>$reset_link</code>
                            </div>
                        </div>
                        <hr>
                        <div class='debug-info'>
                            <h6><i class='fas fa-info-circle'></i> Debug Information:</h6>
                            <ul class='list-unstyled'>
                                <li><strong>Email:</strong> <code>$email</code></li>
                                <li><strong>Token:</strong> <code>$token</code></li>
                                <li><strong>Expires:</strong> <code>$expires</code></li>
                                <li><strong>Status:</strong> <span class='badge bg-success'>Active</span></li>
                            </ul>
                            <small class='text-muted'>
                                <i class='fas fa-lightbulb'></i> Copy the link above and paste it in a new browser tab to test the password reset functionality.
                            </small>
                        </div>
                    </div>
                ";
                
                // 记录到日志
                error_log("Password reset token generated for $email. Token: $token, Expires: $expires");
                
            } else {
                // ========== 生产环境：发送邮件 ==========
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'yourgmail@gmail.com'; // 替换为你的邮箱
                $mail->Password = 'your-app-password';   // 替换为应用专用密码
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('yourgmail@gmail.com', 'User Car Portal');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = "Password Reset Request - User Car Portal";
                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                        <h2 style='color: #2563eb;'>Password Reset Request</h2>
                        <p>Hello,</p>
                        <p>You requested a password reset for your User Car Portal account.</p>
                        <p style='background: #f8fafc; padding: 1rem; border-radius: 8px; border-left: 4px solid #2563eb;'>
                            <strong>Reset Link:</strong><br>
                            <a href='$reset_link' style='color: #2563eb; text-decoration: none; font-weight: bold;'>Reset Your Password</a>
                        </p>
                        <p><small>This link will expire in 1 hour for security reasons.</small></p>
                        <p>If you did not request this password reset, please ignore this email.</p>
                        <hr style='border: none; border-top: 1px solid #e2e8f0;'>
                        <p style='color: #64748b; font-size: 0.9em;'>
                            Best regards,<br>
                            <strong>User Car Portal Team</strong>
                        </p>
                    </div>
                ";
                $mail->AltBody = "Password Reset Request\n\nClick this link to reset your password: $reset_link\n\nThis link will expire in 1 hour.\n\nIf you did not request this, please ignore this email.\n\nBest regards,\nUser Car Portal Team";

                $mail->send();
                $message = "A password reset link has been sent to your email. Please check your inbox (and spam folder).";
            }
            
        } catch (Exception $e) {
            // 记录详细错误信息（仅开发环境）
            if (defined('DEBUG') && DEBUG) {
                error_log("Password reset error for $email: " . $e->getMessage());
                $error = "Error: " . $e->getMessage(); // 显示详细错误
            } else {
                $error = "Failed to process your request. Please try again later.";
            }
            
            // 用户友好的错误消息
            if (strpos($e->getMessage(), "No account found") !== false) {
                $error = "No account found with that email address.";
            }
        } finally {
            // 安全清理所有资源
            $resources_to_close = ['result', 'check_stmt', 'insert_stmt', 'delete_stmt'];
            foreach ($resources_to_close as $resource) {
                if (isset($$resource) && $$resource instanceof mysqli_result) {
                    $$resource->close();
                } elseif (isset($$resource) && $$resource instanceof mysqli_stmt) {
                    $$resource->close();
                }
                unset($$resource); // 释放引用
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Request a password reset link for User Car Portal">
    <title>Send Password Reset Link - User Car Portal</title>
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
        .email-icon {
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
        /* ========== 调试样式 ============ */
        .debug-link-box {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 1px solid #0ea5e9;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1rem 0;
        }
        .debug-link-box .btn {
            font-size: 1.1rem;
            padding: 0.75rem 1.5rem;
        }
        .link-display {
            margin-top: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            border-left: 4px solid #0ea5e9;
        }
        .debug-info {
            background: #f8fafc;
            border-radius: 8px;
            padding: 1rem;
            font-size: 0.9rem;
        }
        .debug-info code {
            background: #e2e8f0;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        .debug-info .badge {
            font-size: 0.8rem;
        }
        @media (max-width: 576px) {
            .reset-section {
                margin: 1rem;
                padding: 2rem 1.5rem;
            }
            .debug-link-box {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="container">
        <section class="reset-section">
            <div class="text-center mb-4">
                <i class="fas fa-key fa-3x text-primary mb-3" style="color: var(--primary-color);"></i>
                <h2>Request Password Reset</h2>
                <p class="text-muted">Enter your email address to receive a password reset link.</p>
            </div>

            <?php if ($message): ?>
                <?php echo $message; ?>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!$message && !$error): ?>
            <form method="post" action="send_mail.php" id="resetForm">
                <div class="form-floating mb-4 position-relative">
                    <i class="fas fa-envelope email-icon"></i>
                    <input type="email" 
                           class="form-control" 
                           id="email" 
                           name="email"
                           value="<?php echo htmlspecialchars($email); ?>"
                           placeholder="name@example.com" 
                           required>
                    <label for="email">Email Address</label>
                </div>
                
                <button type="submit" class="btn btn-primary mb-3">
                    Send Reset Link
                    <i class="fas fa-paper-plane ms-2"></i>
                </button>
            </form>
            <?php endif; ?>

            <div class="text-center">
                <a href="login.php" class="back-to-login">
                    <i class="fas fa-arrow-left me-1"></i>
                    Back to Login
                </a>
            </div>

            <?php if (DEBUG): ?>
            <div class="mt-4 p-3 bg-light border rounded">
                <small class="text-muted">
                    <i class="fas fa-code"></i> <strong>Development Mode:</strong> 
                    Email sending is disabled. Token links are displayed directly for testing.
                </small>
                <br>
                <small class="text-success">
                    <i class="fas fa-toggle-on"></i> DEBUG = <code>true</code>
                </small>
            </div>
            <?php endif; ?>
        </section>
    </main>

    <?php include 'footer.php'; ?>

    <?php if ($message && DEBUG): ?>
    <script>
        // 高亮显示链接框
        document.querySelector('.debug-link-box').scrollIntoView({ 
            behavior: 'smooth', 
            block: 'center' 
        });
        
        // 添加复制链接功能
        const linkCode = document.querySelector('.link-display code');
        if (linkCode) {
            linkCode.addEventListener('click', function() {
                navigator.clipboard.writeText(this.textContent).then(() => {
                    // 创建临时提示
                    const tooltip = document.createElement('div');
                    tooltip.textContent = 'Link copied!';
                    tooltip.style.cssText = `
                        position: fixed; top: 20px; right: 20px; 
                        background: #10b981; color: white; padding: 0.5rem 1rem;
                        border-radius: 6px; z-index: 9999; font-size: 0.9rem;
                        animation: slideIn 0.3s ease;
                    `;
                    document.body.appendChild(tooltip);
                    
                    setTimeout(() => tooltip.remove(), 2000);
                });
            });
        }
        
        // 添加样式
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            .link-display code:hover {
                background: #e0f2fe !important;
                cursor: pointer;
                transition: background 0.2s ease;
            }
        `;
        document.head.appendChild(style);
    </script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// 安全关闭数据库连接
if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
    $conn->close();
}
?>