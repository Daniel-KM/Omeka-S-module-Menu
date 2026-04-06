<?php declare(strict_types=1);

namespace Menu\Service\Form\Element;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Menu\Form\Element\OptionalPropertySelect;
use Psr\Container\ContainerInterface;

class OptionalPropertySelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $element = new OptionalPropertySelect(null, $options ?? []);
        $element->setEventManager($services->get('EventManager'));
        $element->setApiManager($services->get('Omeka\ApiManager'));
        $element->setTranslator($services->get('MvcTranslator'));
        return $element;
    }
}
