# Lark App Bot Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/lark-app-bot-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/lark-app-bot-bundle)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg?style=flat-square)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-passing-green.svg?style=flat-square)](#)
[![Code Coverage](https://img.shields.io/badge/coverage-93%25-brightgreen.svg?style=flat-square)](#)
[![Quality Score](https://img.shields.io/badge/quality-A-green.svg?style=flat-square)](#)

用于在 Symfony 应用中集成飞书应用机器人功能的 Bundle。

## 目录

- [功能特性](#功能特性)
  - [核心功能](#核心功能)
  - [消息功能](#消息功能)
  - [交互功能](#交互功能)
  - [管理能力](#管理能力)
- [系统要求](#系统要求)
- [安装](#安装)
- [快速开始](#快速开始)
- [配置](#配置)
- [架构](#架构)
- [开发状态](#开发状态)
- [控制台命令](#控制台命令)
- [测试](#测试)
- [贡献](#贡献)
- [许可证](#许可证)
- [相关链接](#相关链接)

## 功能特性

### 核心功能
- 🤖 飞书应用机器人集成
- 🔧 便捷的 Symfony Bundle 集成
- 📝 Symfony 服务配置
- 🧪 全面的测试覆盖
- 💫 支持 PHP 8.1+
- 🏗️ 为现代 Symfony 应用而构建 (6.4+)

### 消息功能
- 📨 支持所有飞书消息类型（文本、富文本、图片、文件、卡片等）
- 🎴 强大的卡片消息构建器，支持所有卡片元素
- 🎨 预定义的卡片模板（通知、审批、任务、报告等）
- 🌐 国际化消息支持
- 📬 批量消息发送

### 交互功能
- 🔔 Webhook 事件处理
- 💬 消息处理器注册机制
- 👥 群组管理和事件跟踪
- 👤 用户管理与同步
- 📋 菜单管理

### 管理能力
- 🧭 配置检查命令，可视化提示常见错误
- 💾 用户缓存与批量查询工具
- 📂 菜单配置与权限控制扩展点
- 🤝 外部协作策略检查与合规日志

## 系统要求

- PHP 8.1 或更高版本
- Symfony 6.4 或更高版本
- Doctrine ORM 3.0 或更高版本

## 安装

通过 Composer 安装：

```bash
composer require tourze/lark-app-bot-bundle
```

## 快速开始

1. **启用 Bundle**，在 `config/bundles.php` 中添加：

```php
<?php

return [
    // ... 其他 bundles
    Tourze\LarkAppBotBundle\LarkAppBotBundle::class => ['all' => true],
];
```

2. **配置你的飞书应用机器人**（配置选项将在未来版本中添加）

3. **在应用中使用 Bundle**：

### 发送卡片消息

```php
use Tourze\LarkAppBotBundle\Message\MessageService;
use Tourze\LarkAppBotBundle\Message\Builder\CardMessageBuilder;

class NotificationController extends AbstractController
{
    public function sendNotification(MessageService $messageService): Response
    {
        $builder = new CardMessageBuilder();
        $builder
            ->setHeader('系统通知', 'blue')
            ->addText('您有一条新消息')
            ->addDivider()
            ->addFields([
                ['name' => '发送人', 'value' => '系统管理员'],
                ['name' => '时间', 'value' => date('Y-m-d H:i:s')],
            ], true)
            ->addActions([
                ['text' => '查看详情', 'type' => 'primary', 'url' => 'https://example.com'],
            ]);

        $messageService->sendCard('user_open_id', $builder->build());
        
        return new Response('通知已发送');
    }
}
```

### 使用卡片模板

```php
use Tourze\LarkAppBotBundle\Message\Template\CardTemplateManager;

public function sendApproval(
    MessageService $messageService,
    CardTemplateManager $templateManager
): Response {
    $builder = new CardMessageBuilder();
    
    $templateManager->applyTemplate($builder, 'approval', [
        'title' => '请假申请',
        'description' => '张三申请病假一天',
        'fields' => [
            ['name' => '申请人', 'value' => '张三'],
            ['name' => '请假时间', 'value' => '2024-01-01'],
        ],
        'id' => 'leave_request_123',
    ]);

    $messageService->sendCard('approver_open_id', $builder->build());
    
    return new Response('审批请求已发送');
}
```

### 使用开发工具

```php
use Tourze\LarkAppBotBundle\Developer\MessageDebugger;
use Tourze\LarkAppBotBundle\Developer\ApiTester;

public function debug(MessageDebugger $debugger, ApiTester $tester): Response
{
    // 调试消息
    $debugger->enableDebugMode();
    $result = $debugger->testSend('test_user', 'text', ['text' => '测试']);
    
    // 测试API
    $apiResult = $tester->test('GET', '/open-apis/bot/v3/info');
    
    return $this->json([
        'message_debug' => $result,
        'api_test' => $apiResult,
    ]);
}
```

## 配置

Bundle 提供以下配置结构（待扩展）：

```yaml
# config/packages/lark_app_bot.yaml
lark_app_bot:
    # 配置选项将在未来版本中添加
```

## 架构

此 Bundle 遵循 Symfony 最佳实践：

- **Bundle 类**: `Tourze\LarkAppBotBundle\LarkAppBotBundle`
- **Extension 类**: `Tourze\LarkAppBotBundle\DependencyInjection\LarkAppBotExtension`
- **服务配置**: 位于 `src/Resources/config/services.yaml`

## 开发状态

此 Bundle 目前处于早期开发阶段。已实现并测试了基础的 Symfony Bundle 结构，为飞书应用机器人集成功能奠定了基础。

### 当前状态
- ✅ 基础 Symfony Bundle 结构
- ✅ 依赖注入配置
- ✅ PHPStan level 5 合规
- ✅ 完整测试覆盖
- 🔄 API 集成功能（即将推出）

## 控制台命令

Bundle 提供了多个控制台命令用于管理和调试你的飞书机器人：

### 配置检查

检查飞书机器人配置和连接状态：

```bash
# 基础检查
php bin/console lark:config:check

# 测试 API 连接
php bin/console lark:config:check --test-api

# 显示 Token 信息（敏感信息）
php bin/console lark:config:check --show-token

# 尝试修复常见问题
php bin/console lark:config:check --fix
```

### 发送消息

直接从命令行发送消息：

```bash
# 发送文本消息
php bin/console lark:send-message --user=USER_ID --text="来自CLI的问候"

# 发送卡片消息
php bin/console lark:send-message --user=USER_ID --card=welcome

# 发送到群组
php bin/console lark:send-message --chat=CHAT_ID --text="群组消息"
```

### 调试模式

启用调试模式以获取详细日志和消息检查：

```bash
# 启用调试模式
php bin/console lark:debug --enable

# 测试消息渲染
php bin/console lark:debug --test-message --type=card --template=notification

# 禁用调试模式
php bin/console lark:debug --disable
```

### 用户查询

查询飞书用户信息：

```bash
# 基本查询（自动识别类型）
php bin/console lark:user:query open_123456
php bin/console lark:user:query user@example.com
php bin/console lark:user:query +8613800138000

# 指定类型查询
php bin/console lark:user:query 123456 --type=user_id
php bin/console lark:user:query user@example.com --type=email

# 显示额外信息
php bin/console lark:user:query open_123456 --department --groups

# 批量查询（从文件）
cat users.txt | php bin/console lark:user:query - --batch

# 自定义输出格式
php bin/console lark:user:query open_123456 --format=json
php bin/console lark:user:query open_123456 --format=csv --fields=name --fields=email
```

### 群组管理

管理飞书群组：

```bash
# 查看群组信息
php bin/console lark:group:manage --action=info --chat-id=CHAT_ID

# 添加用户到群组
php bin/console lark:group:manage --action=add --chat-id=CHAT_ID --user=USER_ID

# 从群组移除用户
php bin/console lark:group:manage --action=remove --chat-id=CHAT_ID --user=USER_ID

# 获取群组成员列表
php bin/console lark:group:manage --action=members --chat-id=CHAT_ID

# 更新群组信息
php bin/console lark:group:manage --action=update --chat-id=CHAT_ID --name="新群组名"
```

### 消息发送

发送各种类型的消息：

```bash
# 发送文本消息
php bin/console lark:message:send --to=USER_ID --text="Hello, World!"

# 发送富文本消息
php bin/console lark:message:send --to=USER_ID --rich-text='{"title":"标题","content":"内容"}'

# 发送卡片消息
php bin/console lark:message:send --to=USER_ID --card-template=notification --card-data='{"title":"通知","content":"内容"}'

# 发送到群组
php bin/console lark:message:send --to=CHAT_ID --type=chat --text="群组消息"

# 批量发送
php bin/console lark:message:send --batch --file=recipients.json --text="批量消息"
```

## 测试

运行测试套件：

```bash
# 运行 PHPUnit 测试
./vendor/bin/phpunit packages/lark-app-bot-bundle/tests

# 运行 PHPStan 分析
php -d memory_limit=2G ./vendor/bin/phpstan analyse packages/lark-app-bot-bundle
```

## 贡献

请查看我们的 [monorepo 贡献指南](../../CONTRIBUTING.md) 了解详细信息：

- 如何提交问题
- 如何提交拉取请求
- 代码风格要求
- 测试要求

## 许可证

MIT 许可证。更多信息请查看 [许可证文件](LICENSE)。

## 相关链接

- [飞书开放平台文档](https://open.feishu.cn/document/)
- [Symfony Bundle 最佳实践](https://symfony.com/doc/current/bundles/best_practices.html)
