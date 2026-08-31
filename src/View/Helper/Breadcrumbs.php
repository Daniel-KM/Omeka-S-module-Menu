<?php declare(strict_types=1);

namespace Menu\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Menu\Site\Navigation\Breadcrumb\ContainerBuilder;

/**
 * Laminas-compliant breadcrumbs view helper.
 *
 * This helper uses a proper Laminas Navigation container built by ContainerBuilder,
 * then delegates rendering to the standard Laminas breadcrumbs navigation helper.
 *
 * Usage in templates:
 *   <?= $this->breadcrumbs() ?>
 *   <?= $this->breadcrumbs(['separator' => ' > ', 'home' => true]) ?>
 *
 * Options:
 *   - home: bool - Include home link (default: true)
 *   - collections: bool - Include collections link (default: true)
 *   - collections_url: string - Custom URL for collections
 *   - collections_label: string - Custom label for collections
 *   - itemset: bool - Include primary item set (default: true)
 *   - itemsetstree: bool - Include item set tree (default: true)
 *   - current: bool - Include current page/resource (default: true)
 *   - separator: string - Separator between crumbs (default: use CSS)
 *   - linkLast: bool - Render last crumb as link (default: false)
 *   - minDepth: int - Minimum depth to render (default: 0)
 *   - partial: string - Custom partial template
 *   - template: string - Alias for partial
 */
class Breadcrumbs extends AbstractHelper
{
    /**
     * @var ContainerBuilder
     */
    protected $containerBuilder;

    /**
     * Default template for rendering.
     *
     * @var string
     */
    protected $defaultTemplate = 'common/breadcrumbs';

    public function __construct(ContainerBuilder $containerBuilder)
    {
        $this->containerBuilder = $containerBuilder;
    }

    /**
     * Render breadcrumbs using Laminas Navigation.
     *
     * @param array $options Breadcrumb options
     * @return string HTML output
     */
    public function __invoke(array $options = []): string
    {
        $view = $this->getView();

        // Get current site
        $site = $this->currentSite();
        if (!$site) {
            return '';
        }

        // Get route match
        $routeMatch = $this->getRouteMatch();

        // Get current resource from view variables, or fall back to the route
        // match (CleanUrl sets controller + id after resolving the clean url).
        $resource = $view->resource
            ?? $view->item
            ?? $view->itemSet
            ?? $view->media
            ?? $view->annotation
            ?? null;
        if (!$resource && $routeMatch) {
            $resource = $this->resourceFromRouteMatch($routeMatch);
        }

        // Check homepage setting
        if (empty($options['homepage'])) {
            $matchedRoute = $routeMatch ? $routeMatch->getMatchedRouteName() : null;
            if ($matchedRoute === 'site' || $matchedRoute === 'top') {
                return '';
            }
        }

        // Merge with site settings
        $siteSetting = $view->plugin('siteSetting');
        $siteOptions = $this->getSiteSettings($siteSetting);
        $options = array_merge($siteOptions, $options);

        // Build the navigation container
        $container = $this->containerBuilder->build($site, $routeMatch, $resource, $options);
        $template = $options['template'] ?? $options['partial'] ?? $this->defaultTemplate;

        // Prepare all the data here so the partial only builds the markup.
        $links = $this->prepareLinks($container, $options);
        $separator = isset($options['separator']) && $options['separator'] !== ''
            ? $options['separator']
            : '/';
        $ariaLabel = !empty($options['aria_label'])
            ? $options['aria_label']
            : $view->plugin('translate')('Breadcrumb'); // @translate
        $schema = !empty($options['schema_org'])
            ? $this->buildSchemaOrg($links)
            : '';

        return $view->partial($template, [
            'site' => $site,
            'options' => $options,
            'links' => $links,
            'separator' => $separator,
            'ariaLabel' => $ariaLabel,
            'schema' => $schema,
            // Kept for backward compatibility with old themes overriding the
            // partial: the raw container and the flat crumb list.
            'breadcrumbs' => $container,
            'crumbs' => $this->buildFlatCrumbs($container),
        ]);
    }

    /**
     * Build the ordered list of crumbs (root to active leaf) from container.
     *
     * Returns plain data so the partial stays free of any navigation logic:
     * each crumb has "label", "title", "uri", "active", "no_link", "resource".
     */
    protected function prepareLinks($container, array $options): array
    {
        $view = $this->getView();
        $translate = $view->plugin('translate');

        $breadcrumbs = $view->navigation($container)->breadcrumbs();
        if (isset($options['minDepth'])) {
            $breadcrumbs->setMinDepth((int) $options['minDepth']);
        }

        $active = $breadcrumbs->findActive($container);
        if (!$active) {
            return [];
        }

        // Walk from the deepest active page up to the root (the container is
        // not a page and stops the loop).
        $chain = [];
        $page = $active['page'];
        while ($page instanceof \Laminas\Navigation\Page\AbstractPage) {
            array_unshift($chain, $page);
            $page = $page->getParent();
        }

        $links = [];
        $lastIndex = count($chain) - 1;
        foreach ($chain as $index => $page) {
            $uri = (string) $page->getHref();
            $links[] = [
                'label' => (string) $translate($page->getLabel(), $page->getTextDomain()),
                'title' => (string) $translate($page->getTitle(), $page->getTextDomain()),
                'uri' => $uri,
                'active' => $index === $lastIndex,
                'no_link' => $uri === '',
                'resource' => $page instanceof \Menu\Site\Navigation\Page\ResourcePage
                    ? $page->getOmekaResource()
                    : null,
            ];
        }

        return $links;
    }

    /**
     * Build schema.org BreadcrumbList JSON payload, without the tag script.
     */
    protected function buildSchemaOrg(array $links): string
    {
        if (!$links) {
            return '';
        }

        $serverUrl = $this->getView()->plugin('serverUrl');

        $items = [];
        $position = 1;
        foreach ($links as $link) {
            $entry = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $link['label'],
            ];
            if ($link['uri'] !== '') {
                $entry['item'] = strpos($link['uri'], 'http') === 0
                    ? $link['uri']
                    : $serverUrl($link['uri']);
            }
            $items[] = $entry;
        }

        $json = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return '';
        }

        // Escape closing tags to prevent a script breakout while keeping the
        // JSON valid for parsers.
        return str_replace('</', '<\/', $json);
    }

    /**
     * Build flat crumbs array from Navigation container for backward compatibility.
     *
     * Old themes expect $crumbs as array of ['label' => ..., 'uri' => ..., 'resource' => ...]
     */
    protected function buildFlatCrumbs($container): array
    {
        $crumbs = [];
        $iterator = new \RecursiveIteratorIterator(
            $container,
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $page) {
            $resource = null;
            if ($page instanceof \Menu\Site\Navigation\Page\ResourcePage) {
                $resource = $page->getOmekaResource();
            }

            $crumbs[] = [
                'label' => $page->getLabel(),
                'uri' => $page->getUri(),
                'resource' => $resource,
            ];
        }

        return $crumbs;
    }

    /**
     * Get breadcrumb settings from site settings.
     */
    protected function getSiteSettings($siteSetting): array
    {
        $crumbsSettings = $siteSetting('menu_breadcrumbs_crumbs', []);

        // Convert multicheckbox format to boolean options.
        // When not configured (empty array), default to home + current.
        if (is_array($crumbsSettings)) {
            if (empty($crumbsSettings)) {
                $crumbsSettings = ['home' => true, 'current' => true];
            } else {
                $crumbsSettings = array_fill_keys($crumbsSettings, true) + [
                    'home' => false,
                    'collections' => false,
                    'itemset' => false,
                    'itemsetstree' => false,
                    'current' => false,
                ];
            }
        }

        return [
            'home' => $crumbsSettings['home'] ?? true,
            'collections' => $crumbsSettings['collections'] ?? true,
            'itemset' => $crumbsSettings['itemset'] ?? true,
            'itemsetstree' => $crumbsSettings['itemsetstree'] ?? true,
            'current' => $crumbsSettings['current'] ?? true,
            'prepend' => $siteSetting('menu_breadcrumbs_prepend', []),
            'collections_url' => $siteSetting('menu_breadcrumbs_collections_url', ''),
            'collections_label' => $siteSetting('menu_breadcrumbs_collections_label', ''),
            'collections_item_set_property' => $siteSetting('menu_breadcrumbs_collections_item_set_property', ''),
            'collections_item_set_value' => $siteSetting('menu_breadcrumbs_collections_item_set_value', ''),
            'separator' => $siteSetting('menu_breadcrumbs_separator', ''),
            'homepage' => $siteSetting('menu_breadcrumbs_homepage', false),
            'property_itemset' => $siteSetting('menu_breadcrumbs_property_itemset', ''),
            'schema_org' => (bool) $siteSetting('menu_breadcrumbs_schema_org', true),
            'aria_label' => (string) $siteSetting('menu_breadcrumbs_aria_label', ''),
        ];
    }

    /**
     * Get the current site from the view.
     */
    protected function currentSite(): ?\Omeka\Api\Representation\SiteRepresentation
    {
        $view = $this->getView();
        return $view->site ?? $view->site = $view
            ->getHelperPluginManager()
            ->get('Laminas\View\Helper\ViewModel')
            ->getRoot()
            ->getVariable('site');
    }

    /**
     * Resolve resource representation from route match
     *
     * It is used when the breadcrumbs are rendered from the layout, where child
     * view variables are not propagated. CleanUrl and standard site/resource
     * route both set id + controller on the route match.
     */
    protected function resourceFromRouteMatch(\Laminas\Router\Http\RouteMatch $routeMatch)
    {
        $id = $routeMatch->getParam('id') ?? $routeMatch->getParam('item-set-id');
        if (!$id) {
            return null;
        }
        $map = [
            'item' => 'items',
            'item-set' => 'item_sets',
            'media' => 'media',
            'digital-object' => 'digital_objects',
            'annotation' => 'annotations',
            'Omeka\Controller\Site\Item' => 'items',
            'Omeka\Controller\Site\ItemSet' => 'item_sets',
            'Omeka\Controller\Site\Media' => 'media',
            'DigitalObject\Controller\Site\DigitalObject' => 'digital_objects',
            'Annotate\Controller\Site\Annotation' => 'annotations',
        ];
        $controller = $routeMatch->getParam('controller')
            ?? $routeMatch->getParam('__CONTROLLER__');
        $resourceName = $map[$controller] ?? null;
        if (!$resourceName && $routeMatch->getParam('item-set-id')) {
            $resourceName = 'item_sets';
            $id = $routeMatch->getParam('item-set-id');
        }
        if (!$resourceName) {
            return null;
        }
        try {
            $site = $this->currentSite();
            $api = $site->getServiceLocator()->get('Omeka\ApiManager');
            return $api->read($resourceName, ['id' => $id])->getContent();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get the current route match.
     */
    protected function getRouteMatch(): ?\Laminas\Router\Http\RouteMatch
    {
        $site = $this->currentSite();
        if (!$site) {
            return null;
        }

        return $site->getServiceLocator()
            ->get('Application')
            ->getMvcEvent()
            ->getRouteMatch();
    }
}
