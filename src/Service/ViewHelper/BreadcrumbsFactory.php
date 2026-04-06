<?php declare(strict_types=1);

namespace Menu\Service\ViewHelper;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Menu\View\Helper\Breadcrumbs;
use Psr\Container\ContainerInterface;

class BreadcrumbsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new Breadcrumbs(
            $services->get('Menu\Site\Navigation\Breadcrumb\ContainerBuilder')
        );
    }
}
