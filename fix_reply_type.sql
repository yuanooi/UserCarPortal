-- 修复contact_message表的reply_type字段
-- 为现有记录设置默认值

-- 更新现有记录，将空的reply_type设置为'human'（默认值）
UPDATE contact_message 
SET reply_type = 'human' 
WHERE reply_type IS NULL OR reply_type = '';

-- 确保needs_human_reply字段有默认值
UPDATE contact_message 
SET needs_human_reply = 0 
WHERE needs_human_reply IS NULL;

-- 显示更新结果
SELECT 
    COUNT(*) as total_messages,
    SUM(CASE WHEN reply_type = 'ai' THEN 1 ELSE 0 END) as ai_replies,
    SUM(CASE WHEN reply_type = 'human' THEN 1 ELSE 0 END) as human_replies,
    SUM(CASE WHEN reply_type IS NULL OR reply_type = '' THEN 1 ELSE 0 END) as null_replies
FROM contact_message;
