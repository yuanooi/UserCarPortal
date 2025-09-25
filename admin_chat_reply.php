<?php
session_start();
include 'includes/db.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?show_login=1");
    exit();
}

$admin_id = $_SESSION['user_id'];
$admin_username = $_SESSION['username'];
$message = '';

// Handle admin reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reply'])) {
    $message_id = intval($_POST['message_id']);
    $admin_reply = trim($_POST['admin_reply']);
    
    if (!empty($admin_reply)) {
        // Check if there's already a reply, if so, append to it
        $stmt = $conn->prepare("SELECT admin_reply FROM contact_message WHERE id = ?");
        $stmt->bind_param("i", $message_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing_reply = $result->fetch_assoc();
        
        if ($existing_reply && !empty($existing_reply['admin_reply'])) {
            // Append to existing reply
            $new_reply = $existing_reply['admin_reply'] . "\n\n--- Additional Reply ---\n" . $admin_reply;
        } else {
            $new_reply = $admin_reply;
        }
        
        $stmt = $conn->prepare("UPDATE contact_message SET admin_reply = ?, replied_at = NOW() WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $new_reply, $message_id);
            if ($stmt->execute()) {
                $message = "✅ Reply sent successfully!";
            } else {
                $message = "❌ Failed to send reply.";
            }
            $stmt->close();
        } else {
            $message = "❌ Database error.";
        }
    } else {
        $message = "❌ Please enter a reply message.";
    }
}

// Get selected user's messages (if user_id is provided)
$selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$selected_user_info = null;

if ($selected_user_id) {
    // Get user info
    $stmt = $conn->prepare("SELECT id, username, email, phone FROM users WHERE id = ?");
    $stmt->bind_param("i", $selected_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $selected_user_info = $result->fetch_assoc();
    $stmt->close();
}

// Get all users who have sent messages
$users = [];
$stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.username, u.email, u.phone, COUNT(cm.id) as message_count,
           MAX(cm.created_at) as last_message_time
    FROM users u 
    INNER JOIN contact_message cm ON u.id = cm.user_id 
    GROUP BY u.id, u.username, u.email, u.phone
    ORDER BY last_message_time DESC
");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();
}

// Get messages for selected user or all messages
if ($selected_user_id) {
    $stmt = $conn->prepare("
        SELECT cm.*, u.username, u.email as user_email, u.phone as user_phone
        FROM contact_message cm 
        LEFT JOIN users u ON cm.user_id = u.id 
        WHERE cm.user_id = ?
        ORDER BY cm.created_at ASC
    ");
    $stmt->bind_param("i", $selected_user_id);
} else {
    $stmt = $conn->prepare("
        SELECT cm.*, u.username, u.email as user_email, u.phone as user_phone
        FROM contact_message cm 
        LEFT JOIN users u ON cm.user_id = u.id 
        ORDER BY cm.created_at DESC
    ");
}
$stmt->execute();
$result = $stmt->get_result();
$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}
$stmt->close();

// Get statistics
$stats = [];
$stats['total_users'] = count($users);
$stats['total_messages'] = count($messages);
$stats['replied_messages'] = count(array_filter($messages, function($msg) { return !empty($msg['admin_reply']); }));
$stats['pending_messages'] = $stats['total_messages'] - $stats['replied_messages'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Chat Reply - User Car Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        }

        .chat-container {
            max-width: 1200px;
            margin: 2rem auto;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
            display: flex;
            min-height: 80vh;
        }

        .users-sidebar {
            width: 300px;
            background: #f8fafc;
            border-right: 1px solid #e2e8f0;
            overflow-y: auto;
        }

        .users-header {
            background: var(--primary-color);
            color: white;
            padding: 1rem;
            text-align: center;
        }

        .user-item {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-item:hover {
            background: #e2e8f0;
        }

        .user-item.active {
            background: var(--primary-color);
            color: white;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--accent-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .user-meta {
            font-size: 0.8rem;
            opacity: 0.7;
        }

        .message-count {
            background: var(--accent-color);
            color: white;
            border-radius: 12px;
            padding: 0.2rem 0.5rem;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .chat-header {
            background: var(--primary-color);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }

        .chat-messages {
            flex: 1;
            padding: 1.5rem;
            overflow-y: auto;
            background: #f8fafc;
            max-height: 500px;
        }

        .message-item {
            margin-bottom: 1.5rem;
            animation: fadeInUp 0.3s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message-bubble {
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            max-width: 80%;
            word-wrap: break-word;
            position: relative;
        }

        .message-user {
            background: var(--primary-color);
            color: white;
            margin-right: auto;
            border-bottom-left-radius: 4px;
        }

        .message-admin {
            background: var(--accent-color);
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 4px;
        }

        .message-meta {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 0.5rem;
        }

        .message-type-badge {
            position: absolute;
            top: -8px;
            right: 10px;
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .reply-form {
            padding: 1.5rem;
            background: white;
            border-top: 1px solid #e2e8f0;
        }

        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 1rem;
        }

        .no-messages {
            text-align: center;
            padding: 3rem;
            color: var(--secondary-color);
        }

        @media (max-width: 768px) {
            .chat-container {
                flex-direction: column;
                margin: 1rem;
            }
            
            .users-sidebar {
                width: 100%;
                max-height: 200px;
            }
            
            .message-bubble {
                max-width: 90%;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <!-- Message Display -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-info alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="chat-container">
        <!-- Users Sidebar -->
        <div class="users-sidebar">
            <div class="users-header">
                <h5><i class="fas fa-users me-2"></i>Users</h5>
                <small><?php echo $stats['total_users']; ?> users with messages</small>
            </div>
            
            <?php foreach ($users as $user): ?>
                <div class="user-item <?php echo ($selected_user_id == $user['id']) ? 'active' : ''; ?>" 
                     onclick="location.href='admin_chat_reply.php?user_id=<?php echo $user['id']; ?>'">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($user['username']); ?></div>
                        <div class="user-meta">
                            <?php echo htmlspecialchars($user['email']); ?>
                            <br>
                            <small><?php echo date('M j, g:i A', strtotime($user['last_message_time'])); ?></small>
                        </div>
                    </div>
                    <div class="message-count"><?php echo $user['message_count']; ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Chat Main Area -->
        <div class="chat-main">
            <div class="chat-header">
                <h4><i class="fas fa-comments me-2"></i>Admin Chat Reply</h4>
                <?php if ($selected_user_info): ?>
                    <div class="subtitle">
                        Chatting with: <strong><?php echo htmlspecialchars($selected_user_info['username']); ?></strong>
                        <br>
                        <small><?php echo htmlspecialchars($selected_user_info['email']); ?></small>
                    </div>
                <?php else: ?>
                    <div class="subtitle">Select a user to start chatting</div>
                <?php endif; ?>
            </div>

            <div class="chat-messages" id="chatMessages">
                <?php if ($selected_user_id && !empty($messages)): ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="message-item">
                            <!-- User Message -->
                            <div class="message-bubble message-user">
                                <div class="message-type-badge">User</div>
                                <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                <div class="message-meta">
                                    <?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?>
                                </div>
                            </div>

                            <!-- Admin Reply -->
                            <?php if (!empty($msg['admin_reply'])): ?>
                                <div class="message-bubble message-admin">
                                    <div class="message-type-badge">Admin</div>
                                    <?php echo nl2br(htmlspecialchars($msg['admin_reply'])); ?>
                                    <div class="message-meta">
                                        <?php echo date('M j, Y g:i A', strtotime($msg['replied_at'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php elseif ($selected_user_id && empty($messages)): ?>
                    <div class="no-messages">
                        <i class="fas fa-comments fa-3x mb-3"></i>
                        <h5>No messages from this user</h5>
                        <p>This user hasn't sent any messages yet.</p>
                    </div>
                <?php else: ?>
                    <div class="no-messages">
                        <i class="fas fa-comments fa-3x mb-3"></i>
                        <h5>Select a user to start chatting</h5>
                        <p>Choose a user from the sidebar to view and reply to their messages.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Reply Form -->
            <?php if ($selected_user_id): ?>
                <div class="reply-form">
                    <form method="post">
                        <input type="hidden" name="message_id" value="<?php echo end($messages)['id'] ?? ''; ?>">
                        <div class="input-group">
                            <textarea name="admin_reply" class="form-control" rows="2" 
                                      placeholder="Type your reply here..." required></textarea>
                            <button type="submit" name="submit_reply" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-scroll to bottom of chat messages
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }

            // Auto-dismiss alerts
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);

            // Auto-refresh page every 30 seconds to get new messages
            setInterval(function() {
                if (window.location.search.includes('user_id=')) {
                    window.location.reload();
                }
            }, 30000);
        });
    </script>
</body>
</html>
