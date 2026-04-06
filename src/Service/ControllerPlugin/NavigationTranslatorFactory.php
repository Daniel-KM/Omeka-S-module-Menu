<?php declare(strict_types=1);

namespace Menu\Service\ControllerPlugin;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Menu\Mvc\Controller\Plugin\NavigationTranslator;
use Psr\Container\ContainerInterface;

class NavigationTranslatorFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $name, array $options = null)
    {
        return new NavigationTranslator(
            $services->get('MvcTranslator'),
            $services->get('Omeka\Site\NavigationLinkManager'),
            $services->get('ViewHelperManager')->get('Url')
        );
    }
}
