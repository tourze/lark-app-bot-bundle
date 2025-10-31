<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\ExternalCollaboration;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Tourze\LarkAppBotBundle\Event\GenericEvent;
use Tourze\LarkAppBotBundle\Exception\InvalidAuditFormatException;

/**
 * 审计日志记录器.
 *
 * 记录外部协作相关的所有操作
 */
#[Autoconfigure(public: true)]
class AuditLogger
{
    /**
     * 审计事件类型.
     */
    public const EVENT_EXTERNAL_USER_LOGIN = 'external_user_login';
    public const EVENT_EXTERNAL_USER_ACCESS = 'external_user_access';
    public const EVENT_EXTERNAL_USER_DENIED = 'external_user_denied';
    public const EVENT_INVITATION_CREATED = 'invitation_created';
    public const EVENT_INVITATION_APPROVED = 'invitation_approved';
    public const EVENT_INVITATION_REJECTED = 'invitation_rejected';
    public const EVENT_PERMISSION_CHANGED = 'permission_changed';
    public const EVENT_SECURITY_VIOLATION = 'security_violation';
    public const EVENT_DATA_EXPORTED = 'data_exported';
    public const EVENT_FILE_SHARED = 'file_shared';
    public const EVENT_GROUP_ACCESS = 'group_access';

    /**
     * 日志级别.
     */
    public const LEVEL_INFO = 'info';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR = 'error';
    public const LEVEL_CRITICAL = 'critical';

    private LoggerInterface $logger;

    private EventDispatcherInterface $eventDispatcher;

    private ExternalUserIdentifier $userIdentifier;

    private RequestStack $requestStack;

    /**
     * 审计日志存储.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $auditLogs = [];

    public function __construct(
        LoggerInterface $logger,
        EventDispatcherInterface $eventDispatcher,
        ExternalUserIdentifier $userIdentifier,
        RequestStack $requestStack,
    ) {
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
        $this->userIdentifier = $userIdentifier;
        $this->requestStack = $requestStack;
    }

    /**
     * 记录审计日志.
     *
     * @param string               $eventType 事件类型
     * @param string               $userId    用户ID
     * @param array<string, mixed> $data      附加数据
     * @param string               $level     日志级别
     */
    public function log(
        string $eventType,
        string $userId,
        array $data = [],
        string $level = self::LEVEL_INFO,
    ): void {
        $auditLog = [
            'id' => $this->generateLogId(),
            'event_type' => $eventType,
            'user_id' => $userId,
            'is_external' => $this->userIdentifier->isExternalUser($userId),
            'timestamp' => time(),
            'datetime' => (new \DateTime())->format('Y-m-d H:i:s'),
            'level' => $level,
            'data' => $data,
            'ip_address' => $this->getClientIp(),
            'user_agent' => $this->getUserAgent(),
            'session_id' => $this->getSessionId(),
        ];

        // 添加额外的上下文信息
        $auditLog = $this->enrichLogData($auditLog);

        // 存储日志
        $this->auditLogs[] = $auditLog;

        // 记录到标准日志
        $this->logToStandardLogger($auditLog);

        // 触发审计事件
        $this->eventDispatcher->dispatch(new GenericEvent('audit.log.created', [
            'audit_log' => $auditLog,
        ]));

        // 对于关键事件，可能需要实时通知
        if ($this->isCriticalEvent($eventType)) {
            $this->handleCriticalEvent($auditLog);
        }
    }

    /**
     * 记录外部用户登录.
     *
     * @param string               $userId  用户ID
     * @param bool                 $success 是否成功
     * @param array<string, mixed> $details 详细信息
     */
    public function logExternalUserLogin(string $userId, bool $success, array $details = []): void
    {
        $this->log(
            self::EVENT_EXTERNAL_USER_LOGIN,
            $userId,
            array_merge($details, [
                'success' => $success,
                'login_time' => time(),
            ]),
            $success ? self::LEVEL_INFO : self::LEVEL_WARNING
        );
    }

    /**
     * 记录外部用户访问.
     *
     * @param string $userId   用户ID
     * @param string $resource 访问的资源
     * @param bool   $allowed  是否允许
     */
    public function logExternalUserAccess(string $userId, string $resource, bool $allowed): void
    {
        $this->log(
            $allowed ? self::EVENT_EXTERNAL_USER_ACCESS : self::EVENT_EXTERNAL_USER_DENIED,
            $userId,
            [
                'resource' => $resource,
                'allowed' => $allowed,
                'access_time' => time(),
            ],
            $allowed ? self::LEVEL_INFO : self::LEVEL_WARNING
        );
    }

    /**
     * 记录权限变更.
     *
     * @param string               $targetUserId 目标用户ID
     * @param string               $operatorId   操作者ID
     * @param array<string, mixed> $changes      变更内容
     */
    public function logPermissionChange(
        string $targetUserId,
        string $operatorId,
        array $changes,
    ): void {
        $this->log(
            self::EVENT_PERMISSION_CHANGED,
            $operatorId,
            [
                'target_user_id' => $targetUserId,
                'changes' => $changes,
                'change_time' => time(),
            ],
            self::LEVEL_WARNING
        );
    }

    /**
     * 记录安全违规.
     *
     * @param string               $userId        用户ID
     * @param string               $violationType 违规类型
     * @param array<string, mixed> $details       详细信息
     */
    public function logSecurityViolation(
        string $userId,
        string $violationType,
        array $details = [],
    ): void {
        $this->log(
            self::EVENT_SECURITY_VIOLATION,
            $userId,
            array_merge($details, [
                'violation_type' => $violationType,
                'violation_time' => time(),
            ]),
            self::LEVEL_CRITICAL
        );
    }

    /**
     * 查询审计日志.
     *
     * @param array<string, mixed> $criteria 查询条件
     * @param int                  $limit    限制数量
     * @param int                  $offset   偏移量
     *
     * @return array<int, array<string, mixed>>
     */
    public function query(array $criteria = [], int $limit = 100, int $offset = 0): array
    {
        $filtered = $this->auditLogs;

        // 应用过滤条件
        if ([] !== $criteria) {
            $filtered = array_filter($filtered, function ($log) use ($criteria) {
                foreach ($criteria as $key => $value) {
                    if (!isset($log[$key]) || $log[$key] !== $value) {
                        return false;
                    }
                }

                return true;
            });
        }

        // 按时间倒序排序
        usort($filtered, fn ($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        // 应用分页
        return \array_slice($filtered, $offset, $limit);
    }

    /**
     * 获取用户的审计日志.
     *
     * @param string $userId 用户ID
     * @param int    $days   最近天数
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUserAuditLogs(string $userId, int $days = 7): array
    {
        $since = time() - ($days * 24 * 60 * 60);

        return array_filter($this->auditLogs, function ($log) use ($userId, $since) {
            return $log['user_id'] === $userId && $log['timestamp'] >= $since;
        });
    }

    /**
     * 获取审计统计
     *
     * @param int $days 统计天数
     *
     * @return array<string, mixed>
     */
    public function getStatistics(int $days = 7): array
    {
        return (new AuditStatistics($this->auditLogs))->get($days);
    }


    /**
     * 导出审计日志.
     *
     * @param array<string, mixed> $criteria 导出条件
     * @param string               $format   导出格式（json/csv）
     *
     * @throws InvalidAuditFormatException 导出失败时抛出异常
     */
    public function export(array $criteria = [], string $format = 'json'): string
    {
        $logs = $this->query($criteria, \PHP_INT_MAX);
        return (new AuditExporter())->export($logs, $format);
    }

    /**
     * 丰富日志数据.
     *
     * @param array<string, mixed> $log 原始日志
     *
     * @return array<string, mixed>
     */
    private function enrichLogData(array $log): array
    {
        $request = $this->requestStack->getCurrentRequest();

        // 添加环境信息
        $log['environment'] = [
            'php_version' => \PHP_VERSION,
            'server_name' => $request?->getHost() ?? 'unknown',
            'request_method' => $request?->getMethod() ?? 'unknown',
            'request_uri' => $request?->getRequestUri() ?? 'unknown',
        ];

        return $log;
    }

    /**
     * 记录到标准日志.
     *
     * @param array<string, mixed> $auditLog 审计日志
     */
    private function logToStandardLogger(array $auditLog): void
    {
        $datetime = 'unknown';
        if (isset($auditLog['datetime']) && is_scalar($auditLog['datetime'])) {
            $datetime = (string) $auditLog['datetime'];
        }

        $userId = 'unknown';
        if (isset($auditLog['user_id']) && is_scalar($auditLog['user_id'])) {
            $userId = (string) $auditLog['user_id'];
        }

        $eventType = 'unknown';
        if (isset($auditLog['event_type']) && is_scalar($auditLog['event_type'])) {
            $eventType = (string) $auditLog['event_type'];
        }

        $message = \sprintf(
            'Audit: %s - User: %s - Event: %s',
            $datetime,
            $userId,
            $eventType
        );

        switch ($auditLog['level']) {
            case self::LEVEL_INFO:
                $this->logger->info($message, $auditLog);
                break;
            case self::LEVEL_WARNING:
                $this->logger->warning($message, $auditLog);
                break;
            case self::LEVEL_ERROR:
                $this->logger->error($message, $auditLog);
                break;
            case self::LEVEL_CRITICAL:
                $this->logger->critical($message, $auditLog);
                break;
        }
    }

    /**
     * 判断是否为关键事件.
     *
     * @param string $eventType 事件类型
     */
    private function isCriticalEvent(string $eventType): bool
    {
        return \in_array($eventType, [
            self::EVENT_SECURITY_VIOLATION,
            self::EVENT_PERMISSION_CHANGED,
            self::EVENT_DATA_EXPORTED,
        ], true);
    }

    /**
     * 处理关键事件.
     *
     * @param array<string, mixed> $auditLog 审计日志
     */
    private function handleCriticalEvent(array $auditLog): void
    {
        // 触发关键事件通知
        $this->eventDispatcher->dispatch(new GenericEvent('audit.critical.event', [
            'audit_log' => $auditLog,
        ]));
    }

    // CSV 导出逻辑已迁移至 AuditExporter

    /**
     * 获取客户端IP.
     */
    private function getClientIp(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request?->getClientIp() ?? 'unknown';
    }

    /**
     * 获取用户代理.
     */
    private function getUserAgent(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request?->headers->get('User-Agent') ?? 'unknown';
    }

    /**
     * 获取会话ID.
     */
    private function getSessionId(): string
    {
        $sessionId = session_id();

        return false !== $sessionId ? $sessionId : 'no-session';
    }

    /**
     * 生成日志ID.
     */
    private function generateLogId(): string
    {
        return 'audit_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }

    // CSV 相关逻辑已迁移至导出器
}
