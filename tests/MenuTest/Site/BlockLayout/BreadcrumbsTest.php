<?php declare(strict_types=1);

namespace MenuTest\Site\BlockLayout;

use MenuTest\MenuTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests for the Breadcrumbs block layout.
 */
class BreadcrumbsTest extends AbstractHttpControllerTestCase
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

    /**
     * Test that the Breadcrumbs block layout is registered.
     */
    public function testBlockLayoutIsRegistered(): void
    {
        $config = $this->getServiceLocator()->get('Config');
        $blockLayouts = $config['block_layouts']['invokables'] ?? [];
        $this->assertArrayHasKey('breadcrumbs', $blockLayouts);
    }

    /**
     * Test that the block class exists.
     */
    public function testBlockClassExists(): void
    {
        $this->assertTrue(class_exists(\Menu\Site\BlockLayout\Breadcrumbs::class));
    }

    /**
     * Test that a page with Breadcrumbs block can be created.
     */
    public function testPageWithBreadcrumbsBlockCanBeCreated(): void
    {
        // Create a test site.
        $site = $this->createSite('test-breadcrumbs-site', 'Test Breadcrumbs Site');

        // Create a page with a Breadcrumbs block.
        $response = $this->api()->create('site_pages', [
            'o:site' => ['o:id' => $site->id()],
            'o:slug' => 'test-breadcrumbs',
            'o:title' => 'Test Breadcrumbs Page',
            'o:block' => [
                [
                    'o:layout' => 'breadcrumbs',
                    'o:data' => [],
                ],
            ],
        ]);
        $page = $response->getContent();

        $this->assertNotNull($page);
        $this->assertEquals('test-breadcrumbs', $page->slug());

        // Verify the block was saved.
        $savedBlocks = $page->blocks();
        $this->assertCount(1, $savedBlocks);
        $this->assertEquals('breadcrumbs', $savedBlocks[0]->layout());
    }
}
