<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Menu;

use Symfony\Component\Yaml\Yaml;
use Tourze\LarkAppBotBundle\Exception\ValidationException;

/**
 * 菜单构建工具
 * 提供便捷的菜单创建和管理功能.
 */
final class MenuBuilder
{
    private MenuConfig $config;

    /**
     * @var array<string, MenuConfig>
     */
    private array $versions = [];

    private ?string $currentVersion = null;

    public function __construct()
    {
        $this->config = new MenuConfig();
    }

    /**
     * 创建新的菜单构建器实例.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * 添加一级菜单.
     *
     * @param string      $text  菜单文本
     * @param string|null $value 菜单值（如果没有子菜单）
     *
     * @throws ValidationException
     */
    public function addMenu(string $text, ?string $value = null): self
    {
        $this->config->addTopLevelMenu($text, $value);

        return $this;
    }

    /**
     * 添加子菜单（链式调用）.
     *
     * @param string $text  子菜单文本
     * @param string $value 子菜单值
     *
     * @throws ValidationException
     */
    public function withSubMenu(string $text, string $value): self
    {
        $this->config->addSubMenu($text, $value);

        return $this;
    }

    /**
     * 批量添加子菜单.
     *
     * @param array<array{text: string, value: string}> $subMenus
     *
     * @throws ValidationException
     */
    public function withSubMenus(array $subMenus): self
    {
        foreach ($subMenus as $subMenu) {
            if (!isset($subMenu['text']) || !isset($subMenu['value'])) {
                throw new ValidationException('子菜单必须包含 text 和 value');
            }
            $this->config->addSubMenu($subMenu['text'], $subMenu['value']);
        }

        return $this;
    }

    /**
     * 从YAML文件加载菜单配置.
     *
     * @param string $yamlFile YAML文件路径
     *
     * @throws ValidationException
     */
    public function fromYaml(string $yamlFile): self
    {
        if (!file_exists($yamlFile)) {
            throw new ValidationException(\sprintf('YAML文件不存在：%s', $yamlFile));
        }

        $content = file_get_contents($yamlFile);
        if (false === $content) {
            throw new ValidationException(\sprintf('无法读取YAML文件：%s', $yamlFile));
        }

        $data = Yaml::parse($content);
        if (!\is_array($data)) {
            throw new ValidationException('YAML文件格式错误');
        }

        return $this->fromArray($data);
    }

    /**
     * 从数组加载菜单配置.
     *
     * @param array<string, mixed> $data
     *
     * @throws ValidationException
     */
    public function fromArray(array $data): self
    {
        $this->config = MenuConfig::fromArray($data);

        return $this;
    }

    /**
     * 保存菜单配置到YAML文件.
     *
     * @param string $yamlFile YAML文件路径
     */
    public function toYaml(string $yamlFile): bool
    {
        $data = $this->config->toArray();
        $yaml = Yaml::dump($data, 4, 2);

        $result = file_put_contents($yamlFile, $yaml);

        return false !== $result;
    }

    /**
     * 获取构建的菜单配置.
     */
    public function build(): MenuConfig
    {
        return clone $this->config;
    }

    /**
     * 重置菜单配置.
     */
    public function reset(): self
    {
        $this->config = new MenuConfig();

        return $this;
    }

    /**
     * 保存当前配置为版本.
     *
     * @param string $version 版本标识
     */
    public function saveVersion(string $version): self
    {
        $this->versions[$version] = clone $this->config;
        $this->currentVersion = $version;

        return $this;
    }

    /**
     * 加载指定版本的配置.
     *
     * @param string $version 版本标识
     *
     * @throws ValidationException
     */
    public function loadVersion(string $version): self
    {
        if (!isset($this->versions[$version])) {
            throw new ValidationException(\sprintf('版本 "%s" 不存在', $version));
        }

        $this->config = clone $this->versions[$version];
        $this->currentVersion = $version;

        return $this;
    }

    /**
     * 获取所有版本.
     *
     * @return array<string>
     */
    public function getVersions(): array
    {
        return array_keys($this->versions);
    }

    /**
     * 获取当前版本.
     */
    public function getCurrentVersion(): ?string
    {
        return $this->currentVersion;
    }

    /**
     * 预览菜单（生成可读的文本格式）.
     */
    public function preview(): string
    {
        $menuArray = $this->config->toArray();
        $preview = "机器人菜单预览：\n";
        $preview .= str_repeat('=', 40) . "\n";

        assert(isset($menuArray['menu']['list']) && \is_array($menuArray['menu']['list']), 'Menu list must be an array');

        if ([] === $menuArray['menu']['list']) {
            $preview .= "（空菜单）\n";

            return $preview;
        }

        foreach ($menuArray['menu']['list'] as $index => $topLevel) {
            $preview .= $this->renderTopLevelMenu($topLevel, $index);
        }

        $preview .= str_repeat('=', 40) . "\n";

        // 添加统计信息
        $valueMapping = $this->config->getValueTextMapping();
        $preview .= \sprintf(
            "总计：%d 个一级菜单，%d 个可点击项\n",
            $this->config->getMenuCount(),
            \count($valueMapping)
        );

        return $preview;
    }

    /**
     * @param array<string,mixed> $topLevel
     */
    private function renderTopLevelMenu(array $topLevel, int $index): string
    {
        assert(isset($topLevel['text']) && \is_string($topLevel['text']), 'Menu text must be a string');

        $line = \sprintf('%d. %s', $index + 1, $topLevel['text']);

        if (isset($topLevel['value'])) {
            assert(\is_string($topLevel['value']), 'Menu value must be a string');
            $line .= \sprintf(' [%s]', $topLevel['value']);
        }

        $line .= "\n";

        if (isset($topLevel['sub_menu']['list'])) {
            assert(\is_array($topLevel['sub_menu']['list']), 'Sub menu list must be an array');
            $line .= $this->renderSubMenus($topLevel['sub_menu']['list'], $index);
        }

        return $line;
    }

    /**
     * @param array<int, array<string,mixed>> $list
     */
    private function renderSubMenus(array $list, int $topIndex): string
    {
        $out = '';
        foreach ($list as $subIndex => $subMenu) {
            assert(\is_array($subMenu), 'Sub menu must be an array');
            assert(isset($subMenu['text']) && \is_string($subMenu['text']), 'Sub menu text must be a string');
            assert(isset($subMenu['value']) && \is_string($subMenu['value']), 'Sub menu value must be a string');

            $out .= \sprintf(
                "   %d.%d %s [%s]\n",
                $topIndex + 1,
                $subIndex + 1,
                $subMenu['text'],
                $subMenu['value']
            );
        }

        return $out;
    }

    /**
     * 生成菜单处理器模板代码
     *
     * @param string $namespace PHP命名空间
     * @param string $className 类名
     */
    public function generateHandlerTemplate(string $namespace, string $className): string
    {
        $valueMapping = $this->config->getValueTextMapping();
        $template = "<?php\n\n";
        $template .= "declare(strict_types=1);\n\n";
        $template .= "namespace {$namespace};\n\n";
        $template .= "use Tourze\\LarkAppBotBundle\\Event\\MenuEvent;\n";
        $template .= "use Tourze\\LarkAppBotBundle\\Menu\\MenuService;\n\n";
        $template .= "/**\n";
        $template .= " * 自动生成的菜单处理器类\n";
        $template .= ' * 生成时间：' . date('Y-m-d H:i:s') . "\n";
        $template .= " */\n";
        $template .= "class {$className}\n";
        $template .= "{\n";
        $template .= "    private MenuService \$menuService;\n\n";
        $template .= "    public function __construct(MenuService \$menuService)\n";
        $template .= "    {\n";
        $template .= "        \$this->menuService = \$menuService;\n";
        $template .= "    }\n\n";
        $template .= "    /**\n";
        $template .= "     * 注册所有菜单处理器\n";
        $template .= "     */\n";
        $template .= "    public function registerHandlers(): void\n";
        $template .= "    {\n";

        foreach ($valueMapping as $value => $text) {
            $methodName = $this->generateMethodName($value);
            $template .= "        \$this->menuService->registerHandler(\n";
            $template .= "            '{$value}',\n";
            $template .= "            [\$this, '{$methodName}']\n";
            $template .= "        );\n\n";
        }

        $template .= "    }\n\n";

        // 生成处理方法
        foreach ($valueMapping as $value => $text) {
            $methodName = $this->generateMethodName($value);
            $template .= "    /**\n";
            $template .= "     * 处理菜单：{$text}\n";
            $template .= "     */\n";
            $template .= "    public function {$methodName}(MenuEvent \$event): void\n";
            $template .= "    {\n";
            $template .= "        // TODO: 实现 \"{$text}\" 的处理逻辑\n";
            $template .= "        \$userId = \$event->getOperatorOpenId();\n";
            $template .= "    }\n\n";
        }

        $template .= "}\n";

        return $template;
    }

    /**
     * 验证菜单配置.
     *
     * @return array<string> 返回验证错误信息数组
     */
    public function validate(): array
    {
        $errors = [];
        $menuArray = $this->config->toArray();

        if (!isset($menuArray['menu']['list']) || !\is_array($menuArray['menu']['list']) || [] === $menuArray['menu']['list']) {
            $errors[] = '菜单不能为空';

            return $errors;
        }

        $allValues = [];
        $menuList = $menuArray['menu']['list'];

        foreach ($menuList as $index => $topLevel) {
            $validationResult = $this->validateTopLevelMenu($topLevel, $index, $allValues, $errors);
            $allValues = $validationResult['allValues'];
            $errors = $validationResult['errors'];
        }

        return $errors;
    }

    /**
     * 生成方法名.
     */
    private function generateMethodName(string $value): string
    {
        // 将下划线转换为驼峰命名
        $parts = explode('_', $value);
        $methodName = 'handle';
        foreach ($parts as $part) {
            $methodName .= ucfirst(strtolower($part));
        }

        return $methodName;
    }

    /**
     * 验证一级菜单.
     *
     * @param array<string, mixed> $topLevel
     * @param array<string>        $allValues
     * @param array<string>        $errors
     *
     * @return array{allValues: array<string>, errors: array<string>}
     */
    private function validateTopLevelMenu(array $topLevel, int $index, array $allValues, array $errors): array
    {
        $hasValue = isset($topLevel['value']);
        $hasSubMenu = isset($topLevel['sub_menu']['list']);

        if ($hasValue) {
            $valueResult = $this->validateMenuValue($topLevel['value'], $allValues, $errors);
            $allValues = $valueResult['allValues'];
            $errors = $valueResult['errors'];

            $conflictResult = $this->checkValueAndSubMenuConflict($topLevel, $index, $errors);
            $errors = $conflictResult['errors'];
        } elseif (!$hasSubMenu) {
            $errors[] = \sprintf('第 %d 个一级菜单没有设置 value 也没有子菜单', $index + 1);
        }

        if ($hasSubMenu) {
            $subMenuResult = $this->validateSubMenus($topLevel['sub_menu']['list'], $allValues, $errors);
            $allValues = $subMenuResult['allValues'];
            $errors = $subMenuResult['errors'];
        }

        return ['allValues' => $allValues, 'errors' => $errors];
    }

    /**
     * 验证菜单值.
     *
     * @param array<string> $allValues
     * @param array<string> $errors
     *
     * @return array{allValues: array<string>, errors: array<string>}
     */
    private function validateMenuValue(string $value, array $allValues, array $errors): array
    {
        if (\in_array($value, $allValues, true)) {
            $errors[] = \sprintf('菜单值重复：%s', $value);
        }
        $allValues[] = $value;

        return ['allValues' => $allValues, 'errors' => $errors];
    }

    /**
     * 检查值和子菜单冲突.
     *
     * @param array<string, mixed> $topLevel
     * @param array<string>        $errors
     *
     * @return array{errors: array<string>}
     */
    private function checkValueAndSubMenuConflict(array $topLevel, int $index, array $errors): array
    {
        if (isset($topLevel['sub_menu'])) {
            $errors[] = \sprintf('第 %d 个一级菜单同时设置了 value 和子菜单', $index + 1);
        }

        return ['errors' => $errors];
    }

    /**
     * 验证子菜单.
     *
     * @param array<int, array<string, mixed>> $subMenuList
     * @param array<string>                    $allValues
     * @param array<string>                    $errors
     *
     * @return array{allValues: array<string>, errors: array<string>}
     */
    private function validateSubMenus(array $subMenuList, array $allValues, array $errors): array
    {
        foreach ($subMenuList as $subMenu) {
            if (isset($subMenu['value'])) {
                $result = $this->validateMenuValue($subMenu['value'], $allValues, $errors);
                $allValues = $result['allValues'];
                $errors = $result['errors'];
            }
        }

        return ['allValues' => $allValues, 'errors' => $errors];
    }
}
