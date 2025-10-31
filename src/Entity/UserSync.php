<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\LarkAppBotBundle\Repository\UserSyncRepository;

#[ORM\Entity(repositoryClass: UserSyncRepository::class)]
#[ORM\Table(name: 'lark_user_sync', options: ['comment' => '飞书用户同步记录表'])]
class UserSync implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[IndexColumn]
    #[ORM\Column(name: 'user_id', type: Types::STRING, length: 100, unique: true, options: ['comment' => '飞书用户ID'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $userId = '';

    #[IndexColumn]
    #[ORM\Column(name: 'open_id', type: Types::STRING, length: 100, nullable: true, options: ['comment' => 'Open ID'])]
    #[Assert\Length(max: 100)]
    private ?string $openId = null;

    #[IndexColumn]
    #[ORM\Column(name: 'union_id', type: Types::STRING, length: 100, nullable: true, options: ['comment' => 'Union ID'])]
    #[Assert\Length(max: 100)]
    private ?string $unionId = null;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 100, options: ['comment' => '用户名'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $name = '';

    #[IndexColumn]
    #[ORM\Column(name: 'email', type: Types::STRING, length: 255, nullable: true, options: ['comment' => '邮箱'])]
    #[Assert\Length(max: 255)]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(name: 'mobile', type: Types::STRING, length: 20, nullable: true, options: ['comment' => '手机号'])]
    #[Assert\Length(max: 20)]
    private ?string $mobile = null;

    /** @var array<string>|null */
    #[ORM\Column(name: 'department_ids', type: Types::JSON, nullable: true, options: ['comment' => '部门ID JSON'])]
    #[Assert\Type(type: 'array')]
    private ?array $departmentIds = null;

    #[IndexColumn]
    #[ORM\Column(name: 'sync_status', type: Types::STRING, length: 20, options: ['comment' => '同步状态: pending, success, failed'])]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['pending', 'success', 'failed'])]
    private string $syncStatus = 'pending';

    #[ORM\Column(name: 'sync_at', type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '同步时间'])]
    #[Assert\DateTime]
    private ?\DateTimeImmutable $syncAt = null;

    #[ORM\Column(name: 'error_message', type: Types::TEXT, nullable: true, options: ['comment' => '错误信息'])]
    #[Assert\Type(type: 'string')]
    private ?string $errorMessage = null;

    public function __toString(): string
    {
        return \sprintf('用户同步 %s (%s)', $this->name, $this->userId);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getOpenId(): ?string
    {
        return $this->openId;
    }

    public function setOpenId(?string $openId): void
    {
        $this->openId = $openId;
    }

    public function getUnionId(): ?string
    {
        return $this->unionId;
    }

    public function setUnionId(?string $unionId): void
    {
        $this->unionId = $unionId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getMobile(): ?string
    {
        return $this->mobile;
    }

    public function setMobile(?string $mobile): void
    {
        $this->mobile = $mobile;
    }

    /**
     * @return array<string>|null
     */
    public function getDepartmentIds(): ?array
    {
        return $this->departmentIds;
    }

    /**
     * @param array<string>|null $departmentIds
     */
    public function setDepartmentIds(?array $departmentIds): void
    {
        $this->departmentIds = $departmentIds;
    }

    public function getSyncStatus(): string
    {
        return $this->syncStatus;
    }

    public function setSyncStatus(string $syncStatus): void
    {
        $allowed = ['pending', 'success', 'failed'];
        if (!\in_array($syncStatus, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid sync status');
        }

        $this->syncStatus = $syncStatus;
    }

    public function getSyncAt(): ?\DateTimeImmutable
    {
        return $this->syncAt;
    }

    public function setSyncAt(?\DateTimeImmutable $syncAt): void
    {
        $this->syncAt = $syncAt;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }
}
