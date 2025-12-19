<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Command\Checker;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\LarkAppBotBundle\Command\Checker\ApiConnectionChecker;
use Tourze\LarkAppBotBundle\Service\Client\LarkClientInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(ApiConnectionChecker::class)]
#[RunTestsInSeparateProcesses]
final class ApiConnectionCheckerTest extends AbstractIntegrationTestCase
{
    private ApiConnectionChecker $checker;

    private MockObject&LarkClientInterface $mockLarkClient;

    private MockObject&SymfonyStyle $mockIo;

    public function testConstructor(): void
    {
        $this->assertInstanceOf(ApiConnectionChecker::class, $this->checker);
    }

    public function testGetName(): void
    {
        $name = $this->checker->getName();
        $this->assertSame('API连接测试', $name);
    }

    public function testCheckWithSuccessfulApiResponse(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getContent')->willReturn(json_encode([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'app' => [
                    'app_name' => '测试应用',
                    'app_type' => '企业自建应用',
                    'status' => '启用',
                    'create_time' => 1609459200,
                ],
            ],
        ]));

        $this->mockLarkClient
            ->expects($this->once())
            ->method('request')
            ->with('GET', '/open-apis/auth/v3/app_info')
            ->willReturn($mockResponse)
        ;

        $this->mockIo->expects($this->atLeastOnce())->method('comment');
        $this->mockIo->expects($this->atLeastOnce())->method('success');
        $this->mockIo->expects($this->once())->method('definitionList');

        $result = $this->checker->check($this->mockIo);
        $this->assertTrue($result);
    }

    public function testCheckWithApiException(): void
    {
        $this->mockLarkClient
            ->expects($this->once())
            ->method('request')
            ->willThrowException(new \Exception('连接失败'))
        ;

        $this->mockIo->expects($this->atLeastOnce())->method('comment');
        $this->mockIo->expects($this->atLeastOnce())
            ->method('error')
        ;

        $result = $this->checker->check($this->mockIo);
        $this->assertTrue($result);
    }

    public function testCheckWithHttpError(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(500);

        $this->mockLarkClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse)
        ;

        $this->mockIo->expects($this->atLeastOnce())->method('comment');
        $this->mockIo->expects($this->atLeastOnce())
            ->method('error')
        ;

        $result = $this->checker->check($this->mockIo);
        $this->assertTrue($result);
    }

    public function testCheckWithApiError(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getContent')->willReturn(json_encode([
            'code' => 40001,
            'msg' => '无效的访问令牌',
        ]));

        $this->mockLarkClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse)
        ;

        $this->mockIo->expects($this->atLeastOnce())->method('comment');
        $this->mockIo->expects($this->atLeastOnce())
            ->method('error')
        ;

        $result = $this->checker->check($this->mockIo);
        $this->assertTrue($result);
    }

    public function testCheckWithInvalidJsonResponse(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getContent')->willReturn('invalid json');

        $this->mockLarkClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse)
        ;

        $this->mockIo->expects($this->atLeastOnce())->method('comment');
        $this->mockIo->expects($this->atLeastOnce())
            ->method('error')
        ;

        $result = $this->checker->check($this->mockIo);
        $this->assertTrue($result);
    }

    protected function onSetUp(): void
    {
        $this->mockLarkClient = $this->createMock(LarkClientInterface::class);
        $this->mockIo = $this->createMock(SymfonyStyle::class);

        // 从容器获取服务，集成测试要求这样做
        $this->checker = self::getService(ApiConnectionChecker::class);
    }

    protected function prepareMockServices(): void
    {
        // 设置Mock服务到容器中
        $container = self::getContainer();
        $container->set('tourze.lark_app_bot.client', $this->mockLarkClient);
    }
}
