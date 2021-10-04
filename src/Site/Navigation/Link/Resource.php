<?php declare(strict_types=1);

namespace Menu\Site\Navigation\Link;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Site\Navigation\Link\LinkInterface;
use Omeka\Stdlib\ErrorStore;
use Omeka\Site\Navigation\Link\Fallback;

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
        if (isset($data['label']) && '' !== trim($data['label'])) {
            return $data['label'];
        }

        $services = $site->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        try {
            /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource */
            $resource = $api->read('resources', ['id' => $data['id']])->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            $translator = $services->get('MvcTranslator');
            return $translator->translate('[Unknown resource]'); // @translate
        }
        // TODO Use language of the site to select title?
        return $resource->displayTitle();
    }

    public function toZend(array $data, SiteRepresentation $site)
    {
        return $this->toLaminas($data, $site);
    }

    public function toLaminas(array $data, SiteRepresentation $site)
    {
        $id = $data['id'];
        $api = $site->getServiceLocator()->get('Omeka\ApiManager');
        try {
            /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource */
            $resource = $api->read('resources', ['id' => $id])->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            $fallback = new Fallback('resource');
            return $fallback->toZend($data, $site);
        }
        return [
            'route' => 'site/resource-id',
            'params' => [
                'site-slug' => $site->slug(),
                'controller' => $resource->getControllerName(),
                'id' => $id,
                'action' => 'show',
            ],
        ];
    }

    public function toJstree(array $data, SiteRepresentation $site)
    {
        return [
            'label' => $data['label'] ?? '',
            'id' => $data['id'],
        ];
    }
}
