<?php declare(strict_types=1);

namespace Menu\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Menu\View\Helper\NavMenu;

class NavMenuFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new NavMenu(
            $services,
            $services->get('ControllerPluginManager')->get('navigationTranslator')
        );
    }
}
