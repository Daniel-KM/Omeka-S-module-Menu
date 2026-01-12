<?php declare(strict_types=1);

namespace Menu\Site\Navigation\Link;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Site\Navigation\Link\LinkInterface;
use Omeka\Stdlib\ErrorStore;

class Resource implements LinkInterface
{
    /**
     * @var array Cache of loaded resources to avoid duplicate api calls.
     */
    protected static $resourceCache = [];

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

        $id = (int) $data['id'];
        if (!$id) {
            $translator = $site->getServiceLocator()->get('MvcTranslator');
            return $translator->translate('[Unknown resource]'); // @translate
        }

        $resource = $this->fetchResource($id, $site);
        if ($resource === null) {
            $translator = $site->getServiceLocator()->get('MvcTranslator');
            return sprintf($translator->translate('[Unknown resource #%d]'), $id); // @translate
        }

        // TODO Use language of the site to select title?
        return $resource->displayTitle();
    }

    public function toZend(array $data, SiteRepresentation $site)
    {
        $id = (int) $data['id'];
        $resource = $this->fetchResource($id, $site);

        if ($resource === null) {
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
                'class' => 'resource',
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
            'class' => 'resource ' . $controllerName,
        ];
    }

    /**
     * Fetch a resource with caching to avoid duplicate api calls.
     *
     * @return \Omeka\Api\Representation\AbstractResourceEntityRepresentation|null
     */
    protected function fetchResource(int $id, SiteRepresentation $site)
    {
        if (!$id) {
            return null;
        }

        if (array_key_exists($id, self::$resourceCache)) {
            return self::$resourceCache[$id];
        }

        $api = $site->getServiceLocator()->get('Omeka\ApiManager');
        try {
            self::$resourceCache[$id] = $api->read('resources', ['id' => $id])->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            self::$resourceCache[$id] = null;
        }

        return self::$resourceCache[$id];
    }

    public function toJstree(array $data, SiteRepresentation $site)
    {
        static $privateResources = null;

        // Get all resource visibilities one time to avoid a looped query.
        if ($privateResources === null) {
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
