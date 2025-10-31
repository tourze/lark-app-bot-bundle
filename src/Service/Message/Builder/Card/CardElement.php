<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message\Builder\Card;

/**
 * 卡片元素基类
 * 提供卡片元素的通用功能.
 */
abstract class CardElement
{
    /**
     * 元素标签.
     */
    protected string $tag;

    /**
     * 元素数据.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * 构造函数.
     */
    public function __construct()
    {
        $this->tag = $this->getTag();
    }

    /**
     * 转换为数组.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge(['tag' => $this->tag], $this->data);
    }

    /**
     * 获取元素标签.
     */
    abstract protected function getTag(): string;

    /**
     * 设置数据.
     *
     * @param string $key   键名
     * @param mixed  $value 值
     */
    protected function setData(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * 获取数据.
     *
     * @param string $key 键名
     */
    protected function getData(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }
}
