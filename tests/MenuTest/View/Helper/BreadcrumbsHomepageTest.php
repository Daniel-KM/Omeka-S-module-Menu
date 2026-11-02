<?php declare(strict_types=1);

namespace MenuTest\View\Helper;

use Laminas\Router\Http\RouteMatch;
use MenuTest\MenuTestTrait;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * The breadcrumbs are skipped on the home page, reached by the route "site" or
 * by the url of the page, unless the option "homepage" is set.
 */
class BreadcrumbsHomepageTest extends AbstractHttpControllerTestCase
{
    use MenuTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        parent::tearDown();
    }

    protected function createPage(SiteRepresentation $site, string $slug): \Omeka\Api\Representation\SitePageRepresentation
    {
        return $this->api()->create('site_pages', [
            'o:site' => ['o:id' => $site->id()],
            'o:slug' => $slug,
            'o:title' => ucfirst($slug),
        ])->getContent();
    }

    /**
     * Render the breadcrumbs as for the specified route of the site.
     */
    protected function renderBreadcrumbs(SiteRepresentation $site, string $routeName, array $params = [], array $options = []): string
    {
        $services = $this->getServiceLocator();
        $services->get('Omeka\Settings\Site')->setTargetId($site->id());

        $routeMatch = new RouteMatch($params + ['site-slug' => $site->slug(), '__SITE__' => true]);
        $routeMatch->setMatchedRouteName($routeName);
        $services->get('Application')->getMvcEvent()->setRouteMatch($routeMatch);

        $view = $services->get('ViewHelperManager')->getRenderer();
        $view->site = $site;
        $helper = $services->get('ViewHelperManager')->get('breadcrumbs');
        $helper->setView($view);
        return $helper($options);
    }

    public function testBreadcrumbsAreSkippedOnHomepage(): void
    {
        $site = $this->createSite('test-breadcrumbs-home', 'Test breadcrumbs home');
        $home = $this->createPage($site, 'home-page');
        $other = $this->createPage($site, 'about');
        $site = $this->api()->update('sites', $site->id(), ['o:homepage' => ['o:id' => $home->id()]], [], ['isPartial' => true])->getContent();

        $this->assertSame('', $this->renderBreadcrumbs($site, 'site'));
        $this->assertSame('', $this->renderBreadcrumbs($site, 'site/page', ['page-slug' => $home->slug()]));
        $this->assertNotSame('', $this->renderBreadcrumbs($site, 'site/page', ['page-slug' => $other->slug()]));

        // The option "homepage" displays them on the home page too.
        $this->assertNotSame('', $this->renderBreadcrumbs($site, 'site/page', ['page-slug' => $home->slug()], ['homepage' => true]));
    }

    public function testFirstPageOfNavigationIsHomepageWithoutHomepage(): void
    {
        $site = $this->createSite('test-breadcrumbs-nav', 'Test breadcrumbs nav');
        $first = $this->createPage($site, 'first');
        $second = $this->createPage($site, 'second');
        $site = $this->api()->update('sites', $site->id(), [
            'o:navigation' => [
                ['type' => 'page', 'data' => ['label' => '', 'id' => $first->id()], 'links' => []],
                ['type' => 'page', 'data' => ['label' => '', 'id' => $second->id()], 'links' => []],
            ],
        ], [], ['isPartial' => true])->getContent();
        $this->assertNull($site->homepage());

        $this->assertSame('', $this->renderBreadcrumbs($site, 'site/page', ['page-slug' => $first->slug()]));
        $this->assertNotSame('', $this->renderBreadcrumbs($site, 'site/page', ['page-slug' => $second->slug()]));
    }
}
