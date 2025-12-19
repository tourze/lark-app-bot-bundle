<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Menu;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\Exception\ValidationException;
use Tourze\LarkAppBotBundle\Service\Menu\MenuConfig;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * 菜单配置测试.
 *
 * @internal
 */
#[CoversClass(MenuConfig::class)]
#[RunTestsInSeparateProcesses]
final class MenuConfigTest extends AbstractIntegrationTestCase
{
    public function testToArray(): void
    {
        $config = self::getService(MenuConfig::class);
        $this->assertIsArray($config->toArray());
    }

    public function testAddTopLevelMenu(): void
    {
        $config = self::getService(MenuConfig::class);
        $config->addTopLevelMenu('数据查询');

        $array = $config->toArray();
        $this->assertIsArray($array);
        $this->assertCount(1, $array['menu']['list']);
        $this->assertSame('数据查询', $array['menu']['list'][0]['text']);
        $this->assertArrayNotHasKey('value', $array['menu']['list'][0]);
    }

    public function testAddTopLevelMenuWithValue(): void
    {
        $config = self::getService(MenuConfig::class);
        $config->addTopLevelMenu('设置', 'settings');

        $array = $config->toArray();
        $this->assertIsArray($array);
        $this->assertCount(1, $array['menu']['list']);
        $this->assertSame('设置', $array['menu']['list'][0]['text']);
        $this->assertSame('settings', $array['menu']['list'][0]['value']);
        $this->assertSame(MenuConfig::TYPE_TEXT, $array['menu']['list'][0]['type']);
    }

    public function testAddSubMenu(): void
    {
        $config = self::getService(MenuConfig::class);
        $config->addTopLevelMenu('数据查询')
            ->addSubMenu('销售数据', 'query_sales')
            ->addSubMenu('用户统计', 'query_users')
        ;

        $array = $config->toArray();
        $this->assertIsArray($array);
        $this->assertCount(1, $array['menu']['list']);
        $this->assertCount(2, $array['menu']['list'][0]['sub_menu']['list']);

        $subMenus = $array['menu']['list'][0]['sub_menu']['list'];
        $this->assertSame('销售数据', $subMenus[0]['text']);
        $this->assertSame('query_sales', $subMenus[0]['value']);
        $this->assertSame('用户统计', $subMenus[1]['text']);
        $this->assertSame('query_users', $subMenus[1]['value']);
    }

    public function testMaxTopLevelMenusValidation(): void
    {
        $config = self::getService(MenuConfig::class);
        $config->addTopLevelMenu('菜单1')
            ->addTopLevelMenu('菜单2')
            ->addTopLevelMenu('菜单3')
        ;

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('最多只能添加 3 个一级菜单');

        $config->addTopLevelMenu('菜单4');
    }

    public function testMaxSubMenusValidation(): void
    {
        $config = self::getService(MenuConfig::class);
        $config->addTopLevelMenu('数据查询');

        for ($i = 1; $i <= 5; ++$i) {
            $config->addSubMenu("子菜单{$i}", "sub_{$i}");
        }

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('每个一级菜单下最多只能添加 5 个子菜单');

        $config->addSubMenu('子菜单6', 'sub_6');
    }

    public function testMenuTextLengthValidation(): void
    {
        $config = self::getService(MenuConfig::class);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('菜单文本最多 8 个字符');

        $config->addTopLevelMenu('这是一个超过八个字符的菜单文本');
    }

    public function testEmptyMenuTextValidation(): void
    {
        $config = self::getService(MenuConfig::class);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('菜单文本不能为空');

        $config->addTopLevelMenu('');
    }

    public function testCannotAddSubMenuToMenuWithValue(): void
    {
        $config = self::getService(MenuConfig::class);
        $config->addTopLevelMenu('设置', 'settings');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('一级菜单已有直接功能，不能添加子菜单');

        $config->addSubMenu('子设置', 'sub_settings');
    }

    public function testCannotAddSubMenuWithoutTopLevelMenu(): void
    {
        $config = self::getService(MenuConfig::class);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('必须先添加一级菜单');

        $config->addSubMenu('子菜单', 'sub_menu');
    }

    public function testFromArray(): void
    {
        $data = [
            'menu' => [
                'list' => [
                    [
                        'text' => '数据查询',
                        'sub_menu' => [
                            'list' => [
                                ['text' => '销售数据', 'value' => 'query_sales', 'type' => 'text'],
                                ['text' => '用户统计', 'value' => 'query_users', 'type' => 'text']]]],
                    [
                        'text' => '设置',
                        'value' => 'settings',
                        'type' => 'text']]]];

        $config = MenuConfig::fromArray($data);
        $array = $config->toArray();

        $this->assertSame($data, $array);
    }

    public function testGetValueTextMapping(): void
    {
        $config = self::getService(MenuConfig::class);
        $config->addTopLevelMenu('数据查询')
            ->addSubMenu('销售数据', 'query_sales')
            ->addSubMenu('用户统计', 'query_users')
            ->addTopLevelMenu('设置', 'settings')
        ;

        $mapping = $config->getValueTextMapping();

        $this->assertSame([
            'query_sales' => '数据查询 > 销售数据',
            'query_users' => '数据查询 > 用户统计',
            'settings' => '设置'], $mapping);
    }

    public function testHasValue(): void
    {
        $config = self::getService(MenuConfig::class);
        $config->addTopLevelMenu('数据查询')
            ->addSubMenu('销售数据', 'query_sales')
            ->addTopLevelMenu('设置', 'settings')
        ;

        $this->assertTrue($config->hasValue('query_sales'));
        $this->assertTrue($config->hasValue('settings'));
        $this->assertFalse($config->hasValue('non_existent'));
    }

    public function testClear(): void
    {
        $config = self::getService(MenuConfig::class);
        $config->addTopLevelMenu('菜单1')
            ->addTopLevelMenu('菜单2')
        ;

        $this->assertSame(2, $config->getMenuCount());

        $config->clear();

        $this->assertSame(0, $config->getMenuCount());
        $array = $config->toArray();
        $this->assertEmpty($array['menu']['list']);
    }

    protected function prepareMockServices(): void
    {
        // 此测试不需要 Mock 服务
    }

    protected function onSetUp(): void
    {// 无需特殊初始化
    }
}
