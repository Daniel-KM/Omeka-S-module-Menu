<?php declare(strict_types=1);

namespace Menu\Site\Navigation\Link;

use Omeka\Api\Representation\SiteRepresentation;

class Page extends \Omeka\Site\Navigation\Link\Page
{
    public function toJstree(array $data, SiteRepresentation $site)
    {
        static $privatePages = null;

        // Get all resource visibilities one time to avoid a looped query.
        if (is_null($privatePages)) {
            // Get only private resources: they are generally a small number in
            // a digital library. It avoids a too much big output.
            $privatePages = $site->getServiceLocator()->get('Omeka\Connection')
                ->executeQuery("SELECT `id`, 1 FROM `site_page` WHERE `site_id` = {$site->id()} AND `is_public` = 0;")
                ->fetchAllKeyValue();
        }

        return [
            'label' => $data['label'] ?? '',
            'id' => (int) $data['id'],
            'is_public' => empty($privatePages[$data['id']]),
        ];
    }
}
