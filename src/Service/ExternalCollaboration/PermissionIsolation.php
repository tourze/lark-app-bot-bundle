<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\ExternalCollaboration;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * 权限隔离机制.
 *
 * 用于实现内外部用户的权限隔离
 */
#[Autoconfigure(public: true)]
class PermissionIsolation
{
    /**
     * 权限级别常量.
     */
    public const LEVEL_NONE = 0;
    public const LEVEL_READ = 1;
    public const LEVEL_WRITE = 2;
    public const LEVEL_ADMIN = 3;

    /**
     * 资源类型常量.
     */
    public const RESOURCE_MESSAGE = 'message';
    public const RESOURCE_FILE = 'file';
    public const RESOURCE_GROUP = 'group';
    public const RESOURCE_USER_INFO = 'user_info';
    public const RESOURCE_MENU = 'menu';
    public const RESOURCE_API = 'api';

    private ExternalUserIdentifier $userIdentifier;

    private CacheItemPoolInterface $cache;

    private LoggerInterface $logger;

    /**
     * 默认权限配置.
     *
     * @var array<string, array<string, int>>
     */
    private array $defaultPermissions = [
        'internal' => [
            self::RESOURCE_MESSAGE => self::LEVEL_WRITE,
            self::RESOURCE_FILE => self::LEVEL_WRITE,
            self::RESOURCE_GROUP => self::LEVEL_WRITE,
            self::RESOURCE_USER_INFO => self::LEVEL_READ,
            self::RESOURCE_MENU => self::LEVEL_WRITE,
            self::RESOURCE_API => self::LEVEL_WRITE,
        ],
        'external' => [
            self::RESOURCE_MESSAGE => self::LEVEL_READ,
            self::RESOURCE_FILE => self::LEVEL_NONE,
            self::RESOURCE_GROUP => self::LEVEL_READ,
            self::RESOURCE_USER_INFO => self::LEVEL_NONE,
            self::RESOURCE_MENU => self::LEVEL_READ,
            self::RESOURCE_API => self::LEVEL_NONE,
        ],
    ];

    public function __construct(
        ExternalUserIdentifier $userIdentifier,
        CacheItemPoolInterface $cache,
        ?LoggerInterface $logger = null,
    ) {
        $this->userIdentifier = $userIdentifier;
        $this->cache = $cache;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * 检查用户对资源的访问权限.
     *
     * @param string $userId        用户ID
     * @param string $resource      资源类型
     * @param int    $requiredLevel 所需权限级别
     */
    public function checkPermission(string $userId, string $resource, int $requiredLevel): bool
    {
        $userType = $this->userIdentifier->isExternalUser($userId) ? 'external' : 'internal';
        $userLevel = $this->getUserPermissionLevel($userId, $resource, $userType);

        $hasPermission = $userLevel >= $requiredLevel;

        $this->logger->info('Permission check', [
            'user_id' => $userId,
            'user_type' => $userType,
            'resource' => $resource,
            'required_level' => $requiredLevel,
            'user_level' => $userLevel,
            'has_permission' => $hasPermission,
        ]);

        return $hasPermission;
    }

    /**
     * 设置用户对资源的权限.
     *
     * @param string $userId   用户ID
     * @param string $resource 资源类型
     * @param int    $level    权限级别
     */
    public function setPermission(string $userId, string $resource, int $level): void
    {
        $cacheKey = \sprintf('permission_%s_%s', $userId, $resource);
        $cacheItem = $this->cache->getItem($cacheKey);

        $cacheItem->set($level);
        $cacheItem->expiresAfter(3600);
        $this->cache->save($cacheItem);

        $this->logger->info('Permission set', [
            'user_id' => $userId,
            'resource' => $resource,
            'level' => $level,
        ]);
    }

    /**
     * 批量设置用户权限.
     *
     * @param string             $userId      用户ID
     * @param array<string, int> $permissions 权限配置
     */
    public function setPermissions(string $userId, array $permissions): void
    {
        foreach ($permissions as $resource => $level) {
            $this->setPermission($userId, $resource, $level);
        }
    }

    /**
     * 获取用户的所有权限.
     *
     * @param string $userId 用户ID
     *
     * @return array<string, int>
     */
    public function getUserPermissions(string $userId): array
    {
        $userType = $this->userIdentifier->isExternalUser($userId) ? 'external' : 'internal';
        $permissions = [];

        foreach ($this->defaultPermissions[$userType] as $resource => $defaultLevel) {
            $cacheKey = \sprintf('permission_%s_%s', $userId, $resource);
            $cacheItem = $this->cache->getItem($cacheKey);

            if ($cacheItem->isHit()) {
                $permissions[$resource] = $cacheItem->get();
            } else {
                $permissions[$resource] = $defaultLevel;
            }
        }

        return $permissions;
    }

    /**
     * 清除用户权限缓存.
     *
     * @param string $userId 用户ID
     */
    public function clearUserPermissions(string $userId): void
    {
        $resources = array_keys($this->defaultPermissions['internal']);

        foreach ($resources as $resource) {
            $cacheKey = \sprintf('permission_%s_%s', $userId, $resource);
            $this->cache->deleteItem($cacheKey);
        }

        $this->logger->info('User permissions cleared', [
            'user_id' => $userId,
        ]);
    }

    /**
     * 过滤用户可访问的资源列表.
     *
     * @param string             $userId        用户ID
     * @param array<int, string> $resources     资源列表
     * @param int                $requiredLevel 所需权限级别
     *
     * @return array<int, string>
     */
    public function filterAccessibleResources(string $userId, array $resources, int $requiredLevel): array
    {
        $accessible = [];

        foreach ($resources as $resource) {
            if ($this->checkPermission($userId, $resource, $requiredLevel)) {
                $accessible[] = $resource;
            }
        }

        return $accessible;
    }

    /**
     * 获取权限级别名称.
     *
     * @param int $level 权限级别
     */
    public static function getLevelName(int $level): string
    {
        return match ($level) {
            self::LEVEL_NONE => 'none',
            self::LEVEL_READ => 'read',
            self::LEVEL_WRITE => 'write',
            self::LEVEL_ADMIN => 'admin',
            default => 'unknown',
        };
    }

    /**
     * 从名称获取权限级别.
     *
     * @param string $name 权限级别名称
     */
    public static function getLevelFromName(string $name): int
    {
        return match (strtolower($name)) {
            'none' => self::LEVEL_NONE,
            'read' => self::LEVEL_READ,
            'write' => self::LEVEL_WRITE,
            'admin' => self::LEVEL_ADMIN,
            default => self::LEVEL_NONE,
        };
    }

    /**
     * 获取用户对特定资源的权限级别.
     *
     * @param string $userId   用户ID
     * @param string $resource 资源类型
     * @param string $userType 用户类型
     */
    private function getUserPermissionLevel(string $userId, string $resource, string $userType): int
    {
        // 先从缓存中查找用户特定权限
        $cacheKey = \sprintf('permission_%s_%s', $userId, $resource);
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        // 使用默认权限
        $level = $this->defaultPermissions[$userType][$resource] ?? self::LEVEL_NONE;

        // 缓存权限信息
        $cacheItem->set($level);
        $cacheItem->expiresAfter(3600); // 1小时
        $this->cache->save($cacheItem);

        return $level;
    }
}
