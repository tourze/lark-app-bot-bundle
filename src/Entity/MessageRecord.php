<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\LarkAppBotBundle\Repository\MessageRecordRepository;

#[ORM\Entity(repositoryClass: MessageRecordRepository::class)]
#[ORM\Table(name: 'lark_message_record', options: ['comment' => '飞书消息记录表'])]
class MessageRecord implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[IndexColumn]
    #[ORM\Column(name: 'message_id', type: Types::STRING, length: 100, options: ['comment' => '飞书消息ID'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $messageId = '';

    #[IndexColumn]
    #[ORM\Column(name: 'chat_id', type: Types::STRING, length: 100, options: ['comment' => '聊天ID'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $chatId = '';

    #[ORM\Column(name: 'chat_type', type: Types::STRING, length: 20, options: ['comment' => '聊天类型: p2p, group'])]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['p2p', 'group'])]
    private string $chatType = 'p2p';

    #[IndexColumn]
    #[ORM\Column(name: 'sender_id', type: Types::STRING, length: 100, options: ['comment' => '发送者ID'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $senderId = '';

    #[ORM\Column(name: 'sender_type', type: Types::STRING, length: 20, options: ['comment' => '发送者类型: user, bot'])]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['user', 'bot'])]
    private string $senderType = 'user';

    #[IndexColumn]
    #[ORM\Column(name: 'message_type', type: Types::STRING, length: 50, options: ['comment' => '消息类型: text, image, file, card'])]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['text', 'image', 'file', 'card', 'video', 'audio', 'sticker', 'post', 'share_chat', 'share_user'])]
    private string $messageType = 'text';

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'content', type: Types::JSON, options: ['comment' => '消息内容JSON'])]
    #[Assert\NotNull]
    private array $content = [];

    public function __toString(): string
    {
        return \sprintf('消息 #%s [%s]', $this->messageId, $this->messageType);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessageId(): string
    {
        return $this->messageId;
    }

    public function setMessageId(string $messageId): void
    {
        $this->messageId = $messageId;
    }

    public function getChatId(): string
    {
        return $this->chatId;
    }

    public function setChatId(string $chatId): void
    {
        $this->chatId = $chatId;
    }

    public function getChatType(): string
    {
        return $this->chatType;
    }

    public function setChatType(string $chatType): void
    {
        $this->chatType = $chatType;
    }

    public function getSenderId(): string
    {
        return $this->senderId;
    }

    public function setSenderId(string $senderId): void
    {
        $this->senderId = $senderId;
    }

    public function getSenderType(): string
    {
        return $this->senderType;
    }

    public function setSenderType(string $senderType): void
    {
        $this->senderType = $senderType;
    }

    public function getMessageType(): string
    {
        return $this->messageType;
    }

    public function setMessageType(string $messageType): void
    {
        $this->messageType = $messageType;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContent(): array
    {
        return $this->content;
    }

    /**
     * @param array<string, mixed> $content
     */
    public function setContent(array $content): void
    {
        $this->content = $content;
    }

    public function isFromBot(): bool
    {
        return 'bot' === $this->senderType;
    }

    public function isGroupMessage(): bool
    {
        return 'group' === $this->chatType;
    }
}
