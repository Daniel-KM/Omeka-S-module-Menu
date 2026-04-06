<?php declare(strict_types=1);

namespace Menu\Service\ViewHelper;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Menu\View\Helper\NavMenu;
use Psr\Container\ContainerInterface;

class NavMenuFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new NavMenu(
            $services->get('ControllerPluginManager')->get('navigationTranslator'),
            $services
        );
    }
}
