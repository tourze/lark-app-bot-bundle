<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tourze\LarkAppBotBundle\Service\User\UserFormatter;

/**
 * @internal
 */
#[CoversClass(UserFormatter::class)]
final class UserFormatterTest extends TestCase
{
    private UserFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new UserFormatter();
    }

    public function testFormatDisplayNameWithI18n(): void
    {
        $user = [
            'display_name_i18n' => [
                'zh_CN' => '张三',
                'en_US' => 'Zhang San'],
            'name' => '张三备用',
            'en_name' => 'Zhang San Backup'];

        $result = $this->formatter->formatDisplayName($user, 'zh_CN');
        $this->assertSame('张三', $result);

        $result = $this->formatter->formatDisplayName($user, 'en_US');
        $this->assertSame('Zhang San', $result);
    }

    public function testFormatDisplayNameWithLocaleName(): void
    {
        $user = [
            'name' => '李四',
            'en_name' => 'Li Si'];

        $result = $this->formatter->formatDisplayName($user, 'zh_CN');
        $this->assertSame('李四', $result);

        $result = $this->formatter->formatDisplayName($user, 'en_US');
        $this->assertSame('Li Si', $result);
    }

    public function testFormatDisplayNameWithNickname(): void
    {
        $user = [
            'nickname' => 'user123'];

        $result = $this->formatter->formatDisplayName($user);
        $this->assertSame('user123', $result);
    }

    public function testFormatDisplayNameEmpty(): void
    {
        $user = [];

        $result = $this->formatter->formatDisplayName($user);
        $this->assertSame('未知用户', $result);
    }

    public function testFormatDisplayNameWithJobTitle(): void
    {
        $user = [
            'name' => '王五',
            'job_title' => '高级工程师'];

        $result = $this->formatter->formatDisplayName($user, 'zh_CN', true);
        $this->assertSame('王五 (高级工程师)', $result);

        $result = $this->formatter->formatDisplayName($user, 'zh_CN', false);
        $this->assertSame('王五', $result);
    }

    /**
     * @param array<string, mixed> $user
     */
    #[TestWith(['72', ['avatar' => ['avatar_72' => 'http://example.com/72.jpg']], 'http://example.com/72.jpg'])]
    #[TestWith(['240', ['avatar' => ['avatar_240' => 'http://example.com/240.jpg']], 'http://example.com/240.jpg'])]
    #[TestWith(['640', ['avatar' => ['avatar_640' => 'http://example.com/640.jpg']], 'http://example.com/640.jpg'])]
    #[TestWith(['origin', ['avatar' => ['avatar_origin' => 'http://example.com/origin.jpg']], 'http://example.com/origin.jpg'])]
    public function testGetAvatarUrlWithSize(string $size, array $user, string $expected): void
    {
        $result = $this->formatter->getAvatarUrl($user, $size);
        $this->assertSame($expected, $result);
    }

    public function testGetAvatarUrlFallback(): void
    {
        $user = [
            'avatar' => [
                'avatar_72' => 'http://example.com/72.jpg']];

        // 240 should fallback to 72
        $result = $this->formatter->getAvatarUrl($user, '240');
        $this->assertSame('http://example.com/72.jpg', $result);

        // 640 should fallback to 72
        $result = $this->formatter->getAvatarUrl($user, '640');
        $this->assertSame('http://example.com/72.jpg', $result);
    }

    public function testGetAvatarUrlDefault(): void
    {
        $user = [
            'avatar' => [
                'avatar_240' => 'http://example.com/240.jpg']];

        $result = $this->formatter->getAvatarUrl($user);
        $this->assertSame('http://example.com/240.jpg', $result);

        $result = $this->formatter->getAvatarUrl($user, 'invalid');
        $this->assertSame('http://example.com/240.jpg', $result);
    }

    public function testGetAvatarUrlEmpty(): void
    {
        $user = [];

        $result = $this->formatter->getAvatarUrl($user);
        $this->assertNull($result);
    }

    public function testFormatDisplayNamePriority(): void
    {
        // 测试优先级: display_name_i18n > name/en_name > nickname
        $user = [
            'display_name_i18n' => [
                'zh_CN' => 'I18N名称'],
            'name' => 'Local名称',
            'nickname' => 'Nickname'];

        $result = $this->formatter->formatDisplayName($user, 'zh_CN');
        $this->assertSame('I18N名称', $result);

        // 移除 i18n，应该使用 name
        unset($user['display_name_i18n']);
        $result = $this->formatter->formatDisplayName($user, 'zh_CN');
        $this->assertSame('Local名称', $result);

        // 移除 name，应该使用 nickname
        unset($user['name']);
        $result = $this->formatter->formatDisplayName($user, 'zh_CN');
        $this->assertSame('Nickname', $result);
    }
}
