<?php declare(strict_types=1);

namespace Menu\Site\Navigation\Breadcrumb;

use Laminas\Navigation\Navigation;
use Laminas\Navigation\Page\Uri as UriPage;
use Laminas\Router\Http\RouteMatch;
use Menu\Site\Navigation\Page\ResourcePage;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\ItemSetRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\View\Helper\Url as UrlHelper;

/**
 * Builds a Laminas Navigation container for breadcrumbs.
 *
 * This builder creates a proper hierarchical Navigation container that
 * integrates with Laminas Navigation breadcrumbs helper, including:
 * - Proper Page objects with isActive() detection
 * - Acl integration
 * - Resource hierarchy (Media → Item → ItemSet → Collections → Home)
 */
class ContainerBuilder
{
    /**
     * @var ApiManager
     */
    protected $api;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var UrlHelper
     */
    protected $urlHelper;

    /**
     * @var array
     */
    protected $defaultOptions = [
        'home' => true,
        'collections' => true,
        'collections_url' => '',
        'itemset' => true,
        'itemsetstree' => true,
        'current' => true,
        'homepage' => false,
        'separator' => '',
        'prepend' => [],
        'property_itemset' => '',
    ];

    public function __construct(
        ApiManager $api,
        TranslatorInterface $translator,
        UrlHelper $urlHelper
    ) {
        $this->api = $api;
        $this->translator = $translator;
        $this->urlHelper = $urlHelper;
    }

    /**
     * Build a Navigation container for breadcrumbs.
     *
     * @param SiteRepresentation $site The current site
     * @param RouteMatch|null $routeMatch Current route match
     * @param AbstractResourceEntityRepresentation|null $resource Current resource (if any)
     * @param array $options Breadcrumb options
     * @return Navigation
     */
    public function build(
        SiteRepresentation $site,
        ?RouteMatch $routeMatch = null,
        ?AbstractResourceEntityRepresentation $resource = null,
        array $options = []
    ): Navigation {
        $options = array_merge($this->defaultOptions, $options);
        $siteSlug = $site->slug();
        $url = $this->urlHelper;
        $translate = $this->translator;

        // Build the hierarchy from root to current.
        // Track both pages array and current parent page object to properly
        // build nested structures.
        $pages = [];
        $currentParent = &$pages;
        $currentParentPage = null;

        // Home page.
        if ($options['home']) {
            $homePage = new UriPage([
                'label' => $translate->translate('Home'),
                'uri' => $site->siteUrl(),
            ]);
            $pages[] = $homePage;
            $currentParentPage = $homePage;
            $currentParent = [];
        }

        // Prepended links.
        if (!empty($options['prepend'])) {
            foreach ($options['prepend'] as $prepend) {
                if (isset($prepend['uri']) && isset($prepend['label'])) {
                    $prependPage = new UriPage([
                        'label' => $prepend['label'],
                        'uri' => $prepend['uri'],
                    ]);
                    if ($currentParentPage) {
                        $currentParentPage->addPage($prependPage);
                    } else {
                        $pages[] = $prependPage;
                    }
                    $currentParentPage = $prependPage;
                    $currentParent = [];
                }
            }
        }

        // Handle based on resource type.
        if ($resource) {
            $this->buildResourceHierarchy($currentParent, $resource, $site, $options);
        } elseif ($routeMatch) {
            $this->buildRouteHierarchy($currentParent, $routeMatch, $site, $options);
        }

        // Sync the built hierarchy to the parent page.
        if ($currentParentPage && $currentParent) {
            foreach ($currentParent as $page) {
                $currentParentPage->addPage($page);
            }
        }

        return new Navigation($pages);
    }

    /**
     * Build hierarchy for a resource (item, item set, media).
     */
    protected function buildResourceHierarchy(
        array &$parent,
        AbstractResourceEntityRepresentation $resource,
        SiteRepresentation $site,
        array $options
    ): void {
        // Track current parent page for proper nesting.
        $currentParentPage = null;

        // Helper to add a page to the hierarchy.
        $addPage = function ($page) use (&$parent, &$currentParentPage) {
            if ($currentParentPage) {
                $currentParentPage->addPage($page);
            } else {
                $parent[] = $page;
            }
        };

        // Determine resource type and build appropriate hierarchy.
        if ($resource instanceof MediaRepresentation) {
            $item = $resource->item();

            // Collections link.
            if ($options['collections']) {
                $currentParentPage = $this->addCollectionsPage($parent, $site, $options);
            }

            // Item sets.
            if ($options['itemset'] || $options['itemsetstree']) {
                $itemSetPage = $this->addItemSetHierarchy($parent, $item, $site, $options);
                if ($itemSetPage) {
                    $currentParentPage = $itemSetPage;
                }
            }

            // Parent item.
            $itemPage = $this->createResourcePage($item, $site);
            $addPage($itemPage);
            $currentParentPage = $itemPage;

            // Current media.
            if ($options['current']) {
                $mediaPage = $this->createResourcePage($resource, $site);
                $mediaPage->setActive(true);
                $addPage($mediaPage);
            }
        } elseif ($resource instanceof ItemRepresentation) {
            // Collections link.
            if ($options['collections']) {
                $currentParentPage = $this->addCollectionsPage($parent, $site, $options);
            }

            // Item sets.
            if ($options['itemset'] || $options['itemsetstree']) {
                $itemSetPage = $this->addItemSetHierarchy($parent, $resource, $site, $options);
                if ($itemSetPage) {
                    $currentParentPage = $itemSetPage;
                }
            }

            // Current item.
            if ($options['current']) {
                $itemPage = $this->createResourcePage($resource, $site);
                $itemPage->setActive(true);
                $addPage($itemPage);
            }
        } elseif ($resource instanceof ItemSetRepresentation) {
            // Collections link.
            if ($options['collections']) {
                $currentParentPage = $this->addCollectionsPage($parent, $site, $options);
            }

            // Item set tree (ancestors).
            if ($options['itemsetstree']) {
                $lastAncestor = $this->addItemSetTreeAncestors($parent, $resource, $site);
                if ($lastAncestor) {
                    $currentParentPage = $lastAncestor;
                }
            }

            // Current item set.
            if ($options['current']) {
                $itemSetPage = $this->createResourcePage($resource, $site);
                $itemSetPage->setActive(true);
                $addPage($itemSetPage);
            }
        }
    }

    /**
     * Build hierarchy based on route for non-resource pages.
     */
    protected function buildRouteHierarchy(
        array &$parent,
        RouteMatch $routeMatch,
        SiteRepresentation $site,
        array $options
    ): void {
        $matchedRoute = $routeMatch->getMatchedRouteName();
        $translate = $this->translator;
        $url = $this->urlHelper;
        $siteSlug = $site->slug();

        // Track current parent page for proper nesting.
        $currentParentPage = null;

        // Helper to add a page to the hierarchy.
        $addPage = function ($page) use (&$parent, &$currentParentPage) {
            if ($currentParentPage) {
                $currentParentPage->addPage($page);
            } else {
                $parent[] = $page;
            }
        };

        switch ($matchedRoute) {
            case 'site/resource':
                $controller = $routeMatch->getParam('controller', 'item');
                $action = $routeMatch->getParam('action', 'browse');

                if ($options['collections'] && $controller !== 'item-set') {
                    $currentParentPage = $this->addCollectionsPage($parent, $site, $options);
                }

                if ($options['current']) {
                    $label = $this->getControllerLabel($controller, $action);
                    $browsePage = new UriPage([
                        'label' => $translate->translate($label),
                        'uri' => $url('site/resource', [
                            'site-slug' => $siteSlug,
                            'controller' => $controller,
                            'action' => $action,
                        ]),
                        'active' => true,
                    ]);
                    $addPage($browsePage);
                }
                break;

            case 'site/item-set':
                if ($options['collections']) {
                    $currentParentPage = $this->addCollectionsPage($parent, $site, $options);
                }

                $itemSetId = $routeMatch->getParam('item-set-id');
                if ($itemSetId && $options['current']) {
                    try {
                        $itemSet = $this->api->read('item_sets', $itemSetId)->getContent();
                        $itemSetPage = $this->createResourcePage($itemSet, $site);
                        $itemSetPage->setActive(true);
                        $addPage($itemSetPage);
                    } catch (\Exception $e) {
                        // Item set not found.
                    }
                }
                break;

            default:
                // For other routes, just add a current page indicator.
                if ($options['current']) {
                    $currentPage = new UriPage([
                        'label' => $translate->translate('Current page'),
                        'uri' => '',
                        'active' => true,
                    ]);
                    $addPage($currentPage);
                }
                break;
        }
    }

    /**
     * Add collections page to hierarchy.
     *
     * @return UriPage The created collections page, so children can be added to it.
     */
    protected function addCollectionsPage(array &$parent, SiteRepresentation $site, array $options): UriPage
    {
        $translate = $this->translator;
        $url = $this->urlHelper;
        $siteSlug = $site->slug();

        $collectionsUrl = $options['collections_url'] ?? null;
        if (!$collectionsUrl) {
            $collectionsUrl = $url('site/resource', [
                'site-slug' => $siteSlug,
                'controller' => 'item-set',
                'action' => 'browse',
            ]);
        }

        $collectionsPage = new UriPage([
            'label' => $translate->translate('Collections'),
            'uri' => $collectionsUrl,
        ]);
        $parent[] = $collectionsPage;

        return $collectionsPage;
    }

    /**
     * Add item set hierarchy for an item.
     *
     * @return ResourcePage|null Last added page, so children can be added to
     * it.
     */
    protected function addItemSetHierarchy(
        array &$parent,
        ItemRepresentation $item,
        SiteRepresentation $site,
        array $options
    ): ?ResourcePage {
        if ($options['itemsetstree']) {
            // Try to use item sets tree if available.
            $itemSet = $this->getPrimaryItemSetFromTree($item, $site);
            if ($itemSet) {
                $lastPage = $this->addItemSetTreeAncestors($parent, $itemSet, $site);
                // Add the item set itself.
                $itemSetPage = $this->createResourcePage($itemSet, $site);
                if ($lastPage) {
                    $lastPage->addPage($itemSetPage);
                } else {
                    $parent[] = $itemSetPage;
                }
                return $itemSetPage;
            }
        }

        // Fall back to primary item set.
        if ($options['itemset']) {
            $itemSet = $this->getPrimaryItemSet($item, $site, $options);
            if ($itemSet) {
                $itemSetPage = $this->createResourcePage($itemSet, $site);
                $parent[] = $itemSetPage;
                return $itemSetPage;
            }
        }

        return null;
    }

    /**
     * Add item set tree ancestors.
     *
     * @return ResourcePage|null Last added page, so children can be added to
     * it.
     */
    protected function addItemSetTreeAncestors(
        array &$parent,
        ItemSetRepresentation $itemSet,
        SiteRepresentation $site
    ): ?ResourcePage {
        // Get ancestors from ItemSetsTree if available.
        $ancestors = $this->getItemSetAncestors($itemSet, $site);

        $lastPage = null;
        foreach ($ancestors as $ancestor) {
            $ancestorPage = $this->createResourcePage($ancestor, $site);
            if ($lastPage) {
                $lastPage->addPage($ancestorPage);
            } else {
                $parent[] = $ancestorPage;
            }
            $lastPage = $ancestorPage;
        }

        return $lastPage;
    }

    /**
     * Create a ResourcePage for an Omeka resource.
     */
    protected function createResourcePage(
        AbstractResourceEntityRepresentation $resource,
        SiteRepresentation $site
    ): ResourcePage {
        $page = new ResourcePage([
            'label' => (string) $resource->displayTitle(),
            'uri' => $resource->siteUrl($site->slug()),
        ]);
        $page->setOmekaResource($resource);
        return $page;
    }

    /**
     * Get the primary item set for an item.
     *
     * If property_itemset is set, use that property to determine the primary
     * item set. Otherwise, return the first item set.
     */
    protected function getPrimaryItemSet(
        ItemRepresentation $item,
        SiteRepresentation $site,
        array $options = []
    ): ?ItemSetRepresentation {
        // Check if a specific property defines the primary item set.
        $propertyItemSet = $options['property_itemset'] ?? '';
        if ($propertyItemSet) {
            $values = $item->value($propertyItemSet, ['all' => true]);
            foreach ($values as $value) {
                $valueResource = $value->valueResource();
                if ($valueResource instanceof ItemSetRepresentation) {
                    return $valueResource;
                }
            }
        }

        // Fall back to first item set.
        $itemSets = $item->itemSets();
        foreach ($itemSets as $itemSet) {
            return $itemSet;
        }
        return null;
    }

    /**
     * Get item set with most ancestors from tree (for better breadcrumb depth).
     */
    protected function getPrimaryItemSetFromTree(
        ItemRepresentation $item,
        SiteRepresentation $site
    ): ?ItemSetRepresentation {
        // This would integrate with ItemSetsTree module.
        // For now, fall back to primary.
        return $this->getPrimaryItemSet($item, $site);
    }

    /**
     * Get ancestors of an item set from ItemSetsTree.
     *
     * @return ItemSetRepresentation[]
     */
    protected function getItemSetAncestors(
        ItemSetRepresentation $itemSet,
        SiteRepresentation $site
    ): array {
        // This would integrate with ItemSetsTree module.
        // For now, return empty array.
        return [];
    }

    /**
     * Get label for a controller/action combination.
     */
    protected function getControllerLabel(string $controller, string $action): string
    {
        $labels = [
            'item-set' => 'Item sets',
            'item' => 'Items',
            'media' => 'Media',
        ];

        if ($action === 'search') {
            return 'Search';
        }

        return $labels[$controller] ?? 'Browse';
    }
}
