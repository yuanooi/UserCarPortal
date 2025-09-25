<?php
session_start();
include 'includes/db.php';

// Initialize CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize message
$message = "";

// Handle logout message
if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    $message = "✅ Successfully logged out";
    // Clear session data
    $_SESSION = array();
    session_destroy();
    // Start new session and regenerate CSRF token
    session_start();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Log CSRF token for debugging
    error_log("CSRF Check: POST Token: " . ($_POST['csrf_token'] ?? 'Not set') . ", Session Token: " . ($_SESSION['csrf_token'] ?? 'Not set'));

    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "⚠️ Invalid request, CSRF validation failed";
        error_log("CSRF validation failed for email: " . ($_POST['email'] ?? 'Unknown'));
        // Regenerate CSRF token for next attempt
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validate input
        if (empty($email) || empty($password)) {
            $message = "⚠️ Email and password cannot be empty";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "⚠️ Invalid email format";
        } else {
            // Query user with role and user_type
            $stmt = $conn->prepare("SELECT id, username, password, role, user_type FROM users WHERE email = ? LIMIT 1");
            if (!$stmt) {
                $message = "❌ Database error, please try again later";
                error_log("Login prepare error: " . $conn->error);
            } else {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($row = $result->fetch_assoc()) {
                    if (password_verify($password, $row['password'])) {
                        // Login successful
                        $_SESSION['user_id'] = $row['id'];
                        $_SESSION['username'] = $row['username'];
                        $_SESSION['role'] = $row['role'];
                        $_SESSION['user_type'] = $row['user_type'];
                        $message = "✅ Login successful, redirecting...";
                        // Log user details for debugging
                        error_log("Login Success: user_id={$row['id']}, username={$row['username']}, email={$email}, role={$row['role']}, user_type={$row['user_type']}");
                        // Redirect based on role and user_type
                        if ($row['role'] === 'admin') {
                            $redirect_url = "admin_dashboard.php";
                        } elseif ($row['user_type'] === 'seller') {
                            $redirect_url = "user_dashboard.php";
                        } else {
                            $redirect_url = "index.php";
                        }
                        error_log("Redirecting to: $redirect_url");
                        header("refresh:2;url=$redirect_url");
                    } else {
                        $message = "❌ Incorrect password";
                    }
                } else {
                    $message = "⚠️ Account not found";
                }
                $stmt->close();
            }
        }
        // Regenerate CSRF token after POST
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login to User Car Portal">
    <title>User Login - User Car Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Responsive Design -->
    <link href="assets/css/responsive.css" rel="stylesheet">
    <!-- Mobile Optimization -->
    <script src="assets/js/mobile-optimization.js" defer></script>
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --accent-color: #10b981;
            --bg-light: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--bg-light) 0%, #e2e8f0 100%);
            color: #1e293b;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: var(--card-shadow);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-color) !important;
        }

        .nav-link {
            color: var(--secondary-color) !important;
            font-weight: 500;
            transition: var(--transition);
            border-radius: 8px;
            padding: 0.5rem 1rem;
            margin: 0 0.25rem;
        }

        .nav-link:hover {
            color: var(--primary-color) !important;
            background-color: rgba(37, 99, 235, 0.1);
        }

        .login-section {
            max-width: 500px;
            margin: 3rem auto;
            padding: 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
        }

        .login-section h2 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .form-control {
            border-radius: 50px;
            border: 2px solid #e2e8f0;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
        }

        .input-group-text {
            border-radius: 50px 0 0 50px;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-right: none;
        }

        .btn-primary {
            border-radius: 50px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            background: var(--primary-color);
            border: none;
            transition: var(--transition);
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .alert {
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .register-link, .forgot-password {
            display: block;
            text-align: center;
            margin-top: 1rem;
            color: var(--primary-color);
            font-weight: 500;
            text-decoration: none;
        }

        .register-link:hover, .forgot-password:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }

        @media (max-width: 576px) {
            .login-section {
                margin: 1.5rem;
                padding: 1.5rem;
            }

            .login-section h2 {
                font-size: 1.5rem;
            }

            .form-control {
                padding: 0.6rem 1.2rem;
                font-size: 0.9rem;
            }

            .btn-primary {
                padding: 0.6rem 1.2rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    
    <!-- Login Form -->
    <section class="login-section">
        <h2>🔑 User Login</h2>
        <?php if ($message): ?>
            <div class="alert <?php echo strpos($message, 'successful') !== false ? 'alert-success' : 'alert-danger'; ?>" role="alert">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    <input type="email" name="email" class="form-control" id="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" placeholder="Enter your email" required>
                </div>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" class="form-control" id="password" placeholder="Enter your password" required minlength="6">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
            <a href="register.php" class="register-link">Don't have an account? Register Now</a>
            <a href="forgetpassword.php" class="forgot-password">Forgot Password?</a>
        </form>
    </section>

   

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
<?php
if ($conn->ping()) {
    $conn->close();
    error_log("login.php database connection closed successfully");
} else {
    error_log("login.php database connection already closed or lost");
}
?>