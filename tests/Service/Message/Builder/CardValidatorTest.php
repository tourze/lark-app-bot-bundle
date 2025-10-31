<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Message\Builder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tourze\LarkAppBotBundle\Exception\ValidationException;
use Tourze\LarkAppBotBundle\Service\Message\Builder\CardValidator;

/**
 * 卡片验证器测试.
 *
 * @internal
 */
#[CoversClass(CardValidator::class)]
class CardValidatorTest extends TestCase
{
    private CardValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new CardValidator();
    }

    public function testValidatePassesForValidCardWithElements(): void
    {
        $card = [
            'elements' => [
                ['tag' => 'div', 'text' => ['content' => 'Hello', 'tag' => 'plain_text']],
            ],
        ];

        $this->validator->validate($card);

        // 如果没有异常抛出，验证通过
        $this->assertTrue(true, 'Valid card passed validation');
    }

    public function testValidatePassesForValidCardWithI18nElements(): void
    {
        $card = [
            'i18n_elements' => [
                'zh_cn' => [['tag' => 'div', 'text' => ['content' => '你好', 'tag' => 'plain_text']]],
                'en_us' => [['tag' => 'div', 'text' => ['content' => 'Hello', 'tag' => 'plain_text']]],
            ],
        ];

        $this->validator->validate($card);

        $this->assertTrue(true, 'Validation passed');
    }

    public function testValidateThrowsExceptionForCardWithoutElements(): void
    {
        $card = [];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('卡片至少需要包含一个元素');

        $this->validator->validate($card);
    }

    public function testValidateThrowsExceptionForCardWithEmptyElements(): void
    {
        $card = [
            'elements' => [],
            'i18n_elements' => [],
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('卡片至少需要包含一个元素');

        $this->validator->validate($card);
    }

    public function testValidateThrowsExceptionForCardWithTooManyElements(): void
    {
        $elements = [];
        for ($i = 0; $i < 51; ++$i) {
            $elements[] = ['tag' => 'div', 'text' => ['content' => "Element {$i}", 'tag' => 'plain_text']];
        }

        $card = ['elements' => $elements];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('卡片元素数量不能超过50个');

        $this->validator->validate($card);
    }

    public function testValidatePassesForCardWithExactly50Elements(): void
    {
        $elements = [];
        for ($i = 0; $i < 50; ++$i) {
            $elements[] = ['tag' => 'div', 'text' => ['content' => "Element {$i}", 'tag' => 'plain_text']];
        }

        $card = ['elements' => $elements];

        $this->validator->validate($card);

        $this->assertTrue(true, 'Validation passed');
    }

    public function testValidatePassesForCardWithValidHeader(): void
    {
        $card = [
            'elements' => [
                ['tag' => 'div', 'text' => ['content' => 'Content', 'tag' => 'plain_text']],
            ],
            'header' => [
                'title' => [
                    'content' => 'Card Title',
                    'tag' => 'plain_text',
                ],
            ],
        ];

        $this->validator->validate($card);

        $this->assertTrue(true, 'Validation passed');
    }

    public function testValidateThrowsExceptionForHeaderWithoutTitleContent(): void
    {
        $card = [
            'elements' => [
                ['tag' => 'div', 'text' => ['content' => 'Content', 'tag' => 'plain_text']],
            ],
            'header' => [
                'title' => [
                    'content' => '',
                    'tag' => 'plain_text',
                ],
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('卡片头部标题不能为空');

        $this->validator->validate($card);
    }

    public function testValidateThrowsExceptionForHeaderWithMissingTitle(): void
    {
        $card = [
            'elements' => [
                ['tag' => 'div', 'text' => ['content' => 'Content', 'tag' => 'plain_text']],
            ],
            'header' => [],
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('卡片头部标题不能为空');

        $this->validator->validate($card);
    }

    public function testValidatePassesForHeaderWithValidTemplate(): void
    {
        $card = [
            'elements' => [
                ['tag' => 'div', 'text' => ['content' => 'Content', 'tag' => 'plain_text']],
            ],
            'header' => [
                'title' => [
                    'content' => 'Title',
                    'tag' => 'plain_text',
                ],
                'template' => 'blue',
            ],
        ];

        $this->validator->validate($card);

        $this->assertTrue(true, 'Validation passed');
    }

    public function testValidateThrowsExceptionForHeaderWithInvalidTemplate(): void
    {
        $card = [
            'elements' => [
                ['tag' => 'div', 'text' => ['content' => 'Content', 'tag' => 'plain_text']],
            ],
            'header' => [
                'title' => [
                    'content' => 'Title',
                    'tag' => 'plain_text',
                ],
                'template' => 'invalid_color',
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('无效的头部模板颜色');

        $this->validator->validate($card);
    }

    public function testValidatePassesForTemplateCard(): void
    {
        $card = [
            'type' => 'template',
            'data' => [
                'template_id' => 'template_123',
                'template_variable' => [
                    'key1' => 'value1',
                ],
            ],
        ];

        $this->validator->validate($card);

        $this->assertTrue(true, 'Validation passed');
    }

    public function testValidateThrowsExceptionForTemplateCardWithoutTemplateId(): void
    {
        $card = [
            'type' => 'template',
            'data' => [
                'template_variable' => [
                    'key1' => 'value1',
                ],
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('模板ID不能为空');

        $this->validator->validate($card);
    }

    public function testValidateThrowsExceptionForTemplateCardWithEmptyTemplateId(): void
    {
        $card = [
            'type' => 'template',
            'data' => [
                'template_id' => '',
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('模板ID不能为空');

        $this->validator->validate($card);
    }

    #[TestWith(['blue'])]
    #[TestWith(['turquoise'])]
    #[TestWith(['orange'])]
    #[TestWith(['red'])]
    #[TestWith(['grey'])]
    #[TestWith(['green'])]
    #[TestWith(['purple'])]
    #[TestWith(['indigo'])]
    #[TestWith(['wathet'])]
    public function testValidatePassesForAllValidTemplateColors(string $template): void
    {
        $card = [
            'elements' => [
                ['tag' => 'div', 'text' => ['content' => 'Content', 'tag' => 'plain_text']],
            ],
            'header' => [
                'title' => [
                    'content' => 'Title',
                    'tag' => 'plain_text',
                ],
                'template' => $template,
            ],
        ];

        $this->validator->validate($card);

        $this->assertTrue(true, 'Validation passed');
    }

    public function testValidate(): void
    {
        // 测试验证器基本功能
        $validCard = [
            'elements' => [
                ['tag' => 'div', 'text' => ['content' => 'Valid content', 'tag' => 'plain_text']],
            ],
            'header' => [
                'title' => [
                    'content' => 'Valid title',
                    'tag' => 'plain_text',
                ],
                'template' => 'blue',
            ],
        ];

        // 正常卡片应该通过验证
        $this->validator->validate($validCard);
        $this->assertTrue(true, 'Valid card passed validation');

        // 测试模板卡片验证
        $templateCard = [
            'type' => 'template',
            'data' => [
                'template_id' => 'valid_template_id',
                'template_variable' => ['key' => 'value'],
            ],
        ];
        $this->validator->validate($templateCard);
        $this->assertTrue(true, 'Valid template card passed validation');

        // 测试无效卡片应该抛出异常
        $invalidCard = ['elements' => []];
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('卡片至少需要包含一个元素');
        $this->validator->validate($invalidCard);
    }
}
