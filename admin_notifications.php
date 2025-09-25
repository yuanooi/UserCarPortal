<?php
session_start();
include 'includes/db.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?show_login=1");
    exit();
}

$admin_id = $_SESSION['user_id'];

// Handle mark as read action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $notification_id = intval($_POST['notification_id']);
    
    $stmt = $conn->prepare("UPDATE admin_notifications SET status = 'read', is_read = 1 WHERE id = ? AND admin_id = ?");
    $stmt->bind_param("ii", $notification_id, $admin_id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: admin_notifications.php");
    exit();
}

// Handle mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE admin_notifications SET status = 'read', is_read = 1 WHERE admin_id = ? AND status = 'unread'");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: admin_notifications.php");
    exit();
}

// Get admin notifications
$stmt = $conn->prepare("
    SELECT an.*, c.brand, c.model, c.year, u.username 
    FROM admin_notifications an 
    LEFT JOIN cars c ON an.related_car_id = c.id 
    LEFT JOIN users u ON an.user_id = u.id
    WHERE an.admin_id = ? 
    ORDER BY an.created_at DESC
");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$notifications = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Count unread notifications
$unread_count = 0;
foreach ($notifications as $notification) {
    if ($notification['status'] === 'unread') {
        $unread_count++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Notifications - User Car Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --bg-light: #f8fafc;
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-light);
        }

        .notification-card {
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
            transition: var(--transition);
            border-left: 4px solid transparent;
        }

        .notification-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .notification-card.unread {
            border-left-color: var(--primary-color);
            background: #fefefe;
        }

        .notification-card.read {
            border-left-color: var(--secondary-color);
            background: #f8f9fa;
            opacity: 0.8;
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .notification-icon.offer_response {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .notification-icon.message {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary-color);
        }

        .notification-icon.acquisition {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .badge-unread {
            background: var(--primary-color);
            color: white;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 10px;
        }

        .btn-mark-read {
            background: transparent;
            border: 1px solid var(--secondary-color);
            color: var(--secondary-color);
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .btn-mark-read:hover {
            background: var(--secondary-color);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--secondary-color);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">
                        <i class="fas fa-bell me-2"></i>Admin Notifications
                    </h2>
                    <p class="text-muted mb-0">
                        Monitor user activities and system alerts
                    </p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <?php if ($unread_count > 0): ?>
                        <span class="badge-unread">
                            <i class="fas fa-circle me-1"></i><?php echo $unread_count; ?> Unread
                        </span>
                    <?php endif; ?>
                    <?php if ($unread_count > 0): ?>
                        <form method="POST" class="d-inline">
                            <button type="submit" name="mark_all_read" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-check-double me-1"></i>Mark All Read
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <h4>No Notifications</h4>
                    <p>You don't have any notifications yet.</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="col-12">
                            <div class="notification-card card <?php echo $notification['status'] === 'unread' ? 'unread' : 'read'; ?>">
                                <div class="card-body">
                                    <div class="d-flex align-items-start">
                                        <div class="notification-icon <?php 
                                            if ($notification['notification_type'] === 'offer_response') echo 'offer_response';
                                            elseif ($notification['notification_type'] === 'acquisition') echo 'acquisition';
                                            else echo 'message';
                                        ?> me-3">
                                            <i class="fas <?php 
                                                if ($notification['notification_type'] === 'offer_response') echo 'fa-handshake';
                                                elseif ($notification['notification_type'] === 'acquisition') echo 'fa-car';
                                                else echo 'fa-envelope';
                                            ?>"></i>
                                        </div>
                                        
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="mb-1 fw-semibold">
                                                        <?php echo htmlspecialchars($notification['title']); ?>
                                                    </h6>
                                                    <?php if ($notification['username']): ?>
                                                        <small class="text-muted">
                                                            <i class="fas fa-user me-1"></i>
                                                            From: <?php echo htmlspecialchars($notification['username']); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                    <?php if ($notification['brand'] && $notification['model']): ?>
                                                        <br><small class="text-muted">
                                                            <i class="fas fa-car me-1"></i>
                                                            <?php echo htmlspecialchars($notification['brand'] . ' ' . $notification['model']); ?>
                                                            <?php if ($notification['year']): ?>
                                                                (<?php echo $notification['year']; ?>)
                                                            <?php endif; ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                                    </small>
                                                    <?php if ($notification['status'] === 'unread'): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                            <button type="submit" name="mark_read" class="btn-mark-read">
                                                                <i class="fas fa-check me-1"></i>Mark Read
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <p class="mb-0 text-dark">
                                                <?php echo htmlspecialchars($notification['message']); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
