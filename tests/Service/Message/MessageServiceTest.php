<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\LarkAppBotBundle\Exception\ValidationException;
use Tourze\LarkAppBotBundle\Service\Authentication\TokenProviderInterface;
use Tourze\LarkAppBotBundle\Service\Client\LarkClient;
use Tourze\LarkAppBotBundle\Service\Message\Builder\TextMessageBuilder;
use Tourze\LarkAppBotBundle\Service\Message\MessageService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 *
 * 注意：本测试类中使用了具体类的 Mock（LarkClient）
 * LarkClient 是具体类，需要 Mock 以避免对HTTP客户端的依赖
 * 测试重点在于验证 MessageService 的消息发送逻辑，而不是HTTP客户端的具体实现
 */
#[CoversClass(MessageService::class)]
#[RunTestsInSeparateProcesses]
final class MessageServiceTest extends AbstractIntegrationTestCase
{
    private MessageService $messageService;

    private LarkClient $mockLarkClient;

    public function testSendTextMessage(): void
    {
        $receiveId = 'ou_123456';
        $text = 'Hello, World!';

        /*
         * 使用具体类 ResponseInterface 创建 Mock 对象的原因：
         * 1. ResponseInterface 是 Symfony HTTP 客户端的标准响应接口
         * 2. 这是接口 mock 的正确用法，符合依赖倒置原则
         * 3. Mock 该接口可以模拟各种消息发送的响应结果
         * 4. 注意：这里 mock 的是接口而非具体类，是良好的实践
         */
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')
            ->willReturn(json_encode([
                'code' => 0,
                'msg' => 'success',
                'data' => [
                    'message_id' => 'om_123456789']]))
        ;

        $this->mockLarkClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                '/open-apis/im/v1/messages',
                [
                    'query' => ['receive_id_type' => MessageService::RECEIVE_ID_TYPE_OPEN_ID],
                    'json' => [
                        'receive_id' => $receiveId,
                        'msg_type' => MessageService::MSG_TYPE_TEXT,
                        'content' => '{"text":"Hello, World!"}']])
            ->willReturn($response)
        ;

        $result = $this->messageService->sendText($receiveId, $text);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message_id', $result);
        $this->assertSame('om_123456789', $result['message_id']);
    }

    public function testSendWithBuilder(): void
    {
        $receiveId = 'ou_123456';
        $builder = TextMessageBuilder::create('Test message');

        /*
         * 使用具体类 ResponseInterface 创建 Mock 对象的原因：
         * 1. ResponseInterface 是 Symfony HTTP 客户端的标准响应接口
         * 2. 这是接口 mock 的正确用法，符合依赖倒置原则
         * 3. Mock 该接口可以模拟各种消息发送的响应结果
         * 4. 注意：这里 mock 的是接口而非具体类，是良好的实践
         */
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')
            ->willReturn(json_encode([
                'code' => 0,
                'msg' => 'success',
                'data' => [
                    'message_id' => 'om_123456789']]))
        ;

        $this->mockLarkClient->expects($this->once())
            ->method('request')
            ->willReturn($response)
        ;

        $result = $this->messageService->sendWithBuilder($receiveId, $builder);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message_id', $result);
    }

    public function testValidateMsgTypeThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('不支持的消息类型: invalid_type');

        $this->messageService->send('ou_123', 'invalid_type', ['text' => 'test']);
    }

    public function testValidateReceiveIdTypeThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('不支持的接收者ID类型: invalid_id_type');

        $this->messageService->send(
            'ou_123',
            MessageService::MSG_TYPE_TEXT,
            ['text' => 'test'],
            'invalid_id_type'
        );
    }

    public function testReplyMessage(): void
    {
        $messageId = 'om_123456';
        $text = 'Reply message';

        /*
         * 使用具体类 ResponseInterface 创建 Mock 对象的原因：
         * 1. ResponseInterface 是 Symfony HTTP 客户端的标准响应接口
         * 2. 这是接口 mock 的正确用法，符合依赖倒置原则
         * 3. Mock 该接口可以模拟各种消息发送的响应结果
         * 4. 注意：这里 mock 的是接口而非具体类，是良好的实践
         */
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')
            ->willReturn(json_encode([
                'code' => 0,
                'msg' => 'success',
                'data' => [
                    'message_id' => 'om_789012']]))
        ;

        $this->mockLarkClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                '/open-apis/im/v1/messages/om_123456/reply',
                [
                    'json' => [
                        'msg_type' => MessageService::MSG_TYPE_TEXT,
                        'content' => '{"text":"Reply message"}']])
            ->willReturn($response)
        ;

        $result = $this->messageService->reply(
            $messageId,
            MessageService::MSG_TYPE_TEXT,
            ['text' => $text]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message_id', $result);
        $this->assertSame('om_789012', $result['message_id']);
    }

    public function testEditMessage(): void
    {
        $messageId = 'om_123456';
        $newText = 'Edited message';

        /*
         * 使用具体类 ResponseInterface 创建 Mock 对象的原因：
         * 1. ResponseInterface 是 Symfony HTTP 客户端的标准响应接口
         * 2. 这是接口 mock 的正确用法，符合依赖倒置原则
         * 3. Mock 该接口可以模拟各种消息发送的响应结果
         * 4. 注意：这里 mock 的是接口而非具体类，是良好的实践
         */
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')
            ->willReturn(json_encode([
                'code' => 0,
                'msg' => 'success',
                'data' => [
                    'message_id' => 'om_123456',
                    'msg_type' => MessageService::MSG_TYPE_TEXT]]))
        ;

        $this->mockLarkClient->expects($this->once())
            ->method('request')
            ->with(
                'PUT',
                '/open-apis/im/v1/messages/om_123456',
                [
                    'json' => [
                        'msg_type' => MessageService::MSG_TYPE_TEXT,
                        'content' => '{"text":"Edited message"}']])
            ->willReturn($response)
        ;

        $result = $this->messageService->edit(
            $messageId,
            MessageService::MSG_TYPE_TEXT,
            ['text' => $newText]);

        // edit 方法返回 data 字段，不是整个响应
        $this->assertNotEmpty($result);
    }

    public function testRecallMessage(): void
    {
        $messageId = 'om_123456';

        /*
         * 使用具体类 ResponseInterface 创建 Mock 对象的原因：
         * 1. ResponseInterface 是 Symfony HTTP 客户端的标准响应接口
         * 2. 这是接口 mock 的正确用法，符合依赖倒置原则
         * 3. Mock 该接口可以模拟各种消息发送的响应结果
         * 4. 注意：这里 mock 的是接口而非具体类，是良好的实践
         */
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')
            ->willReturn(json_encode([
                'code' => 0,
                'msg' => 'success',
                'data' => ['status' => 'success']]))
        ;

        $this->mockLarkClient->expects($this->once())
            ->method('request')
            ->with(
                'DELETE',
                '/open-apis/im/v1/messages/om_123456'
            )
            ->willReturn($response)
        ;

        $result = $this->messageService->recall($messageId);

        // recall 方法返回 data 字段，不是整个响应
        $this->assertNotEmpty($result);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
    }

    public function testGetMessage(): void
    {
        $messageId = 'om_123456';

        /*
         * 使用具体类 ResponseInterface 创建 Mock 对象的原因：
         * 1. ResponseInterface 是 Symfony HTTP 客户端的标准响应接口
         * 2. 这是接口 mock 的正确用法，符合依赖倒置原则
         * 3. Mock 该接口可以模拟各种消息发送的响应结果
         * 4. 注意：这里 mock 的是接口而非具体类，是良好的实践
         */
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')
            ->willReturn(json_encode([
                'code' => 0,
                'msg' => 'success',
                'data' => [
                    'message_id' => 'om_123456',
                    'msg_type' => 'text',
                    'content' => '{"text":"Hello"}']]))
        ;

        $this->mockLarkClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                '/open-apis/im/v1/messages/om_123456'
            )
            ->willReturn($response)
        ;

        $result = $this->messageService->get($messageId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message_id', $result);
        $this->assertSame('om_123456', $result['message_id']);
    }

    public function testSendBatchMessage(): void
    {
        $receiveIds = ['ou_123', 'ou_456', 'ou_789'];
        $text = 'Batch message';

        /*
         * 使用具体类 ResponseInterface 创建 Mock 对象的原因：
         * 1. ResponseInterface 是 Symfony HTTP 客户端的标准响应接口
         * 2. 这是接口 mock 的正确用法，符合依赖倒置原则
         * 3. Mock 该接口可以模拟各种消息发送的响应结果
         * 4. 注意：这里 mock 的是接口而非具体类，是良好的实践
         */
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')
            ->willReturn(json_encode([
                'code' => 0,
                'msg' => 'success',
                'data' => [
                    'message_id' => 'om_batch_123',
                    'invalid_receive_ids' => []]]))
        ;

        $this->mockLarkClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                '/open-apis/message/v4/batch_send',
                [
                    'query' => ['receive_id_type' => MessageService::RECEIVE_ID_TYPE_OPEN_ID],
                    'json' => [
                        'receive_ids' => $receiveIds,
                        'msg_type' => MessageService::MSG_TYPE_TEXT,
                        'content' => '{"text":"Batch message"}']])
            ->willReturn($response)
        ;

        $result = $this->messageService->sendBatch(
            $receiveIds,
            MessageService::MSG_TYPE_TEXT,
            ['text' => $text]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message_id', $result);
        $this->assertSame('om_batch_123', $result['message_id']);
    }

    public function testSendBatchMessageWithEmptyReceiversThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('接收者ID列表不能为空');

        $this->messageService->sendBatch([],
            MessageService::MSG_TYPE_TEXT,
            ['text' => 'test']);
    }

    public function testSendBatchMessageWithTooManyReceiversThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('批量发送消息接收者数量不能超过200');

        $receiveIds = array_fill(0, 201, 'ou_123');

        $this->messageService->sendBatch(
            $receiveIds,
            MessageService::MSG_TYPE_TEXT,
            ['text' => 'test']);
    }

    public function testGetSupportedMsgTypes(): void
    {
        $types = $this->messageService->getSupportedMsgTypes();

        $this->assertIsArray($types);
        $this->assertArrayHasKey(MessageService::MSG_TYPE_TEXT, $types);
        $this->assertArrayHasKey(MessageService::MSG_TYPE_RICH_TEXT, $types);
        $this->assertArrayHasKey(MessageService::MSG_TYPE_IMAGE, $types);
        $this->assertArrayHasKey(MessageService::MSG_TYPE_FILE, $types);
    }

    public function testSendCard(): void
    {
        $receiveId = 'ou_123456';
        $card = [
            'elements' => [
                [
                    'tag' => 'div',
                    'text' => [
                        'tag' => 'plain_text',
                        'content' => 'Card content']]]];

        /*
         * 使用具体类 ResponseInterface 创建 Mock 对象的原因：
         * 1. ResponseInterface 是 Symfony HTTP 客户端的标准响应接口
         * 2. 这是接口 mock 的正确用法，符合依赖倒置原则
         * 3. Mock 该接口可以模拟各种消息发送的响应结果
         * 4. 注意：这里 mock 的是接口而非具体类，是良好的实践
         */
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')
            ->willReturn(json_encode([
                'code' => 0,
                'msg' => 'success',
                'data' => [
                    'message_id' => 'om_card_123']]))
        ;

        $this->mockLarkClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                '/open-apis/im/v1/messages',
                [
                    'query' => ['receive_id_type' => MessageService::RECEIVE_ID_TYPE_OPEN_ID],
                    'json' => [
                        'receive_id' => $receiveId,
                        'msg_type' => MessageService::MSG_TYPE_INTERACTIVE,
                        'content' => json_encode($card, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)]])
            ->willReturn($response)
        ;

        $result = $this->messageService->sendCard($receiveId, $card);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message_id', $result);
        $this->assertSame('om_card_123', $result['message_id']);
    }

    public function testSendCardWithRootId(): void
    {
        $receiveId = 'ou_123456';
        $card = [
            'elements' => [
                [
                    'tag' => 'div',
                    'text' => [
                        'tag' => 'plain_text',
                        'content' => 'Reply card content']]]];
        $rootId = 'om_root_456';

        /*
         * 使用具体类 ResponseInterface 创建 Mock 对象的原因：
         * 1. ResponseInterface 是 Symfony HTTP 客户端的标准响应接口
         * 2. 这是接口 mock 的正确用法，符合依赖倒置原则
         * 3. Mock 该接口可以模拟各种消息发送的响应结果
         * 4. 注意：这里 mock 的是接口而非具体类，是良好的实践
         */
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')
            ->willReturn(json_encode([
                'code' => 0,
                'msg' => 'success',
                'data' => [
                    'message_id' => 'om_card_reply_123']]))
        ;

        $this->mockLarkClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                '/open-apis/im/v1/messages',
                [
                    'query' => ['receive_id_type' => MessageService::RECEIVE_ID_TYPE_OPEN_ID],
                    'json' => [
                        'receive_id' => $receiveId,
                        'msg_type' => MessageService::MSG_TYPE_INTERACTIVE,
                        'content' => json_encode($card, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES),
                        'root_id' => $rootId]])
            ->willReturn($response)
        ;

        $result = $this->messageService->sendCard($receiveId, $card, $rootId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message_id', $result);
        $this->assertSame('om_card_reply_123', $result['message_id']);
    }

    public function testSendFile(): void
    {
        $receiveId = 'ou_123456';
        $fileKey = 'file_v2_xxxxx';

        /*
         * 使用具体类 ResponseInterface 创建 Mock 对象的原因：
         * 1. ResponseInterface 是 Symfony HTTP 客户端的标准响应接口
         * 2. 这是接口 mock 的正确用法，符合依赖倒置原则
         * 3. Mock 该接口可以模拟各种消息发送的响应结果
         * 4. 注意：这里 mock 的是接口而非具体类，是良好的实践
         */
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')
            ->willReturn(json_encode([
                'code' => 0,
                'msg' => 'success',
                'data' => [
                    'message_id' => 'om_file_123']]))
        ;

        $this->mockLarkClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                '/open-apis/im/v1/messages',
                [
                    'query' => ['receive_id_type' => MessageService::RECEIVE_ID_TYPE_OPEN_ID],
                    'json' => [
                        'receive_id' => $receiveId,
                        'msg_type' => MessageService::MSG_TYPE_FILE,
                        'content' => '{"file_key":"file_v2_xxxxx"}']])
            ->willReturn($response)
        ;

        $result = $this->messageService->sendFile($receiveId, $fileKey);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message_id', $result);
        $this->assertSame('om_file_123', $result['message_id']);
    }

    public function testSendImage(): void
    {
        $receiveId = 'ou_123456';
        $imageKey = 'img_v2_xxxxx';

        /*
         * 使用具体类 ResponseInterface 创建 Mock 对象的原因：
         * 1. ResponseInterface 是 Symfony HTTP 客户端的标准响应接口
         * 2. 这是接口 mock 的正确用法，符合依赖倒置原则
         * 3. Mock 该接口可以模拟各种消息发送的响应结果
         * 4. 注意：这里 mock 的是接口而非具体类，是良好的实践
         */
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')
            ->willReturn(json_encode([
                'code' => 0,
                'msg' => 'success',
                'data' => [
                    'message_id' => 'om_image_123']]))
        ;

        $this->mockLarkClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                '/open-apis/im/v1/messages',
                [
                    'query' => ['receive_id_type' => MessageService::RECEIVE_ID_TYPE_OPEN_ID],
                    'json' => [
                        'receive_id' => $receiveId,
                        'msg_type' => MessageService::MSG_TYPE_IMAGE,
                        'content' => '{"image_key":"img_v2_xxxxx"}']])
            ->willReturn($response)
        ;

        $result = $this->messageService->sendImage($receiveId, $imageKey);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message_id', $result);
        $this->assertSame('om_image_123', $result['message_id']);
    }

    public function testSendRichText(): void
    {
        $receiveId = 'ou_123456';
        $post = [
            'zh_cn' => [
                'title' => '标题',
                'content' => [
                    [
                        [
                            'tag' => 'text',
                            'text' => '富文本内容']]]]];

        /*
         * 使用具体类 ResponseInterface 创建 Mock 对象的原因：
         * 1. ResponseInterface 是 Symfony HTTP 客户端的标准响应接口
         * 2. 这是接口 mock 的正确用法，符合依赖倒置原则
         * 3. Mock 该接口可以模拟各种消息发送的响应结果
         * 4. 注意：这里 mock 的是接口而非具体类，是良好的实践
         */
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')
            ->willReturn(json_encode([
                'code' => 0,
                'msg' => 'success',
                'data' => [
                    'message_id' => 'om_richtext_123']]))
        ;

        $this->mockLarkClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                '/open-apis/im/v1/messages',
                [
                    'query' => ['receive_id_type' => MessageService::RECEIVE_ID_TYPE_OPEN_ID],
                    'json' => [
                        'receive_id' => $receiveId,
                        'msg_type' => MessageService::MSG_TYPE_RICH_TEXT,
                        'content' => json_encode($post, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)]])
            ->willReturn($response)
        ;

        $result = $this->messageService->sendRichText($receiveId, $post);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message_id', $result);
        $this->assertSame('om_richtext_123', $result['message_id']);
    }

    public function testSendRichTextWithRootId(): void
    {
        $receiveId = 'ou_123456';
        $post = [
            'zh_cn' => [
                'title' => '回复标题',
                'content' => [
                    [
                        [
                            'tag' => 'text',
                            'text' => '回复的富文本内容']]]]];
        $rootId = 'om_root_789';

        /*
         * 使用具体类 ResponseInterface 创建 Mock 对象的原因：
         * 1. ResponseInterface 是 Symfony HTTP 客户端的标准响应接口
         * 2. 这是接口 mock 的正确用法，符合依赖倒置原则
         * 3. Mock 该接口可以模拟各种消息发送的响应结果
         * 4. 注意：这里 mock 的是接口而非具体类，是良好的实践
         */
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')
            ->willReturn(json_encode([
                'code' => 0,
                'msg' => 'success',
                'data' => [
                    'message_id' => 'om_richtext_reply_123']]))
        ;

        $this->mockLarkClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                '/open-apis/im/v1/messages',
                [
                    'query' => ['receive_id_type' => MessageService::RECEIVE_ID_TYPE_OPEN_ID],
                    'json' => [
                        'receive_id' => $receiveId,
                        'msg_type' => MessageService::MSG_TYPE_RICH_TEXT,
                        'content' => json_encode($post, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES),
                        'root_id' => $rootId]])
            ->willReturn($response)
        ;

        $result = $this->messageService->sendRichText($receiveId, $post, $rootId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message_id', $result);
        $this->assertSame('om_richtext_reply_123', $result['message_id']);
    }

    protected function onSetUp(): void
    {
        $this->setupMockServices();

        // 从容器获取 MessageService 服务
        $service = self::getContainer()->get(MessageService::class);
        $this->assertInstanceOf(MessageService::class, $service);
        $this->messageService = $service;
    }

    private function setupMockServices(): void
    {
        // 创建 Mock Token Provider 避免真实 API 调用
        $mockTokenProvider = $this->createMock(TokenProviderInterface::class);
        $mockTokenProvider->method('getToken')->willReturn('mock_token_123456');

        // 创建 Mock LarkClient 以避免真实 HTTP 请求
        $this->mockLarkClient = $this->createMock(LarkClient::class);

        // 重新定义容器中的服务
        self::getContainer()->set(TokenProviderInterface::class, $mockTokenProvider);
        self::getContainer()->set(LarkClient::class, $this->mockLarkClient);
    }
}
