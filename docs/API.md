# API Documentation

## Table of Contents

- [Core Services](#core-services)
  - [MessageService](#messageservice)
  - [UserService](#userservice)
  - [GroupService](#groupservice)
  - [WebhookHandler](#webhookhandler)
- [Message Builders](#message-builders)
  - [CardMessageBuilder](#cardmessagebuilder)
  - [RichTextBuilder](#richtextbuilder)
- [Event System](#event-system)
- [Developer Tools](#developer-tools)
  - [MessageDebugger](#messagedebugger)
  - [ApiTester](#apitester)
  - [PerformanceProfiler](#performanceprofiler)

## Core Services

### MessageService

The main service for sending messages through Lark.

```php
use Tourze\LarkAppBotBundle\Message\MessageService;
```

#### Methods

##### sendText
```php
public function sendText(string $receiver, string $text): array
```
Send a plain text message.

**Parameters:**
- `$receiver` - User open_id, union_id, user_id, or chat_id
- `$text` - The text content to send

**Returns:** Array with message sending result

##### sendRichText
```php
public function sendRichText(string $receiver, array $content): array
```
Send a rich text message with formatting.

**Parameters:**
- `$receiver` - User open_id, union_id, user_id, or chat_id
- `$content` - Rich text content structure

**Example:**
```php
$content = [
    [
        [
            'tag' => 'text',
            'text' => 'Hello ',
        ],
        [
            'tag' => 'a',
            'text' => 'World',
            'href' => 'https://example.com',
        ],
    ],
];
```

##### sendCard
```php
public function sendCard(string $receiver, array $card): array
```
Send an interactive card message.

**Parameters:**
- `$receiver` - User open_id, union_id, user_id, or chat_id
- `$card` - Card structure (use CardMessageBuilder for easy creation)

##### sendImage
```php
public function sendImage(string $receiver, string $imageKey): array
```
Send an image message.

**Parameters:**
- `$receiver` - User open_id, union_id, user_id, or chat_id
- `$imageKey` - The image key obtained from file upload

##### sendFile
```php
public function sendFile(string $receiver, string $fileKey): array
```
Send a file message.

**Parameters:**
- `$receiver` - User open_id, union_id, user_id, or chat_id
- `$fileKey` - The file key obtained from file upload

##### batchSend
```php
public function batchSend(array $receivers, string $msgType, array $content): array
```
Send messages to multiple receivers.

**Parameters:**
- `$receivers` - Array of receiver IDs
- `$msgType` - Message type ('text', 'card', etc.)
- `$content` - Message content

### UserService

Service for managing Lark users.

```php
use Tourze\LarkAppBotBundle\User\UserService;
```

#### Methods

##### getUser
```php
public function getUser(string $userId, string $userIdType = 'open_id'): array
```
Get user information.

**Parameters:**
- `$userId` - User identifier
- `$userIdType` - Type of user ID ('open_id', 'union_id', 'user_id')

##### batchGetUsers
```php
public function batchGetUsers(array $userIds, string $userIdType = 'open_id'): array
```
Get information for multiple users.

##### searchUsers
```php
public function searchUsers(string $query, array $options = []): array
```
Search for users.

**Parameters:**
- `$query` - Search query
- `$options` - Additional search options (page_size, page_token)

##### syncUsers
```php
public function syncUsers(callable $processor = null): void
```
Synchronize all users from Lark.

**Parameters:**
- `$processor` - Optional callback to process each user batch

### GroupService

Service for managing Lark groups (chats).

```php
use Tourze\LarkAppBotBundle\Group\GroupService;
```

#### Methods

##### getGroup
```php
public function getGroup(string $chatId): array
```
Get group information.

##### getGroupMembers
```php
public function getGroupMembers(string $chatId, array $options = []): array
```
Get members of a group.

**Parameters:**
- `$chatId` - Chat ID
- `$options` - Options like page_size, page_token

##### addMembers
```php
public function addMembers(string $chatId, array $userIds): array
```
Add members to a group.

##### removeMembers
```php
public function removeMembers(string $chatId, array $userIds): array
```
Remove members from a group.

### WebhookHandler

Handler for processing Lark webhook events.

```php
use Tourze\LarkAppBotBundle\Webhook\WebhookHandler;
```

#### Methods

##### handle
```php
public function handle(Request $request): array
```
Handle incoming webhook request.

**Returns:** Response array for Lark

##### registerHandler
```php
public function registerHandler(string $eventType, callable $handler): void
```
Register a custom event handler.

**Parameters:**
- `$eventType` - The event type to handle
- `$handler` - Callback function to process the event

## Message Builders

### CardMessageBuilder

Builder for creating interactive card messages.

```php
use Tourze\LarkAppBotBundle\Message\Builder\CardMessageBuilder;

$builder = new CardMessageBuilder();
```

#### Methods

##### setHeader
```php
public function setHeader(string $title, string $template = 'blue'): self
```
Set the card header.

**Parameters:**
- `$title` - Header title
- `$template` - Color template ('blue', 'wathet', 'turquoise', 'green', 'yellow', 'orange', 'red', 'carmine', 'violet', 'purple', 'indigo', 'grey')

##### addText
```php
public function addText(string $content, bool $isMarkdown = false): self
```
Add a text element.

##### addImage
```php
public function addImage(string $imageKey, string $alt = ''): self
```
Add an image element.

##### addDivider
```php
public function addDivider(): self
```
Add a divider line.

##### addFields
```php
public function addFields(array $fields, bool $isShort = false): self
```
Add field elements.

**Parameters:**
- `$fields` - Array of fields with 'name' and 'value'
- `$isShort` - Whether to display fields in short format

##### addActions
```php
public function addActions(array $actions): self
```
Add action buttons.

**Parameters:**
- `$actions` - Array of actions with 'text', 'url', 'type', etc.

##### build
```php
public function build(): array
```
Build the final card structure.

### RichTextBuilder

Builder for creating rich text content.

```php
use Tourze\LarkAppBotBundle\Message\Builder\RichTextBuilder;

$builder = new RichTextBuilder();
```

#### Methods

##### addText
```php
public function addText(string $text, array $style = []): self
```
Add styled text.

**Parameters:**
- `$text` - Text content
- `$style` - Style options (bold, italic, underline, lineThrough)

##### addLink
```php
public function addLink(string $text, string $href): self
```
Add a hyperlink.

##### addMention
```php
public function addMention(string $userId, string $userName): self
```
Add a user mention.

##### addImage
```php
public function addImage(string $imageKey, int $width = 300, int $height = 300): self
```
Add an inline image.

##### newLine
```php
public function newLine(): self
```
Start a new line.

##### build
```php
public function build(): array
```
Build the final rich text structure.

## Event System

The bundle dispatches various events that you can listen to:

### Event Classes

#### MessageReceivedEvent
Dispatched when a message is received.

```php
use Tourze\LarkAppBotBundle\Event\MessageReceivedEvent;

class MessageReceivedEvent
{
    public function getMessage(): array;
    public function getSender(): array;
    public function getMessageType(): string;
    public function getContent(): array;
}
```

#### UserAddedEvent
Dispatched when a user is added to the bot.

```php
use Tourze\LarkAppBotBundle\Event\UserAddedEvent;

class UserAddedEvent
{
    public function getUser(): array;
    public function getOperator(): array;
    public function getTimestamp(): int;
}
```

#### GroupCreatedEvent
Dispatched when a group is created with the bot.

```php
use Tourze\LarkAppBotBundle\Event\GroupCreatedEvent;

class GroupCreatedEvent
{
    public function getGroup(): array;
    public function getOperator(): array;
    public function getMembers(): array;
}
```

### Event Listeners

Create event listeners or subscribers:

```php
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tourze\LarkAppBotBundle\Event\MessageReceivedEvent;

class MessageEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            MessageReceivedEvent::class => 'onMessageReceived',
        ];
    }
    
    public function onMessageReceived(MessageReceivedEvent $event): void
    {
        $message = $event->getMessage();
        // Process the message
    }
}
```

## Developer Tools

### MessageDebugger

Tool for debugging messages before sending.

```php
use Tourze\LarkAppBotBundle\Developer\MessageDebugger;
```

#### Methods

##### enableDebugMode
```php
public function enableDebugMode(): void
```
Enable debug mode (messages won't be sent).

##### testSend
```php
public function testSend(string $receiver, string $msgType, array $content): array
```
Test sending a message without actually sending it.

##### previewCard
```php
public function previewCard(array $card): string
```
Get a preview of how a card will look.

### ApiTester

Tool for testing Lark API endpoints.

```php
use Tourze\LarkAppBotBundle\Developer\ApiTester;
```

#### Methods

##### test
```php
public function test(string $method, string $endpoint, array $data = []): array
```
Test an API endpoint.

**Parameters:**
- `$method` - HTTP method (GET, POST, etc.)
- `$endpoint` - API endpoint path
- `$data` - Request data

##### batchTest
```php
public function batchTest(array $tests): array
```
Run multiple API tests.

### PerformanceProfiler

Tool for profiling API performance.

```php
use Tourze\LarkAppBotBundle\Performance\PerformanceProfiler;
```

#### Methods

##### startProfiling
```php
public function startProfiling(string $name): void
```
Start profiling a section.

##### stopProfiling
```php
public function stopProfiling(string $name): array
```
Stop profiling and get results.

##### getReport
```php
public function getReport(): array
```
Get a complete performance report.

## Error Handling

All services throw specific exceptions for different error cases:

- `LarkApiException` - API call failures
- `InvalidArgumentException` - Invalid parameters
- `AuthenticationException` - Authentication failures
- `RateLimitException` - Rate limit exceeded

Example error handling:

```php
use Tourze\LarkAppBotBundle\Exception\LarkApiException;

try {
    $messageService->sendText('user_id', 'Hello');
} catch (LarkApiException $e) {
    // Handle API error
    $errorCode = $e->getErrorCode();
    $errorMessage = $e->getErrorMessage();
}
```