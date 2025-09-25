<?php
session_start();
include 'includes/db.php';

// Check database connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    $message = "❌ Database connection failed, please try again later.";
}

// Check user login status
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?show_login=1");
    exit();
}

// Get admin contact information
function getAdminContact($conn) {
    $admin_contact = [
        'email' => 'admin@usercarportal.com',
        'phone' => ''
    ];
    $stmt = $conn->prepare("SELECT email, phone FROM users WHERE role = 'admin' LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $admin_contact['email'] = $row['email'] ?: $admin_contact['email'];
            $admin_contact['phone'] = $row['phone'] ?: '';
        } else {
            error_log("No admin found in users table");
        }
        $stmt->close();
    } else {
        error_log("Admin contact query prepare error: " . $conn->error);
    }
    return $admin_contact;
}

$admin_contact = getAdminContact($conn);
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    // Basic validation
    if (empty($name) || empty($email) || empty($message)) {
        $error = "Please fill in all required fields (name, email, and message).";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Get current user ID
        $user_id = $_SESSION['user_id'];
        
        // Insert contact message
        $stmt = $conn->prepare("INSERT INTO contact_message (user_id, name, email, phone, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("issss", $user_id, $name, $email, $phone, $message);
            if ($stmt->execute()) {
                $success = true;
                $name = $email = $phone = $message = '';
            } else {
                error_log("Contact message insertion failed: " . $conn->error);
                $error = "Submission failed, please try again later.";
            }
            $stmt->close();
        } else {
            error_log("Contact message insertion prepare error: " . $conn->error);
            $error = "System error, please contact the administrator.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Contact User Car Portal - Get support or make inquiries">
    <title>User Car Portal - Contact Us</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css">
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

        .content {
            flex: 1 0 auto;
            padding: 2rem 0;
        }

        .contact-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--card-shadow);
            max-width: 800px;
            margin: 0 auto 2rem;
        }

        .contact-info {
            background: linear-gradient(135deg, var(--bg-light), #e2e8f0);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
        }

        .contact-info i {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin-right: 1rem;
        }

        .contact-form .form-control {
            border-radius: 50px;
            border: 2px solid #e2e8f0;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            transition: var(--transition);
        }

        .contact-form .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
        }

        .contact-form .btn {
            border-radius: 50px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            background: var(--primary-color);
            border: none;
            transition: var(--transition);
            width: 100%;
        }

        .contact-form .btn:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .alert {
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .footer {
            flex-shrink: 0;
            background: #1e293b;
            color: white;
            padding: 1rem 0;
            text-align: center;
            margin-top: 2rem;
        }

        @media (max-width: 768px) {
            .contact-section {
                padding: 1.5rem;
            }
            .contact-form .btn {
                padding: 0.6rem 1.5rem;
            }
        }
    </style>
</head>
<body>
<!-- Header -->
<?php include 'header.php'; ?>

<div class="content">
    <div class="container">
        <section class="contact-section">
            <h2 class="text-center mb-4" style="color: var(--primary-color);">Contact Us</h2>

            <!-- Contact Information -->
            <div class="contact-info">
                <div class="d-flex align-items-center mb-2">
                    <i class="fas fa-envelope"></i>
                    <span>Email: <a href="mailto:<?php echo htmlspecialchars($admin_contact['email']); ?>" style="color: var(--primary-color);"><?php echo htmlspecialchars($admin_contact['email']); ?></a></span>
                </div>
                <?php if (!empty($admin_contact['phone'])): ?>
                    <div class="d-flex align-items-center">
                        <i class="fas fa-phone"></i>
                        <span>Phone: <a href="tel:<?php echo htmlspecialchars($admin_contact['phone']); ?>" style="color: var(--primary-color);"><?php echo htmlspecialchars($admin_contact['phone']); ?></a></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Contact Form -->
            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    Thank you for your message! We will get back to you soon.
                </div>
            <?php elseif ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form class="contact-form" method="POST" action="contact.php">
                <div class="mb-3">
                    <label for="name" class="form-label">Name *</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email *</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="phone" class="form-label">Phone (Optional)</label>
                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label for="message" class="form-label">Message *</label>
                    <textarea class="form-control" id="message" name="message" rows="5" required><?php echo htmlspecialchars($message ?? ''); ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Send Message</button>
            </form>
        </section>
    </div>
</div>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <p>&copy; <?php echo date('Y'); ?> User Car Portal. All rights reserved.</p>
        <p>Current Time: <?php echo date('H:i A T, l, F d, Y'); ?> (<?php echo date_default_timezone_get(); ?>)</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

