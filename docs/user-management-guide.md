# 用户管理指南

本指南介绍如何使用飞书应用机器人Bundle的用户管理功能。

## 功能概述

用户管理模块提供了完整的用户信息管理功能，包括：

- 用户信息获取和缓存
- 批量用户查询
- 用户搜索
- 用户权限管理
- 用户数据同步
- 用户行为追踪
- 用户数据导入导出

## 基本使用

### 1. 获取用户信息

```php
use Tourze\LarkAppBotBundle\User\UserService;

$userService = $container->get(UserService::class);

// 通过 open_id 获取用户
$user = $userService->getUser('ou_123456', 'open_id');

// 通过其他ID类型获取
$user = $userService->getUser('user@example.com', 'email');
$user = $userService->getUser('13800138000', 'mobile');

// 只获取特定字段
$user = $userService->getUser('ou_123456', 'open_id', ['name', 'email', 'department_ids']);
```

### 2. 批量获取用户

```php
$userIds = ['ou_123', 'ou_456', 'ou_789'];
$users = $userService->batchGetUsers($userIds, 'open_id');

foreach ($users as $userId => $user) {
    echo "用户 {$user['name']} 的ID是 {$userId}\n";
}
```

### 3. 搜索用户

```php
// 按关键字搜索
$results = $userService->searchUsers([
    'query' => '张三',
    'page_size' => 50,
]);

// 按部门搜索
$results = $userService->searchUsers([
    'department_id' => 'dept_123',
    'include_resigned' => false,
]);

// 处理分页
$pageToken = null;
do {
    $results = $userService->searchUsers([
        'query' => '产品',
        'page_token' => $pageToken,
        'page_size' => 100,
    ]);
    
    foreach ($results['items'] as $item) {
        $user = $item['user'];
        // 处理用户数据
    }
    
    $pageToken = $results['page_token'] ?? null;
} while ($results['has_more'] ?? false);
```

### 4. 权限管理

```php
// 检查用户权限
$hasPermission = $userService->hasPermission('ou_123', 'manage_users');

if ($hasPermission) {
    // 用户有权限
}

// 租户管理员自动拥有所有权限
$user = $userService->getUser('ou_admin', 'open_id');
if ($user['is_tenant_manager'] ?? false) {
    // 是租户管理员
}
```

### 5. 用户关系

```php
// 获取用户的部门列表
$departments = $userService->getUserDepartments('ou_123');
foreach ($departments['items'] as $dept) {
    echo "部门ID: {$dept['department_id']}\n";
    if ($dept['is_primary_dept'] ?? false) {
        echo "这是主部门\n";
    }
}

// 获取用户的上级
$leader = $userService->getUserLeader('ou_123');
if ($leader) {
    echo "上级是: {$leader['name']}\n";
}

// 获取用户的下属
$subordinates = $userService->getUserSubordinates('ou_leader');
foreach ($subordinates as $subordinate) {
    echo "下属: {$subordinate['name']}\n";
}
```

## 高级功能

### 1. 用户数据管理器

UserDataManager 提供了更高级的用户数据管理功能：

```php
use Tourze\LarkAppBotBundle\User\UserDataManager;

$dataManager = $container->get(UserDataManager::class);

// 获取用户完整数据（包括部门、权限、上下级关系等）
$userData = $dataManager->getUserData('ou_123');

// 批量获取用户数据
$userIds = ['ou_123', 'ou_456'];
$usersData = $dataManager->batchGetUserData($userIds);

// 更新用户自定义数据
$dataManager->updateUserCustomData('ou_123', [
    'preferences' => ['theme' => 'dark'],
    'settings' => ['notifications' => true],
]);

// 导出用户数据
$exportData = $dataManager->exportUserData('ou_123', 'open_id', [
    'include_metadata' => true,
    'include_relations' => true,
]);

// 导入用户数据
$userId = $dataManager->importUserData($exportData);

// 刷新用户数据
$refreshedData = $dataManager->refreshUserData('ou_123');
```

### 2. 用户缓存管理

UserCacheManager 提供了灵活的缓存策略：

```php
use Tourze\LarkAppBotBundle\User\UserCacheManager;

$cacheManager = $container->get(UserCacheManager::class);

// 缓存用户数据
$cacheManager->cacheUser('ou_123', 'open_id', $userData);

// 批量缓存
$cacheManager->batchCacheUsers($users, 'open_id');

// 使缓存失效
$cacheManager->invalidateUser('ou_123', 'open_id');

// 预热缓存
$cacheManager->warmupUsers(['ou_123', 'ou_456']);

// 设置缓存时间
$cacheManager->setTtlConfig('user_permissions', 300); // 5分钟

// 获取缓存统计
$stats = $cacheManager->getCacheStats();
```

### 3. 用户同步服务

UserSyncService 负责保持用户数据的最新状态：

```php
use Tourze\LarkAppBotBundle\User\UserSyncService;

$syncService = $container->get(UserSyncService::class);

// 同步单个用户
$syncService->syncUser('ou_123');

// 批量同步
$syncService->batchSyncUsers(['ou_123', 'ou_456']);

// 同步整个部门的用户
$syncService->syncDepartmentUsers('dept_123');

// 强制同步（忽略同步间隔）
$syncService->syncUser('ou_123', 'open_id', true);
```

### 4. 用户行为追踪

UserTracker 记录和分析用户行为：

```php
use Tourze\LarkAppBotBundle\User\UserTracker;

$tracker = $container->get(UserTracker::class);

// 记录用户活动
$tracker->trackActivity('ou_123', 'open_id', 'message_sent', [
    'chat_id' => 'oc_abc',
    'message_type' => 'text',
]);

// 获取用户活动历史
$activities = $tracker->getUserActivities('ou_123');

// 获取在线用户
$onlineUsers = $tracker->getOnlineUsers();

// 批量获取用户状态
$statuses = $tracker->batchGetUserStatus(['ou_123', 'ou_456']);

// 生成用户活动统计
$stats = $tracker->getUserActivityStats('ou_123');

// 生成活动趋势
$trends = $tracker->generateActivityTrends('ou_123', 'daily', 7);
```

### 5. 用户工具类

UserTools 提供了各种实用的工具方法：

```php
use Tourze\LarkAppBotBundle\User\UserTools;

// 验证用户ID类型
UserTools::validateUserIdType('open_id'); // 通过
UserTools::validateUserIdType('invalid'); // 抛出异常

// 提取用户ID
$openId = UserTools::extractUserId($user, 'open_id');
$allIds = UserTools::getAllUserIds($user);

// 格式化显示名称
$displayName = UserTools::formatDisplayName($user, 'zh_CN');
$nameWithTitle = UserTools::formatDisplayName($user, 'zh_CN', true);

// 获取头像URL
$avatarUrl = UserTools::getAvatarUrl($user, '240'); // 240x240
$smallAvatar = UserTools::getAvatarUrl($user, '72'); // 72x72

// 检查用户状态
$status = UserTools::checkUserStatus($user);
if ($status['is_active']) {
    echo "用户状态: {$status['status_text']}\n";
}

// 解析权限
$permissions = UserTools::parsePermissions($user);

// 计算组织层级
$orgLevel = UserTools::calculateOrgLevel($user);

// 格式化联系方式（支持脱敏）
$contact = UserTools::formatContactInfo($user, true);

// 比较用户信息差异
$diff = UserTools::compareUserInfo($oldUser, $newUser);
print_r($diff['changed']);

// 生成用户摘要
$summary = UserTools::generateUserSummary($user);
```

## 事件系统

用户管理模块会触发以下事件：

```php
use Tourze\LarkAppBotBundle\Event\UserEvent;

// 监听用户事件
$eventDispatcher->addListener(UserEvent::USER_CREATED, function (UserEvent $event) {
    $user = $event->getUser();
    $context = $event->getContext();
    // 处理用户创建事件
});

$eventDispatcher->addListener(UserEvent::USER_UPDATED, function (UserEvent $event) {
    $user = $event->getUser();
    $changes = $event->getContext()['changes'] ?? [];
    // 处理用户更新事件
});

$eventDispatcher->addListener(UserEvent::USER_ACTIVITY, function (UserEvent $event) {
    $user = $event->getUser();
    $activityType = $event->getContext()['activity_type'] ?? '';
    // 处理用户活动事件
});
```

### 事件类型

- `USER_CREATED` - 用户创建
- `USER_UPDATED` - 用户更新
- `USER_DELETED` - 用户删除
- `USER_ACTIVITY` - 用户活动
- `USER_DATA_LOADED` - 用户数据加载
- `USER_DATA_UPDATED` - 用户数据更新
- `USER_DATA_DELETED` - 用户数据删除
- `USER_DATA_IMPORTED` - 用户数据导入

## 最佳实践

### 1. 缓存策略

- 用户基本信息缓存1小时
- 权限信息缓存5分钟
- 搜索结果缓存5分钟
- 根据业务需求调整缓存时间

### 2. 批量操作

优先使用批量接口减少API调用：

```php
// 好的做法
$users = $userService->batchGetUsers($userIds);

// 避免的做法
foreach ($userIds as $userId) {
    $user = $userService->getUser($userId); // 会产生N次API调用
}
```

### 3. 错误处理

```php
try {
    $user = $userService->getUser('ou_123');
} catch (ApiException $e) {
    // 处理API错误
    $logger->error('获取用户失败', ['error' => $e->getMessage()]);
} catch (ValidationException $e) {
    // 处理验证错误
    $logger->warning('参数验证失败', ['error' => $e->getMessage()]);
}
```

### 4. 权限设计

建议使用自定义属性存储细粒度权限：

```json
{
  "custom_attrs": [
    {
      "key": "permissions",
      "value": ["read_reports", "manage_users", "export_data"]
    }
  ]
}
```

### 5. 性能优化

- 使用内存缓存减少重复查询
- 合理设置缓存TTL
- 使用预热功能提前加载常用数据
- 定期清理过期的活动记录

## 配置选项

在 `services.yaml` 中配置：

```yaml
services:
  Tourze\LarkAppBotBundle\User\UserService:
    arguments:
      $client: '@Tourze\LarkAppBotBundle\Client\LarkClient'
      $logger: '@logger'
      $cache: '@lark_app_bot.cache'
    tags:
      - { name: monolog.logger, channel: lark }

  Tourze\LarkAppBotBundle\User\UserCacheManager:
    arguments:
      $cache: '@lark_app_bot.cache'
      $logger: '@logger'
    tags:
      - { name: monolog.logger, channel: lark }
```

## 故障排除

### 常见问题

1. **缓存未生效**
   - 检查缓存服务是否正确配置
   - 确认缓存目录有写入权限
   - 查看日志中的缓存相关错误

2. **批量查询失败**
   - 飞书API限制每次最多查询50个用户
   - 检查用户ID格式是否正确
   - 确认使用了正确的ID类型

3. **权限检查失败**
   - 确认用户的custom_attrs中包含permissions字段
   - 检查权限名称拼写是否正确
   - 租户管理员应该拥有所有权限

4. **同步延迟**
   - 默认同步间隔为5分钟
   - 可以使用强制同步立即更新
   - 检查同步服务的日志输出

## 总结

用户管理模块提供了完整的用户信息管理解决方案，通过合理使用缓存、批量操作和事件系统，可以构建高效的用户管理功能。建议根据实际业务需求调整缓存策略和同步频率，以达到最佳性能。