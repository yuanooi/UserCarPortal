<?php
session_start();
include 'includes/db.php';

// Check admin permissions
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?show_login=1");
    exit();
}

include 'ai_chat_handler.php';
$ai_handler = new AIChatHandler($conn);

// Get messages that need human reply
$pending_messages = $ai_handler->getMessagesNeedingHumanReply();

// Get all messages (including processed ones)
$all_messages = [];
$stmt = $conn->prepare("SELECT cm.*, u.username FROM contact_message cm LEFT JOIN users u ON cm.user_id = u.id ORDER BY cm.created_at DESC LIMIT 50");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $all_messages[] = $row;
    }
    $stmt->close();
}

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $message_id = intval($_POST['message_id']);
    $admin_reply = trim($_POST['admin_reply']);
    
    if (!empty($admin_reply)) {
        $stmt = $conn->prepare("UPDATE contact_message SET admin_reply = ?, replied_at = NOW(), reply_type = 'human', needs_human_reply = 0 WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $admin_reply, $message_id);
            if ($stmt->execute()) {
                $success_message = "Reply sent successfully!";
                // Refresh page
                header("Location: admin_chat_dashboard.php?success=1");
                exit();
            }
            $stmt->close();
        }
    }
}

// Handle mark as processed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_processed'])) {
    $message_id = intval($_POST['message_id']);
    $ai_handler->markAsProcessed($message_id);
    header("Location: admin_chat_dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Chat Dashboard - User Car Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --accent-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
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

        .dashboard-header {
            background: white;
            padding: 2rem 0;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 2rem;
        }

        .stats-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            border-left: 4px solid var(--primary-color);
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .stats-card.pending {
            border-left-color: var(--warning-color);
        }

        .stats-card.ai-replies {
            border-left-color: var(--accent-color);
        }

        .stats-card.total {
            border-left-color: var(--secondary-color);
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stats-label {
            color: var(--secondary-color);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .message-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
        }

        .message-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .message-card.pending {
            border-left: 4px solid var(--warning-color);
        }

        .message-card.ai-replied {
            border-left: 4px solid var(--accent-color);
        }

        .message-card.human-replied {
            border-left: 4px solid var(--primary-color);
        }

        .message-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .message-user {
            font-weight: 600;
            color: var(--primary-color);
        }

        .message-time {
            color: var(--secondary-color);
            font-size: 0.85rem;
        }

        .message-content {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 3px solid var(--primary-color);
        }

        .reply-form {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
            display: none;
        }

        .reply-form.active {
            display: block;
        }

        .btn-reply {
            background: var(--primary-color);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            transition: var(--transition);
        }

        .btn-reply:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .btn-mark-processed {
            background: var(--accent-color);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            transition: var(--transition);
        }

        .btn-mark-processed:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .badge-ai {
            background: var(--accent-color);
            color: white;
        }

        .badge-human {
            background: var(--primary-color);
            color: white;
        }

        .badge-pending {
            background: var(--warning-color);
            color: white;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: var(--accent-color);
        }

        .tabs-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
        }

        .nav-tabs {
            border-bottom: 1px solid #e2e8f0;
            padding: 0 1rem;
        }

        .nav-tabs .nav-link {
            border: none;
            color: var(--secondary-color);
            font-weight: 500;
            padding: 1rem 1.5rem;
            transition: var(--transition);
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
        }

        .tab-content {
            padding: 1.5rem;
        }

        @media (max-width: 768px) {
            .stats-card {
                margin-bottom: 1rem;
            }
            
            .message-card {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="dashboard-header">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h1 class="text-center mb-0">
                        <i class="fas fa-robot me-2"></i>AI Chat Dashboard
                    </h1>
                    <p class="text-center text-muted mt-2">Monitor AI responses and handle human intervention</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle me-2"></i>Reply sent successfully!
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card pending">
                    <div class="stats-number text-warning"><?php echo count($pending_messages); ?></div>
                    <div class="stats-label">Pending Human Reply</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card ai-replies">
                    <div class="stats-number text-success">
                        <?php 
                        $ai_count = 0;
                        foreach ($all_messages as $msg) {
                            if (isset($msg['reply_type']) && $msg['reply_type'] === 'ai') $ai_count++;
                        }
                        echo $ai_count;
                        ?>
                    </div>
                    <div class="stats-label">AI Replies</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number text-primary">
                        <?php 
                        $human_count = 0;
                        foreach ($all_messages as $msg) {
                            if (isset($msg['reply_type']) && $msg['reply_type'] === 'human') $human_count++;
                        }
                        echo $human_count;
                        ?>
                    </div>
                    <div class="stats-label">Human Replies</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card total">
                    <div class="stats-number text-secondary"><?php echo count($all_messages); ?></div>
                    <div class="stats-label">Total Messages</div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs-container">
            <ul class="nav nav-tabs" id="chatTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
                        <i class="fas fa-clock me-2"></i>Pending Replies (<?php echo count($pending_messages); ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab">
                        <i class="fas fa-list me-2"></i>All Messages
                    </button>
                </li>
            </ul>
            <div class="tab-content" id="chatTabContent">
                <!-- Pending Replies Tab -->
                <div class="tab-pane fade show active" id="pending" role="tabpanel">
                    <?php if (empty($pending_messages)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                            <h5>All caught up!</h5>
                            <p>No messages require human intervention at the moment.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pending_messages as $msg): ?>
                            <div class="message-card pending">
                                <div class="message-header">
                                    <div>
                                        <div class="message-user">
                                            <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($msg['username'] ?? $msg['name']); ?>
                                        </div>
                                        <div class="message-time">
                                            <i class="fas fa-clock me-1"></i><?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?>
                                        </div>
                                    </div>
                                    <span class="badge badge-pending">Needs Human Reply</span>
                                </div>
                                
                                <div class="message-content">
                                    <strong>Message:</strong><br>
                                    <?php echo htmlspecialchars($msg['message']); ?>
                                </div>

                                <div class="d-flex gap-2">
                                    <button class="btn btn-reply" onclick="toggleReplyForm('reply-form-<?php echo $msg['id']; ?>')">
                                        <i class="fas fa-reply me-1"></i>Reply
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                        <button type="submit" name="mark_processed" class="btn btn-mark-processed">
                                            <i class="fas fa-check me-1"></i>Mark as Processed
                                        </button>
                                    </form>
                                </div>

                                <form class="reply-form" id="reply-form-<?php echo $msg['id']; ?>" method="POST">
                                    <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                    <div class="mb-3">
                                        <label for="admin_reply-<?php echo $msg['id']; ?>" class="form-label">Your Reply</label>
                                        <textarea class="form-control" id="admin_reply-<?php echo $msg['id']; ?>" name="admin_reply" rows="3" required placeholder="Type your reply here..."></textarea>
                                    </div>
                                    <button type="submit" name="reply_message" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-1"></i>Send Reply
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- All Messages Tab -->
                <div class="tab-pane fade" id="all" role="tabpanel">
                    <?php if (empty($all_messages)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-comments fa-3x mb-3"></i>
                            <h5>No messages yet</h5>
                            <p>Messages will appear here as users start conversations.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($all_messages as $msg): ?>
                            <div class="message-card <?php echo (isset($msg['reply_type']) && $msg['reply_type'] === 'ai') ? 'ai-replied' : ((isset($msg['reply_type']) && $msg['reply_type'] === 'human') ? 'human-replied' : 'pending'); ?>">
                                <div class="message-header">
                                    <div>
                                        <div class="message-user">
                                            <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($msg['username'] ?? $msg['name']); ?>
                                        </div>
                                        <div class="message-time">
                                            <i class="fas fa-clock me-1"></i><?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?>
                                        </div>
                                    </div>
                                    <?php if (isset($msg['reply_type']) && $msg['reply_type'] === 'ai'): ?>
                                        <span class="badge badge-ai">AI Reply</span>
                                    <?php elseif (isset($msg['reply_type']) && $msg['reply_type'] === 'human'): ?>
                                        <span class="badge badge-human">Human Reply</span>
                                    <?php else: ?>
                                        <span class="badge badge-pending">Pending</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="message-content">
                                    <strong>Message:</strong><br>
                                    <?php echo htmlspecialchars($msg['message']); ?>
                                </div>

                                <?php if (!empty($msg['admin_reply'])): ?>
                                    <div class="message-content">
                                        <strong>Reply:</strong><br>
                                        <?php echo htmlspecialchars($msg['admin_reply']); ?>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                Replied: <?php echo date('M j, Y g:i A', strtotime($msg['replied_at'])); ?>
                                                <?php if (isset($msg['reply_type']) && $msg['reply_type'] === 'ai'): ?>
                                                    <i class="fas fa-robot ms-2"></i>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleReplyForm(formId) {
            const form = document.getElementById(formId);
            form.classList.toggle('active');
        }

        // Auto-refresh every 30 seconds to check for new messages
        setInterval(function() {
            if (document.getElementById('pending-tab').classList.contains('active')) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>
