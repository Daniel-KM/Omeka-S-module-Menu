<?php declare(strict_types=1);

namespace MenuTest\Site\BlockLayout;

use MenuTest\MenuTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests for the Menu block layout.
 */
class MenuTest extends AbstractHttpControllerTestCase
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
     * Test that the Menu block layout is registered.
     */
    public function testBlockLayoutIsRegistered(): void
    {
        $config = $this->getServiceLocator()->get('Config');
        $blockLayouts = $config['block_layouts']['invokables'] ?? [];
        $this->assertArrayHasKey('menu', $blockLayouts);
    }

    /**
     * Test that the block class exists.
     */
    public function testBlockClassExists(): void
    {
        $this->assertTrue(class_exists(\Menu\Site\BlockLayout\Menu::class));
    }

    /**
     * Test that a page with Menu block can be created.
     */
    public function testPageWithMenuBlockCanBeCreated(): void
    {
        // Create a test site.
        $site = $this->createSite('test-menu-block-site', 'Test Menu Block Site');

        // Create a menu first.
        $this->createMenu($site->id(), 'footer', [
            $this->createMenuLink('url', 'Home', ['url' => '/']),
        ]);

        // Create a page with a Menu block.
        $response = $this->api()->create('site_pages', [
            'o:site' => ['o:id' => $site->id()],
            'o:slug' => 'test-menu-block',
            'o:title' => 'Test Menu Block Page',
            'o:block' => [
                [
                    'o:layout' => 'menu',
                    'o:data' => [
                        'menu' => 'footer',
                    ],
                ],
            ],
        ]);
        $page = $response->getContent();

        $this->assertNotNull($page);
        $this->assertEquals('test-menu-block', $page->slug());

        // Verify the block was saved with menu data.
        $savedBlocks = $page->blocks();
        $this->assertCount(1, $savedBlocks);
        $this->assertEquals('menu', $savedBlocks[0]->layout());
        $this->assertEquals('footer', $savedBlocks[0]->dataValue('menu'));
    }
}
