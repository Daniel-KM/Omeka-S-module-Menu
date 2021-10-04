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
     * - site (SiteRepresentation, default: null): use a menu from another site
     * - template (string, default: common/menu): template to use
     * - noNav (bool): don't prepare nav (for performance and manual build)
     *
     * Options for Laminas Navigation
     * - partial (string|null): template for the menu
     * - indent (string|int): indentation
     * - minDepth (int|null): min depth of the navigation
     * - maxDepth (int|null): max depth of the navigation
     * - ulClass (string, default: "navigation"): css class for ul element
     * - liActiveClass (string, default: "active"): css class for active li element
     * - onlyActiveBranch (bool): whether only active branch should be rendered
     * - renderParents (bool, default: true): whether parents should be rendered
     *   if only rendering active branch
     * - escapeLabels (bool): escape labels
     * - addClassToListItem (bool): add class to list item
     *
     * Other options are passed to the template
     *
     * @link https://docs.laminas.dev/laminas-navigation/helpers/menu
     */
    public function __invoke(?string $name = null, array $options = []): string
    {
        $partial = $options['template'] ?? $this->template;
        unset($options['template']);

        if (empty($options['site'])) {
            $options['site'] = $this->currentSite();
        }

        $options['name'] = $name;
        if ($name) {
            $menus = $this->view->siteSetting('menu_menus', []);
            if (!isset($menus[$name])) {
                return '';
            }
            $options['menu'] = $menus[$name];
            $options['nav'] = empty($options['noNav'])
                ? $this->publicNav($options['site'], $options['menu'])
                : null;
        } else {
            $options['menu'] = $options['site']->navigation();
            $options['nav'] = empty($options['noNav'])
                ? $options['site']->publicNav()
                : null;
        }

        $options += [
            'partial' => null,
            'indent' => '',
            'minDepth' => null,
            'maxDepth' => null,
            'ulClass' => 'navigation',
            'liActiveClass' => 'active',
            'onlyActiveBranch' => false,
            'renderParents' => true,
            'escapeLabels' => true,
            'addClassToListItems' => false,
        ];

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
    protected function publicNav(SiteRepresentation $site, array $menu): \Laminas\View\Helper\Navigation
    {
        // Build a new Navigation helper so these changes don't leak around to other places,
        // then set it to always disable translation for any of its "child" helpers (menu,
        // breadcrumb, etc.)
        $helper = $this->view->getHelperPluginManager()->build('Navigation');
        $helper->getPluginManager()->addInitializer(function ($container, $plugin) {
            $plugin->setTranslatorEnabled(false);
        });
        return $helper($this->getPublicNavContainer($site, $menu));
    }

    /**
     * Get the navigation container for this site's public nav
     *
     * Adapted from SiteRepresentation::getPublicNavContainer().
     * @see \Omeka\Api\Representation\SiteRepresentation::getPublicNavContainer()
     */
    protected function getPublicNavContainer(SiteRepresentation $site, array $menu): \Laminas\Navigation\Navigation
    {
        $factory = new ConstructedNavigationFactory($this->navigationTranslator->toLaminas($site, $menu));
        return $factory($this->services, '');
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
