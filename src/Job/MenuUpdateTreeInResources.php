<?php declare(strict_types=1);

namespace Menu\Job;

use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\ResourceTemplateRepresentation;
use Omeka\Job\AbstractJob;
use Omeka\Stdlib\Message;

class MenuUpdateTreeInResources extends AbstractJob
{
    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var array
     */
    protected $properties;

    /**
     * @var int
     */
    protected $totalMenuElements = 0;

    /**
     * @var int
     */
    protected $totalResources = 0;

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
        $this->connection = $services->get('Omeka\Connection');
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

        $updateResources = $settings->get('menu_update_resources');
        if (!in_array($updateResources, ['yes', 'template_intersect', 'template_properties'])) {
            $this->logger->notice(new Message(
                'The settings does not require to update resources.' // @translate
            ));
            return ;
        }

        $onlyTemplates = $settings->get('menu_update_templates') ?: [];

        $broaderTerms = $settings->get('menu_properties_broader') ?: [];
        $narrowerTerms = $settings->get('menu_properties_narrower') ?: [];

        if ($updateResources === 'template_properties') {
            $broaders = [];
            $narrowers = [];
        } else {
            if ($updateResources !== 'template_properties' && !$broaderTerms && !$narrowerTerms) {
                $this->logger->notice(new Message(
                    'No relations to create: settings "broader" and "narrower" are no defined.' // @translate
                ));
                return;
            }

            $propertyIds = $this->getPropertyIds();

            $broaders = array_intersect_key($propertyIds, array_flip($broaderTerms));
            $narrowers = array_intersect_key($propertyIds, array_flip($narrowerTerms));

            if (($broaderTerms && !$broaders) || ($narrowerTerms && !$narrowers)) {
                $this->logger->err(new Message(
                    'Settings for "broader" or "narrower" are not correct.' // @translate
                ));
                return;
            }
        }

        // Use a recursive method, since the menu is an array and array_walk
        // cannot be used.
        $this->totalMenuElements = $this->countMenuElements($menu);
        $this->totalResources = $this->countMenuElements($menu, ['resource']);
        $this->totalProcessed = 0;
        $this->totalUpdated = 0;
        $this->totalError = 0;
        $updateResourceFromMenu = null;
        $updateResourceFromMenu = function (array $links, ?int $parentResourceId = null)
            use (&$updateResourceFromMenu, $broaders, $narrowers, $updateResources, $onlyTemplates): void {
            foreach ($links as $link) {
                /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource */
                $resource = null;
                $resourceId = null;
                if ($link['type'] === 'resource') {
                    $resource = $this->getResourceFromId($link['data']['id'] ?? null);
                    if (!$resource) {
                        $this->logger->warn(new Message(
                            'Resource #%1$d does not exist.', // @translate
                            $link['data']['id'] ?? 0
                        ));
                        ++$this->totalError;
                        continue;
                    }

                    ++$this->totalProcessed;

                    $resourceId = $resource->id();
                    $template = $resource->resourceTemplate();
                    if ($updateResources !== 'yes' && !$template) {
                        $this->logger->warn(new Message(
                            'Resource #%1$d has no template and cannot be updated.', // @translate
                            $resourceId
                        ));
                        continue;
                    }

                    if (!empty($onlyTemplates) && !in_array($template, $onlyTemplates)) {
                        $this->logger->warn(new Message(
                            'Resource #%1$d has a template not in the limited list of templates.', // @translate
                            $resourceId
                        ));
                        continue;
                    }

                    if ($updateResources === 'template_properties') {
                        $broaderProperties = $this->getPropertiesFromTemplate($template, 'menu_broader');
                        $narrowerProperties = $this->getPropertiesFromTemplate($template, 'menu_narrower');
                    } elseif ($updateResources === 'template_intersect') {
                        $broaderProperties = $this->filterPropertiesWithTemplate($broaders, $template);
                        $narrowerProperties = $this->filterPropertiesWithTemplate($narrowers, $template);
                    } else {
                        $broaderProperties = $broaders;
                        $narrowerProperties = $narrowers;
                    }
                    if (!$broaderProperties && !$narrowerProperties) {
                        $this->logger->warn(new Message(
                            'The template #%1$d (%2$s) has no properties to update.', // @translate
                            $template->id(), $template->label()
                        ));
                        continue;
                    }

                    // Update requires to pass all values, so json decode it.
                    $meta = json_decode(json_encode($resource), true);
                    $toUpdate = false;

                    if ($broaderProperties) {
                        $meta = $this->appendLinkedResourceToValues($resource, $meta, $broaderProperties, $parentResourceId, $toUpdate);
                    }

                    if ($narrowerProperties) {
                        foreach ($link['links'] as $subLink) {
                            if ($subLink['type'] === 'resource') {
                                /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $subResource */
                                $subResource = $this->getResourceFromId($subLink['data']['id'] ?? null);
                                if (!$subResource) {
                                    // The relation is already removed in resource.
                                    // Message will be appended on next loop.
                                    continue;
                                }
                                $isToUpdate = false;
                                $meta = $this->appendLinkedResourceToValues($resource, $meta, $narrowerProperties, $subResource->id(), $isToUpdate);
                                $toUpdate = $toUpdate || $isToUpdate;
                            }
                        }
                    }

                    if ($toUpdate) {
                        $this->api->update($resource->resourceName(), $resource->id(), $meta, [], ['isPartial' => false]);
                        ++$this->totalUpdated;
                    }
                }
            }

            // Process sub-links in all cases to update all resources.
            foreach ($links as $link) {
                if (!empty($link['links'])) {
                    $updateResourceFromMenu($link['links'], $resourceId);
                }
            }
        };

        $updateResourceFromMenu($menu);

        $this->logger->info(new Message(
            'End of the job: %1$d menu elements, %2$d resources, %3$d/%4$d resources updated, %5$d errors.', // @translate
            $this->totalMenuElements, $this->totalResources, $this->totalUpdated, $this->totalProcessed, $this->totalError
        ));
    }

    /**
     * Count the number of elements of a menu.
     */
    protected function countMenuElements(array $menu, array $onlyTypes = []): int
    {
        $total = 0;
        $countElements = null;
        $countElements = function (array $links) use (&$countElements, &$total, $onlyTypes): void {
            foreach ($links as $link) {
                if ($onlyTypes) {
                    if (in_array($link['type'] ?? null, $onlyTypes)) {
                        ++$total;
                    }
                } else {
                    ++$total;
                }
                if (!empty($link['links'])) {
                    $countElements($link['links']);
                }
            }
        };
        $countElements($menu);
        return $total;
    }

    /**
     * Get the resource from an id.
     *
     * Avoid the exception when the resource id does no exists.
     */
    protected function getResourceFromId($resourceId): ?AbstractResourceEntityRepresentation
    {
        $resourceId = (int) $resourceId;
        if (!$resourceId) {
            return null;
        }
        try {
            return $this->api->read('resources', ['id' => $resourceId])->getContent();
        } catch (NotFoundException $e) {
            return null;
        }
    }

    /**
     * Append a linked resource to resource values if needed.
     */
    protected function appendLinkedResourceToValues(
        AbstractResourceEntityRepresentation $resource,
        array $values,
        array $properties,
        ?int $linkedResourceId,
        bool &$isToUpdate
    ): array {
        $isToUpdate = false;
        if (!count($properties) || !$linkedResourceId) {
            return $values;
        }
        foreach ($properties as $propertyTerm => $propertyId) {
            if (empty($values[$propertyTerm]) || !$this->isValuePresent($values[$propertyTerm], $linkedResourceId)) {
                $isToUpdate = true;
                // TODO Ideally, when the datatype is "resource:xxx", it should be checked against the resource, but this is "resource:item" most of the times and identified in template else.
                $dataType = $this->dataTypeForPropertyOfResource($resource, $propertyId)
                    ?? $this->dataTypeResourceId($linkedResourceId)
                    ?? 'resource';
                $values[$propertyTerm][] = [
                    'property_id' => $propertyId,
                    'type' => $dataType,
                    'value_resource_id' => $linkedResourceId,
                ];
            }
        }
        return $values;
    }

    /**
     * Get only properties present in a resource template, if any.
     */
    protected function filterPropertiesWithTemplate(array $properties, ?ResourceTemplateRepresentation $template): array
    {
        if (!$template) {
            return [];
        }
        foreach ($properties as $propertyTerm => $propertyId) {
            if (!$template->resourceTemplateProperty($propertyId)) {
                unset($properties[$propertyTerm]);
            }
        }
        return $properties;
    }

    protected function getPropertiesFromTemplate(ResourceTemplateRepresentation $template, $setting): array
    {
        static $templatePropeties = [];

        if (!$template instanceof \AdvancedResourceTemplate\Api\Representation\ResourceTemplateRepresentation) {
            return [];
        }

        $templateId = $template->id();
        if (isset($templatePropeties[$templateId][$setting])) {
            return $templatePropeties[$templateId][$setting];
        }

        $result = [];
        foreach ($template->resourceTemplateProperties() as $rtp) {
            if ($rtp->mainDataValue($setting)) {
                $property = $rtp->property();
                $result[$property->term()] = $property->id();
            }
        }

        $templatePropeties[$templateId][$setting] = $result;
        return $result;
    }

    /**
     * Check if a linked resource is present in a list of values.
     */
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
     * Get the resource data type name of a resource.
     */
    protected function dataTypeResourceId(?int $resourceId): ?string
    {
        if (empty($resourceId)) {
            return null;
        }

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

    /**
     * Get all property ids by term.
     *
     * @return array Associative array of ids by term.
     */
    protected function getPropertyIds(): array
    {
        if (isset($this->properties)) {
            return $this->properties;
        }

        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select(
                'DISTINCT CONCAT(vocabulary.prefix, ":", property.local_name) AS term',
                'property.id AS id',
                // Only the two first selects are needed, but some databases
                // require "order by" or "group by" value to be in the select.
                'vocabulary.id'
            )
            ->from('property', 'property')
            ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id')
            ->orderBy('vocabulary.id', 'asc')
            ->addOrderBy('property.id', 'asc')
            ->addGroupBy('property.id')
        ;
        $this->properties = array_map('intval', $this->connection->executeQuery($qb)->fetchAllKeyValue());
        return $this->properties;
    }
}
