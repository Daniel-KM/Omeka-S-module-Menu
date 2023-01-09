<?php declare(strict_types=1);

namespace Menu\Site\Navigation\Link;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Site\Navigation\Link\LinkInterface;
use Omeka\Stdlib\ErrorStore;

class Resource implements LinkInterface
{
    public function getName()
    {
        return 'Resource'; // @translate
    }

    public function getFormTemplate()
    {
        return 'common/navigation-link-form/resource';
    }

    public function isValid(array $data, ErrorStore $errorStore)
    {
        if (!isset($data['id']) || !is_numeric($data['id']) || (int) $data['id'] <= 0) {
            $errorStore->addError('o:navigation', 'Invalid navigation: resource link missing resource ID'); // @translate
            return false;
        }
        return true;
    }

    public function getLabel(array $data, SiteRepresentation $site)
    {
        if (isset($data['label']) && trim($data['label']) !== '') {
            return $data['label'];
        }

        $services = $site->getServiceLocator();
        $id = (int) $data['id'];
        if (!$id) {
            $translator = $services->get('MvcTranslator');
            return $translator->translate('[Unknown resource]'); // @translate
        }

        $api = $services->get('Omeka\ApiManager');
        try {
            /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource */
            $resource = $api->read('resources', ['id' => $id])->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            $translator = $services->get('MvcTranslator');
            return sprintf($translator->translate('[Unknown resource #%d]'), $id); // @translate
        }

        // TODO Use language of the site to select title?
        return $resource->displayTitle();
    }

    /**
     * @deprecated Since Omeka v3.0 Use toLaminas() instead.
     */
    public function toZend(array $data, SiteRepresentation $site)
    {
        return $this->toLaminas($data, $site);
    }

    public function toLaminas(array $data, SiteRepresentation $site)
    {
        $id = (int) $data['id'];
        $api = $site->getServiceLocator()->get('Omeka\ApiManager');
        try {
            // TODO Return scalar id and resource type for performance or store the type and the title.
            // TODO Manage language.
            /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource */
            $resource = $api->read('resources', ['id' => $id])->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            return [
                'visible' => false,
                'route' => 'site/resource-id',
                'params' => [
                    'site-slug' => $site->slug(),
                    'controller' => 'item',
                    'action' => 'show',
                    'id' => $id,
                ],
                // Nobody has this right, except reviewer and above.
                // This is a resource for acl permissions.
                'resource' => \Omeka\Entity\Resource::class,
                'privilege' => 'read',
            ];
        }
        $controllerName = $resource->getControllerName();
        return [
            'route' => 'site/resource-id',
            'controller' => $controllerName,
            'action' => 'show',
            'params' => [
                'site-slug' => $site->slug(),
                'controller' => $controllerName,
                'action' => 'show',
                'id' => $id,
            ],
        ];
    }

    public function toJstree(array $data, SiteRepresentation $site)
    {
        static $privateResources = null;

        // Get all resource visibilities one time to avoid a looped query.
        if (is_null($privateResources)) {
            // Get only private resources: they are generally a small number in
            // a digital library. It avoids a too much big output.
            $privateResources = $site->getServiceLocator()->get('Omeka\Connection')
                ->executeQuery('SELECT `id`, 1 FROM `resource` WHERE `is_public` = 0;')
                ->fetchAllKeyValue();
        }

        return [
            'label' => $data['label'] ?? '',
            'id' => (int) $data['id'],
            'is_public' => empty($privateResources[$data['id']]),
        ];
    }
}
