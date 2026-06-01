<?php declare(strict_types=1);

namespace Menu\Site\Navigation\Breadcrumb;

use Laminas\Authentication\AuthenticationService;
use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\Navigation\Navigation;
use Laminas\Navigation\Page\Uri as UriPage;
use Laminas\Router\Http\RouteMatch;
use Laminas\View\Helper\Url as UrlHelper;
use Menu\Site\Navigation\Page\ResourcePage;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\ItemSetRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Settings\SiteSettings;

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
     * @var AuthenticationService|null
     */
    protected $auth;

    /**
     * @var SiteSettings|null
     */
    protected $siteSettings;

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
        UrlHelper $urlHelper,
        ?AuthenticationService $auth = null,
        ?SiteSettings $siteSettings = null
    ) {
        $this->api = $api;
        $this->translator = $translator;
        $this->urlHelper = $urlHelper;
        $this->auth = $auth;
        $this->siteSettings = $siteSettings;
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
        // Pages added before the resource hierarchy (home, prepend) go
        // into $pages. The resource/route hierarchy is collected in
        // $childPages, then attached as children of the last ancestor
        // page, or merged flat into $pages when there is no ancestor.
        $pages = [];
        $currentParentPage = null;

        // Home page.
        if ($options['home']) {
            $homePage = new UriPage([
                'label' => $translate->translate('Home'),
                'uri' => $site->siteUrl(),
            ]);
            $pages[] = $homePage;
            $currentParentPage = $homePage;
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
                }
            }
        }

        // Handle based on resource type.
        $childPages = [];
        if ($resource) {
            $this->buildResourceHierarchy($childPages, $resource, $site, $options);
        } elseif ($routeMatch) {
            $this->buildRouteHierarchy($childPages, $routeMatch, $site, $options);
        }

        // Sync the built hierarchy to the parent page.
        if ($currentParentPage) {
            foreach ($childPages as $page) {
                $currentParentPage->addPage($page);
            }
        } else {
            $pages = array_merge($pages, $childPages);
        }

        return new Navigation($pages);
    }

    /**
     * Build hierarchy for a resource (item, item set, media).
     *
     * Pages are chained as parent/child so that Laminas breadcrumbs renders the
     * full path from root to active page.
     */
    protected function buildResourceHierarchy(
        array &$parent,
        AbstractResourceEntityRepresentation $resource,
        SiteRepresentation $site,
        array $options
    ): void {
        // Resolve item & item-set context for items and media.
        $item = $resource instanceof MediaRepresentation
            ? $resource->item()
            : ($resource instanceof ItemRepresentation ? $resource : null);

        // Look up primary item set when any of collections/itemset/itemsetstree
        // is enabled in site settings. The lookup decides Collections vs Search
        // anchor when collections is enabled, even if itemset/itemsetstree are
        // off.
        $primaryItemSet = null;
        if ($item
            && ($options['collections'] || $options['itemset'] || $options['itemsetstree'])
        ) {
            $primaryItemSet = $options['itemsetstree']
                ? $this->getPrimaryItemSetFromTree($item, $site)
                : null;
            if (!$primaryItemSet) {
                $primaryItemSet = $this->getPrimaryItemSet($item, $site, $options);
            }
        }

        // Anchor: Collections (with item set) or Search (without), only when
        // collections is enabled in site settings.
        $anchor = null;
        if ($options['collections']) {
            if ($resource instanceof ItemSetRepresentation || $primaryItemSet) {
                $anchor = $this->addCollectionsPage($parent, $site, $options);
            } else {
                $anchor = $this->addSearchPage($parent, $site, $options);
            }
        }

        $cursor = $anchor;

        // Item set ancestors: only when itemsetstree is enabled.
        if ($options['itemsetstree']) {
            $itemSetForTree = $resource instanceof ItemSetRepresentation
                ? $resource
                : $primaryItemSet;
            if ($itemSetForTree) {
                $cursor = $this->chainItemSetAncestors(
                    $parent, $cursor, $itemSetForTree, $site
                );
            }
        }

        // Item set itself: only when itemset or itemsetstree is enabled.
        if ($primaryItemSet && ($options['itemset'] || $options['itemsetstree'])) {
            $itemSetPage = $this->createResourcePage($primaryItemSet, $site);
            $cursor = $this->chainAdd($parent, $cursor, $itemSetPage);
        }

        // Current resource.
        if ($options['current']) {
            if ($resource instanceof MediaRepresentation) {
                $itemPage = $this->createResourcePage($item, $site);
                $cursor = $this->chainAdd($parent, $cursor, $itemPage);
                $mediaPage = $this->createResourcePage($resource, $site);
                $mediaPage->setActive(true);
                $this->chainAdd($parent, $cursor, $mediaPage);
            } else {
                $resourcePage = $this->createResourcePage($resource, $site);
                $resourcePage->setActive(true);
                $this->chainAdd($parent, $cursor, $resourcePage);
            }
        }
    }

    /**
     * Add a page either as child of cursor or top-level of $parent. Returns the
     * new cursor (the page just added).
     */
    protected function chainAdd(array &$parent, $cursor, $page)
    {
        if ($cursor) {
            $cursor->addPage($page);
        } else {
            $parent[] = $page;
        }
        return $page;
    }

    /**
     * Chain item-set ancestors from cursor. Returns the last ancestor (or
     * cursor unchanged when no ancestor exists).
     */
    protected function chainItemSetAncestors(
        array &$parent,
        $cursor,
        ItemSetRepresentation $itemSet,
        SiteRepresentation $site
    ) {
        $ancestors = $this->getItemSetAncestors($itemSet, $site);
        foreach ($ancestors as $ancestor) {
            $page = $this->createResourcePage($ancestor, $site);
            $cursor = $this->chainAdd($parent, $cursor, $page);
        }
        return $cursor;
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
        $addPage = function ($page) use (&$parent, &$currentParentPage): void {
            if ($currentParentPage) {
                $currentParentPage->addPage($page);
            } else {
                $parent[] = $page;
            }
        };

        // Search pages declared by AdvancedSearch use route names with the
        // pattern "search-page-{slug}". Treat them as search browse.
        if (strpos((string) $matchedRoute, 'search-page-') === 0) {
            if ($options['current']) {
                $currentParentPage = $this->addSearchPage($parent, $site, $options);
                $currentParentPage->setActive(true);
            }
            return;
        }

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
                if ($itemSetId) {
                    try {
                        $itemSet = $this->api->read('item_sets', $itemSetId)->getContent();
                        if ($options['itemsetstree']) {
                            $cursor = $this->chainItemSetAncestors(
                                $parent, $currentParentPage, $itemSet, $site
                            );
                            if ($cursor !== $currentParentPage) {
                                $currentParentPage = $cursor;
                            }
                        }
                        if ($options['current']) {
                            $itemSetPage = $this->createResourcePage($itemSet, $site);
                            $itemSetPage->setActive(true);
                            $addPage($itemSetPage);
                        }
                    } catch (\Throwable $e) {
                        // Item set not found.
                    }
                }
                break;

            case 'site/guest':
            case 'site/guest/anonymous':
            case 'site/guest/guest':
                $action = $routeMatch->getParam('action');
                if ($action === 'update-account' || $action === 'update-email') {
                    $currentParentPage = $this->addAccountPage($parent, $site);
                    if ($options['current']) {
                        $page = new UriPage([
                            'label' => $translate->translate('My settings'), // @translate
                            'uri' => $url('site/guest/guest', [
                                'site-slug' => $siteSlug,
                                'action' => 'update-account',
                            ]),
                            'active' => true,
                        ]);
                        $addPage($page);
                    }
                } elseif ($options['current']) {
                    $page = $this->addAccountPage($parent, $site);
                    $page->setActive(true);
                }
                break;

            case 'site/selection':
            case 'site/selection-id':
                $isLogged = $this->isUserLogged();
                if ($isLogged) {
                    $currentParentPage = $this->addAccountPage($parent, $site);
                }
                if ($options['current']) {
                    $selectionsPage = $this->buildSelectionsPage($site, $isLogged);
                    $selectionsPage->setActive(true);
                    $addPage($selectionsPage);
                }
                break;

            case 'site/guest/selection':
            case 'site/guest/selection-id':
                $currentParentPage = $this->addAccountPage($parent, $site);
                if ($options['current']) {
                    $selectionsPage = $this->buildSelectionsPage($site, true);
                    $selectionsPage->setActive(true);
                    $addPage($selectionsPage);
                }
                break;

            case 'site/contribution':
            case 'site/contribution-id':
            case 'site/guest/contribution':
            case 'site/guest/contribution-id':
                if ($this->isUserLogged()) {
                    $currentParentPage = $this->addAccountPage($parent, $site);
                }
                if ($options['current']) {
                    $page = new UriPage([
                        'label' => $translate->translate('My contributions'), // @translate
                        'uri' => $url('site/guest/contribution', [
                            'site-slug' => $siteSlug,
                        ]),
                        'active' => true,
                    ]);
                    $addPage($page);
                }
                break;

            case 'site/search-history':
            case 'site/search-history-id':
            case 'site/guest/search-history':
                if ($this->isUserLogged()) {
                    $currentParentPage = $this->addAccountPage($parent, $site);
                }
                if ($options['current']) {
                    $page = new UriPage([
                        'label' => $translate->translate('My searches'), // @translate
                        'uri' => $url('site/guest/search-history', [
                            'site-slug' => $siteSlug,
                        ]),
                        'active' => true,
                    ]);
                    $addPage($page);
                }
                break;

            case 'site/subscription':
            case 'site/subscription-id':
                if ($this->isUserLogged()) {
                    $currentParentPage = $this->addAccountPage($parent, $site);
                }
                if ($options['current']) {
                    $page = new UriPage([
                        'label' => $translate->translate('My subscriptions'), // @translate
                        'uri' => $url('site/subscription', [
                            'site-slug' => $siteSlug,
                        ]),
                        'active' => true,
                    ]);
                    $addPage($page);
                }
                break;

            case 'site/page':
                $pageSlug = $routeMatch->getParam('page-slug');
                if ($pageSlug) {
                    // Walk the site navigation tree to find ancestors.
                    $path = $this->findNavigationPagePath(
                        $site, $pageSlug
                    );
                    if ($path) {
                        $lastIndex = count($path) - 1;
                        foreach ($path as $i => $crumb) {
                            $crumbPage = new UriPage([
                                'label' => $crumb['label'],
                                'uri' => $crumb['uri'],
                                'active' => $i === $lastIndex,
                            ]);
                            $addPage($crumbPage);
                            if ($i < $lastIndex) {
                                $currentParentPage = $crumbPage;
                            }
                        }
                    } elseif ($options['current']) {
                        // Page not in navigation: show just its title.
                        foreach ($site->pages() as $sp) {
                            if ($sp->slug() === $pageSlug) {
                                $pageCrumb = new UriPage([
                                    'label' => $sp->title(),
                                    'uri' => $url('site/page', [
                                        'site-slug' => $siteSlug,
                                        'page-slug' => $pageSlug,
                                    ]),
                                    'active' => true,
                                ]);
                                $addPage($pageCrumb);
                                break;
                            }
                        }
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
     * Add a Search page (default search config) to hierarchy.
     *
     * Falls back to the standard item browse when no search config is set.
     */
    protected function addSearchPage(array &$parent, SiteRepresentation $site, array $options): UriPage
    {
        $translate = $this->translator;
        $url = $this->urlHelper;
        $siteSlug = $site->slug();

        $searchUrl = null;
        if ($this->siteSettings) {
            try {
                $configId = (int) $this->siteSettings->get(
                    'advancedsearch_main_config', 0, $site->id()
                );
                if ($configId) {
                    $searchConfig = $this->api->read(
                        'search_configs', ['id' => $configId]
                    )->getContent();
                    $searchUrl = $url('search-page-' . $searchConfig->slug(), [
                        'site-slug' => $siteSlug,
                    ]);
                }
            } catch (\Throwable $e) {
                $searchUrl = null;
            }
        }
        if (!$searchUrl) {
            $searchUrl = $url('site/resource', [
                'site-slug' => $siteSlug,
                'controller' => 'item',
                'action' => 'browse',
            ]);
        }

        $searchPage = new UriPage([
            'label' => $translate->translate('Search'), // @translate
            'uri' => $searchUrl,
        ]);
        $parent[] = $searchPage;

        return $searchPage;
    }

    /**
     * Add a "My account" page to hierarchy.
     */
    protected function addAccountPage(array &$parent, SiteRepresentation $site): UriPage
    {
        $translate = $this->translator;
        $url = $this->urlHelper;
        $accountPage = new UriPage([
            'label' => $translate->translate('My account'), // @translate
            'uri' => $url('site/guest', ['site-slug' => $site->slug()]),
        ]);
        $parent[] = $accountPage;
        return $accountPage;
    }

    /**
     * Build the "My selections" page for the current visitor (logged or not).
     */
    protected function buildSelectionsPage(SiteRepresentation $site, bool $isLogged): UriPage
    {
        $translate = $this->translator;
        $url = $this->urlHelper;
        $siteSlug = $site->slug();
        $uri = $isLogged
            ? $url('site/guest/selection', [
                'site-slug' => $siteSlug,
                'action' => 'browse',
            ])
            : $url('site/selection', [
                'site-slug' => $siteSlug,
                'action' => 'browse',
            ]);
        return new UriPage([
            'label' => $translate->translate('My selections'), // @translate
            'uri' => $uri,
        ]);
    }

    /**
     * Whether a user is currently logged in.
     */
    protected function isUserLogged(): bool
    {
        return $this->auth ? $this->auth->hasIdentity() : false;
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

    /**
     * Find the path from root to a page in the site navigation tree.
     *
     * Walks the navigation recursively to find the target page by slug,
     * collecting ancestor crumbs along the way.
     *
     * @return array|null Array of ['label' => string, 'uri' => string]
     *   from root to target, or null if the page is not in the tree.
     */
    protected function findNavigationPagePath(
        SiteRepresentation $site,
        string $targetPageSlug
    ): ?array {
        $siteSlug = $site->slug();
        $url = $this->urlHelper;

        // Build page ID → slug/title map.
        $pageMap = [];
        foreach ($site->pages() as $p) {
            $pageMap[$p->id()] = [
                'slug' => $p->slug(),
                'title' => $p->title(),
            ];
        }

        $search = function (array $items) use (
            &$search, $targetPageSlug, $siteSlug, $url, $pageMap
        ): ?array {
            foreach ($items as $item) {
                $type = $item['type'] ?? '';
                $data = $item['data'] ?? [];

                // Check if this item is the target page.
                if ($type === 'page' && isset($data['id'])) {
                    $page = $pageMap[$data['id']] ?? null;
                    if ($page && $page['slug'] === $targetPageSlug) {
                        $label = ($data['label'] ?? '') !== ''
                            ? $data['label'] : $page['title'];
                        return [[
                            'label' => $label,
                            'uri' => $url('site/page', [
                                'site-slug' => $siteSlug,
                                'page-slug' => $page['slug'],
                            ]),
                        ]];
                    }
                }

                // Recurse into sub-links.
                if (!empty($item['links'])) {
                    $subPath = $search($item['links']);
                    if ($subPath !== null) {
                        $crumb = $this->navigationItemCrumb(
                            $item, $pageMap, $siteSlug
                        );
                        return $crumb
                            ? array_merge([$crumb], $subPath)
                            : $subPath;
                    }
                }
            }
            return null;
        };

        return $search($site->navigation());
    }

    /**
     * Get a breadcrumb entry for a navigation item (ancestor in the tree).
     *
     * @return array|null ['label' => string, 'uri' => string] or null.
     */
    protected function navigationItemCrumb(
        array $item,
        array $pageMap,
        string $siteSlug
    ): ?array {
        $type = $item['type'] ?? '';
        $data = $item['data'] ?? [];
        $url = $this->urlHelper;

        if ($type === 'page' && isset($data['id'])) {
            $page = $pageMap[$data['id']] ?? null;
            if (!$page) {
                return null;
            }
            $label = ($data['label'] ?? '') !== ''
                ? $data['label'] : $page['title'];
            return [
                'label' => $label,
                'uri' => $url('site/page', [
                    'site-slug' => $siteSlug,
                    'page-slug' => $page['slug'],
                ]),
            ];
        }

        if ($type === 'url') {
            $label = $data['label'] ?? '';
            return $label !== ''
                ? ['label' => $label, 'uri' => $data['url'] ?? '']
                : null;
        }

        // For other link types (searchingPage, structure, etc.),
        // use label if available.
        $label = $data['label'] ?? '';
        return $label !== '' ? ['label' => $label, 'uri' => ''] : null;
    }
}
