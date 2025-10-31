# Migration Guide

## Migrating from Other Lark SDKs

This guide helps you migrate from other Lark/Feishu SDKs to the Lark App Bot Bundle.

## Table of Contents

- [From Official Lark SDK](#from-official-lark-sdk)
- [From Laravel Lark SDK](#from-laravel-lark-sdk)
- [From Custom Implementations](#from-custom-implementations)
- [Common Migration Tasks](#common-migration-tasks)

## From Official Lark SDK

If you're migrating from the official Lark SDK, here are the key differences:

### Authentication

**Before (Official SDK):**
```php
$client = new \Lark\Client([
    'app_id' => 'your_app_id',
    'app_secret' => 'your_app_secret',
]);
$token = $client->getAccessToken();
```

**After (Lark App Bot Bundle):**
```php
// Authentication is handled automatically
// Just inject the service you need
public function __construct(
    private MessageService $messageService
) {}
```

### Sending Messages

**Before:**
```php
$client->message->send([
    'receive_id' => 'user_open_id',
    'msg_type' => 'text',
    'content' => json_encode(['text' => 'Hello']),
]);
```

**After:**
```php
$this->messageService->sendText('user_open_id', 'Hello');
```

### Card Messages

**Before:**
```php
$card = [
    'config' => ['wide_screen_mode' => true],
    'elements' => [
        [
            'tag' => 'div',
            'text' => [
                'tag' => 'plain_text',
                'content' => 'Hello World',
            ],
        ],
    ],
];
$client->message->send([
    'receive_id' => 'user_open_id',
    'msg_type' => 'interactive',
    'content' => json_encode(['card' => $card]),
]);
```

**After:**
```php
$builder = new CardMessageBuilder();
$builder->addText('Hello World');
$this->messageService->sendCard('user_open_id', $builder->build());
```

## From Laravel Lark SDK

### Service Registration

**Before (Laravel):**
```php
// config/services.php
'lark' => [
    'app_id' => env('LARK_APP_ID'),
    'app_secret' => env('LARK_APP_SECRET'),
];

// In controller
$lark = app('lark');
```

**After (Symfony):**
```yaml
# config/packages/lark_app_bot.yaml
lark_app_bot:
    app_id: '%env(LARK_APP_ID)%'
    app_secret: '%env(LARK_APP_SECRET)%'
```

```php
// In controller - use dependency injection
public function __construct(
    private MessageService $messageService
) {}
```

### Webhook Handling

**Before (Laravel):**
```php
Route::post('/lark/webhook', function (Request $request) {
    $data = $request->all();
    // Manual verification and processing
});
```

**After (Symfony):**
```php
#[Route('/lark/webhook', methods: ['POST'])]
public function webhook(Request $request, WebhookHandler $handler): Response
{
    return new JsonResponse($handler->handle($request));
}
```

## From Custom Implementations

### API Calls

**Before (Custom):**
```php
$response = $httpClient->post('https://open.feishu.cn/open-apis/im/v1/messages', [
    'headers' => [
        'Authorization' => 'Bearer ' . $token,
    ],
    'json' => [
        'receive_id' => 'user_open_id',
        'msg_type' => 'text',
        'content' => '{"text":"Hello"}',
    ],
]);
```

**After:**
```php
// All API calls are abstracted
$this->messageService->sendText('user_open_id', 'Hello');

// Or use the client directly for custom endpoints
$response = $this->larkClient->request('POST', '/open-apis/custom/endpoint', [
    'json' => ['data' => 'value'],
]);
```

### Error Handling

**Before:**
```php
try {
    $response = $httpClient->post(...);
    if ($response->getStatusCode() !== 200) {
        // Handle error
    }
} catch (\Exception $e) {
    // Handle exception
}
```

**After:**
```php
use Tourze\LarkAppBotBundle\Exception\LarkApiException;

try {
    $this->messageService->sendText('user_open_id', 'Hello');
} catch (LarkApiException $e) {
    // Specific error handling
    $errorCode = $e->getErrorCode();
    $errorMsg = $e->getErrorMessage();
}
```

## Common Migration Tasks

### 1. Update Dependencies

Remove old SDK dependencies and add the bundle:

```bash
composer remove old-lark-sdk
composer require tourze/lark-app-bot-bundle
```

### 2. Update Configuration

Move your configuration to Symfony's config:

```yaml
# config/packages/lark_app_bot.yaml
lark_app_bot:
    app_id: '%env(LARK_APP_ID)%'
    app_secret: '%env(LARK_APP_SECRET)%'
    verification_token: '%env(LARK_VERIFICATION_TOKEN)%'
```

### 3. Refactor Service Usage

Replace direct API calls with service methods:

```php
// Before
$client->user->get(['user_id' => 'ou_123']);

// After
$this->userService->getUser('ou_123');
```

### 4. Update Event Handling

Replace custom event handling with the bundle's event system:

```php
// Before
if ($data['event_type'] === 'message') {
    // Handle message
}

// After - use event subscriber
class MessageSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            MessageReceivedEvent::class => 'onMessage',
        ];
    }
    
    public function onMessage(MessageReceivedEvent $event): void
    {
        // Handle message
    }
}
```

### 5. Update Tests

Update your tests to use the bundle's testing utilities:

```php
// Before
$mockClient = $this->createMock(LarkClient::class);

// After
use Tourze\LarkAppBotBundle\Tests\TestCase;

class MyTest extends TestCase
{
    public function testMessage(): void
    {
        $messageService = $this->getMessageService();
        // Test with real services
    }
}
```

## Migration Checklist

- [ ] Update composer dependencies
- [ ] Create bundle configuration file
- [ ] Update environment variables
- [ ] Replace direct API calls with services
- [ ] Update webhook endpoints
- [ ] Refactor event handling to use event subscribers
- [ ] Update error handling
- [ ] Update tests
- [ ] Test in development environment
- [ ] Deploy to production

## Getting Help

If you encounter issues during migration:

1. Check the [API Documentation](API.md)
2. Review the [README](../README.md) for examples
3. Check existing tests for usage patterns
4. Open an issue on GitHub

## Feature Comparison

| Feature | Official SDK | Laravel SDK | This Bundle |
|---------|-------------|-------------|-------------|
| Auto Authentication | ❌ | ✅ | ✅ |
| Dependency Injection | ❌ | ✅ | ✅ |
| Event System | ❌ | ❌ | ✅ |
| Message Builder | ❌ | ❌ | ✅ |
| Circuit Breaker | ❌ | ❌ | ✅ |
| Debug Tools | ❌ | ❌ | ✅ |
| Symfony Integration | ❌ | ❌ | ✅ |
| Type Safety | ❌ | ❌ | ✅ |
| PSR Standards | ✅ | ✅ | ✅ |

## Performance Considerations

The bundle includes several performance optimizations:

1. **Automatic Token Caching** - Tokens are cached to reduce API calls
2. **Connection Pooling** - HTTP connections are reused
3. **Circuit Breaker** - Prevents cascading failures
4. **Multi-level Cache** - Improves response times

Make sure to enable caching in production:

```yaml
lark_app_bot:
    cache:
        enabled: true
        ttl: 3600
```