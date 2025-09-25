<?php
session_start();
include 'includes/db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?show_login=1&message=" . urlencode("Please log in as an admin to manage orders."));
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Check database connection
if ($conn->connect_error) {
    error_log("Database connection failed in admin_orders.php: " . $conn->connect_error);
    $message = "❌ Database connection failed, please contact the administrator";
}

// Handle order approval or cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id']) && isset($_POST['action']) && isset($_POST['csrf_token'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("CSRF token validation failed for admin order action, order_id: " . ($_POST['order_id'] ?? 'unknown'));
        $message = "❌ Invalid request, please try again.";
    } else {
        $order_id = intval($_POST['order_id']);
        $action = $_POST['action'];
        
        // Verify order exists and is pending
        $stmt = $conn->prepare("
            SELECT o.id, o.car_id, o.user_id, c.brand, c.model, c.year, c.price, u.email, u.username
            FROM orders o
            JOIN cars c ON o.car_id = c.id
            JOIN users u ON o.user_id = u.id
            WHERE o.id = ? AND o.order_status = 'ordered'
        ");
        if ($stmt) {
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $order = $result->fetch_assoc();
                $car_id = $order['car_id'];

                $conn->begin_transaction();
                try {
                    if ($action === 'approve') {
                        // Update order status to 'completed'
                        $update_order_stmt = $conn->prepare("
                            UPDATE orders 
                            SET order_status = 'completed', updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $update_order_stmt->bind_param("i", $order_id);
                        $update_order_stmt->execute();
                        $update_order_stmt->close();

                        // Update car status to 'sold'
                        $update_car_stmt = $conn->prepare("
                            UPDATE cars 
                            SET status = 'sold' 
                            WHERE id = ?
                        ");
                        $update_car_stmt->bind_param("i", $car_id);
                        $update_car_stmt->execute();
                        $update_car_stmt->close();

                        $message = "✅ Order approved successfully!";
                    } elseif ($action === 'cancel') {
                        // Update order status to 'cancelled'
                        $update_order_stmt = $conn->prepare("
                            UPDATE orders 
                            SET order_status = 'cancelled', updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $update_order_stmt->bind_param("i", $order_id);
                        $update_order_stmt->execute();
                        $update_order_stmt->close();

                        // Update car status to 'available'
                        $update_car_stmt = $conn->prepare("
                            UPDATE cars 
                            SET status = 'available' 
                            WHERE id = ?
                        ");
                        $update_car_stmt->bind_param("i", $car_id);
                        $update_car_stmt->execute();
                        $update_car_stmt->close();

                        $message = "✅ Order cancelled successfully!";
                    }

                    $conn->commit();
                    error_log("Order ID {$order_id} " . ($action === 'approve' ? 'completed' : 'cancelled') . " by admin ID {$user_id}, car ID {$car_id}");
                } catch (Exception $e) {
                    $conn->rollback();
                    error_log("Failed to " . ($action === 'approve' ? 'complete' : 'cancel') . " order ID {$order_id}: " . $e->getMessage());
                    $message = "❌ Failed to " . ($action === 'approve' ? 'approve' : 'cancel') . " order, please try again.";
                }
            } else {
                error_log("Order ID {$order_id} not found or not pending");
                $message = "❌ Order not found or cannot be processed.";
            }
            $stmt->close();
        } else {
            error_log("Order verification query preparation failed: " . $conn->error);
            $message = "❌ Database query error, please contact the administrator";
        }
    }
}

// Fetch pending orders
$orders = [];
if ($conn->ping()) {
    $stmt = $conn->prepare("
        SELECT o.id, o.car_id, o.user_id, o.created_at, c.brand, c.model, c.year, c.price, u.username
        FROM orders o
        JOIN cars c ON o.car_id = c.id
        JOIN users u ON o.user_id = u.id
        WHERE o.order_status = 'ordered'
        ORDER BY o.created_at DESC
    ");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $orders = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        error_log("Pending orders query preparation failed: " . $conn->error);
        $message = "❌ Database query error, please contact the administrator";
    }
} else {
    error_log("Database connection closed before fetching orders in admin_orders.php");
    $message = "❌ Database connection lost, please contact the administrator";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Admin Order Management - User Car Portal">
    <title>Manage Orders - User Car Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        .order-section {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
        }

        .order-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            background-color: #f8fafc;
            transition: var(--transition);
        }

        .order-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .order-card h5 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.75rem;
        }

        .order-card .text-muted {
            font-size: 0.9rem;
        }

        .btn-approve {
            background: var(--accent-color);
            color: white;
            border-radius: 50px;
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            border: none;
            transition: var(--transition);
        }

        .btn-approve:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-cancel {
            background: #dc3545;
            color: white;
            border-radius: 50px;
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            border: none;
            transition: var(--transition);
        }

        .btn-cancel:hover {
            background: #b02a37;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }

        .no-orders {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
        }

        .no-orders i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .order-section {
                margin: 1rem;
                padding: 1.5rem;
            }

            .order-card {
                padding: 1rem;
            }

            .order-card h5 {
                font-size: 1.1rem;
            }

            .btn-approve, .btn-cancel {
                padding: 0.4rem 1.2rem;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 576px) {
            .order-section {
                padding: 1rem;
            }

            .order-card h5 {
                font-size: 1rem;
            }

            .btn-approve, .btn-cancel {
                padding: 0.3rem 1rem;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
<!-- Header -->
<?php 
error_log("Including header.php, connection status: " . ($conn->ping() ? "active" : "closed"));
include 'header.php'; 
?>

<!-- Orders Section -->
<section class="order-section">
    <div class="container">
        <h2 class="text-center mb-4" style="color: var(--primary-color);">Manage Pending Orders</h2>
        <?php if ($message): ?>
            <div class="alert alert-<?php echo strpos($message, '✅') === 0 ? 'success' : 'danger'; ?>" role="alert">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($orders)): ?>
            <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <h5><?php echo htmlspecialchars($order['brand'] . ' ' . $order['model'] . ' (' . $order['year'] . ')'); ?></h5>
                    <p class="text-muted mb-2">
                        <i class="fas fa-tag me-1"></i>Price: RM <?php echo number_format($order['price'], 0); ?>
                    </p>
                    <p class="text-muted mb-2">
                        <i class="fas fa-user me-1"></i>Buyer: <?php echo htmlspecialchars($order['username']); ?>
                    </p>
                    <p class="text-muted mb-2">
                        <i class="fas fa-calendar me-1"></i>Ordered on: <?php 
                            $date = new DateTime($order['created_at']);
                            echo $date->format('Y-m-d H:i');
                        ?>
                    </p>
                    <div class="d-flex justify-content-between align-items-center">
                        <a href="car_detail.php?id=<?php echo $order['car_id']; ?>" class="btn btn-primary">
                            <i class="fas fa-eye me-1"></i>View Car
                        </a>
                        <div class="d-flex gap-2">
                            <form method="post" action="admin_orders.php" onsubmit="return confirm('Are you sure you want to approve this order?');">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <button type="submit" class="btn btn-approve">
                                    <i class="fas fa-check me-1"></i>Approve
                                </button>
                            </form>
                            <form method="post" action="admin_orders.php" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <button type="submit" class="btn btn-cancel">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-orders">
                <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                <h3 class="mb-3">No Pending Orders</h3>
                <p class="text-muted">There are no orders awaiting approval.</p>
                <a href="index.php" class="btn btn-primary">Browse Vehicles</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Footer -->
<?php 
error_log("Including footer.php, connection status: " . ($conn->ping() ? "active" : "closed"));
include 'footer.php'; 
?>

</body>
</html>
<?php
if ($conn->ping()) {
    $conn->close();
    error_log("Database connection closed successfully in admin_orders.php");
} else {
    error_log("Database connection already closed or lost in admin_orders.php");
}
?>