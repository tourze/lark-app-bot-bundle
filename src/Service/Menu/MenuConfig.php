<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Menu;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\LarkAppBotBundle\Exception\ValidationException;

/**
 * 菜单配置类
 * 用于定义和验证机器人菜单结构.
 */
#[Autoconfigure(public: true)]
class MenuConfig
{
    /**
     * 菜单类型常量.
     */
    public const TYPE_TEXT = 'text';

    /**
     * 菜单限制常量.
     */
    public const MAX_TOP_LEVEL_MENUS = 3;
    public const MAX_SUB_MENUS = 5;
    public const MAX_TEXT_LENGTH = 8;

    /**
     * @var array<int, array{
     *     text: string,
     *     value?: string,
     *     type?: string,
     *     sub_menu?: array{
     *         list: array<int, array{
     *             text: string,
     *             value: string,
     *             type: string
     *         }>
     *     }
     * }>
     */
    private array $menuItems = [];

    /**
     * 添加一级菜单.
     *
     * @param string      $text  菜单显示文本
     * @param string|null $value 菜单值（如果没有子菜单）
     *
     * @throws ValidationException
     */
    public function addTopLevelMenu(string $text, ?string $value = null): self
    {
        $this->validateMenuText($text);

        if (\count($this->menuItems) >= self::MAX_TOP_LEVEL_MENUS) {
            throw new ValidationException(\sprintf('最多只能添加 %d 个一级菜单', self::MAX_TOP_LEVEL_MENUS));
        }

        /** @var array{text: string, value?: string, type?: string, sub_menu?: array{list: array<int, array{text: string, value: string, type: string}>}} $menuItem */
        $menuItem = [
            'text' => $text,
        ];

        if (null !== $value) {
            $menuItem['value'] = $value;
            $menuItem['type'] = self::TYPE_TEXT;
        }

        $this->menuItems[] = $menuItem;

        return $this;
    }

    /**
     * 为最后一个一级菜单添加子菜单.
     *
     * @param string $text  子菜单显示文本
     * @param string $value 子菜单值
     *
     * @throws ValidationException
     */
    public function addSubMenu(string $text, string $value): self
    {
        if ([] === $this->menuItems) {
            throw new ValidationException('必须先添加一级菜单');
        }

        $this->validateMenuText($text);

        $lastIndex = \count($this->menuItems) - 1;
        $lastMenuItem = $this->menuItems[$lastIndex];

        // 如果一级菜单有 value，则不能添加子菜单
        if (isset($lastMenuItem['value'])) {
            throw new ValidationException('一级菜单已有直接功能，不能添加子菜单');
        }

        // 初始化子菜单列表
        if (!isset($lastMenuItem['sub_menu'])) {
            /** @var array{list: array<int, array{text: string, value: string, type: string}>} $subMenu */
            $subMenu = ['list' => []];
            $lastMenuItem['sub_menu'] = $subMenu;
        }

        $subMenuCount = \count($lastMenuItem['sub_menu']['list']);
        if ($subMenuCount >= self::MAX_SUB_MENUS) {
            throw new ValidationException(\sprintf('每个一级菜单下最多只能添加 %d 个子菜单', self::MAX_SUB_MENUS));
        }

        /** @var array{text: string, value: string, type: string} $subMenuItem */
        $subMenuItem = [
            'text' => $text,
            'value' => $value,
            'type' => self::TYPE_TEXT,
        ];
        $lastMenuItem['sub_menu']['list'][] = $subMenuItem;
        $this->menuItems[$lastIndex] = $lastMenuItem;

        return $this;
    }

    /**
     * 转换为飞书API格式.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'menu' => [
                'list' => $this->menuItems,
            ],
        ];
    }

    /**
     * 从数组创建菜单配置.
     *
     * @param array<string, mixed> $config
     *
     * @throws ValidationException
     */
    public static function fromArray(array $config): self
    {
        $menu = new self();

        self::validateMenuStructure($config);

        $menuConfigData = $config['menu'] ?? null;
        assert(is_array($menuConfigData) && isset($menuConfigData['list']) && \is_array($menuConfigData['list']), 'Menu list must be an array');
        foreach ($menuConfigData['list'] as $topLevel) {
            // 确保$topLevel是数组类型
            if (!is_array($topLevel)) {
                continue;
            }
            self::processTopLevelMenu($menu, $topLevel);
        }

        return $menu;
    }

    /**
     * 获取所有菜单值到文本的映射.
     *
     * @return array<string, string>
     */
    public function getValueTextMapping(): array
    {
        $mapping = [];

        foreach ($this->menuItems as $topLevel) {
            // 一级菜单的直接值
            if (isset($topLevel['value'])) {
                $mapping[$topLevel['value']] = $topLevel['text'];
            }

            // 子菜单的值
            if (isset($topLevel['sub_menu']['list'])) {
                foreach ($topLevel['sub_menu']['list'] as $subMenu) {
                    $mapping[$subMenu['value']] = \sprintf(
                        '%s > %s',
                        $topLevel['text'],
                        $subMenu['text']
                    );
                }
            }
        }

        return $mapping;
    }

    /**
     * 检查菜单值是否存在.
     */
    public function hasValue(string $value): bool
    {
        $mapping = $this->getValueTextMapping();

        return isset($mapping[$value]);
    }

    /**
     * 获取菜单项数量.
     */
    public function getMenuCount(): int
    {
        return \count($this->menuItems);
    }

    /**
     * 清空菜单配置.
     */
    public function clear(): self
    {
        $this->menuItems = [];

        return $this;
    }

    /**
     * 验证菜单文本长度.
     *
     * @throws ValidationException
     */
    private function validateMenuText(string $text): void
    {
        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            throw new ValidationException(\sprintf('菜单文本最多 %d 个字符', self::MAX_TEXT_LENGTH));
        }

        if ('' === $text) {
            throw new ValidationException('菜单文本不能为空');
        }
    }

    /**
     * 验证菜单结构.
     *
     * @param array<string, mixed> $config
     *
     * @throws ValidationException
     */
    private static function validateMenuStructure(array $config): void
    {
        $menuConfig = $config['menu'] ?? null;
        if (!is_array($menuConfig) || !isset($menuConfig['list']) || !\is_array($menuConfig['list'])) {
            throw new ValidationException('无效的菜单配置格式');
        }
    }

    /**
     * 处理一级菜单.
     *
     * @param array<string, mixed> $topLevel
     *
     * @throws ValidationException
     */
    private static function processTopLevelMenu(self $menuConfig, array $topLevel): void
    {
        self::validateTopLevelMenu($topLevel);

        assert(isset($topLevel['text']) && \is_string($topLevel['text']), 'Menu text must be a string');
        assert(!isset($topLevel['value']) || \is_string($topLevel['value']), 'Menu value must be a string or null');

        $menuConfig->addTopLevelMenu(
            $topLevel['text'],
            $topLevel['value'] ?? null
        );

        self::processSubMenus($menuConfig, $topLevel);
    }

    /**
     * 验证一级菜单.
     *
     * @param array<string, mixed> $topLevel
     *
     * @throws ValidationException
     */
    private static function validateTopLevelMenu(array $topLevel): void
    {
        if (!isset($topLevel['text'])) {
            throw new ValidationException('菜单项必须包含 text 字段');
        }
    }

    /**
     * 处理子菜单.
     *
     * @param array<string, mixed> $topLevel
     *
     * @throws ValidationException
     */
    private static function processSubMenus(self $menuConfig, array $topLevel): void
    {
        $subMenu = $topLevel['sub_menu'] ?? null;
        if (!is_array($subMenu) || !isset($subMenu['list']) || !\is_array($subMenu['list'])) {
            return;
        }

        foreach ($subMenu['list'] as $subMenuItem) {
            // 确保$subMenuItem是数组类型
            if (!is_array($subMenuItem)) {
                continue;
            }
            self::addSubMenuItem($menuConfig, $subMenuItem);
        }
    }

    /**
     * 添加子菜单项.
     *
     * @param array<string, mixed> $subMenu
     *
     * @throws ValidationException
     */
    private static function addSubMenuItem(self $menuConfig, array $subMenu): void
    {
        if (!isset($subMenu['text']) || !isset($subMenu['value'])) {
            throw new ValidationException('子菜单必须包含 text 和 value 字段');
        }

        assert(\is_string($subMenu['text']), 'Sub menu text must be a string');
        assert(\is_string($subMenu['value']), 'Sub menu value must be a string');

        $menuConfig->addSubMenu($subMenu['text'], $subMenu['value']);
    }
}
