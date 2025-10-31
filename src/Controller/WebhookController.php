<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Controller;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\LarkAppBotBundle\Event\UrlVerificationEvent;
use Tourze\LarkAppBotBundle\EventSubscriber\EventDispatcher;
use Tourze\LarkAppBotBundle\Exception\ValidationException;

/**
 * Webhook控制器.
 *
 * 处理飞书推送的事件回调
 */
#[WithMonologChannel(channel: 'lark_app_bot')]
final class WebhookController extends AbstractController
{
    private const SIGNATURE_HEADER = 'X-Lark-Signature';
    private const REQUEST_ID_HEADER = 'X-Lark-Request-Id';
    private const REQUEST_TIMESTAMP_HEADER = 'X-Lark-Request-Timestamp';

    private readonly string $verificationToken;

    private readonly string $encryptKey;

    public function __construct(
        private readonly EventDispatcher $eventDispatcher,
        private readonly LoggerInterface $logger,
        private readonly EventDispatcherInterface $symfonyEventDispatcher,
    ) {
        $token = $_ENV['LARK_VERIFICATION_TOKEN'] ?? '';
        $this->verificationToken = \is_string($token) ? $token : '';

        $key = $_ENV['LARK_ENCRYPT_KEY'] ?? '';
        $this->encryptKey = \is_string($key) ? $key : '';
    }

    /**
     * 处理飞书webhook回调.
     */
    #[Route(path: '/lark/webhook', name: 'lark_webhook', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        try {
            // 记录请求信息
            $this->logRequest($request);

            // 验证签名
            $this->verifySignature($request);

            // 解析请求体
            $data = $this->parseRequestBody($request);

            // 验证token
            $this->verifyToken($data);

            // 处理不同类型的事件
            return $this->handleEvent($data, $request);
        } catch (ValidationException $e) {
            $this->logger->error('Webhook验证失败', [
                'error' => $e->getMessage(),
                'validation_errors' => $e->getValidationErrors(),
            ]);

            return new JsonResponse([
                'code' => 400,
                'msg' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            $this->logger->error('处理webhook失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'code' => 500,
                'msg' => 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 验证请求签名.
     *
     * @throws ValidationException
     */
    private function verifySignature(Request $request): void
    {
        $signature = $request->headers->get(self::SIGNATURE_HEADER);
        $timestamp = $request->headers->get(self::REQUEST_TIMESTAMP_HEADER);
        $requestId = $request->headers->get(self::REQUEST_ID_HEADER);
        $body = $request->getContent();

        if (null === $signature || null === $timestamp || null === $requestId) {
            throw new ValidationException('缺少必要的请求头');
        }

        // 验证时间戳，防止重放攻击（5分钟内有效）
        $currentTime = time();
        $requestTime = (int) $timestamp;
        if (abs($currentTime - $requestTime) > 300) {
            throw new ValidationException('请求时间戳无效');
        }

        // 计算签名
        $content = $timestamp . ':' . $requestId . ':' . $this->encryptKey . ':' . $body;
        $expectedSignature = hash('sha256', $content);

        if (!hash_equals($expectedSignature, $signature)) {
            throw new ValidationException('签名验证失败');
        }
    }

    /**
     * 解析请求体.
     *
     * @return array<string, mixed>
     * @throws ValidationException
     */
    private function parseRequestBody(Request $request): array
    {
        $content = $request->getContent();
        if ('' === $content) {
            throw new ValidationException('请求体为空');
        }

        try {
            $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ValidationException('JSON解析失败: ' . $e->getMessage());
        }

        if (!\is_array($data)) {
            throw new ValidationException('请求体格式错误');
        }

        // 确保返回类型为 array<string, mixed>
        $result = [];
        foreach ($data as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }

    /**
     * 验证token.
     *
     * @param array<string, mixed> $data
     *
     * @throws ValidationException
     */
    private function verifyToken(array $data): void
    {
        // URL验证事件使用token字段
        if (isset($data['token']) && \is_string($data['token'])) {
            if ($data['token'] !== $this->verificationToken) {
                throw new ValidationException('Token验证失败');
            }

            return;
        }

        // 普通事件使用header中的token
        if (isset($data['header']) && \is_array($data['header']) && isset($data['header']['token']) && \is_string($data['header']['token'])) {
            if ($data['header']['token'] !== $this->verificationToken) {
                throw new ValidationException('Token验证失败');
            }

            return;
        }

        throw new ValidationException('缺少验证token');
    }

    /**
     * 处理事件.
     *
     * @param array<string, mixed> $data
     */
    private function handleEvent(array $data, Request $request): Response
    {
        // 处理URL验证
        if (isset($data['type']) && 'url_verification' === $data['type']) {
            return $this->handleUrlVerification($data);
        }

        // 处理事件回调
        if (isset($data['header']) && \is_array($data['header']) && isset($data['header']['event_type'])) {
            return $this->handleEventCallback($data, $request);
        }

        throw new ValidationException('未知的事件类型');
    }

    /**
     * 处理URL验证.
     *
     * @param array<string, mixed> $data
     */
    private function handleUrlVerification(array $data): Response
    {
        if (!isset($data['challenge']) || !\is_string($data['challenge'])) {
            throw new ValidationException('缺少challenge字段');
        }

        // 分发URL验证事件
        $event = new UrlVerificationEvent($data['challenge'], $data);
        $this->symfonyEventDispatcher->dispatch($event);

        return new JsonResponse([
            'challenge' => $data['challenge'],
        ]);
    }

    /**
     * 处理事件回调.
     *
     * @param array<string, mixed> $data
     */
    private function handleEventCallback(array $data, Request $request): Response
    {
        $header = $this->extractEventHeader($data);
        $eventType = $this->extractEventType($header);
        $eventContext = $this->extractEventContext($header);

        $this->logEventCallback($eventType, $eventContext);
        $this->dispatchEvent($data, $eventType, $eventContext, $request);

        return new JsonResponse(['code' => 0]);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function extractEventHeader(array $data): array
    {
        if (!isset($data['header']) || !\is_array($data['header'])) {
            throw new ValidationException('缺少header字段');
        }

        // 确保返回类型为 array<string, mixed>
        $header = $data['header'];
        $result = [];
        foreach ($header as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $header
     */
    private function extractEventType(array $header): string
    {
        if (!isset($header['event_type']) || !\is_string($header['event_type'])) {
            throw new ValidationException('缺少event_type字段');
        }

        return $header['event_type'];
    }

    /**
     * @param array<string, mixed> $header
     *
     * @return array<string, string>
     */
    private function extractEventContext(array $header): array
    {
        return [
            'event_id' => isset($header['event_id']) && \is_string($header['event_id']) ? $header['event_id'] : '',
            'tenant_key' => isset($header['tenant_key']) && \is_string($header['tenant_key']) ? $header['tenant_key'] : '',
            'app_id' => isset($header['app_id']) && \is_string($header['app_id']) ? $header['app_id'] : '',
        ];
    }

    /**
     * @param array<string, string> $eventContext
     */
    private function logEventCallback(string $eventType, array $eventContext): void
    {
        $this->logger->info('收到事件回调', [
            'event_type' => $eventType,
            'event_id' => $eventContext['event_id'],
            'tenant_key' => $eventContext['tenant_key'],
            'app_id' => $eventContext['app_id'],
        ]);
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $eventContext
     */
    private function dispatchEvent(array $data, string $eventType, array $eventContext, Request $request): void
    {
        try {
            $eventData = isset($data['event']) && \is_array($data['event']) ? $data['event'] : [];
            /* @var array<string, mixed> $eventData */
            $this->eventDispatcher->dispatch($eventType, $eventData, [
                'event_id' => $eventContext['event_id'],
                'tenant_key' => $eventContext['tenant_key'],
                'app_id' => $eventContext['app_id'],
                'request' => $request,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('事件分发失败', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);

            // 飞书要求即使处理失败也要返回200
        }
    }

    /**
     * 记录请求信息.
     */
    private function logRequest(Request $request): void
    {
        $this->logger->debug('收到webhook请求', [
            'method' => $request->getMethod(),
            'uri' => $request->getUri(),
            'headers' => $request->headers->all(),
            'body' => $request->getContent(),
        ]);
    }
}
