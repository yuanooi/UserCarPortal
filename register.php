<?php
session_start();
include 'includes/db.php';

// Initialize CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize error and success messages
$message = "";
$success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("⚠️ Invalid request, CSRF validation failed");
    }

    // Sanitize input
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Validate input
    if (empty($username) || empty($email) || empty($password) || empty($confirm)) {
        $message = "⚠️ All required fields (username, email, password, confirm password) cannot be empty";
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $message = "⚠️ Username must be between 3 and 50 characters";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "⚠️ Invalid email format";
    } elseif (!empty($phone) && !preg_match('/^[0-9\-]{10,15}$/', $phone)) {
        $message = "⚠️ Please enter a valid phone number (10-15 digits or hyphens)";
    } elseif (strlen($password) < 6) {
        $message = "⚠️ Password must be at least 6 characters long";
    } elseif ($password !== $confirm) {
        $message = "❌ Passwords do not match";
    } else {
        // Check for duplicate username
        $check_username = $conn->prepare("SELECT id FROM users WHERE username = ?");
        if (!$check_username) {
            $message = "❌ Database error, please try again later";
            error_log("Check username prepare error: " . $conn->error);
        } else {
            $check_username->bind_param("s", $username);
            $check_username->execute();
            $check_username->store_result();

            if ($check_username->num_rows > 0) {
                $message = "⚠️ This username is already taken, please choose another";
            } else {
                // Check for duplicate email
                $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
                if (!$check_email) {
                    $message = "❌ Database error, please try again later";
                    error_log("Check email prepare error: " . $conn->error);
                } else {
                    $check_email->bind_param("s", $email);
                    $check_email->execute();
                    $check_email->store_result();

                    if ($check_email->num_rows > 0) {
                        $message = "⚠️ This email is already registered";
                    } else {
                        // Hash password
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                        // Get user type from form
                        $user_type = $_POST['user_type'] ?? 'buyer';
                        
                        // Insert new user with default 'user' role and selected user_type
                        $stmt = $conn->prepare("INSERT INTO users (username, email, phone, password, role, user_type) VALUES (?, ?, ?, ?, 'user', ?)");
                        if (!$stmt) {
                            $message = "❌ Database error, please try again later";
                            error_log("Insert user prepare error: " . $conn->error);
                        } else {
                            $stmt->bind_param("sssss", $username, $email, $phone, $hashedPassword, $user_type);
                            if ($stmt->execute()) {
                                $success = true;
                                $message = "✅ Registration successful! Redirecting to login page...";
                                header("refresh:3;url=login.php");
                            } else {
                                $message = "❌ Registration failed, please try again later";
                                error_log("Insert user execute error: " . $stmt->error);
                            }
                            $stmt->close();
                        }
                    }
                    $check_email->close();
                }
            }
            $check_username->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Register for User Car Portal">
    <title>User Registration - User Car Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
            align-items: center;
            justify-content: center;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            padding: 2.5rem;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            max-width: 500px;
            width: 100%;
            margin: 1rem;
        }

        .glass-card h3 {
            font-weight: 700;
            color: var(--primary-color);
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-control {
            border-radius: var(--border-radius);
            border: 2px solid #e2e8f0;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: var(--transition);
            background: rgba(255, 255, 255, 0.9);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background: white;
        }

        .input-group-text {
            border-radius: var(--border-radius) 0 0 var(--border-radius);
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-right: none;
            color: var(--secondary-color);
        }

        .btn-primary {
            border-radius: var(--border-radius);
            font-weight: 600;
            background: var(--primary-color);
            border: none;
            padding: 0.75rem 2rem;
            transition: var(--transition);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .alert {
            border-radius: var(--border-radius);
            border: none;
            font-weight: 500;
        }

        .progress {
            height: 6px;
            margin-top: 10px;
            display: none;
            border-radius: var(--border-radius);
        }

        .password-strength {
            font-size: 0.9rem;
            font-weight: 500;
        }

        .form-label {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }

        .register-link {
            color: var(--primary-color);
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
        }

        .register-link:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }

        @media (max-width: 576px) {
            .glass-card {
                padding: 2rem;
                margin: 0.5rem;
            }
            
            .glass-card h3 {
                font-size: 1.5rem;
            }
            
            .form-control {
                padding: 0.6rem 0.8rem;
                font-size: 0.9rem;
            }
            
            .btn-primary {
                padding: 0.6rem 1.5rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="col-md-6 col-lg-5 mx-auto">
        <div class="glass-card">
            <h3 class="text-center mb-4">🚗 User Registration</h3>

            <?php if ($message): ?>
                <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?> text-center">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php if ($success): ?>
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width: 100%"></div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <form method="POST" action="" onsubmit="return validateForm()">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="mb-3">
                    <label for="username" class="form-label">
                        <i class="fas fa-user me-2"></i>Username
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-user text-muted"></i>
                        </span>
                        <input type="text" name="username" id="username" class="form-control" 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                               placeholder="Enter your username" required minlength="3" maxlength="50">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope me-2"></i>Email Address
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-envelope text-muted"></i>
                        </span>
                        <input type="email" name="email" id="email" class="form-control" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                               placeholder="Enter your email address" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">
                        <i class="fas fa-phone me-2"></i>Phone Number
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-phone text-muted"></i>
                        </span>
                        <input type="tel" name="phone" id="phone" class="form-control" 
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" 
                               placeholder="Enter your phone number" pattern="[0-9\-]{10,15}" 
                               title="Please enter a 10-15 digit phone number">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock me-2"></i>Password
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-lock text-muted"></i>
                        </span>
                        <input type="password" id="password" name="password" class="form-control" 
                               placeholder="At least 6 characters" required minlength="6" onkeyup="checkStrength()">
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="fas fa-eye" id="passwordIcon"></i>
                        </button>
                    </div>
                    <small id="strengthMsg" class="password-strength"></small>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label">
                        <i class="fas fa-lock me-2"></i>Confirm Password
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-lock text-muted"></i>
                        </span>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" 
                               placeholder="Re-enter your password" required minlength="6">
                        <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                            <i class="fas fa-eye" id="confirmPasswordIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="user_type" class="form-label">
                        <i class="fas fa-users me-2"></i>User Type
                    </label>
                    <select class="form-select" name="user_type" id="user_type" required>
                        <option value="">Please select user type</option>
                        <option value="buyer" <?php echo isset($_POST['user_type']) && $_POST['user_type'] === 'buyer' ? 'selected' : ''; ?>>
                            <i class="fas fa-shopping-cart me-1"></i>Buyer (Purchase Vehicles)
                        </option>
                        <option value="seller" <?php echo isset($_POST['user_type']) && $_POST['user_type'] === 'seller' ? 'selected' : ''; ?>>
                            <i class="fas fa-store me-1"></i>Seller (Sell Vehicles)
                        </option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-3 mb-3">
                    <i class="fas fa-user-plus me-2"></i>Create Account
                </button>
            </form>

            <div class="text-center">
                <p class="mb-0 text-muted">Already have an account?</p>
                <a href="login.php" class="register-link">
                    <i class="fas fa-sign-in-alt me-1"></i>Login Now
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password toggle functionality
    const togglePassword = document.getElementById('togglePassword');
    const passwordField = document.getElementById('password');
    const passwordIcon = document.getElementById('passwordIcon');
    
    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
    const confirmPasswordField = document.getElementById('confirm_password');
    const confirmPasswordIcon = document.getElementById('confirmPasswordIcon');
    
    if (togglePassword && passwordField) {
        togglePassword.addEventListener('click', function() {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            passwordIcon.classList.toggle('fa-eye', type === 'password');
            passwordIcon.classList.toggle('fa-eye-slash', type === 'text');
        });
    }
    
    if (toggleConfirmPassword && confirmPasswordField) {
        toggleConfirmPassword.addEventListener('click', function() {
            const type = confirmPasswordField.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordField.setAttribute('type', type);
            confirmPasswordIcon.classList.toggle('fa-eye', type === 'password');
            confirmPasswordIcon.classList.toggle('fa-eye-slash', type === 'text');
        });
    }
    
    // Form validation
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                showAlert('Passwords do not match!', 'danger');
                document.getElementById('confirm_password').focus();
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                showAlert('Password must be at least 6 characters long!', 'danger');
                document.getElementById('password').focus();
                return false;
            }
            
            document.querySelector('.progress').style.display = 'block';
            return true;
        });
    }
});

function validateForm() {
    let pwd = document.getElementById('password').value;
    let confirm = document.getElementById('confirm_password').value;
    if (pwd !== confirm) {
        showAlert("Passwords do not match!", 'danger');
        return false;
    }
    document.querySelector('.progress').style.display = 'block';
    return true;
}

function checkStrength() {
    let pwd = document.getElementById('password').value;
    let msg = document.getElementById('strengthMsg');
    
    if (pwd.length === 0) {
        msg.innerHTML = "";
        msg.style.color = "";
        return;
    }
    
    if (pwd.length < 6) {
        msg.innerHTML = "Weak 🔴";
        msg.style.color = "#dc3545";
    } else if (pwd.match(/[A-Z]/) && pwd.match(/[0-9]/) && pwd.length >= 8) {
        msg.innerHTML = "Strong 🟢";
        msg.style.color = "#198754";
    } else {
        msg.innerHTML = "Medium 🟠";
        msg.style.color = "#fd7e14";
    }
}

function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'danger' ? 'exclamation-triangle' : 'check-circle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}
</script>

</body>
</html>
