<?php
session_start();
include 'includes/db.php';

// Check if user is logged in and has correct role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'user' && $_SESSION['role'] !== 'admin') || ($_SESSION['role'] === 'user' && $_SESSION['user_type'] !== 'seller')) {
    header("Location: index.php?show_login=1");
    exit;
}

// Initialize CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize messages
$message = "";
$success = false;

// Handle approve/reject actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("⚠️ Invalid request, CSRF validation failed");
    }

    $car_id = isset($_POST['car_id']) ? intval($_POST['car_id']) : 0;
    $action = $_POST['action'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    if ($car_id <= 0 || !in_array($action, ['approve', 'reject']) || empty($reason)) {
        $message = "⚠️ Invalid action or reason missing";
    } else {
        // Fetch car details to get user_id
        $stmt = $conn->prepare("SELECT user_id, brand, model FROM cars WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("i", $car_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $message = "❌ Car not found or not pending";
        } else {
            $car = $result->fetch_assoc();
            $user_id = $car['user_id'];
            $car_name = $car['brand'] . ' ' . $car['model'];

            // Update car status
            $new_status = $action === 'approve' ? 'available' : 'rejected';
            $update_stmt = $conn->prepare("UPDATE cars SET status = ? WHERE id = ?");
            $update_stmt->bind_param("si", $new_status, $car_id);
            if ($update_stmt->execute()) {
                // Insert notification
                $notification_message = $action === 'approve' 
                    ? "Your car ($car_name) has been approved. Reason: $reason"
                    : "Your car ($car_name) has been rejected. Reason: $reason";
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, car_id, message, status) VALUES (?, ?, ?, 'unread')");
                $notif_stmt->bind_param("iis", $user_id, $car_id, $notification_message);
                $notif_stmt->execute();
                $notif_stmt->close();

                $success = true;
                $message = "✅ Car $action successfully! Notification sent to seller.";
            } else {
                $message = "❌ Failed to $action car";
                error_log("Car $action error: " . $update_stmt->error);
            }
            $update_stmt->close();
        }
        $stmt->close();
    }
}

// Fetch pending cars
$stmt = $conn->prepare("SELECT c.id, c.brand, c.model, c.year, c.price, c.description, u.username 
                        FROM cars c 
                        JOIN users u ON c.user_id = u.id 
                        WHERE c.status = 'pending'");
$stmt->execute();
$result = $stmt->get_result();
$pending_cars = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Review Cars & Send Notifications</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --accent-color: #10b981;
            --bg-light: #f8f9fa;
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-light);
        }

        .container {
            max-width: 900px;
            margin-top: 50px;
        }

        .car-card {
            border-radius: var(--border-radius);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }

        .btn-primary {
            border-radius: var(--border-radius);
            font-weight: 600;
            background: var(--primary-color);
            border-color: var(--primary-color);
            transition: var(--transition);
        }

        .btn-primary:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
            transform: translateY(-2px);
        }

        .btn-danger {
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: var(--transition);
        }

        .btn-danger:hover {
            background: #c82333;
            border-color: #c82333;
            transform: translateY(-2px);
        }

        .alert {
            border-radius: var(--border-radius);
        }

        .form-label {
            font-weight: 500;
            color: var(--secondary-color);
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container">
    <h3 class="text-center mb-4"><i class="fas fa-car me-2"></i>Review Cars & Send Notifications</h3>

    <?php if ($message): ?>
        <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?> text-center">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($pending_cars)): ?>
        <p class="text-center text-muted">No pending cars to review.</p>
    <?php else: ?>
        <?php foreach ($pending_cars as $car): ?>
            <div class="car-card card shadow">
                <div class="card-body">
                    <h5><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?> (<?php echo htmlspecialchars($car['year']); ?>)</h5>
                    <p><strong>Seller:</strong> <?php echo htmlspecialchars($car['username']); ?></p>
                    <p><strong>Price:</strong> RM <?php echo number_format($car['price'], 2); ?></p>
                    <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($car['description'])); ?></p>
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="car_id" value="<?php echo $car['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Reason for Action</label>
                            <textarea name="reason" class="form-control" required placeholder="Enter reason for approval or rejection"></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="action" value="approve" class="btn btn-primary"><i class="fas fa-check me-2"></i>Approve</button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger"><i class="fas fa-times me-2"></i>Reject</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <p class="text-center mt-3">
        <a href="admin_dashboard.php" class="text-decoration-none">Back to Dashboard</a>
    </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
