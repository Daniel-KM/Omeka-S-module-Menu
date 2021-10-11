<?php declare(strict_types=1);

namespace Menu\Service\Form\Element;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Menu\Form\Element\MenuSelect;

class MenuSelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $currentSite = $services->get('ControllerPluginManager')->get('currentSite');
        $currentSite = $currentSite();
        $element = new MenuSelect(null, $options);
        return $element
            ->setSettings($services->get($currentSite ? 'Omeka\Settings\Site' : 'Omeka\Settings'));
    }
}
