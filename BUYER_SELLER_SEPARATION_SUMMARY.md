# Buyer/Seller 分离功能总结

## 概述
实现了buyer和seller的清晰分离，虽然它们在数据库中都是'user'角色，但通过`user_type`字段来区分功能权限。

## 数据库结构更改

### 新增字段
- **user_type**: `ENUM('buyer','seller')` - 区分用户类型
- **默认值**: 'buyer'

### 表结构
```sql
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `user_type` enum('buyer','seller') NOT NULL DEFAULT 'buyer',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
);
```

## 功能分离

### Buyer (买家) 功能
- **订单管理**: `cancel_order.php`
- **收藏夹**: `my_favorites.php`
- **消息**: `my_messages.php`
- **浏览历史**: `history.php`
- **车辆详情查看**: `car_detail.php`
- **联系卖家**: WhatsApp功能

### Seller (卖家) 功能
- **上传车辆**: `car_add.php`
- **车辆管理**: `user_dashboard.php`
- **通知管理**: `seller_notifications.php`
- **车辆审核**: 等待管理员审核

### Admin (管理员) 功能
- 所有管理功能
- 车辆审核
- 订单管理
- 用户管理

## 登录重定向逻辑

### 登录后重定向
- **Admin**: → `admin_dashboard.php`
- **Seller**: → `user_dashboard.php`
- **Buyer**: → `index.php`

### 权限检查
```php
// Buyer功能检查
if ($_SESSION['role'] === 'user' && $_SESSION['user_type'] === 'buyer')

// Seller功能检查  
if ($_SESSION['role'] === 'user' && $_SESSION['user_type'] === 'seller')

// Admin功能检查
if ($_SESSION['role'] === 'admin')
```

## 用户界面更新

### 角色显示
- **页面顶部**: 显示欢迎消息和角色徽章
- **导航栏**: 显示用户名和角色标识
- **颜色编码**:
  - Admin: 红色徽章
  - Seller: 绿色徽章  
  - Buyer: 蓝色徽章

### 导航菜单
- **Buyer**: 只显示买家相关功能
- **Seller**: 只显示卖家相关功能
- **Admin**: 显示所有管理功能

## 注册流程

### 用户类型选择
- 注册时必须选择用户类型
- 选项: "Buyer (购买车辆)" 或 "Seller (出售车辆)"
- 默认值: buyer

### 表单验证
- 用户类型为必填字段
- 数据库存储对应的user_type值

## 数据库更新脚本

### 运行 `update_roles.sql`
```sql
-- 添加user_type字段
ALTER TABLE users ADD COLUMN user_type ENUM('buyer','seller') NOT NULL DEFAULT 'buyer';

-- 更新现有用户
UPDATE users SET role = 'user', user_type = 'buyer' WHERE role = 'buyer';
UPDATE users SET role = 'user', user_type = 'seller' WHERE role = 'seller';
UPDATE users SET user_type = 'buyer' WHERE role = 'admin';
```

## 测试账户

### 现有测试账户
- **buyer@gmail.com**: Buyer用户
- **seller@gmail.com**: Seller用户  
- **admin@gmail.com**: Admin用户

### 登录测试
1. 使用buyer邮箱登录 → 重定向到index.php
2. 使用seller邮箱登录 → 重定向到user_dashboard.php
3. 使用admin邮箱登录 → 重定向到admin_dashboard.php

## 权限控制

### 页面访问控制
- **my_favorites.php**: 仅buyer可访问
- **cancel_order.php**: 仅buyer可访问
- **user_dashboard.php**: 仅seller可访问
- **car_add.php**: seller和admin可访问

### 功能按钮控制
- 导航栏根据用户类型显示不同功能
- 车辆详情页面的功能按钮根据用户类型显示

## 部署步骤

1. **运行数据库更新脚本**
   ```bash
   mysql -u root -p car_portal < update_roles.sql
   ```

2. **部署更新的PHP文件**
   - 所有PHP文件已更新
   - 包含新的权限检查逻辑

3. **测试功能**
   - 测试不同用户类型的登录
   - 验证权限控制是否正常工作
   - 检查导航菜单显示

## 注意事项

- 保持向后兼容性
- 现有数据会自动迁移
- 所有功能保持原有逻辑
- 只是增加了权限分离
