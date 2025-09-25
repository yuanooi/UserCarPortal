# 角色合并更改总结

## 概述
将原有的三个角色（admin, seller, buyer）合并为两个角色（admin, user）。

## 主要更改

### 1. 数据库结构更改
- **文件**: `cars.sql`
- **更改**: 将 `role` 字段从 `enum('admin','buyer','seller')` 改为 `enum('admin','user')`
- **默认值**: 从 `'buyer'` 改为 `'user'`
- **现有数据**: 提供了 `update_roles.sql` 脚本来更新现有数据库

### 2. 认证和登录逻辑
- **文件**: `login.php`, `index.php`
- **更改**: 
  - 移除了seller重定向到seller_dashboard.php的逻辑
  - 所有非admin用户现在重定向到index.php
  - 注册时不再需要选择角色，默认创建为'user'角色

### 3. 用户界面更新
- **文件**: `header.php`
- **更改**:
  - 合并了buyer和seller的功能菜单
  - 所有用户现在都可以访问：订单、收藏、消息、历史记录、上传车辆、车辆管理、通知等功能
  - 更新了角色检查逻辑

### 4. 文件重命名和更新
- **重命名**: `seller_dashboard.php` → `user_dashboard.php`
- **更新**: 所有引用seller_dashboard.php的地方都已更新

### 5. 权限检查更新
以下文件的角色检查已从seller/buyer更新为user：
- `my_favorites.php`
- `car_detail.php` 
- `car_add.php`
- `cancel_order.php`
- `user_dashboard.php` (原seller_dashboard.php)

### 6. 文本和界面更新
- **文件**: `all_faqs.php`
- **更改**: 将所有"seller"和"buyer"的引用更新为"user"

## 新角色结构

### Admin (管理员)
- 管理所有车辆审核
- 处理订单管理
- 回复用户消息
- 管理评论
- 处理试驾请求

### User (用户)
- 可以购买车辆（原buyer功能）
- 可以出售车辆（原seller功能）
- 管理订单
- 管理收藏
- 发送消息
- 查看历史记录
- 上传车辆
- 管理自己的车辆
- 接收通知

## 部署说明

1. **数据库更新**: 运行 `update_roles.sql` 脚本更新现有用户角色
2. **文件部署**: 部署所有更新的PHP文件
3. **测试**: 验证所有功能是否正常工作

## 注意事项

- `seller_notifications.php` 文件保持不变，因为它实际上是管理员审核车辆的功能
- 所有现有的车辆、订单、收藏等数据保持不变
- 用户权限现在更加统一，所有用户都可以同时作为买家和卖家使用系统
