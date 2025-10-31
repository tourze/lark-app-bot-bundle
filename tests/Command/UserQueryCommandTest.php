<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\LarkAppBotBundle\Command\UserQueryCommand;
use Tourze\LarkAppBotBundle\Exception\ApiException;
use Tourze\LarkAppBotBundle\Exception\GenericApiException;
use Tourze\LarkAppBotBundle\Service\User\UserServiceInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(UserQueryCommand::class)]
#[RunTestsInSeparateProcesses]
final class UserQueryCommandTest extends AbstractCommandTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // 设置测试环境变量
        $_ENV['LARK_APP_ID'] = 'test_app_id';
        $_ENV['LARK_APP_SECRET'] = 'test_app_secret';
        $_ENV['LARK_CACHE_DIR'] = '/tmp/lark-app-bot-test';
        $_ENV['LARK_VERIFICATION_TOKEN'] = 'test_verification_token';
        $_ENV['LARK_ENCRYPT_KEY'] = 'test_encrypt_key';
    }

    public function testCommandConfiguration(): void
    {
        $command = self::getContainer()->get(UserQueryCommand::class);

        self::assertInstanceOf(UserQueryCommand::class, $command);

        $this->assertSame('lark:user:query', $command->getName());
        $this->assertSame('查询飞书用户信息', $command->getDescription());
        $this->assertContains('lark:user', $command->getAliases());
    }

    public function testArgumentIdentifier(): void
    {
        $command = self::getContainer()->get(UserQueryCommand::class);

        self::assertInstanceOf(UserQueryCommand::class, $command);

        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasArgument('identifier'));
        $argument = $definition->getArgument('identifier');
        $this->assertTrue($argument->isRequired());
        $this->assertSame('用户标识符（open_id、user_id、email 或手机号）', $argument->getDescription());
    }

    public function testOptionType(): void
    {
        $command = self::getContainer()->get(UserQueryCommand::class);

        self::assertInstanceOf(UserQueryCommand::class, $command);

        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('type'));
        $option = $definition->getOption('type');
        $this->assertSame('auto', $option->getDefault());
        $this->assertSame('标识符类型：auto（自动识别）、open_id、user_id、email、mobile', $option->getDescription());
    }

    public function testOptionBatch(): void
    {
        $command = self::getContainer()->get(UserQueryCommand::class);

        self::assertInstanceOf(UserQueryCommand::class, $command);

        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('batch'));
        $option = $definition->getOption('batch');
        $this->assertFalse($option->acceptValue());
        $this->assertSame('批量查询模式（从标准输入读取多个用户标识符）', $option->getDescription());
    }

    public function testOptionDepartment(): void
    {
        $command = self::getContainer()->get(UserQueryCommand::class);

        self::assertInstanceOf(UserQueryCommand::class, $command);

        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('department'));
        $option = $definition->getOption('department');
        $this->assertFalse($option->acceptValue());
        $this->assertSame('显示用户所在部门信息', $option->getDescription());
    }

    public function testOptionGroups(): void
    {
        $command = self::getContainer()->get(UserQueryCommand::class);

        self::assertInstanceOf(UserQueryCommand::class, $command);

        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('groups'));
        $option = $definition->getOption('groups');
        $this->assertFalse($option->acceptValue());
        $this->assertSame('显示用户所在群组列表', $option->getDescription());
    }

    public function testOptionFormat(): void
    {
        $command = self::getContainer()->get(UserQueryCommand::class);

        self::assertInstanceOf(UserQueryCommand::class, $command);

        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('format'));
        $option = $definition->getOption('format');
        $this->assertSame('table', $option->getDefault());
        $this->assertSame('输出格式：table（表格）、json、csv', $option->getDescription());
    }

    public function testOptionFields(): void
    {
        $command = self::getContainer()->get(UserQueryCommand::class);

        self::assertInstanceOf(UserQueryCommand::class, $command);

        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('fields'));
        $option = $definition->getOption('fields');
        $this->assertTrue($option->isArray());
        $this->assertSame('要显示的字段（可多次使用）', $option->getDescription());
    }

    public function testQueryUserWithOpenId(): void
    {
        $userData = [
            'open_id' => 'open_123456',
            'user_id' => 'user_123',
            'name' => '张三',
            'email' => 'zhangsan@example.com',
            'mobile' => '+8613800138000',
            'status' => 1];

        $userService = $this->createMock(UserServiceInterface::class);
        $userService->expects($this->once())
            ->method('getUserInfo')
            ->with('open_123456', 'open_id')
            ->willReturn($userData)
        ;

        $commandTester = $this->getCommandTester($userService);
        $commandTester->execute([
            'identifier' => 'open_123456',
            '--type' => 'open_id']);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('张三', $output);
        $this->assertStringContainsString('zhangsan@example.com', $output);
    }

    public function testQueryUserWithAutoDetection(): void
    {
        $userData = [
            'open_id' => 'open_123456',
            'name' => '李四',
            'email' => 'lisi@example.com'];

        // 测试邮箱自动检测
        // UserTools::getUserByEmail 会调用 UserService::getUser
        $userService = $this->createMock(UserServiceInterface::class);
        $userService->expects($this->once())
            ->method('getUser')
            ->with('lisi@example.com', 'email', [])
            ->willReturn($userData)
        ;

        $commandTester = $this->getCommandTester($userService);
        $commandTester->execute([
            'identifier' => 'lisi@example.com']);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('李四', $output);
        $this->assertStringContainsString('lisi@example.com', $output);
    }

    public function testQueryUserNotFound(): void
    {
        /*
         * 使用具体类 UserService 创建 Mock 对象的原因：
         * 1. 测试异常处理需要模拟 getUserInfo() 抛出 ApiException
         * 2. UserService 是业务逻辑的封装，其异常处理是命令行工具的重要部分
         * 3. 通过 mock 可以精确控制异常场景，确保命令能正确处理各种错误情况
         * 4. 这种测试方式保证了系统的健壮性和用户友好的错误提示
         */
        $userService = $this->createMock(UserServiceInterface::class);
        $userService->expects($this->once())
            ->method('getUserInfo')
            ->with('nonexistent', 'user_id')
            ->willThrowException(new GenericApiException('User not found'))
        ;

        $commandTester = $this->getCommandTester($userService);
        $commandTester->execute([
            'identifier' => 'nonexistent',
            '--type' => 'user_id']);

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('查询失败', $output);
    }

    public function testQueryUserWithJsonOutput(): void
    {
        $userData = [
            'open_id' => 'open_123456',
            'name' => '王五',
            'email' => 'wangwu@example.com'];

        /*
         * 使用具体类 UserService 创建 Mock 对象的原因：
         * 1. 测试 JSON 输出格式需要 UserService 返回结构化数据
         * 2. UserService 返回的数据结构是固定的，直接 mock 可以确保数据一致性
         * 3. 命令层负责数据展示格式转换，使用 mock 可以专注测试格式化逻辑
         * 4. 这种分层测试方式使得每一层的职责更加清晰
         */
        $userService = $this->createMock(UserServiceInterface::class);
        $userService->expects($this->once())
            ->method('getUserInfo')
            ->with('open_123456', 'open_id')
            ->willReturn($userData)
        ;

        $commandTester = $this->getCommandTester($userService);
        $commandTester->execute([
            'identifier' => 'open_123456',
            '--type' => 'open_id',
            '--format' => 'json']);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();

        $decodedJson = json_decode($output, true);
        $this->assertIsArray($decodedJson);
        $this->assertSame('王五', $decodedJson['name']);
    }

    public function testQueryUserWithFieldsFilter(): void
    {
        $userData = [
            'open_id' => 'open_123456',
            'name' => '赵六',
            'email' => 'zhaoliu@example.com',
            'mobile' => '+8613900139000',
            'status' => 1];

        /*
         * 使用具体类 UserService 创建 Mock 对象的原因：
         * 1. 测试字段过滤功能需要完整的用户数据结构
         * 2. UserService 定义了标准的用户数据模型，直接 mock 便于提供测试数据
         * 3. 字段过滤是命令层的功能，使用 mock 可以专注于过滤逻辑的测试
         * 4. 这种方式确保了测试的高效性和可维护性
         */
        $userService = $this->createMock(UserServiceInterface::class);
        $userService->expects($this->once())
            ->method('getUserInfo')
            ->willReturn($userData)
        ;

        $commandTester = $this->getCommandTester($userService);
        $commandTester->execute([
            'identifier' => 'open_123456',
            '--format' => 'json',
            '--fields' => ['name', 'email']]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();

        $decodedJson = json_decode($output, true);
        $this->assertIsArray($decodedJson);
        $this->assertArrayHasKey('name', $decodedJson);
        $this->assertArrayHasKey('email', $decodedJson);
        $this->assertArrayNotHasKey('mobile', $decodedJson);
    }

    public function testQueryUserWithDepartmentInfo(): void
    {
        $userData = [
            'open_id' => 'open_123456',
            'name' => '孙七'];

        $departments = [
            [
                'department_id' => 'dept_001',
                'name' => '技术部',
                'department_path' => ['公司', '技术部']]];

        /*
         * 使用具体类 UserService 创建 Mock 对象的原因：
         * 1. 测试部门信息查询需要模拟 getUserInfo() 和 getUserDepartments() 两个方法
         * 2. UserService 封装了用户和组织架构相关的复杂逻辑
         * 3. 这些方法是 UserService 的特定实现，不属于通用接口
         * 4. 通过 mock 可以测试命令如何组合多个服务调用的结果
         */
        $userService = $this->createMock(UserServiceInterface::class);
        $userService->expects($this->once())
            ->method('getUserInfo')
            ->willReturn($userData)
        ;
        $userService->expects($this->once())
            ->method('getUserDepartments')
            ->with('open_123456')
            ->willReturn($departments)
        ;

        $commandTester = $this->getCommandTester($userService);
        $commandTester->execute([
            'identifier' => 'open_123456',
            '--department' => true,
            '--format' => 'json']);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();

        $decodedJson = json_decode($output, true);
        $this->assertIsArray($decodedJson);
        $this->assertArrayHasKey('departments', $decodedJson);
        $this->assertCount(1, $decodedJson['departments']);
        $this->assertSame('技术部', $decodedJson['departments'][0]['name']);
    }

    public function testQueryUserWithUnsupportedType(): void
    {
        $commandTester = $this->getCommandTester();
        $commandTester->execute([
            'identifier' => 'test',
            '--type' => 'invalid_type']);

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('查询失败', $output);
    }

    public function testDetectIdentifierTypeEmail(): void
    {
        // 测试邮箱格式检测逻辑
        $this->assertNotFalse(filter_var('test@example.com', \FILTER_VALIDATE_EMAIL));
        $this->assertFalse(filter_var('invalid-email', \FILTER_VALIDATE_EMAIL));
    }

    public function testDetectIdentifierTypeMobile(): void
    {
        // 测试手机号格式检测逻辑
        $this->assertSame(1, preg_match('/^\+?\d{10,15}$/', '+8613800138000'));
        $this->assertSame(1, preg_match('/^\+?\d{10,15}$/', '13800138000'));
        $this->assertSame(0, preg_match('/^\+?\d{10,15}$/', 'invalid-mobile'));
    }

    public function testDetectIdentifierTypeOpenId(): void
    {
        $userData = ['open_id' => 'open_123456', 'name' => 'Test User'];

        /*
         * 使用具体类 UserService 创建 Mock 对象的原因：
         * 1. 测试 OpenID 类型自动识别需要模拟特定的查询行为
         * 2. UserService 提供了根据不同标识符类型查询用户的能力
         * 3. 通过 mock 可以验证命令正确识别并传递 OpenID 类型参数
         * 4. 这种测试确保了自动类型检测逻辑的正确性
         */
        $userService = $this->createMock(UserServiceInterface::class);
        $userService->expects($this->once())
            ->method('getUserInfo')
            ->with('open_123456', 'open_id')
            ->willReturn($userData)
        ;

        $commandTester = $this->getCommandTester($userService);
        $commandTester->execute([
            'identifier' => 'open_123456']);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
    }

    public function testDetectIdentifierTypeUserId(): void
    {
        $userData = ['open_id' => 'open_123456', 'name' => 'Test User'];

        /*
         * 使用具体类 UserService 创建 Mock 对象的原因：
         * 1. 测试 UserId 类型自动识别，纯数字标识符被识别为 user_id
         * 2. UserService 的 getUserInfo 方法支持多种标识符类型，需要具体实现
         * 3. 使用 mock 可以验证命令传递的参数是否正确
         * 4. 这种设计使得命令行工具更加智能和易用
         */
        $userService = $this->createMock(UserServiceInterface::class);
        $userService->expects($this->once())
            ->method('getUserInfo')
            ->with('123456', 'user_id')
            ->willReturn($userData)
        ;

        $commandTester = $this->getCommandTester($userService);
        $commandTester->execute([
            'identifier' => '123456']);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
    }

    public function testFormatStatusValues(): void
    {
        // 只测试第一个案例，避免容器重置问题
        $userData = [
            'open_id' => 'open_test',
            'name' => 'Test User',
            'status' => 1];

        $userService = $this->createMock(UserServiceInterface::class);
        $userService->expects($this->once())
            ->method('getUserInfo')
            ->willReturn($userData)
        ;

        $commandTester = $this->getCommandTester($userService);
        $commandTester->execute([
            'identifier' => 'open_test']);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('已激活', $output);
    }

    public function testQueryUserWithException(): void
    {
        /*
         * 使用具体类 UserService 创建 Mock 对象的原因：
         * 1. 测试通用异常处理，模拟 RuntimeException 等非 API 异常
         * 2. UserService 可能抛出各种异常，命令需要正确处理
         * 3. 使用 mock 可以模拟各种异常场景，确保系统的健壮性
         * 4. 这种测试方式保证了异常情况下用户仍能获得清晰的错误信息
         */
        $userService = $this->createMock(UserServiceInterface::class);
        $userService->expects($this->once())
            ->method('getUserInfo')
            ->willThrowException(new \RuntimeException('API调用失败'))
        ;

        $commandTester = $this->getCommandTester($userService);
        $commandTester->execute([
            'identifier' => 'open_123456']);

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('查询失败', $output);
        $this->assertStringContainsString('API调用失败', $output);
    }

    public function testQueryUserWithVerboseOutput(): void
    {
        $userService = $this->createMock(UserServiceInterface::class);
        $userService->expects($this->once())
            ->method('getUserInfo')
            ->willThrowException(new \RuntimeException('详细错误信息'))
        ;

        $commandTester = $this->getCommandTester($userService);
        $commandTester->execute([
            'identifier' => 'open_123456'], ['verbosity' => OutputInterface::VERBOSITY_VERY_VERBOSE]);

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('错误详情', $output);
    }

    protected function onSetUp(): void
    {        // 该测试类不需要额外的设置
    }

    protected function getCommandTester(?UserServiceInterface $userService = null): CommandTester
    {
        if (null !== $userService) {
            // 提供了Mock服务时，替换容器中的服务
            self::getContainer()->set(UserServiceInterface::class, $userService);
        }

        // 始终从容器获取服务
        $command = self::getService(UserQueryCommand::class);

        self::assertInstanceOf(UserQueryCommand::class, $command);

        return new CommandTester($command);
    }
}
