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

        // Get current resource from view variables
        $resource = $view->resource ?? $view->item ?? $view->itemSet ?? $view->media ?? null;

        // Get route match
        $routeMatch = $this->getRouteMatch();

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

        // Use partial template if specified
        $template = $options['template'] ?? $options['partial'] ?? null;
        if ($template) {
            return $this->renderWithPartial($container, $options, $template);
        }

        // Use standard Laminas breadcrumbs rendering
        return $this->renderStandard($container, $options);
    }

    /**
     * Render using standard Laminas breadcrumbs helper.
     */
    protected function renderStandard($container, array $options): string
    {
        $view = $this->getView();

        // Get the navigation breadcrumbs helper
        $navHelper = $view->navigation($container);
        $breadcrumbs = $navHelper->breadcrumbs();

        // Configure the helper
        if (isset($options['separator'])) {
            $breadcrumbs->setSeparator(' ' . $options['separator'] . ' ');
        }

        if (isset($options['linkLast'])) {
            $breadcrumbs->setLinkLast((bool) $options['linkLast']);
        }

        if (isset($options['minDepth'])) {
            $breadcrumbs->setMinDepth((int) $options['minDepth']);
        }

        // Render
        $html = $breadcrumbs->render();

        // Wrap in semantic HTML
        if ($html) {
            $translate = $view->plugin('translate');
            $escapeAttr = $view->plugin('escapeHtmlAttr');
            $html = sprintf(
                '<div class="breadcrumbs-parent"><nav id="breadcrumb" class="breadcrumbs" aria-label="%s">%s</nav></div>',
                $escapeAttr($translate('Breadcrumb')),
                $html
            );
        }

        return $html;
    }

    /**
     * Render using a partial template.
     */
    protected function renderWithPartial($container, array $options, string $template): string
    {
        $view = $this->getView();
        $site = $this->currentSite();

        // Build flat crumbs array for backward compatibility with old themes.
        $crumbs = $this->buildFlatCrumbs($container);

        return $view->partial($template, [
            'site' => $site,
            'breadcrumbs' => $container,
            'options' => $options,
            // Keep the crumbs for compatibility with old themes.
            'crumbs' => $crumbs,
        ]);
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

        // Convert multicheckbox format to boolean options
        if (is_array($crumbsSettings)) {
            $crumbsSettings = array_fill_keys($crumbsSettings, true) + [
                'home' => false,
                'collections' => false,
                'itemset' => false,
                'itemsetstree' => false,
                'current' => false,
            ];
        }

        return [
            'home' => $crumbsSettings['home'] ?? true,
            'collections' => $crumbsSettings['collections'] ?? true,
            'itemset' => $crumbsSettings['itemset'] ?? true,
            'itemsetstree' => $crumbsSettings['itemsetstree'] ?? true,
            'current' => $crumbsSettings['current'] ?? true,
            'prepend' => $siteSetting('menu_breadcrumbs_prepend', []),
            'collections_url' => $siteSetting('menu_breadcrumbs_collections_url', ''),
            'separator' => $siteSetting('menu_breadcrumbs_separator', ''),
            'homepage' => $siteSetting('menu_breadcrumbs_homepage', false),
            'property_itemset' => $siteSetting('menu_breadcrumbs_property_itemset', ''),
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
