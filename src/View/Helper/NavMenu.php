<?php declare(strict_types=1);

namespace Menu\View\Helper;

use Laminas\Navigation\Service\ConstructedNavigationFactory;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Helper\AbstractHelper;
use Menu\Mvc\Controller\Plugin\NavigationTranslator;
use Omeka\Api\Representation\SiteRepresentation;

class NavMenu extends AbstractHelper
{
    protected $template = 'common/menu';

    /**
     * @var \Menu\Mvc\Controller\Plugin\NavigationTranslator
     */
    protected $services;

    /**
     * @var \Menu\Mvc\Controller\Plugin\NavigationTranslator
     */
    protected $navigationTranslator;

    public function __construct(ServiceLocatorInterface $services, NavigationTranslator $navigationTranslator)
    {
        $this->services = $services;
        $this->navigationTranslator = $navigationTranslator;
    }

    /**
     * Render a menu.
     *
     * @var string $name Name of the menu.
     * @var array $options
     * - template (string, default: "common/menu"): template to use.
     * - site (SiteRepresentation, default: null): use a menu from another site.
     * - menu (array): use any arbitrary menu or sub-menu instead of the name.
     * - render (string): render as "menu" (default), "breadcrumbs", "prevnext",
     *   "prev", "next".
     * - activeUrl (null|array|string|bool) Set the active url.
     *   - null (default): use the Laminas mechanism (compare with route);
     *   - true: use current url;
     *   - false: no active page;
     *   - string: If a url is set (generally a relative one), it will be
     *     checked against the real url;
     *   - array: when an array with keys "type" and "data" is set, a quick
     *     check is done against the menu element.
     * - noNav (bool): don't prepare nav (for performance and manual build).
     *
     * Options for Laminas Navigation
     *
     * Specific options to render as "menu" (default):
     * - partial (string|null): template for the menu
     * - indent (string|int): indentation
     * - minDepth (int|null): min depth of the navigation
     * - maxDepth (int|null): max depth of the navigation
     * - maxDepthInactive (int|null): max depth of the inactive branches
     *   (partially implemented) It requires an active url, else use maxDepth.
     *   It should be lesser than maxDepth.
     * - ulClass (string, default: "navigation"): css class for ul element
     * - liActiveClass (string, default: "active"): css class for active li element
     * - onlyActiveBranch (bool): whether only active branch should be rendered
     * - renderParents (bool, default: true): whether parents should be rendered
     *   if only rendering active branch
     * - escapeLabels (bool): escape labels
     * - addClassToListItem (bool): add class to list item
     *
     * Specific options to render as "breadcrumbs":
     * - partial (string|null): template for the menu
     * - indent (string|int): indentation
     * - minDepth (int|null): min depth of the navigation
     * - separator (string): String to use between breadcrumbs (default: "&gt;").
     * - linkLast (bool): Set true to render last breadcrumb as a link instead
     *   of a label (default: false).
     *
     * Specific options to render as "prevnext", "prev", "next:
     * - partial (string|null): template for the menu
     *
     * Other options are passed to the template.
     *
     * Note that there are two optional keys to render the menu: "template" (for
     * the menu helper) and "partial" (for the specific menu type).
     * Furthermore, the "partial" is not overridden by the theme, so it should
     * be set to override the default one (exemple: `['partial' => 'common/breadcrumbs']`).
     *
     * @link https://docs.laminas.dev/laminas-navigation/helpers/menu
     * @link https://docs.laminas.dev/laminas-navigation/helpers/breadcrumbs
     */
    public function __invoke(?string $name = null, array $options = []): string
    {
        // Quick check menu name.
        if ($name) {
            $options['menu'] = $this->view->siteSetting('menu_menu:' . $name);
            if (!is_array($options['menu'])) {
                return '';
            }
        }

        $partial = $options['template'] ?? $this->template;
        unset($options['template']);

        $render = $options['render'] ?? null;
        switch ($render) {
            default:
            case '':
            case 'menu':
                $isMenu = true;
                $options += [
                    'site' => null,
                    'name' => $name,
                    'menu' => null,
                    'activeUrl' => null,
                    'noNav' => false,
                    'render' => null,
                    // Specific option of helper Menu.
                    'partial' => null,
                    'indent' => '',
                    'minDepth' => null,
                    'maxDepth' => null,
                    'maxDepthInactive' => null,
                    'ulClass' => 'navigation',
                    'liActiveClass' => 'active',
                    'onlyActiveBranch' => false,
                    'renderParents' => true,
                    'escapeLabels' => true,
                    'addClassToListItem' => false,
                ];
                break;
            case 'breadcrumbs':
                $options += [
                    'site' => null,
                    'name' => $name,
                    'menu' => null,
                    'activeUrl' => null,
                    'noNav' => false,
                    // Specific option of helper Menu.
                    'partial' => null,
                    'indent' => '',
                    'minDepth' => null,
                    'separator' => '&gt;',
                    'linkLast' => false,
                ];
                break;
            case 'prevnext':
            case 'prev':
            case 'next':
                $isPrevNext = true;
                $options += [
                    'site' => null,
                    'name' => $name,
                    'menu' => null,
                    'activeUrl' => null,
                    'noNav' => false,
                    // Specific option of helper Menu.
                    'partial' => null,
                ];
                break;
        }

        if (empty($options['site'])) {
            $options['site'] = $this->currentSite();
        }

        $options['name'] = $name;
        $options['options'] = $options;

        if (!empty($isMenu)) {
            // Max inactive depth cannot be greater than max depth, but don't fix
            // options in order to pass them to special templates.
            $maxDepth = $options['maxDepth'];
            $maxDepthInactive = $options['maxDepthInactive'];
            if (!is_null($maxDepthInactive)
                && !is_null($maxDepth)
                && $maxDepthInactive >= $maxDepth
            ) {
                $maxDepthInactive = null;
            } elseif (!is_null($maxDepthInactive)) {
                $maxDepthInactive = (int) $maxDepthInactive;
            }
            $optionsPublicNav = [
                'activeUrl' => $options['activeUrl'],
                'maxDepthInactive' => $maxDepthInactive,
            ];
        } else {
            unset($options['maxDepthInactive']);
            $optionsPublicNav = [
                'activeUrl' => $options['activeUrl'],
                'maxDepthInactive' => null,
            ];
        }

        if (isset($options['menu'])) {
            $options['nav'] = empty($options['noNav'])
                ? $this->publicNav($options['site'], $options['menu'], $optionsPublicNav)
                : null;
        } else {
            $options['menu'] = $options['site']->navigation();
            $options['nav'] = empty($options['noNav'])
                ? ($options['activeUrl']
                    ? $this->publicNav($options['site'], $options['menu'], $optionsPublicNav)
                    : $options['site']->publicNav()
                )
                : null;
        }

        if (!empty($isPrevNext)) {
            $options['prevnext'] = $this->prevNext($options);
        }

        return $partial !== $this->template && $this->view->resolver($partial)
            ? $this->view->partial($partial, $options)
            : $this->view->partial($this->template, $options);
    }

    /**
     * Get the navigation helper for public-side nav for this site
     *
     * Adapted from SiteRepresentation::publicNav().
     * @see \Omeka\Api\Representation\SiteRepresentation::publicNav()
     *
     * @todo Check if the translator should be skipped here, in particular to display title of resources.
     */
    protected function publicNav(SiteRepresentation $site, ?array $menu = null, array $options = []): \Laminas\View\Helper\Navigation
    {
        // Build a new Navigation helper so these changes don't leak around to other places,
        // then set it to always disable translation for any of its "child" helpers (menu,
        // breadcrumb, etc.)
        $helper = $this->view->getHelperPluginManager()->build('Navigation');
        $helper->getPluginManager()->addInitializer(function ($container, $plugin): void {
            $plugin->setTranslatorEnabled(false);
        });
        return $helper($this->getPublicNavContainer($site, $menu, $options));
    }

    /**
     * Get the navigation container for this site's public nav
     *
     * Adapted from SiteRepresentation::getPublicNavContainer().
     * @see \Omeka\Api\Representation\SiteRepresentation::getPublicNavContainer()
     */
    protected function getPublicNavContainer(SiteRepresentation $site, ?array $menu = null, array $options = []): \Laminas\Navigation\Navigation
    {
        $factory = new ConstructedNavigationFactory($this->navigationTranslator->toLaminas($site, $menu, $options));
        return $factory($this->services, '');
    }

    protected function prevNext(array $options): array
    {
        $prev = null;
        $next = null;
        $render = $options['render'];
        $getPrev = $render === 'prev' || $render === 'prevnext';
        $getNext = $render === 'next' || $render === 'prevnext';
        if ($options['nav']) {
            /** @var \Laminas\View\Helper\Navigation $nav */
            $nav = $options['nav'];
            $activePage = $nav->findActive($nav->getContainer());
            if ($activePage) {
                $prev = $getPrev ? $nav->links()->searchRelPrev($activePage['page']) : null;
                $next = $getNext ? $nav->links()->searchRelnext($activePage['page']) : null;
            }
        } elseif ($options['menu']) {
            // TODO Get prev/next from menu array.
        }
        return [
            'prev' => $prev,
            'next' => $next,
        ];
    }

    protected function currentSite(): ?\Omeka\Api\Representation\SiteRepresentation
    {
        return $this->view->site ?? $this->view->site = $this->view
            ->getHelperPluginManager()
            ->get('Laminas\View\Helper\ViewModel')
            ->getRoot()
            ->getVariable('site');
    }
}
