<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\LarkAppBotBundle\Repository\BotConfigurationRepository;

#[ORM\Entity(repositoryClass: BotConfigurationRepository::class)]
#[ORM\Table(name: 'lark_bot_configuration', options: ['comment' => '飞书机器人配置表'])]
#[ORM\UniqueConstraint(name: 'lark_bot_configuration_uniq_app_config', columns: ['app_id', 'config_key'])]
class BotConfiguration implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[IndexColumn]
    #[ORM\Column(name: 'app_id', type: Types::STRING, length: 100, options: ['comment' => '应用ID'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $appId = '';

    #[ORM\Column(name: 'name', type: Types::STRING, length: 100, options: ['comment' => '配置名称'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $name = '';

    #[IndexColumn]
    #[ORM\Column(name: 'config_key', type: Types::STRING, length: 100, options: ['comment' => '配置键'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $configKey = '';

    #[ORM\Column(name: 'config_value', type: Types::TEXT, options: ['comment' => '配置值'])]
    #[Assert\NotBlank]
    private string $configValue = '';

    #[ORM\Column(name: 'description', type: Types::STRING, length: 500, nullable: true, options: ['comment' => '配置描述'])]
    #[Assert\Length(max: 500)]
    private ?string $description = null;

    #[IndexColumn]
    #[ORM\Column(name: 'is_active', type: Types::BOOLEAN, options: ['comment' => '是否激活'], columnDefinition: 'TINYINT(1) DEFAULT 1')]
    #[Assert\Type(type: 'bool')]
    private bool $isActive = true;

    public function __toString(): string
    {
        return \sprintf('配置 %s (%s)', $this->name, $this->configKey);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function setAppId(string $appId): void
    {
        $this->appId = $appId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getConfigKey(): string
    {
        return $this->configKey;
    }

    public function setConfigKey(string $configKey): void
    {
        $this->configKey = $configKey;
    }

    public function getConfigValue(): string
    {
        return $this->configValue;
    }

    public function setConfigValue(string $configValue): void
    {
        $this->configValue = $configValue;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }
}
