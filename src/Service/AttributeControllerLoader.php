<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service;

use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Routing\RouteCollection;
use Tourze\LarkAppBotBundle\Controller\WebhookController;
use Tourze\RoutingAutoLoaderBundle\Service\RoutingAutoLoaderInterface;

/**
 * 属性控制器加载器.
 *
 * 用于处理控制器属性路由的加载
 */
#[Autoconfigure(public: true)]
#[AutoconfigureTag(name: 'routing.loader')]
final class AttributeControllerLoader extends Loader implements RoutingAutoLoaderInterface
{
    private AttributeRouteControllerLoader $controllerLoader;

    public function __construct()
    {
        parent::__construct();
        $this->controllerLoader = new AttributeRouteControllerLoader();
    }

    /**
     * 加载资源.
     *
     * @param mixed       $resource 资源
     * @param string|null $type     类型
     */
    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        return $this->autoload();
    }

    /**
     * 支持检查.
     *
     * @param mixed       $resource 资源
     * @param string|null $type     类型
     */
    public function supports(mixed $resource, ?string $type = null): bool
    {
        return false;
    }

    /**
     * 自动加载路由.
     */
    public function autoload(): RouteCollection
    {
        $collection = new RouteCollection();
        $collection->addCollection($this->controllerLoader->load(WebhookController::class));

        return $collection;
    }
}
