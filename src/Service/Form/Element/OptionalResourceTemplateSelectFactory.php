<?php declare(strict_types=1);

namespace Menu\Service\Form\Element;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Menu\Form\Element\OptionalResourceTemplateSelect;
use Psr\Container\ContainerInterface;

class OptionalResourceTemplateSelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $element = new OptionalResourceTemplateSelect(null, $options ?? []);
        $element->setApiManager($services->get('Omeka\ApiManager'));
        return $element;
    }
}
