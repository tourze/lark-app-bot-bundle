<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\ExternalCollaboration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\Service\ExternalCollaboration\ExternalUserIdentifier;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(ExternalUserIdentifier::class)]
#[RunTestsInSeparateProcesses]
class ExternalUserIdentifierTest extends AbstractIntegrationTestCase
{
    private ExternalUserIdentifier $identifier;

    public function testIsExternalUser(): void
    {
        $this->assertTrue($this->identifier->isExternalUser('ou_external_123456'));
        $this->assertFalse($this->identifier->isExternalUser('ou_123456'));
    }

    public function testIsExternalGroup(): void
    {
        $this->assertTrue($this->identifier->isExternalGroup('oc_external_123456'));
        $this->assertFalse($this->identifier->isExternalGroup('oc_123456'));
    }

    public function testIdentifyFromUserInfoWithUserType(): void
    {
        $userInfo = ['user_type' => 'external'];
        $this->assertTrue($this->identifier->identifyFromUserInfo($userInfo));

        $userInfo = ['user_type' => 'internal'];
        $this->assertFalse($this->identifier->identifyFromUserInfo($userInfo));
    }

    public function testIdentifyFromUserInfoWithOpenId(): void
    {
        $userInfo = ['open_id' => 'ou_external_123456'];
        $this->assertTrue($this->identifier->identifyFromUserInfo($userInfo));

        $userInfo = ['open_id' => 'ou_123456'];
        $this->assertFalse($this->identifier->identifyFromUserInfo($userInfo));
    }

    public function testIdentifyFromUserInfoWithEmail(): void
    {
        $userInfo = ['email' => 'user@external.com'];
        $this->assertTrue($this->identifier->identifyFromUserInfo($userInfo));

        $userInfo = ['email' => 'user@company.com'];
        $this->assertFalse($this->identifier->identifyFromUserInfo($userInfo));
    }

    public function testIdentifyFromUserInfoWithoutDepartments(): void
    {
        $userInfo = [];
        $this->assertTrue($this->identifier->identifyFromUserInfo($userInfo));

        $userInfo = ['department_ids' => []];
        $this->assertTrue($this->identifier->identifyFromUserInfo($userInfo));

        $userInfo = ['department_ids' => ['dept_123']];
        $this->assertFalse($this->identifier->identifyFromUserInfo($userInfo));
    }

    public function testGetExternalUserTags(): void
    {
        $tags = $this->identifier->getExternalUserTags('ou_external_123456');
        $this->assertIsArray($tags);
        $this->assertArrayHasKey('is_external', $tags);
        $this->assertTrue($tags['is_external']);
        $this->assertArrayHasKey('user_type', $tags);
        $this->assertSame('external', $tags['user_type']);

        $tags = $this->identifier->getExternalUserTags('ou_123456');
        $this->assertEmpty($tags);
    }

    public function testValidateExternalAccess(): void
    {
        $this->assertTrue($this->identifier->validateExternalAccess('ou_123456', 'resource'));
        $this->assertFalse($this->identifier->validateExternalAccess('ou_external_123456', 'resource'));
    }

    public function testConstructorWithoutLogger(): void
    {
        $identifier = self::getService(ExternalUserIdentifier::class);
        $this->assertInstanceOf(ExternalUserIdentifier::class, $identifier);
    }

    protected function onSetUp(): void
    {
        // 获取服务实例
        $this->identifier = self::getService(ExternalUserIdentifier::class);
    }
}
