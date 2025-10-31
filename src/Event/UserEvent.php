<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Event;

/**
 * 用户事件.
 */
class UserEvent extends LarkEvent
{
    public const USER_CREATED = 'lark.user.created';
    public const USER_UPDATED = 'lark.user.updated';
    public const USER_DELETED = 'lark.user.deleted';
    public const USER_ACTIVITY = 'lark.user.activity';
    public const USER_DATA_LOADED = 'lark.user.data_loaded';
    public const USER_DATA_UPDATED = 'lark.user.data_updated';
    public const USER_DATA_DELETED = 'lark.user.data_deleted';
    public const USER_DATA_IMPORTED = 'lark.user.data_imported';

    /** @var array<string, mixed> */
    private array $user;

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $context
     */
    public function __construct(string $type, array $user, array $context = [])
    {
        $this->user = $user;
        parent::__construct($type, $user, $context);
    }

    /**
     * 获取事件类型.
     */
    public function getType(): string
    {
        return $this->eventType;
    }

    /**
     * 获取用户信息.
     */
    /**
     * @return array<string, mixed>
     */
    public function getUser(): array
    {
        return $this->user;
    }

    /**
     * 获取用户ID.
     */
    public function getUserId(): string
    {
        $userId = $this->data['user_id'] ?? '';
        \assert(\is_string($userId));

        return $userId;
    }

    /**
     * 获取用户Open ID.
     */
    public function getOpenId(): string
    {
        $openId = $this->data['open_id'] ?? '';
        \assert(\is_string($openId));

        return $openId;
    }

    /**
     * 获取用户Union ID.
     */
    public function getUnionId(): string
    {
        $unionId = $this->data['union_id'] ?? '';
        \assert(\is_string($unionId));

        return $unionId;
    }

    /**
     * 获取用户名称.
     */
    public function getName(): string
    {
        $name = $this->data['name'] ?? '';
        \assert(\is_string($name));

        return $name;
    }

    /**
     * 获取用户邮箱.
     */
    public function getEmail(): string
    {
        $email = $this->data['email'] ?? '';
        \assert(\is_string($email));

        return $email;
    }

    /**
     * 获取用户手机.
     */
    public function getMobile(): string
    {
        $mobile = $this->data['mobile'] ?? '';
        \assert(\is_string($mobile));

        return $mobile;
    }

    /**
     * 获取用户部门列表.
     *
     * @return array<string>
     */
    public function getDepartmentIds(): array
    {
        $departmentIds = $this->data['department_ids'] ?? [];
        \assert(\is_array($departmentIds));

        // 确保所有部门ID都是字符串
        foreach ($departmentIds as $departmentId) {
            \assert(\is_string($departmentId));
        }

        /** @var array<string> $departmentIds */
        return $departmentIds;
    }

    /**
     * 是否是创建事件.
     */
    public function isCreated(): bool
    {
        return 'contact.user.created_v3' === $this->eventType;
    }

    /**
     * 是否是更新事件.
     */
    public function isUpdated(): bool
    {
        return 'contact.user.updated_v3' === $this->eventType;
    }

    /**
     * 是否是删除事件.
     */
    public function isDeleted(): bool
    {
        return 'contact.user.deleted_v3' === $this->eventType;
    }
}
