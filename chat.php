<?php
session_start();
include 'includes/db.php';

// Check login status
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?show_login=1");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_role = $_SESSION['role'];

// Get user message history
$messages = [];
$stmt = $conn->prepare("SELECT cm.*, u.username FROM contact_message cm LEFT JOIN users u ON cm.user_id = u.id WHERE cm.user_id = ? ORDER BY cm.created_at DESC");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    $stmt->close();
}

// Handle new message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_message'])) {
    $new_message = trim($_POST['new_message']);
    $user_name = $_POST['user_name'] ?? $username;
    $user_email = $_POST['user_email'] ?? '';
    
    if (!empty($new_message)) {
        // Insert new message
        $stmt = $conn->prepare("INSERT INTO contact_message (user_id, name, email, message, created_at) VALUES (?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("isss", $user_id, $user_name, $user_email, $new_message);
            if ($stmt->execute()) {
                $message_id = $conn->insert_id;
                
                // Call AI processing
                include 'ai_chat_handler.php';
                $ai_handler = new AIChatHandler($conn);
                $ai_result = $ai_handler->processMessage($message_id, $new_message);
                
                // Refresh page to show results
                header("Location: chat.php?ai_processed=1");
                exit();
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Support - User Car Portal</title>
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
            min-height: 100vh;
        }

        .chat-container {
            max-width: 1000px;
            margin: 2rem auto;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .chat-header {
            background: var(--primary-color);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }

        .chat-header h2 {
            margin: 0;
            font-weight: 600;
        }

        .chat-header .subtitle {
            opacity: 0.8;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .chat-messages {
            max-height: 500px;
            overflow-y: auto;
            padding: 1.5rem;
            background: #f8fafc;
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
            margin-left: auto;
            border-bottom-right-radius: 4px;
        }

        .message-ai {
            background: var(--accent-color);
            color: white;
            margin-right: auto;
            border-bottom-left-radius: 4px;
        }

        .message-admin {
            background: #f1f5f9;
            color: #1e293b;
            border: 1px solid #e2e8f0;
            margin-right: auto;
            border-bottom-left-radius: 4px;
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

        .chat-input {
            padding: 1.5rem;
            background: white;
            border-top: 1px solid #e2e8f0;
        }

        .input-group {
            position: relative;
        }

        .form-control {
            border-radius: var(--border-radius);
            border: 1px solid #e2e8f0;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
        }

        .btn-send {
            background: var(--primary-color);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: var(--transition);
        }

        .btn-send:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .ai-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent-color);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        .ai-indicator i {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .typing-indicator {
            display: none;
            padding: 1rem 1.5rem;
            background: #f1f5f9;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
            max-width: 80px;
        }

        .typing-dots {
            display: flex;
            gap: 4px;
        }

        .typing-dots span {
            width: 8px;
            height: 8px;
            background: var(--secondary-color);
            border-radius: 50%;
            animation: typing 1.4s infinite ease-in-out;
        }

        .typing-dots span:nth-child(1) { animation-delay: -0.32s; }
        .typing-dots span:nth-child(2) { animation-delay: -0.16s; }

        @keyframes typing {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1); }
        }

        .alert-info {
            background: rgba(37, 99, 235, 0.1);
            border: 1px solid rgba(37, 99, 235, 0.2);
            color: var(--primary-color);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: var(--accent-color);
        }

        @media (max-width: 768px) {
            .chat-container {
                margin: 1rem;
                border-radius: 0;
            }
            
            .message-bubble {
                max-width: 90%;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="chat-container">
        <div class="chat-header">
            <h2><i class="fas fa-comments me-2"></i>Chat Support</h2>
            <div class="subtitle">AI-powered customer support with human backup</div>
        </div>

        <div class="chat-messages" id="chatMessages">
            <?php if (isset($_GET['ai_processed'])): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-robot me-2"></i>Your message has been processed by our AI assistant!
                </div>
            <?php endif; ?>

            <?php if (empty($messages)): ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-comments fa-3x mb-3"></i>
                    <h5>Start a conversation</h5>
                    <p>Ask me anything about our vehicles, services, or general inquiries!</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="message-item">
                        <!-- User Message -->
                        <div class="message-bubble message-user">
                            <div class="message-type-badge">You</div>
                            <?php echo htmlspecialchars($msg['message']); ?>
                            <div class="message-meta">
                                <?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?>
                            </div>
                        </div>

                        <!-- AI/Admin Reply -->
                        <?php if (!empty($msg['admin_reply'])): ?>
                            <div class="message-bubble <?php echo (isset($msg['reply_type']) && $msg['reply_type'] === 'ai') ? 'message-ai' : 'message-admin'; ?>">
                                <div class="message-type-badge">
                                    <?php echo (isset($msg['reply_type']) && $msg['reply_type'] === 'ai') ? 'AI Assistant' : 'Admin'; ?>
                                </div>
                                <?php echo htmlspecialchars($msg['admin_reply']); ?>
                                <div class="message-meta">
                                    <?php echo date('M j, Y g:i A', strtotime($msg['replied_at'])); ?>
                                    <?php if (isset($msg['reply_type']) && $msg['reply_type'] === 'ai'): ?>
                                        <i class="fas fa-robot ms-2"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php elseif (isset($msg['needs_human_reply']) && $msg['needs_human_reply']): ?>
                            <div class="message-bubble message-admin">
                                <div class="message-type-badge">Admin</div>
                                <i class="fas fa-clock me-2"></i>Your message has been forwarded to our admin team. They will respond shortly.
                                <div class="message-meta">
                                    Forwarded to admin
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="typing-indicator" id="typing-<?php echo $msg['id']; ?>">
                                <div class="typing-dots">
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="chat-input">
            <form method="POST" id="chatForm">
                <div class="input-group">
                    <input type="text" class="form-control" name="new_message" id="newMessage" 
                           placeholder="Type your message here..." required>
                    <button type="submit" class="btn btn-send">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
                <input type="hidden" name="user_name" value="<?php echo htmlspecialchars($username); ?>">
                <input type="hidden" name="user_email" value="">
            </form>
        </div>
    </div>

    <script>
        // Auto-scroll to bottom of chat
        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Show typing indicator
        function showTypingIndicator(messageId) {
            const typingIndicator = document.getElementById('typing-' + messageId);
            if (typingIndicator) {
                typingIndicator.style.display = 'block';
            }
        }

        // Hide typing indicator
        function hideTypingIndicator(messageId) {
            const typingIndicator = document.getElementById('typing-' + messageId);
            if (typingIndicator) {
                typingIndicator.style.display = 'none';
            }
        }

        // Form submission
        document.getElementById('chatForm').addEventListener('submit', function(e) {
            const messageInput = document.getElementById('newMessage');
            const message = messageInput.value.trim();
            
            if (message) {
                // Show AI processing indicator
                const aiIndicator = document.createElement('div');
                aiIndicator.className = 'ai-indicator';
                aiIndicator.innerHTML = '<i class="fas fa-robot"></i> AI is processing your message...';
                
                const chatMessages = document.getElementById('chatMessages');
                chatMessages.appendChild(aiIndicator);
                scrollToBottom();
                
                // Clear input
                messageInput.value = '';
            }
        });

        // Auto-scroll on page load
        document.addEventListener('DOMContentLoaded', function() {
            scrollToBottom();
        });

        // Real-time updates (optional - for future enhancement)
        function checkForUpdates() {
            // This could be implemented with AJAX polling or WebSocket
            // to show real-time AI responses
        }

        // Check for updates every 5 seconds
        setInterval(checkForUpdates, 5000);
    </script>
</body>
</html>
