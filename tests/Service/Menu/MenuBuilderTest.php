<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Menu;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\Exception\ValidationException;
use Tourze\LarkAppBotBundle\Service\Menu\MenuBuilder;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * 菜单构建器测试.
 *
 * @internal
 */
#[CoversClass(MenuBuilder::class)]
#[RunTestsInSeparateProcesses]
final class MenuBuilderTest extends AbstractIntegrationTestCase
{
    public function testCreateBuilder(): void
    {
        $builder = MenuBuilder::create();
        $config = $builder->build();
        $this->assertSame(0, $config->getMenuCount());
    }

    public function testBuildSimpleMenu(): void
    {
        $builder = MenuBuilder::create()
            ->addMenu('数据查询')
            ->withSubMenu('销售数据', 'query_sales')
            ->withSubMenu('用户统计', 'query_users')
            ->addMenu('设置', 'settings')
        ;

        $config = $builder->build();
        $array = $config->toArray();

        $this->assertIsArray($array);
        $this->assertCount(2, $array['menu']['list']);
        $this->assertSame('数据查询', $array['menu']['list'][0]['text']);
        $this->assertCount(2, $array['menu']['list'][0]['sub_menu']['list']);
        $this->assertSame('设置', $array['menu']['list'][1]['text']);
        $this->assertSame('settings', $array['menu']['list'][1]['value']);
    }

    public function testWithSubMenus(): void
    {
        $builder = MenuBuilder::create()
            ->addMenu('功能菜单')
            ->withSubMenus([
                ['text' => '功能1', 'value' => 'func1'],
                ['text' => '功能2', 'value' => 'func2'],
                ['text' => '功能3', 'value' => 'func3']])
        ;

        $config = $builder->build();
        $array = $config->toArray();

        $this->assertIsArray($array);
        $this->assertCount(3, $array['menu']['list'][0]['sub_menu']['list']);
    }

    public function testReset(): void
    {
        $builder = MenuBuilder::create()
            ->addMenu('菜单1')
            ->addMenu('菜单2')
        ;

        $config1 = $builder->build();
        $this->assertSame(2, $config1->getMenuCount());

        $builder->reset();
        $config2 = $builder->build();
        $this->assertSame(0, $config2->getMenuCount());
    }

    public function testVersionManagement(): void
    {
        $builder = MenuBuilder::create()
            ->addMenu('版本1菜单')
            ->saveVersion('v1')
            ->reset()
            ->addMenu('版本2菜单1')
            ->addMenu('版本2菜单2')
            ->saveVersion('v2')
        ;

        $this->assertSame(['v1', 'v2'], $builder->getVersions());
        $this->assertSame('v2', $builder->getCurrentVersion());

        $builder->loadVersion('v1');
        $config = $builder->build();
        $this->assertSame(1, $config->getMenuCount());
        $this->assertSame('v1', $builder->getCurrentVersion());
    }

    public function testSaveVersion(): void
    {
        $builder = MenuBuilder::create()->addMenu('A')->saveVersion('sv');
        $this->assertContains('sv', $builder->getVersions());
    }

    public function testLoadVersion(): void
    {
        $builder = MenuBuilder::create()->addMenu('A')->saveVersion('sv');
        $builder->reset()->addMenu('B')->loadVersion('sv');
        $this->assertSame(1, $builder->build()->getMenuCount());
    }

    public function testToYamlAndFromYaml(): void
    {
        $builder = MenuBuilder::create()
            ->addMenu('M1')->withSubMenu('S1', 'v1')->addMenu('M2', 'v2');
        $tmp = sys_get_temp_dir() . '/menu_' . uniqid() . '.yaml';
        $this->assertTrue($builder->toYaml($tmp));
        $this->assertFileExists($tmp);

        $loaded = MenuBuilder::create()->fromYaml($tmp);
        $this->assertSame($builder->build()->toArray(), $loaded->build()->toArray());

        @unlink($tmp);
    }

    public function testFromYaml(): void
    {
        $builder = MenuBuilder::create()->addMenu('M');
        $tmp = sys_get_temp_dir() . '/menu_' . uniqid() . '.yaml';
        $builder->toYaml($tmp);
        $loaded = MenuBuilder::create()->fromYaml($tmp);
        $this->assertSame($builder->build()->toArray(), $loaded->build()->toArray());
        @unlink($tmp);
    }

    public function testAddMenu(): void
    {
        $builder = MenuBuilder::create();
        $builder->addMenu('X');
        $this->assertSame(1, $builder->build()->getMenuCount());
    }

    public function testLoadNonExistentVersion(): void
    {
        $builder = MenuBuilder::create();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('版本 "non_existent" 不存在');

        $builder->loadVersion('non_existent');
    }

    public function testPreview(): void
    {
        $builder = MenuBuilder::create()
            ->addMenu('数据查询')
            ->withSubMenu('销售数据', 'query_sales')
            ->withSubMenu('用户统计', 'query_users')
            ->addMenu('设置', 'settings')
        ;

        $preview = $builder->preview();

        $this->assertStringContainsString('机器人菜单预览', $preview);
        $this->assertStringContainsString('1. 数据查询', $preview);
        $this->assertStringContainsString('1.1 销售数据 [query_sales]', $preview);
        $this->assertStringContainsString('1.2 用户统计 [query_users]', $preview);
        $this->assertStringContainsString('2. 设置 [settings]', $preview);
        $this->assertStringContainsString('总计：2 个一级菜单，3 个可点击项', $preview);
    }

    public function testPreviewEmptyMenu(): void
    {
        $builder = MenuBuilder::create();
        $preview = $builder->preview();

        $this->assertStringContainsString('机器人菜单预览', $preview);
        $this->assertStringContainsString('（空菜单）', $preview);
    }

    public function testGenerateHandlerTemplate(): void
    {
        $builder = MenuBuilder::create()
            ->addMenu('数据查询')
            ->withSubMenu('销售数据', 'query_sales')
            ->withSubMenu('用户统计', 'query_users')
            ->addMenu('设置', 'settings')
        ;

        $template = $builder->generateHandlerTemplate('App\Menu\Handler', 'MenuHandler');

        $this->assertStringContainsString('namespace App\Menu\Handler;', $template);
        $this->assertStringContainsString('class MenuHandler', $template);
        $this->assertStringContainsString('registerHandlers()', $template);
        $this->assertStringContainsString('handleQuerySales', $template);
        $this->assertStringContainsString('handleQueryUsers', $template);
        $this->assertStringContainsString('handleSettings', $template);
        $this->assertStringContainsString('处理菜单：数据查询 > 销售数据', $template);
    }

    public function testValidate(): void
    {
        // 正常菜单
        $builder1 = MenuBuilder::create()
            ->addMenu('数据查询')
            ->withSubMenu('销售数据', 'query_sales')
            ->addMenu('设置', 'settings')
        ;

        $errors1 = $builder1->validate();
        $this->assertEmpty($errors1);

        // 空菜单
        $builder2 = MenuBuilder::create();
        $errors2 = $builder2->validate();
        $this->assertContains('菜单不能为空', $errors2);

        // 重复的菜单值
        $builder3 = MenuBuilder::create()
            ->addMenu('菜单1', 'same_value')
            ->addMenu('菜单2', 'same_value')
        ;

        $errors3 = $builder3->validate();
        $this->assertContains('菜单值重复：same_value', $errors3);

        // 没有值也没有子菜单的一级菜单
        $builder4 = MenuBuilder::create()
            ->addMenu('空菜单')
        ;

        $errors4 = $builder4->validate();
        $this->assertContains('第 1 个一级菜单没有设置 value 也没有子菜单', $errors4);
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
                                ['text' => '销售数据', 'value' => 'query_sales', 'type' => 'text']]]]]]];

        $builder = MenuBuilder::create()->fromArray($data);
        $config = $builder->build();
        $array = $config->toArray();

        $this->assertSame($data, $array);
    }

    protected function prepareMockServices(): void
    {
        // 此测试不需要 Mock 服务
    }

    protected function onSetUp(): void
    {// 无需特殊初始化
    }
}
