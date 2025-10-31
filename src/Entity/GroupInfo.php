<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\LarkAppBotBundle\Repository\GroupInfoRepository;

#[ORM\Entity(repositoryClass: GroupInfoRepository::class)]
#[ORM\Table(name: 'lark_group_info', options: ['comment' => '飞书群组信息表'])]
class GroupInfo implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[IndexColumn]
    #[ORM\Column(name: 'chat_id', type: Types::STRING, length: 100, unique: true, options: ['comment' => '群组ID'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $chatId = '';

    #[ORM\Column(name: 'name', type: Types::STRING, length: 200, options: ['comment' => '群组名称'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    private string $name = '';

    #[ORM\Column(name: 'description', type: Types::TEXT, nullable: true, options: ['comment' => '群组描述'])]
    #[Assert\Type(type: 'string')]
    private ?string $description = null;

    #[IndexColumn]
    #[ORM\Column(name: 'owner_id', type: Types::STRING, length: 100, nullable: true, options: ['comment' => '群主ID'])]
    #[Assert\Length(max: 100)]
    private ?string $ownerId = null;

    #[ORM\Column(name: 'member_count', type: Types::INTEGER, options: ['comment' => '成员数量'], columnDefinition: 'INT DEFAULT 0')]
    #[Assert\PositiveOrZero]
    private int $memberCount = 0;

    #[ORM\Column(name: 'bot_count', type: Types::INTEGER, options: ['comment' => '机器人数量'], columnDefinition: 'INT DEFAULT 0')]
    #[Assert\PositiveOrZero]
    private int $botCount = 0;

    #[IndexColumn]
    #[ORM\Column(name: 'chat_type', type: Types::STRING, length: 50, nullable: true, options: ['comment' => '群组类型'])]
    #[Assert\Length(max: 50)]
    private ?string $chatType = null;

    #[IndexColumn]
    #[ORM\Column(name: 'external', type: Types::BOOLEAN, options: ['comment' => '是否外部群组'], columnDefinition: 'TINYINT(1) DEFAULT 0')]
    #[Assert\Type(type: 'bool')]
    private bool $external = false;

    public function __toString(): string
    {
        return \sprintf('群组 %s (%s)', $this->name, $this->chatId);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChatId(): string
    {
        return $this->chatId;
    }

    public function setChatId(string $chatId): void
    {
        $this->chatId = $chatId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name ?? '';
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getOwnerId(): ?string
    {
        return $this->ownerId;
    }

    public function setOwnerId(?string $ownerId): void
    {
        $this->ownerId = $ownerId;
    }

    public function getMemberCount(): int
    {
        return $this->memberCount;
    }

    public function setMemberCount(int $memberCount): void
    {
        $this->memberCount = $memberCount;
    }

    public function getBotCount(): int
    {
        return $this->botCount;
    }

    public function setBotCount(int $botCount): void
    {
        $this->botCount = $botCount;
    }

    public function getChatType(): ?string
    {
        return $this->chatType;
    }

    public function setChatType(?string $chatType): void
    {
        $this->chatType = $chatType;
    }

    public function isExternal(): bool
    {
        return $this->external;
    }

    public function setExternal(bool $external): void
    {
        $this->external = $external;
    }
}
