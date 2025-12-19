<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\ExternalCollaboration;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tourze\LarkAppBotBundle\Event\GenericEvent;
use Tourze\LarkAppBotBundle\Exception\ApiException;
use Tourze\LarkAppBotBundle\Service\Client\LarkClient;

/**
 * 外部邀请管理器.
 *
 * 管理外部用户的邀请流程
 */
#[Autoconfigure(public: true)]
final class ExternalInvitationManager
{
    /**
     * 邀请状态常量.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';

    private LarkClient $client;

    private CacheItemPoolInterface $cache;

    private EventDispatcherInterface $eventDispatcher;

    private SecurityPolicy $securityPolicy;

    private LoggerInterface $logger;

    public function __construct(
        LarkClient $client,
        CacheItemPoolInterface $cache,
        EventDispatcherInterface $eventDispatcher,
        SecurityPolicy $securityPolicy,
        ?LoggerInterface $logger = null,
    ) {
        $this->client = $client;
        $this->cache = $cache;
        $this->eventDispatcher = $eventDispatcher;
        $this->securityPolicy = $securityPolicy;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * 创建外部邀请.
     *
     * @param array<string, mixed> $invitationData 邀请数据
     *
     * @return array<string, mixed>
     */
    public function createInvitation(array $invitationData): array
    {
        $invitationId = $this->generateInvitationId();

        $invitation = [
            'id' => $invitationId,
            'inviter_id' => $invitationData['inviter_id'],
            'invitee_email' => $invitationData['invitee_email'],
            'invitee_name' => $invitationData['invitee_name'] ?? '',
            'group_id' => $invitationData['group_id'] ?? null,
            'permissions' => $invitationData['permissions'] ?? [],
            'status' => self::STATUS_PENDING,
            'created_at' => time(),
            'expires_at' => time() + (7 * 24 * 60 * 60), // 7天后过期
            'message' => $invitationData['message'] ?? '',
            'security_check_passed' => false,
        ];

        // 执行安全检查
        $securityContext = [
            'inviter_id' => $invitation['inviter_id'],
            'invitee_email' => $invitation['invitee_email'],
            'has_approval' => $invitationData['has_approval'] ?? false,
        ];

        if (!$this->securityPolicy->checkPolicy(SecurityPolicy::POLICY_DATA_ACCESS, $securityContext)) {
            $this->logger->warning('Security policy check failed for invitation', [
                'invitation_id' => $invitationId,
                'context' => $securityContext,
            ]);
            $invitation['status'] = self::STATUS_REJECTED;
            $invitation['rejection_reason'] = 'security_policy_violation';
        } else {
            $invitation['security_check_passed'] = true;
        }

        // 保存邀请
        $this->saveInvitation($invitation);

        // 触发邀请创建事件
        $this->eventDispatcher->dispatch(new GenericEvent('external.invitation.created', [
            'invitation' => $invitation,
        ]));

        $this->logger->info('External invitation created', [
            'invitation_id' => $invitationId,
            'invitee_email' => $invitation['invitee_email'],
        ]);

        return $invitation;
    }

    /**
     * 获取邀请详情.
     *
     * @param string $invitationId 邀请ID
     *
     * @return array<string, mixed>|null
     */
    public function getInvitation(string $invitationId): ?array
    {
        $cacheKey = \sprintf('invitation_%s', $invitationId);
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            $invitation = $cacheItem->get();

            // 验证邀请数据结构
            if (!is_array($invitation)) {
                $this->logger->warning('Invalid invitation data structure', [
                    'invitation_id' => $invitationId,
                ]);

                return null;
            }

            // 检查是否过期
            $expiresAt = $invitation['expires_at'] ?? 0;
            $status = $invitation['status'] ?? '';

            if (is_numeric($expiresAt) && $expiresAt < time() && self::STATUS_PENDING === $status) {
                $invitation['status'] = self::STATUS_EXPIRED;
                $this->saveInvitation($invitation);
            }

            return $invitation;
        }

        return null;
    }

    /**
     * 批准邀请.
     *
     * @param string $invitationId 邀请ID
     * @param string $approverId   批准者ID
     */
    public function approveInvitation(string $invitationId, string $approverId): bool
    {
        $invitation = $this->getInvitation($invitationId);

        if (null === $invitation) {
            $this->logger->error('Invitation not found', ['invitation_id' => $invitationId]);

            return false;
        }

        if (self::STATUS_PENDING !== $invitation['status']) {
            $this->logger->warning('Cannot approve non-pending invitation', [
                'invitation_id' => $invitationId,
                'current_status' => $invitation['status'],
            ]);

            return false;
        }

        $invitation['status'] = self::STATUS_APPROVED;
        $invitation['approved_by'] = $approverId;
        $invitation['approved_at'] = time();

        $this->saveInvitation($invitation);

        // 执行邀请批准后的操作
        $this->processApprovedInvitation($invitation);

        // 触发邀请批准事件
        $this->eventDispatcher->dispatch(new GenericEvent('external.invitation.approved', [
            'invitation' => $invitation,
            'approver_id' => $approverId,
        ]));

        $this->logger->info('Invitation approved', [
            'invitation_id' => $invitationId,
            'approver_id' => $approverId,
        ]);

        return true;
    }

    /**
     * 拒绝邀请.
     *
     * @param string $invitationId 邀请ID
     * @param string $rejectorId   拒绝者ID
     * @param string $reason       拒绝原因
     */
    public function rejectInvitation(string $invitationId, string $rejectorId, string $reason): bool
    {
        $invitation = $this->getInvitation($invitationId);

        if (null === $invitation) {
            return false;
        }

        if (self::STATUS_PENDING !== $invitation['status']) {
            return false;
        }

        $invitation['status'] = self::STATUS_REJECTED;
        $invitation['rejected_by'] = $rejectorId;
        $invitation['rejected_at'] = time();
        $invitation['rejection_reason'] = $reason;

        $this->saveInvitation($invitation);

        // 触发邀请拒绝事件
        $this->eventDispatcher->dispatch(new GenericEvent('external.invitation.rejected', [
            'invitation' => $invitation,
            'rejector_id' => $rejectorId,
            'reason' => $reason,
        ]));

        $this->logger->info('Invitation rejected', [
            'invitation_id' => $invitationId,
            'rejector_id' => $rejectorId,
            'reason' => $reason,
        ]);

        return true;
    }

    /**
     * 撤销邀请.
     *
     * @param string $invitationId 邀请ID
     * @param string $revokerId    撤销者ID
     */
    public function revokeInvitation(string $invitationId, string $revokerId): bool
    {
        $invitation = $this->getInvitation($invitationId);

        if (null === $invitation) {
            return false;
        }

        if (\in_array($invitation['status'], [self::STATUS_APPROVED, self::STATUS_EXPIRED], true)) {
            return false;
        }

        $invitation['status'] = self::STATUS_REVOKED;
        $invitation['revoked_by'] = $revokerId;
        $invitation['revoked_at'] = time();

        $this->saveInvitation($invitation);

        $this->logger->info('Invitation revoked', [
            'invitation_id' => $invitationId,
            'revoker_id' => $revokerId,
        ]);

        return true;
    }

    /**
     * 获取用户的邀请列表.
     *
     * @param string $userId 用户ID
     * @param string $role   角色（inviter/invitee）
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUserInvitations(string $userId, string $role = 'inviter'): array
    {
        $cacheKey = \sprintf('user_invitations_%s_%s', $role, $userId);
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            $invitations = $cacheItem->get();

            return is_array($invitations) ? $invitations : [];
        }

        // 实际应该从数据库查询，这里简化处理
        return [];
    }

    /**
     * 处理已批准的邀请.
     *
     * @param array<string, mixed> $invitation 邀请信息
     */
    private function processApprovedInvitation(array $invitation): void
    {
        try {
            // 发送邀请邮件
            $this->sendInvitationEmail($invitation);

            // 如果有群组ID，添加到群组
            if (null !== $invitation['group_id'] && '' !== $invitation['group_id']) {
                $this->addExternalUserToGroup($invitation['invitee_email'], $invitation['group_id']);
            }

            // 设置权限
            if ([] !== $invitation['permissions']) {
                $this->setExternalUserPermissions($invitation['invitee_email'], $invitation['permissions']);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to process approved invitation', [
                'invitation_id' => $invitation['id'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 发送邀请邮件.
     *
     * @param array<string, mixed> $invitation 邀请信息
     */
    private function sendInvitationEmail(array $invitation): void
    {
        // 这里应该调用邮件服务发送邀请
        $this->logger->info('Sending invitation email', [
            'to' => $invitation['invitee_email'],
            'invitation_id' => $invitation['id'],
        ]);
    }

    /**
     * 添加外部用户到群组.
     *
     * @param string $email   用户邮箱
     * @param string $groupId 群组ID
     */
    private function addExternalUserToGroup(string $email, string $groupId): void
    {
        try {
            // 调用API添加用户到群组
            $this->client->request('POST', '/open-apis/im/v1/chats/' . $groupId . '/members/create', [
                'json' => [
                    'member_id_list' => [$email],
                    'member_id_type' => 'email',
                ],
            ]);
        } catch (ApiException $e) {
            $this->logger->error('Failed to add external user to group', [
                'email' => $email,
                'group_id' => $groupId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 设置外部用户权限.
     *
     * @param string               $email       用户邮箱
     * @param array<string, mixed> $permissions 权限配置
     */
    private function setExternalUserPermissions(string $email, array $permissions): void
    {
        // 这里应该调用权限管理服务设置权限
        $this->logger->info('Setting external user permissions', [
            'email' => $email,
            'permissions' => $permissions,
        ]);
    }

    /**
     * 保存邀请信息.
     *
     * @param array<string, mixed> $invitation 邀请信息
     */
    private function saveInvitation(array $invitation): void
    {
        $cacheKey = \sprintf('invitation_%s', $invitation['id']);
        $cacheItem = $this->cache->getItem($cacheKey);

        $cacheItem->set($invitation);
        $cacheItem->expiresAfter(30 * 24 * 60 * 60); // 30天
        $this->cache->save($cacheItem);

        // 同时更新用户邀请列表缓存
        $this->updateUserInvitationsCache($invitation);
    }

    /**
     * 更新用户邀请列表缓存.
     *
     * @param array<string, mixed> $invitation 邀请信息
     */
    private function updateUserInvitationsCache(array $invitation): void
    {
        // 清除相关用户的邀请列表缓存
        $this->cache->deleteItem(\sprintf('user_invitations_inviter_%s', $invitation['inviter_id']));
    }

    /**
     * 生成邀请ID.
     */
    private function generateInvitationId(): string
    {
        return 'inv_' . bin2hex(random_bytes(16));
    }
}
