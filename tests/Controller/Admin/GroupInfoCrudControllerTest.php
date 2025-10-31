<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\CrudDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Response;
use Tourze\LarkAppBotBundle\Controller\Admin\GroupInfoCrudController;
use Tourze\LarkAppBotBundle\Entity\GroupInfo;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @internal
 */
#[CoversClass(GroupInfoCrudController::class)]
#[RunTestsInSeparateProcesses]
final class GroupInfoCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    /** @return iterable<string, array{string}> */
    public static function provideIndexPageHeaders(): iterable
    {
        return [
            'ID' => ['ID'],
            '群组ID' => ['群组ID'],
            '群组名称' => ['群组名称'],
            '成员数量' => ['成员数量'],
            '外部群组' => ['外部群组'],
            '创建时间' => ['创建时间'],
        ];
    }

    /** @return iterable<string, array{string}> */
    public static function provideNewPageFields(): iterable
    {
        yield 'chatId' => ['chatId'];
        yield 'name' => ['name'];
        yield 'description' => ['description'];
        yield 'ownerId' => ['ownerId'];
        yield 'memberCount' => ['memberCount'];
        yield 'botCount' => ['botCount'];
        yield 'chatType' => ['chatType'];
        yield 'external' => ['external'];
    }

    /** @return iterable<string, array{string}> */
    public static function provideEditPageFields(): iterable
    {
        return self::provideNewPageFields();
    }

    public function testIndexPage(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsAdmin($client);

        $crawler = $client->request('GET', '/admin');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // Navigate to GroupInfo CRUD
        $link = $crawler->filter('a[href*="GroupInfoCrudController"]')->first();
        if ($link->count() > 0) {
            $client->click($link->link());
            self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        }
    }

    public function testEntityFqcnConfiguration(): void
    {
        $controller = new GroupInfoCrudController();
        self::assertSame(GroupInfo::class, $controller::getEntityFqcn());
    }

    public function testCreateGroupInfo(): void
    {
        // Test that the controller methods work correctly
        $controller = new GroupInfoCrudController();
        $fields = $controller->configureFields('new');
        $fieldsArray = iterator_to_array($fields);

        // 基本验证：确保有字段配置
        self::assertNotEmpty($fieldsArray, '新建页面应该有字段配置');

        // 验证字段数量
        self::assertGreaterThan(5, \count($fieldsArray), '应该有超过5个字段配置');
    }

    public function testEditGroupInfo(): void
    {
        // Test that configureFields returns appropriate fields
        $controller = new GroupInfoCrudController();
        $fields = $controller->configureFields('edit');
        $fieldsArray = iterator_to_array($fields);
        self::assertNotEmpty($fieldsArray);
    }

    public function testDetailGroupInfo(): void
    {
        // Test that configureFields returns appropriate fields for detail view
        $controller = new GroupInfoCrudController();
        $fields = $controller->configureFields('detail');
        $fieldsArray = iterator_to_array($fields);
        self::assertNotEmpty($fieldsArray);

        // 验证字段数量（详情页应该包含更多字段）
        self::assertGreaterThan(7, \count($fieldsArray), '详情页应该有超过7个字段配置');
    }

    public function testConfigureActions(): void
    {
        $controller = new GroupInfoCrudController();
        // 测试 configureActions 方法可以正常调用
        $actions = Actions::new();
        $result = $controller->configureActions($actions);
        self::assertSame($actions, $result, 'Actions should be configured and returned');
    }

    public function testConfigureCrud(): void
    {
        $controller = new GroupInfoCrudController();
        // 方法存在性在类定义时已确定，直接调用方法进行测试
        $crud = Crud::new();
        $result = $controller->configureCrud($crud);
        self::assertSame($crud, $result, 'Crud should be configured and returned');
    }

    public function testConfigureFilters(): void
    {
        $controller = new GroupInfoCrudController();
        // 方法存在性在类定义时已确定，直接调用方法进行测试
        $filters = Filters::new();
        $result = $controller->configureFilters($filters);
        self::assertSame($filters, $result, 'Filters should be configurable and returned');
    }

    /**
     * 测试字段在不同页面的可见性.
     */
    public function testFieldVisibilityAcrossPages(): void
    {
        $controller = new GroupInfoCrudController();

        // 测试索引页面 - 某些字段应该被隐藏
        $indexFields = iterator_to_array($controller->configureFields('index'));
        self::assertNotEmpty($indexFields);

        // 测试详情页面 - 应该显示所有字段
        $detailFields = iterator_to_array($controller->configureFields('detail'));
        self::assertNotEmpty($detailFields);

        // 详情页面字段数量应该大于或等于索引页面
        self::assertGreaterThanOrEqual(\count($indexFields), \count($detailFields));
    }

    /**
     * 测试数字字段配置.
     */
    public function testNumericFieldsConfiguration(): void
    {
        $controller = new GroupInfoCrudController();
        $fields = iterator_to_array($controller->configureFields('new'));

        // 基本验证：确保有IntegerField类型
        $hasIntegerFields = false;
        foreach ($fields as $field) {
            $fieldClassName = $field::class;
            if (str_contains($fieldClassName, 'IntegerField')) {
                $hasIntegerFields = true;
                break;
            }
        }
        self::assertTrue($hasIntegerFields, '应该包含IntegerField类型的字段');
    }

    /**
     * 测试表单验证错误 - 提交无效数据应该显示验证错误.
     */
    public function testValidationErrors(): void
    {
        $client = $this->createAuthenticatedClient();
        $crawler = $client->request('GET', $this->generateAdminUrl('new'));
        $this->assertResponseIsSuccessful();

        // 获取表单并设置无效数据来触发验证错误
        $form = $crawler->selectButton('Create')->form();
        $entityName = $this->getEntitySimpleName();

        // 设置无效数据来触发验证错误
        // 必填字段保持空值应该触发验证错误
        $form[$entityName . '[chatId]'] = 'invalid-id';    // 有效的字符串值
        $form[$entityName . '[name]'] = '';                // 空群组名称违反非空约束
        $form[$entityName . '[memberCount]'] = 'invalid';  // 无效的数字值

        $crawler = $client->submit($form);

        // 验证返回状态码（422 Unprocessable Entity 或重定向到表单页面显示错误）
        if (422 === $client->getResponse()->getStatusCode()) {
            $this->assertResponseStatusCodeSame(422);
            // 检查是否有验证错误信息
            $errorText = $crawler->filter('.invalid-feedback, .form-error-message, .alert-danger')->text();
            self::assertNotEmpty($errorText, '应该显示验证错误信息');
        } else {
            // 如果不是422，可能是重定向回表单页面显示错误
            $this->assertResponseIsSuccessful();
            $errorElements = $crawler->filter('.invalid-feedback, .form-error-message, .alert-danger');
            if ($errorElements->count() > 0) {
                $errorText = $errorElements->text();
                self::assertNotEmpty($errorText, '应该显示验证错误信息');
            } else {
                // 如果没有明显的错误元素，检查表单是否仍然存在（说明提交失败）
                $formExists = $crawler->filter('form[name="' . $entityName . '"]')->count() > 0;
                self::assertTrue($formExists, '表单验证失败时应该重新显示表单');
            }
        }
    }

    protected function getEntityFqcn(): string
    {
        return GroupInfo::class;
    }

    /** @return GroupInfoCrudController */
    protected function getControllerService(): AbstractCrudController
    {
        return self::getService(GroupInfoCrudController::class);
    }
}
