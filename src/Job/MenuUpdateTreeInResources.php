<?php declare(strict_types=1);

namespace Menu\Job;

use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Job\AbstractJob;
use Omeka\Stdlib\Message;

class MenuUpdateTreeInResources extends AbstractJob
{
    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var int
     */
    protected $totalProcessed = 0;

    /**
     * @var int
     */
    protected $totalUpdated = 0;

    /**
     * @var int
     */
    protected $totalError = 0;

    public function perform(): void
    {
        /**
         * @var \Laminas\Log\Logger $logger
         * @var \Omeka\Api\Manager $api
         * @var \Doctrine\ORM\EntityManager $entityManager
         */
        $services = $this->getServiceLocator();
        $this->api = $services->get('Omeka\ApiManager');
        $this->logger = $services->get('Omeka\Logger');
        $settings = $services->get('Omeka\Settings');
        $siteSettings = $services->get('Omeka\Settings\Site');

        $siteId = $this->getArg('siteId');
        $menuName = $this->getArg('menu');
        $siteSettings->setTargetId($siteId);

        $menu = $siteSettings->get('menu_menu:' . $menuName);
        if (!is_array($menu)) {
            $this->logger->err(new Message(
                'No menu exists with name "%1$s" in site #%2$s.', // @translate
                $menuName, $siteId
            ));
            return;
        }

        $broaderTerm = $settings->get('menu_property_broader');
        $narrowerTerm = $settings->get('menu_property_narrower');

        if (!$broaderTerm && !$narrowerTerm) {
            $this->logger->notice(new Message(
                'No relations to create: settings is part of and has part are no defined.' // @translate
            ));
            return;
        }

        if ($broaderTerm) {
            $broader = $this->api->search('properties', ['term' => $broaderTerm])->getContent();
            $broader = reset($broader);
        }
        if ($narrowerTerm) {
            $narrower = $this->api->search('properties', ['term' => $narrowerTerm])->getContent();
            $narrower = reset($narrower);
        }

        if (($broaderTerm && !$broader) || ($narrowerTerm && !$narrower)) {
            $this->logger->err(new Message(
                'Settings for is part of or has part are not correct.' // @translate
            ));
            return;
        }

        $broaderId = $broader ? $broader->id() : null;
        $narrowerId = $narrower ? $narrower->id() : null;

        // Use a recursive method, since the menu is an array and array_walk
        // cannot be used.
        $this->totalProcessed = 0;
        $this->totalUpdated = 0;
        $this->totalError = 0;
        $updateResourceFromMenu = null;
        $updateResourceFromMenu = function (array $links, ?int $parentResourceId = null)
            use (&$updateResourceFromMenu, $broaderTerm, $narrowerTerm, $broaderId, $narrowerId)
        : void {
            foreach ($links as $link) {
                /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource */
                $resource = null;
                $resourceId = null;
                if ($link['type'] === 'resource') {
                    $resourceId = empty($link['data']['id']) ? null : (int) $link['data']['id'];
                    try {
                        $resource = $resourceId ? $this->api->read('resources', ['id' => $resourceId])->getContent() : null;
                    } catch (NotFoundException $e) {
                        // Nothing here.
                    }
                    if (!$resource) {
                        $this->logger->warn(new Message(
                            'Resource #%1$d does not exist.', // @translate
                            $resourceId
                        ));
                        ++$this->totalError;
                        continue;
                    }

                    // Update requires to pass all values, so json decode it.
                    $toUpdate = false;
                    $meta = json_decode(json_encode($resource), true);
                    if ($broaderTerm && $parentResourceId) {
                        if (empty($meta[$broaderTerm]) || !$this->isValuePresent($meta[$broaderTerm], $parentResourceId)) {
                            $toUpdate = true;
                            // TODO Ideally, when the datatype is "resource:xxx", it should be checked against the resource, but this is "resource:item" most of the times and identified in template else.
                            $dataType = $this->dataTypeForPropertyOfResource($resource, $broaderId)
                                ?? $this->dataTypeResourceId($parentResourceId)
                                ?? 'resource';
                            $meta[$broaderTerm][] = [
                                'property_id' => $broaderId,
                                'type' => $dataType,
                                'value_resource_id' => $parentResourceId,
                            ];
                        }
                    }
                    if ($narrowerTerm) {
                        foreach ($link['links'] as $subLink) {
                            if ($subLink['type'] === 'resource') {
                                // The check is not required, but for info and
                                // the reesource is loaded next anyway.
                                /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource */
                                $subResource = null;
                                $subResourceId = empty($subLink['data']['id']) ? null : (int) $subLink['data']['id'];
                                try {
                                    $subResource = $subResourceId ? $this->api->read('resources', ['id' => $subResourceId])->getContent() : null;
                                } catch (NotFoundException $e) {
                                    // The relation is already removed in resource.
                                    // Message will be appended below.
                                    continue;
                                }
                                if (!$subResource) {
                                    continue;
                                }
                                if (empty($meta[$narrowerTerm]) || !$this->isValuePresent($meta[$narrowerTerm], $subResourceId)) {
                                    $toUpdate = true;
                                    $dataType = $this->dataTypeForPropertyOfResource($resource, $narrowerId)
                                        ?? $this->dataTypeResourceId($subResourceId)
                                        ?? 'resource';
                                    $meta[$narrowerTerm][] = [
                                        'property_id' => $narrowerId,
                                        'type' => $dataType,
                                        'value_resource_id' => $subResourceId,
                                    ];
                                }
                            }
                        }
                    }
                    if ($toUpdate) {
                        $this->api->update($resource->resourceName(), $resource->id(), $meta, [], ['isPartial' => false]);
                        ++$this->totalUpdated;
                    }
                    ++$this->totalProcessed;
                }
                if ($link['links']) {
                    $updateResourceFromMenu($link['links'], $resourceId);
                }
            }
        };

        $updateResourceFromMenu($menu);

        $this->logger->info(new Message(
            'End of the job: %1$d resources processed, %2$d updated, %3$d errors.', // @translate
            $this->totalProcessed, $this->totalUpdated, $this->totalError
        ));
    }

    protected function isValuePresent(array $values, int $resourceId): bool
    {
        foreach ($values as $value) {
            if (!empty($value['value_resource_id']) && (int) $value['value_resource_id'] === $resourceId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the data type for a linked resource of a resource property via template.
     */
    protected function dataTypeForPropertyOfResource(AbstractResourceEntityRepresentation $resource, int $propertyId): ?string
    {
        $template = $resource->resourceTemplate();
        if (!$template) {
            return null;
        }

        /** @var \Omeka\Api\Representation\ResourceTemplatePropertyRepresentation $templateProperty */
        $templateProperty = $template->resourceTemplateProperty($propertyId);
        if (!$templateProperty) {
            return null;
        }

        $resourceDataTypes = $this->resourceDataTypes();
        $tpdt = $templateProperty->dataTypes();
        $intersect = array_intersect($resourceDataTypes, $tpdt);

        // The specific data type for "resource" should be checked, but it
        // cannot be done here, because it depends on linked resource.
        return $intersect ? reset($intersect) : null;
    }

    /**
     * Get the resource data type name of the resource.
     */
    protected function dataTypeResourceId(int $resourceId): ?string
    {
        try {
            $resource = $this->api->read('resources', ['id' => $resourceId])->getContent();
        } catch (NotFoundException$e) {
            return null;
        }

        $resourceNames = [
            'items' => 'resource:item',
            'item_sets' => 'resource:itemset',
            'media' => 'resource:media',
            'annotations' => 'resource:annotation',
        ];

        return $resourceNames[$resource->resourceName()] ?? 'resource';
    }

    /**
     * List all datatypes whose main type is resource, ordered by most specific.
     *
     * @see \BulkEdit\View\Helper\MainDataType
     * @see \BulkEdit\Service\ViewHelper\CustomVocabBaseTypeFactory
     *
     * @todo Use \BulkEdit\View\Helper\MainDataType when available.
     */
    protected function resourceDataTypes(): array
    {
        static $resourceDataTypes;

        if (!is_null($resourceDataTypes)) {
            return $resourceDataTypes;
        }

        $resourceDataTypes = [
            'resource:annotation',
            'resource:media',
            'resource:item',
            'resource:itemset',
            'resource',
        ];

        $customVocabResources = [];
        $hasCustomVocab = ($module = $this->getServiceLocator()->get('Omeka\ModuleManager')->getModule('CustomVocab'))
            && $module->getState() === \Omeka\Module\Manager::STATE_ACTIVE;
        if (!$hasCustomVocab) {
            return $resourceDataTypes;
        }

        $sql = <<<'SQL'
SELECT CONCAT("customvocab:", `id`)
FROM `custom_vocab`
WHERE `item_set_id` IS NOT NULL
ORDER BY `id`;
SQL;
        $customVocabResources = $this->connection->executeQuery($sql)->fetchFirstColumn() ?: [];

        // The custom vocabs are more specific, so list them first.
        $resourceDataTypes = array_merge($customVocabResources, $resourceDataTypes);

        return $resourceDataTypes;
    }
}
