<?php declare(strict_types=1);

namespace MenuTest\Site\Navigation\Breadcrumb;

use Laminas\Router\Http\RouteMatch;
use Menu\Site\Navigation\Breadcrumb\ContainerBuilder;
use MenuTest\MenuTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

class ContainerBuilderTest extends AbstractHttpControllerTestCase
{
    use MenuTestTrait;

    protected array $createdResources = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        foreach (array_reverse($this->createdResources) as [$resourceName, $id]) {
            try {
                $this->api()->delete($resourceName, $id);
            } catch (\Exception $e) {
                // Ignore errors during cleanup.
            }
        }
        $this->createdResources = [];
        $this->cleanupResources();
        parent::tearDown();
    }

    protected function builder(): ContainerBuilder
    {
        return $this->getServiceLocator()->get('Menu\Site\Navigation\Breadcrumb\ContainerBuilder');
    }

    protected function create(string $resourceName, array $data)
    {
        $resource = $this->api()->create($resourceName, $data)->getContent();
        $this->createdResources[] = [$resourceName, $resource->id()];
        return $resource;
    }

    /**
     * Get the label and the uri of the active page of the container.
     */
    protected function activePage($container): ?array
    {
        $iterator = new \RecursiveIteratorIterator($container, \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $page) {
            if ($page->isActive()) {
                return [$page->getLabel(), $page->getHref()];
            }
        }
        return null;
    }

    public function browseRouteProvider(): array
    {
        return [
            // After dispatch, the param "controller" is the full class.
            'dispatched items' => [['controller' => 'Omeka\Controller\Site\Item', '__CONTROLLER__' => 'item'], 'Items', '/item'],
            'dispatched item sets' => [['controller' => 'Omeka\Controller\Site\ItemSet', '__CONTROLLER__' => 'item-set'], 'Item sets', '/item-set'],
            'dispatched media' => [['controller' => 'Omeka\Controller\Site\Media', '__CONTROLLER__' => 'media'], 'Media', '/media'],
            // Before dispatch, the param "controller" is the short name.
            'not dispatched' => [['controller' => 'item-set'], 'Item sets', '/item-set'],
        ];
    }

    /**
     * @dataProvider browseRouteProvider
     */
    public function testBrowsePageLabel(array $params, string $label, string $uriEnd): void
    {
        $site = $this->createSite('test-breadcrumbs-browse', 'Test breadcrumbs browse');
        $routeMatch = new RouteMatch($params + ['site-slug' => $site->slug(), 'action' => 'browse']);
        $routeMatch->setMatchedRouteName('site/resource');

        $container = $this->builder()->build($site, $routeMatch, null, [
            'home' => false,
            'collections' => false,
            'current' => true,
        ]);
        $active = $this->activePage($container);
        $this->assertNotNull($active);
        $this->assertSame($label, $active[0]);
        $this->assertStringEndsWith($site->slug() . $uriEnd, $active[1]);
    }

    public function testPrimaryItemSetFollowsTheOrderOfTheSite(): void
    {
        $itemSetA = $this->create('item_sets', []);
        $itemSetB = $this->create('item_sets', []);
        $itemSetC = $this->create('item_sets', []);
        $item = $this->create('items', [
            'o:item_set' => [['o:id' => $itemSetA->id()], ['o:id' => $itemSetB->id()]],
        ]);
        $itemOutsideSite = $this->create('items', [
            'o:item_set' => [['o:id' => $itemSetC->id()]],
        ]);

        $site = $this->createSite('test-breadcrumbs-itemset', 'Test breadcrumbs item set');
        $site = $this->api()->update('sites', $site->id(), [
            'o:site_item_set' => [
                ['o:item_set' => ['o:id' => $itemSetB->id()]],
                ['o:item_set' => ['o:id' => $itemSetA->id()]],
            ],
        ], [], ['isPartial' => true])->getContent();

        $method = new \ReflectionMethod(ContainerBuilder::class, 'getPrimaryItemSet');
        $method->setAccessible(true);

        // The second item set of the item is the first one of the site.
        $this->assertSame($itemSetB->id(), $method->invoke($this->builder(), $item, $site)->id());

        // An item set not attached to the site is used as fallback.
        $this->assertSame($itemSetC->id(), $method->invoke($this->builder(), $itemOutsideSite, $site)->id());

        // The property, when set, has priority.
        $item = $this->api()->update('items', $item->id(), [
            'dcterms:isPartOf' => [['type' => 'resource:itemset', 'property_id' => $this->getServiceLocator()->get('Common\EasyMeta')->propertyId('dcterms:isPartOf'), 'value_resource_id' => $itemSetA->id()]],
        ], [], ['isPartial' => true, 'collectionAction' => 'append'])->getContent();
        $this->assertSame($itemSetA->id(), $method->invoke($this->builder(), $item, $site, ['property_itemset' => 'dcterms:isPartOf'])->id());
    }
}
