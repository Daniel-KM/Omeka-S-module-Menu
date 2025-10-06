<?php declare(strict_types=1);

namespace Menu\Site\Navigation\Link;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Site\Navigation\Link\LinkInterface;
use Omeka\Stdlib\ErrorStore;

class Structure implements LinkInterface
{
    public function getName()
    {
        return 'Structure'; // @translate
    }

    public function getFormTemplate()
    {
        return 'common/navigation-link-form/structure';
    }

    public function isValid(array $data, ErrorStore $errorStore)
    {
        return true;
    }

    public function getLabel(array $data, SiteRepresentation $site)
    {
        // Default label is ` ` to avoid to be skipped by NavigationTranslator.
        return is_null($data['label']) || $data['label'] === ''
            ? ' '
            : $data['label'];
    }

    public function toZend(array $data, SiteRepresentation $site)
    {
        $result = [
            'type' => 'uri',
            // TODO The uri should be null, but it throws an exception with breadcrumbs (module BlockPlus) in new versions of php.
            'uri' => '',
        ];
        $class = $data['class'] ?? null;
        if ($class) {
            $result['class'] = $class;
        }
        return $result;
    }

    public function toJstree(array $data, SiteRepresentation $site)
    {
        return [
            'label' => $data['label'] ?? '',
            'is_public' => isset($data['is_public']) ? (bool) $data['is_public'] : true,
        ];
    }
}
