# Lark App Bot Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/lark-app-bot-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/lark-app-bot-bundle)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg?style=flat-square)](https://php.net/)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-passing-green.svg?style=flat-square)](#)
[![Code Coverage](https://img.shields.io/badge/coverage-93%25-brightgreen.svg?style=flat-square)](#)
[![Quality Score](https://img.shields.io/badge/quality-A-green.svg?style=flat-square)](#)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/lark-app-bot-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/lark-app-bot-bundle)

A Symfony bundle for integrating Lark (Feishu) App Bot functionality into your application.

## Table of Contents

- [Features](#features)
  - [Core Features](#core-features)
  - [Messaging Features](#messaging-features)
  - [Interaction Features](#interaction-features)
  - [Management Features](#management-features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [Console Commands](#console-commands)
- [API Documentation](#api-documentation)
- [Architecture](#architecture)
- [Usage Examples](#usage-examples)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [Migration Guide](#migration-guide)
- [Performance Optimization](#performance-optimization)
- [Security Best Practices](#security-best-practices)
- [Contributing](#contributing)
- [Roadmap](#roadmap)
- [License](#license)
- [Links](#links)

## Features

### Core Features
- 🤖 Full Lark (Feishu) Application Bot integration
- 🔧 Easy Symfony integration via Bundle
- 📝 Symfony service configuration
- 🧪 Comprehensive test coverage
- 💫 PHP 8.1+ support
- 🏗️ Built for modern Symfony applications (6.4+)

### Messaging Features
- 📨 Support for all Lark message types (text, rich text, images, files, cards)
- 🎴 Powerful card message builder with fluent interface
- 🎨 Pre-defined card templates (notification, approval, task, report)
- 🌐 Internationalization support
- 📬 Batch message sending

### Interaction Features
- 🔔 Webhook event handling
- 💬 Message handler registration system
- 👥 Group management and event handling
- 👤 User management and synchronization
- 📋 Menu management

### Management Features
- 🧭 Configuration check command to verify credentials and webhook settings
- 💾 User cache layer with batch retrieval helpers
- 📂 Menu configuration builders with permission hooks
- 🤝 External collaboration policy guards and compliance logging

## Requirements

- PHP 8.1 or higher
- Symfony 6.4 or higher
- Doctrine ORM 3.0 or higher

## Installation

Install the bundle via Composer:

```bash
composer require tourze/lark-app-bot-bundle
```

## Quick Start

### 1. Enable the Bundle

Add it to `config/bundles.php`:

```php
<?php

return [
    // ... other bundles
    Tourze\LarkAppBotBundle\LarkAppBotBundle::class => ['all' => true],
];
```

### 2. Configure Your Lark App

Create the configuration file and set your environment variables as shown in the Configuration section.

### 3. Send Your First Message

```php
use Tourze\LarkAppBotBundle\Message\MessageService;

class NotificationController extends AbstractController
{
    public function __construct(
        private MessageService $messageService
    ) {}
    
    public function sendWelcome(): Response
    {
        // Send a simple text message
        $this->messageService->sendText(
            'user_open_id',
            'Welcome to our Lark Bot!'
        );
        
        return new Response('Message sent!');
    }
}
```

### 4. Handle Webhook Events

```php
use Tourze\LarkAppBotBundle\Webhook\WebhookHandler;
use Symfony\Component\HttpFoundation\Request;

class WebhookController extends AbstractController
{
    public function __construct(
        private WebhookHandler $webhookHandler
    ) {}
    
    #[Route('/lark/webhook', methods: ['POST'])]
    public function handle(Request $request): Response
    {
        $result = $this->webhookHandler->handle($request);
        return new JsonResponse($result);
    }
}
```

## Configuration

### Basic Configuration

```yaml
# config/packages/lark_app_bot.yaml
lark_app_bot:
    app_id: '%env(LARK_APP_ID)%'
    app_secret: '%env(LARK_APP_SECRET)%'
    verification_token: '%env(LARK_VERIFICATION_TOKEN)%'
    encrypt_key: '%env(LARK_ENCRYPT_KEY)%' # Optional, for encrypted messages
    
    # Optional configurations
    cache:
        enabled: true
        ttl: 3600
        
    circuit_breaker:
        failure_threshold: 5
        success_threshold: 2
        timeout: 60
        
    rate_limit:
        enabled: true
        limit: 100
        period: 60
```

### Environment Variables

```bash
# .env
LARK_APP_ID=your_app_id
LARK_APP_SECRET=your_app_secret
LARK_VERIFICATION_TOKEN=your_verification_token
LARK_ENCRYPT_KEY=your_encrypt_key # Optional
```

## Console Commands

The bundle provides several console commands for managing and debugging your Lark bot:

### Configuration Check

Check your Lark bot configuration and connection status:

```bash
# Basic check
php bin/console lark:config:check

# Test API connection
php bin/console lark:config:check --test-api

# Show token information (sensitive)
php bin/console lark:config:check --show-token

# Attempt to fix common issues
php bin/console lark:config:check --fix
```

### Send Message

Send messages directly from the command line:

```bash
# Send a text message
php bin/console lark:send-message --user=USER_ID --text="Hello from CLI"

# Send a card message
php bin/console lark:send-message --user=USER_ID --card=welcome

# Send to a chat group
php bin/console lark:send-message --chat=CHAT_ID --text="Group message"
```

### Debug Mode

Enable debug mode for detailed logging and message inspection:

```bash
# Enable debug mode
php bin/console lark:debug --enable

# Test message rendering
php bin/console lark:debug --test-message --type=card --template=notification

# Disable debug mode
php bin/console lark:debug --disable
```

### User Query

Query Lark user information:

```bash
# Basic query (auto-detect type)
php bin/console lark:user:query open_123456
php bin/console lark:user:query user@example.com
php bin/console lark:user:query +8613800138000

# Specify type query
php bin/console lark:user:query 123456 --type=user_id
php bin/console lark:user:query user@example.com --type=email

# Show additional information
php bin/console lark:user:query open_123456 --department --groups

# Batch query (from file)
cat users.txt | php bin/console lark:user:query - --batch

# Custom output format
php bin/console lark:user:query open_123456 --format=json
php bin/console lark:user:query open_123456 --format=csv --fields=name --fields=email
```

### Group Management

Manage Lark groups:

```bash
# View group information
php bin/console lark:group:manage --action=info --chat-id=CHAT_ID

# Add user to group
php bin/console lark:group:manage --action=add --chat-id=CHAT_ID --user=USER_ID

# Remove user from group
php bin/console lark:group:manage --action=remove --chat-id=CHAT_ID --user=USER_ID

# Get group member list
php bin/console lark:group:manage --action=members --chat-id=CHAT_ID

# Update group information
php bin/console lark:group:manage --action=update --chat-id=CHAT_ID --name="New Group Name"
```

### Message Sending

Send various types of messages:

```bash
# Send text message
php bin/console lark:message:send --to=USER_ID --text="Hello, World!"

# Send rich text message
php bin/console lark:message:send --to=USER_ID --rich-text='{"title":"Title","content":"Content"}'

# Send card message
php bin/console lark:message:send --to=USER_ID --card-template=notification --card-data='{"title":"Notification","content":"Content"}'

# Send to chat group
php bin/console lark:message:send --to=CHAT_ID --type=chat --text="Group message"

# Batch send
php bin/console lark:message:send --batch --file=recipients.json --text="Batch message"
```

## API Documentation

### Core Services

#### MessageService
- `sendText(string $receiver, string $text): array`
- `sendRichText(string $receiver, array $content): array`
- `sendCard(string $receiver, array $card): array`
- `sendImage(string $receiver, string $imageKey): array`
- `sendFile(string $receiver, string $fileKey): array`
- `batchSend(array $receivers, string $msgType, array $content): array`

#### UserService
- `getUser(string $userId): array`
- `batchGetUsers(array $userIds): array`
- `searchUsers(string $query): array`
- `syncUsers(): void`

#### GroupService
- `getGroup(string $chatId): array`
- `getGroupMembers(string $chatId): array`
- `addMembers(string $chatId, array $userIds): array`
- `removeMembers(string $chatId, array $userIds): array`

### Event System

The bundle dispatches various events that you can listen to:

- `lark.message.received` - When a message is received
- `lark.user.added` - When a user is added to the bot
- `lark.user.removed` - When a user is removed
- `lark.group.created` - When a group is created
- `lark.group.disbanded` - When a group is disbanded

## Architecture

This bundle follows Symfony best practices and clean architecture principles:

- **Bundle Class**: `Tourze\LarkAppBotBundle\LarkAppBotBundle`
- **Extension Class**: `Tourze\LarkAppBotBundle\DependencyInjection\LarkAppBotExtension`
- **Service Configuration**: Located in `src/Resources/config/services.yaml`
- **Event-Driven**: Uses Symfony's event dispatcher for extensibility
- **PSR Standards**: Follows PSR-4, PSR-12, and PSR-3 standards
- **SOLID Principles**: Designed with SOLID principles in mind

## Usage Examples

### Sending Card Messages

```php
use Tourze\LarkAppBotBundle\Message\Builder\CardMessageBuilder;

$builder = new CardMessageBuilder();
$builder
    ->setHeader('System Notification', 'blue')
    ->addText('You have a new message')
    ->addDivider()
    ->addFields([
        ['name' => 'From', 'value' => 'System Admin'],
        ['name' => 'Time', 'value' => date('Y-m-d H:i:s')],
    ], true)
    ->addActions([
        ['text' => 'View Details', 'type' => 'primary', 'url' => 'https://example.com'],
    ]);

$messageService->sendCard('user_open_id', $builder->build());
```

### Using Card Templates

```php
use Tourze\LarkAppBotBundle\Message\Template\CardTemplateManager;

$templateManager->applyTemplate($builder, 'approval', [
    'title' => 'Leave Request',
    'description' => 'John Doe requested 1 day sick leave',
    'fields' => [
        ['name' => 'Employee', 'value' => 'John Doe'],
        ['name' => 'Date', 'value' => '2024-01-01'],
    ],
    'id' => 'leave_request_123',
]);
```

### Handling User Events

```php
use Tourze\LarkAppBotBundle\Event\UserEventSubscriber;
use Tourze\LarkAppBotBundle\Event\UserAddedEvent;

class CustomUserEventSubscriber extends UserEventSubscriber
{
    public function onUserAdded(UserAddedEvent $event): void
    {
        $user = $event->getUser();
        
        // Send welcome message
        $this->messageService->sendText(
            $user->getOpenId(),
            "Welcome {$user->getName()}!"
        );
    }
}
```

## Testing

Run the test suite:

```bash
# Run PHPUnit tests
./vendor/bin/phpunit packages/lark-app-bot-bundle/tests

# Run PHPStan analysis
php -d memory_limit=2G ./vendor/bin/phpstan analyse packages/lark-app-bot-bundle
```

## Troubleshooting

### Common Issues

1. **Token Refresh Failures**
    - Ensure your app credentials are correct
    - Check network connectivity to Lark servers
    - Verify your app has the necessary permissions

2. **Webhook Verification Failures**
    - Double-check your verification token
    - Ensure the webhook URL is publicly accessible
    - Check request headers for proper formatting

3. **Rate Limiting**
    - The bundle handles rate limiting automatically
    - Adjust rate limit settings in configuration if needed
    - Consider implementing caching for frequently accessed data

## Migration Guide

If you're migrating from another Lark SDK:

1. Update your service injections to use our services
2. Replace API calls with our service methods
3. Update event listeners to use our event system
4. Migrate configuration to our format

## Performance Optimization

- Enable caching to reduce API calls
- Use batch operations where possible
- Implement webhook queuing for high-volume scenarios
- Monitor performance with our built-in profiler

## Security Best Practices

- Always verify webhook signatures
- Store credentials in environment variables
- Implement proper access controls
- Regularly audit permissions and access logs
- Use HTTPS for all webhook endpoints

## Contributing

Please see our [monorepo contributing guidelines](../../CONTRIBUTING.md) for details on:

- How to submit issues
- How to submit pull requests
- Code style requirements
- Testing requirements

## Roadmap

- [ ] Full menu API implementation
- [ ] Advanced workflow builder
- [ ] More card templates
- [ ] GraphQL API support
- [ ] Real-time collaboration features
- [ ] Advanced analytics and reporting

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Links

- [Lark Open Platform Documentation](https://open.feishu.cn/document/)
- [Symfony Bundle Best Practices](https://symfony.com/doc/current/bundles/best_practices.html)
- [Package on Packagist](https://packagist.org/packages/tourze/lark-app-bot-bundle)
- [Issue Tracker](https://github.com/tourze/php-monorepo/issues)
