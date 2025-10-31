<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Command\Helper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\LarkAppBotBundle\Command\Helper\ApiTester;
use Tourze\LarkAppBotBundle\Service\Client\LarkClientInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(ApiTester::class)]
#[RunTestsInSeparateProcesses]
final class ApiTesterTest extends AbstractIntegrationTestCase
{
    private LarkClientInterface&MockObject $mockLarkClient;

    private ApiTester $apiTester;

    private SymfonyStyle&MockObject $mockIo;

    public function testTestApiConnectionSuccess(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('toArray')->willReturn([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'app_name' => 'Test App',
                'app_id' => 'cli_test123',
                'status' => 'active',
                'create_time' => '1640000000',
            ],
        ]);

        $this->mockLarkClient->expects($this->once())
            ->method('request')
            ->with('GET', '/open-apis/app/v6/info')
            ->willReturn($mockResponse)
        ;

        $this->mockIo->expects($this->once())
            ->method('section')
            ->with('API连接测试')
        ;

        $this->mockIo->expects($this->once())
            ->method('success')
            ->with('API连接正常')
        ;

        $result = $this->apiTester->testApiConnection($this->mockIo);

        $this->assertFalse($result);
    }

    public function testTestApiConnectionHttpError(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(500);

        $this->mockLarkClient->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse)
        ;

        $this->mockIo->expects($this->once())
            ->method('error')
            ->with(self::stringContains('HTTP请求失败'))
        ;

        $result = $this->apiTester->testApiConnection($this->mockIo);

        $this->assertTrue($result);
    }

    public function testTestApiConnectionApiError(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('toArray')->willReturn([
            'code' => 99991663,
            'msg' => 'Invalid access token',
        ]);

        $this->mockLarkClient->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse)
        ;

        $this->mockIo->expects($this->once())
            ->method('error')
            ->with(self::callback(function ($messages) {
                return \is_array($messages)
                    && \in_array('API调用失败', $messages, true)
                    && \in_array('错误信息: Invalid access token', $messages, true);
            }))
        ;

        $result = $this->apiTester->testApiConnection($this->mockIo);

        $this->assertTrue($result);
    }

    public function testTestApiConnectionException(): void
    {
        $this->mockLarkClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \RuntimeException('Network error'))
        ;

        $this->mockIo->expects($this->once())
            ->method('error')
            ->with(self::callback(function ($messages) {
                return \is_array($messages)
                    && \in_array('API连接测试失败', $messages, true);
            }))
        ;

        $result = $this->apiTester->testApiConnection($this->mockIo);

        $this->assertTrue($result);
    }

    public function testTestNetworkConnectivitySuccess(): void
    {
        $apiTester = self::getContainer()->get('MockApiTesterFeishu');
        $this->assertInstanceOf(ApiTester::class, $apiTester);

        $this->mockIo->expects($this->once())
            ->method('section')
            ->with('网络连接测试')
        ;

        // Note: This test may fail if actual network connection is attempted
        // Consider mocking fsockopen for more reliable testing
        $result = $apiTester->testNetworkConnectivity($this->mockIo);

        $this->assertIsBool($result);
    }

    public function testTestNetworkConnectivityInvalidDomain(): void
    {
        $apiTester = self::getContainer()->get('MockApiTesterInvalidDomain');
        $this->assertInstanceOf(ApiTester::class, $apiTester);

        $this->mockIo->expects($this->once())
            ->method('error')
            ->with('无法解析API域名')
        ;

        $result = $apiTester->testNetworkConnectivity($this->mockIo);

        $this->assertTrue($result);
    }

    public function testTestNetworkConnectivityMissingConfig(): void
    {
        $apiTester = self::getContainer()->get('MockApiTesterEmptyConfig');
        $this->assertInstanceOf(ApiTester::class, $apiTester);

        $this->mockIo->expects($this->once())
            ->method('error')
            ->with('无法解析API域名')
        ;

        $result = $apiTester->testNetworkConnectivity($this->mockIo);

        $this->assertTrue($result);
    }

    protected function onSetUp(): void
    {
        $this->mockLarkClient = $this->createMock(LarkClientInterface::class);
        $this->mockIo = $this->createMock(SymfonyStyle::class);

        self::getContainer()->set(LarkClientInterface::class, $this->mockLarkClient);
        $this->apiTester = self::getService(ApiTester::class);
    }
}
