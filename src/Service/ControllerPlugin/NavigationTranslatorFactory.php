<?php declare(strict_types=1);

namespace Menu\Service\ControllerPlugin;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Menu\Mvc\Controller\Plugin\NavigationTranslator;

class NavigationTranslatorFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $name, array $options = null)
    {
        return new NavigationTranslator(
            $services->get('Omeka\Site\NavigationLinkManager'),
            $services->get('MvcTranslator'),
            $services->get('ViewHelperManager')->get('Url')
        );
    }
}
