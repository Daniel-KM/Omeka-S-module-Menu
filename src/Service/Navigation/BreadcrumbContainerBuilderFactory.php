<?php declare(strict_types=1);

namespace Menu\Service\Navigation;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Menu\Site\Navigation\Breadcrumb\ContainerBuilder;

class BreadcrumbContainerBuilderFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ContainerBuilder(
            $services->get('Omeka\ApiManager'),
            $services->get('MvcTranslator'),
            $services->get('ViewHelperManager')->get('Url')
        );
    }
}
