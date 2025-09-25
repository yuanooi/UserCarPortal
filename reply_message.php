<?php
session_start();
include 'includes/db.php';

// Check database connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    $message = "❌ Database connection failed, please try again later";
    $error = true;
} else {
    $error = false;
}

// Check admin permissions
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?show_login=1");
    exit();
}

// Get all messages
$messages = [];
if (!$error) {
    $stmt = $conn->prepare("SELECT cm.id, cm.user_id, u.username, cm.name, cm.email, cm.message, cm.created_at FROM contact_message cm LEFT JOIN users u ON cm.user_id = u.id");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        $stmt->close();
    }
    if (empty($messages)) {
        $message = "❌ No messages available";
        $error = true;
    }
}

// Handle reply submission
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message_id']) && isset($_POST['admin_reply'])) {
    $message_id = intval($_POST['message_id']);
    $admin_reply = trim($_POST['admin_reply']);
    if (!empty($admin_reply)) {
        $stmt = $conn->prepare("UPDATE contact_message SET admin_reply = ?, replied_at = NOW() WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $admin_reply, $message_id);
            if ($stmt->execute()) {
                $success = true;
                $message = "Reply sent successfully (ID: $message_id)";
            } else {
                error_log("Reply update failed: " . $conn->error);
                $message = "❌ Reply sending failed, please try again later";
            }
            $stmt->close();
        }
    } else {
        $message = "❌ Reply content cannot be empty";
    }
}
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Reply Messages - User Car Portal">
    <title>User Car Portal - Reply Messages</title>
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

        .reply-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--card-shadow);
            max-width: 800px;
            margin: 0 auto 2rem;
        }

        .message-card {
            background: #f1f5f9;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }

        .message-card h5 {
            color: var(--primary-color);
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .message-card p {
            color: #1e293b;
            margin: 0.5rem 0;
        }

        .reply-form {
            margin-top: 1rem;
            display: none; /* Hidden by default, show on click */
        }

        .reply-form.active {
            display: block;
        }

        .reply-form .form-control {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            resize: vertical;
        }

        .reply-form .btn {
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            background: var(--accent-color);
            border: none;
            transition: var(--transition);
        }

        .reply-form .btn:hover {
            background: #059669;
            transform: translateY(-1px);
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
            .reply-section {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
<!-- Header -->
<?php include 'header.php'; ?>

<div class="content">
    <div class="container">
        <section class="reply-section">
            <h2 class="text-center mb-4" style="color: var(--primary-color);">Reply to Messages</h2>

            <?php if (isset($message) && $error): ?>
                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($message); ?></div>
            <?php elseif ($success): ?>
                <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($message); ?></div>
            <?php elseif (!empty($messages)): ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="message-card">
                        <h5>User: <?php echo htmlspecialchars($msg['username'] ?? $msg['name']); ?></h5>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($msg['email']); ?></p>
                        <p><strong>Message:</strong> <?php echo htmlspecialchars($msg['message']); ?></p>
                        <p><small class="text-muted">Sent At: <?php echo htmlspecialchars($msg['created_at']); ?></small></p>
                        <button class="btn btn-primary btn-sm mb-2" onclick="toggleReplyForm('reply-form-<?php echo $msg['id']; ?>')">Reply</button>
                        <form class="reply-form" id="reply-form-<?php echo $msg['id']; ?>" method="POST" action="reply_message.php">
                            <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                            <div class="mb-3">
                                <label for="admin_reply-<?php echo $msg['id']; ?>" class="form-label">Your Reply</label>
                                <textarea class="form-control" id="admin_reply-<?php echo $msg['id']; ?>" name="admin_reply" rows="3" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-success">Send</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>
</div>


<!-- Footer -->
<?php include 'footer.php'; ?>

<script>
function toggleReplyForm(formId) {
    const form = document.getElementById(formId);
    form.classList.toggle('active');
}
</script>
</body>
</html>

