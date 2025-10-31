<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message\Template;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\LarkAppBotBundle\Exception\ConfigurationException;

/**
 * 消息模板管理器
 * 负责管理和获取消息模板
 */
#[Autoconfigure(public: true)]
class TemplateManager
{
    /**
     * @var array<string, MessageTemplateInterface>
     */
    private array $templates = [];

    /**
     * 注册模板
     */
    public function registerTemplate(MessageTemplateInterface $template): self
    {
        $this->templates[$template->getName()] = $template;

        return $this;
    }

    /**
     * 获取模板
     *
     * @param string $name 模板名称
     *
     * @throws ConfigurationException
     */
    public function getTemplate(string $name): MessageTemplateInterface
    {
        if (!isset($this->templates[$name])) {
            throw new ConfigurationException(\sprintf('模板 "%s" 未找到，可用的模板有: %s', $name, implode(', ', array_keys($this->templates))));
        }

        return $this->templates[$name];
    }

    /**
     * 检查模板是否存在.
     */
    public function hasTemplate(string $name): bool
    {
        return isset($this->templates[$name]);
    }

    /**
     * 获取所有模板
     *
     * @return array<string, MessageTemplateInterface>
     */
    public function getAllTemplates(): array
    {
        return $this->templates;
    }

    /**
     * 获取模板信息列表.
     *
     * @return array<string, array{name: string, description: string, variables: array<string, string>}>
     */
    public function getTemplatesInfo(): array
    {
        $info = [];

        foreach ($this->templates as $name => $template) {
            $info[$name] = [
                'name' => $template->getName(),
                'description' => $template->getDescription(),
                'variables' => $template->getRequiredVariables(),
            ];
        }

        return $info;
    }

    /**
     * 删除模板
     */
    public function removeTemplate(string $name): self
    {
        unset($this->templates[$name]);

        return $this;
    }

    /**
     * 清空所有模板
     */
    public function clearTemplates(): self
    {
        $this->templates = [];

        return $this;
    }

    /**
     * 批量注册模板
     *
     * @param array<MessageTemplateInterface> $templates
     */
    public function registerTemplates(array $templates): self
    {
        foreach ($templates as $template) {
            $this->registerTemplate($template);
        }

        return $this;
    }

    /**
     * 创建默认的模板管理器实例.
     */
    public static function createDefault(): self
    {
        $manager = new self();

        // 注册内置模板
        $manager->registerTemplate(new WelcomeMessageTemplate());
        $manager->registerTemplate(new NotificationMessageTemplate());

        return $manager;
    }
}
