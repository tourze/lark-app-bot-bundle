<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\LarkAppBotBundle\Repository\ApiLogRepository;

#[ORM\Entity(repositoryClass: ApiLogRepository::class)]
#[ORM\Table(name: 'lark_api_log', options: ['comment' => '飞书API调用日志表'])]
class ApiLog implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[IndexColumn]
    #[ORM\Column(name: 'endpoint', type: Types::STRING, length: 500, options: ['comment' => 'API端点'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    private string $endpoint = '';

    #[IndexColumn]
    #[ORM\Column(name: 'method', type: Types::STRING, length: 10, options: ['comment' => 'HTTP方法'])]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'])]
    private string $method = 'GET';

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'request_data', type: Types::JSON, nullable: true, options: ['comment' => '请求数据JSON'])]
    #[Assert\Type(type: 'array')]
    private ?array $requestData = null;

    /** @var array<string, mixed>|null */
    #[Assert\Valid]
    #[ORM\Column(name: 'response_data', type: Types::JSON, nullable: true, options: ['comment' => '响应数据JSON'])]
    #[Assert\Type(type: 'array')]
    private ?array $responseData = null;

    #[IndexColumn]
    #[ORM\Column(name: 'status_code', type: Types::INTEGER, options: ['comment' => '状态码'])]
    #[Assert\NotNull]
    #[Assert\Range(min: 100, max: 599)]
    private int $statusCode = 200;

    #[ORM\Column(name: 'response_time', type: Types::INTEGER, nullable: true, options: ['comment' => '响应时间(毫秒)'])]
    #[Assert\PositiveOrZero]
    private ?int $responseTime = null;

    #[IndexColumn]
    #[ORM\Column(name: 'user_id', type: Types::STRING, length: 100, nullable: true, options: ['comment' => '调用用户ID'])]
    #[Assert\Length(max: 100)]
    private ?string $userId = null;

    #[IndexColumn]
    #[ORM\Column(name: 'create_time', type: Types::DATETIME_IMMUTABLE, options: ['comment' => '创建时间'])]
    #[Assert\NotNull]
    private \DateTimeImmutable $createTime;

    public function __construct()
    {
        $this->createTime = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return \sprintf('API日志 %s %s [%d]', $this->method, $this->endpoint, $this->statusCode);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function setEndpoint(string $endpoint): void
    {
        $this->endpoint = $endpoint;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRequestData(): ?array
    {
        return $this->requestData;
    }

    /**
     * @param array<string, mixed>|null $requestData
     */
    public function setRequestData(?array $requestData): void
    {
        $this->requestData = $requestData;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResponseData(): ?array
    {
        return $this->responseData;
    }

    /**
     * @param array<string, mixed>|null $responseData
     */
    public function setResponseData(?array $responseData): void
    {
        $this->responseData = $responseData;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int $statusCode): void
    {
        $this->statusCode = $statusCode;
    }

    public function getResponseTime(): ?int
    {
        return $this->responseTime;
    }

    public function setResponseTime(?int $responseTime): void
    {
        $this->responseTime = $responseTime;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function getCreateTime(): \DateTimeImmutable
    {
        return $this->createTime;
    }

    public function setCreateTime(\DateTimeImmutable $createTime): void
    {
        $this->createTime = $createTime;
    }
}
