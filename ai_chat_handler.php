<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'includes/db.php';

// AI Chat Handler - Handle AI auto-reply and notify admin
class AIChatHandler {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    // AI reply processing function
    public function processMessage($message_id, $user_message, $vehicle_info = null) {
        // Try AI auto-reply
        $ai_reply = $this->generateAIResponse($user_message, $vehicle_info);
        
        if ($ai_reply && $ai_reply !== 'UNABLE_TO_REPLY') {
            // AI can reply, save reply directly
            $this->saveAIResponse($message_id, $ai_reply);
            return [
                'success' => true,
                'type' => 'ai_reply',
                'message' => 'AI has automatically replied to user message',
                'ai_reply' => $ai_reply
            ];
        } else {
            // AI cannot reply, notify admin
            $this->notifyAdmin($message_id, $user_message, $vehicle_info);
            return [
                'success' => true,
                'type' => 'admin_notification',
                'message' => 'AI cannot reply, admin has been notified'
            ];
        }
    }
    
    // AI reply generation function
    private function generateAIResponse($user_message, $vehicle_info = null) {
        $message = strtolower(trim($user_message));
        
        // If there's vehicle information, add vehicle-specific replies
        if ($vehicle_info) {
            $vehicle_responses = [
                'best price' => "For the best price on this $vehicle_info, I recommend contacting our sales team directly. They can provide you with current promotions and financing options.",
                'test drive' => "Yes! Test drives are available for this $vehicle_info. Please contact us to schedule an appointment at your convenience.",
                'financing' => "We offer various financing options for this $vehicle_info. Our team can help you find the best rates and terms based on your credit profile.",
                'vehicle history' => "For detailed vehicle history and condition information about this $vehicle_info, our admin team will provide you with a comprehensive report.",
                'maintenance' => "Maintenance costs for this $vehicle_info vary based on age and mileage. Our service team can provide you with estimated maintenance schedules and costs.",
                'availability' => "This $vehicle_info is currently available. Please contact us to check availability and schedule a viewing.",
                'warranty' => "Warranty information for this $vehicle_info depends on the year and previous ownership. Our admin team can provide specific warranty details.",
                'insurance' => "Insurance costs for this $vehicle_info can be estimated using our insurance calculator above. For accurate quotes, contact our insurance partners."
            ];
            
            foreach ($vehicle_responses as $keyword => $response) {
                if (strpos($message, $keyword) !== false) {
                    return $response;
                }
            }
        }
        
        // 常见问题回复模板
        $responses = [
            // 问候语
            'hello' => 'Hello! How can I help you today?',
            'hi' => 'Hi there! What can I assist you with?',
            'halo' => 'Hello! How can I help you today?',
            'hey' => 'Hey there! What can I do for you?',
            'good morning' => 'Good morning! How may I help you?',
            'good afternoon' => 'Good afternoon! What can I do for you?',
            'good evening' => 'Good evening! How can I assist you?',
            'greetings' => 'Greetings! How can I help you today?',
            
            // 车辆相关问题
            'car' => 'I can help you with car-related questions. What specific information do you need about our vehicles?',
            'vehicle' => 'I\'d be happy to help with vehicle information. What would you like to know?',
            'price' => 'For pricing information, please specify which vehicle you\'re interested in, and I\'ll provide the details.',
            'availability' => 'To check vehicle availability, please let me know which specific model you\'re looking for.',
            'cost' => 'For pricing information, please specify which vehicle you\'re interested in, and I\'ll provide the details.',
            'expensive' => 'Our vehicles are competitively priced. What specific model are you interested in?',
            'cheap' => 'We offer vehicles at various price points. What\'s your budget range?',
            
            // 服务相关
            'service' => 'Our service team is here to help. What type of service do you need?',
            'maintenance' => 'For maintenance inquiries, please specify your vehicle model and the type of maintenance needed.',
            'repair' => 'I can help with repair information. What seems to be the issue with your vehicle?',
            
            // 联系信息
            'contact' => 'You can reach us at our main office or through our website. Is there something specific you\'d like to discuss?',
            'phone' => 'For immediate assistance, please call our customer service line. What can I help you with?',
            'email' => 'You can email us for detailed inquiries. What information do you need?',
            
            // 营业时间
            'hours' => 'Our business hours are Monday to Friday, 9 AM to 6 PM, and Saturday 9 AM to 4 PM. We\'re closed on Sundays.',
            'open' => 'We\'re open Monday to Friday 9 AM-6 PM and Saturday 9 AM-4 PM. How can I help you?',
            
            // 位置信息
            'location' => 'We\'re located at our main showroom. Would you like directions or more specific location information?',
            'address' => 'Our address is available on our website. Is there something specific you\'re looking for?',
            
            // 预约相关
            'appointment' => 'To schedule an appointment, please let me know what type of service you need and your preferred date.',
            'booking' => 'I can help with booking information. What would you like to schedule?',
            'test drive' => 'Test drives are available by appointment. What vehicle are you interested in testing?',
            
            // Payment related
            'payment' => 'We accept various payment methods including cash, credit cards, and financing options. What payment method are you considering?',
            'finance' => 'We offer financing options for qualified buyers. Would you like information about our financing programs?',
            'loan' => 'We can help with auto loan information. What type of financing are you looking for?',
            
            // 保险相关
            'insurance' => 'We can provide information about insurance options. What type of coverage are you interested in?',
            
            // 感谢和结束语
            'thank you' => 'You\'re welcome! Is there anything else I can help you with?',
            'thanks' => 'My pleasure! Feel free to ask if you have any other questions.',
            'bye' => 'Goodbye! Have a great day and feel free to contact us anytime.',
            'goodbye' => 'Take care! We\'re here whenever you need assistance.',
            
            // 通用回复
            'help' => 'I\'m here to help! What can I assist you with today?',
            'information' => 'I\'d be happy to provide information. What would you like to know?',
            'question' => 'I\'m here to answer your questions. What would you like to ask?',
            'support' => 'Our support team is here to help. What do you need assistance with?',
            'assistance' => 'I\'m here to assist you. How can I help?',
            'need' => 'I\'m here to help with your needs. What are you looking for?',
            'want' => 'I\'d be happy to help you find what you\'re looking for. What do you need?',
            'looking' => 'I can help you find what you\'re looking for. What do you need?',
            'find' => 'I can help you find what you need. What are you looking for?',
            'search' => 'I can help you search for information. What are you looking for?',
            'buy' => 'I can help you with purchasing information. What vehicle are you interested in?',
            'purchase' => 'I can help you with purchasing information. What vehicle are you interested in?',
            'sell' => 'I can help you with selling information. What vehicle are you looking to sell?',
            'trade' => 'I can help you with trade-in information. What vehicle are you looking to trade?'
        ];
        
        // 检查关键词匹配
        foreach ($responses as $keyword => $response) {
            if (strpos($message, $keyword) !== false) {
                return $response;
            }
        }
        
        // 检查多个关键词组合
        $multi_keywords = [
            'car,price' => 'For car pricing information, please specify the make and model you\'re interested in.',
            'vehicle,available' => 'To check vehicle availability, please let me know which specific model you\'re looking for.',
            'test,drive' => 'Test drives are available by appointment. What vehicle would you like to test drive?',
            'financing,options' => 'We offer various financing options. What type of financing are you looking for?',
            'service,appointment' => 'To schedule a service appointment, please let me know what type of service you need.',
            'insurance,quote' => 'For insurance quotes, please provide your vehicle details and coverage preferences.',
            'warranty,information' => 'Warranty information varies by vehicle. What specific warranty details do you need?',
            'delivery,time' => 'Delivery times vary depending on vehicle availability and location. What vehicle are you interested in?'
        ];
        
        foreach ($multi_keywords as $keywords => $response) {
            $keyword_array = explode(',', $keywords);
            $match_count = 0;
            foreach ($keyword_array as $keyword) {
                if (strpos($message, trim($keyword)) !== false) {
                    $match_count++;
                }
            }
            if ($match_count >= 2) {
                return $response;
            }
        }
        
        // 如果无法匹配任何关键词，返回无法回复
        return 'UNABLE_TO_REPLY';
    }
    
    // 保存AI回复到数据库
    private function saveAIResponse($message_id, $ai_reply) {
        $stmt = $this->conn->prepare("UPDATE contact_message SET admin_reply = ?, replied_at = NOW(), reply_type = 'ai', needs_human_reply = 0 WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $ai_reply, $message_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // 通知管理员
    private function notifyAdmin($message_id, $user_message, $vehicle_info = null) {
        // 在数据库中标记需要人工回复
        $stmt = $this->conn->prepare("UPDATE contact_message SET needs_human_reply = 1, ai_processed_at = NOW() WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $message_id);
            $stmt->execute();
            $stmt->close();
        }
        
        // 这里可以添加其他通知方式，比如：
        // - 发送邮件给管理员
        // - 发送短信通知
        // - 推送到管理面板
        // - 记录到日志文件
        
        $this->logAdminNotification($message_id, $user_message, $vehicle_info);
    }
    
    // 记录管理员通知日志
    private function logAdminNotification($message_id, $user_message, $vehicle_info = null) {
        $vehicle_context = $vehicle_info ? " (Vehicle: $vehicle_info)" : "";
        $log_message = "[" . date('Y-m-d H:i:s') . "] Message ID: $message_id needs human reply$vehicle_context. User message: " . substr($user_message, 0, 100) . "...\n";
        error_log($log_message, 3, "logs/admin_notifications.log");
    }
    
    // 获取需要人工回复的消息
    public function getMessagesNeedingHumanReply() {
        $stmt = $this->conn->prepare("SELECT cm.*, u.username FROM contact_message cm LEFT JOIN users u ON cm.user_id = u.id WHERE cm.needs_human_reply = 1 AND cm.admin_reply IS NULL ORDER BY cm.created_at DESC");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            $messages = [];
            while ($row = $result->fetch_assoc()) {
                $messages[] = $row;
            }
            $stmt->close();
            return $messages;
        }
        return [];
    }
    
    // 标记消息为已处理
    public function markAsProcessed($message_id) {
        $stmt = $this->conn->prepare("UPDATE contact_message SET needs_human_reply = 0 WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $message_id);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// 处理AJAX请求
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // 设置错误处理
    error_reporting(E_ALL);
    ini_set('display_errors', 0); // 不显示错误，只记录到JSON响应
    
    header('Content-Type: application/json');
    
    try {
        $ai_handler = new AIChatHandler($conn);
    
    switch ($_POST['action']) {
        case 'vehicle_question':
            if (isset($_POST['user_message']) && isset($_SESSION['user_id'])) {
                $user_id = $_SESSION['user_id'];
                $user_message = $_POST['user_message'];
                $vehicle_id = isset($_POST['vehicle_id']) ? $_POST['vehicle_id'] : null;
                $vehicle_info = isset($_POST['vehicle_info']) ? $_POST['vehicle_info'] : null;
                
                // 创建消息记录
                $stmt = $conn->prepare("INSERT INTO contact_message (user_id, name, email, message, created_at) VALUES (?, ?, ?, ?, NOW())");
                if ($stmt) {
                    $username = $_SESSION['username'] ?? 'User';
                    $email = $_SESSION['email'] ?? '';
                    $stmt->bind_param("isss", $user_id, $username, $email, $user_message);
                    if ($stmt->execute()) {
                        $message_id = $conn->insert_id;
                        
                        // 处理AI回复
                        $result = $ai_handler->processMessage($message_id, $user_message, $vehicle_info);
                        echo json_encode($result);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to create message']);
                    }
                    $stmt->close();
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database error']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Missing parameters or not logged in']);
            }
            break;
            
        case 'process_message':
            if (isset($_POST['message_id']) && isset($_POST['user_message'])) {
                $vehicle_info = isset($_POST['vehicle_info']) ? $_POST['vehicle_info'] : null;
                $result = $ai_handler->processMessage($_POST['message_id'], $_POST['user_message'], $vehicle_info);
                echo json_encode($result);
            } else {
                echo json_encode(['success' => false, 'message' => 'Missing parameters']);
            }
            break;
            
        case 'get_pending_messages':
            $messages = $ai_handler->getMessagesNeedingHumanReply();
            echo json_encode(['success' => true, 'messages' => $messages]);
            break;
            
        case 'mark_processed':
            if (isset($_POST['message_id'])) {
                $ai_handler->markAsProcessed($_POST['message_id']);
                echo json_encode(['success' => true, 'message' => 'Message marked as processed']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Missing message ID']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage(),
            'error_type' => 'exception'
        ]);
    } catch (Error $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Fatal error: ' . $e->getMessage(),
            'error_type' => 'fatal'
        ]);
    }
    exit;
}
?>
