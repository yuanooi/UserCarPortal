-- 更新聊天系统以支持AI回复功能
-- 为contact_message表添加新字段

-- 添加AI回复相关字段
ALTER TABLE contact_message 
ADD COLUMN reply_type ENUM('human', 'ai') DEFAULT 'human' COMMENT '回复类型：human=人工回复，ai=AI回复',
ADD COLUMN needs_human_reply TINYINT(1) DEFAULT 0 COMMENT '是否需要人工回复：0=不需要，1=需要',
ADD COLUMN ai_processed_at TIMESTAMP NULL COMMENT 'AI处理时间',
ADD COLUMN ai_confidence_score DECIMAL(3,2) DEFAULT NULL COMMENT 'AI回复置信度分数';

-- 创建管理员通知表
CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    notification_type ENUM('human_reply_needed', 'urgent_message') DEFAULT 'human_reply_needed',
    title VARCHAR(255) NOT NULL,
    content TEXT,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (message_id) REFERENCES contact_message(id) ON DELETE CASCADE
);

-- 创建AI回复日志表
CREATE TABLE IF NOT EXISTS ai_reply_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    user_message TEXT NOT NULL,
    ai_reply TEXT,
    confidence_score DECIMAL(3,2),
    processing_time_ms INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES contact_message(id) ON DELETE CASCADE
);

-- 创建索引以提高查询性能
CREATE INDEX idx_contact_message_needs_human_reply ON contact_message(needs_human_reply);
CREATE INDEX idx_contact_message_reply_type ON contact_message(reply_type);
CREATE INDEX idx_admin_notifications_is_read ON admin_notifications(is_read);
CREATE INDEX idx_ai_reply_logs_message_id ON ai_reply_logs(message_id);
