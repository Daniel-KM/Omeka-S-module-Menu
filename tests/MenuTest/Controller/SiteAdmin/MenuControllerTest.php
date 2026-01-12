<?php declare(strict_types=1);

namespace MenuTest\Controller\SiteAdmin;

use MenuTest\MenuTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests for the Menu site admin controller.
 */
class MenuControllerTest extends AbstractHttpControllerTestCase
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
     * Test that the module is installed and active.
     */
    public function testModuleIsActive(): void
    {
        $moduleManager = $this->getServiceLocator()->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule('Menu');
        $this->assertNotNull($module);
        $this->assertEquals('active', $module->getState());
    }

    /**
     * Test that block layouts are registered.
     */
    public function testBlockLayoutsAreRegistered(): void
    {
        $config = $this->getServiceLocator()->get('Config');
        $this->assertArrayHasKey('block_layouts', $config);

        $blockLayouts = $config['block_layouts'];
        $this->assertArrayHasKey('invokables', $blockLayouts);

        // Check Menu block layouts are registered.
        $invokables = $blockLayouts['invokables'];
        $this->assertArrayHasKey('breadcrumbs', $invokables);
        $this->assertArrayHasKey('menu', $invokables);
    }

    /**
     * Test that resource page block layouts are registered.
     */
    public function testResourcePageBlockLayoutsAreRegistered(): void
    {
        $config = $this->getServiceLocator()->get('Config');
        $this->assertArrayHasKey('resource_page_block_layouts', $config);

        $resourceBlockLayouts = $config['resource_page_block_layouts'];
        $this->assertArrayHasKey('invokables', $resourceBlockLayouts);

        // Check Menu resource page block layouts are registered.
        $invokables = $resourceBlockLayouts['invokables'];
        $this->assertArrayHasKey('breadcrumbs', $invokables);
        $this->assertArrayHasKey('menu', $invokables);
    }

    /**
     * Test that view helpers are registered.
     */
    public function testViewHelpersAreRegistered(): void
    {
        $viewHelperManager = $this->getServiceLocator()->get('ViewHelperManager');

        // Check Menu view helpers are registered.
        $this->assertTrue($viewHelperManager->has('navMenu'));
        $this->assertTrue($viewHelperManager->has('breadcrumbs'));
    }

    /**
     * Test that navigation links are registered.
     */
    public function testNavigationLinksAreRegistered(): void
    {
        $config = $this->getServiceLocator()->get('Config');
        $this->assertArrayHasKey('navigation_links', $config);

        $navLinks = $config['navigation_links'];
        $this->assertArrayHasKey('invokables', $navLinks);

        // Check Menu navigation link types are registered.
        $invokables = $navLinks['invokables'];
        $this->assertArrayHasKey('resource', $invokables);
        $this->assertArrayHasKey('structure', $invokables);
    }

    /**
     * Test menu creation and retrieval via site settings.
     */
    public function testMenuCreationAndRetrieval(): void
    {
        // Create a test site.
        $site = $this->createSite('test-menu-site', 'Test Menu Site');

        // Create a menu with links.
        $menuLinks = [
            $this->createMenuLink('url', 'Home', ['url' => '/']),
            $this->createMenuLink('url', 'About', ['url' => '/about']),
        ];
        $this->createMenu($site->id(), 'footer', $menuLinks);

        // Retrieve and verify the menu.
        $retrievedMenu = $this->getMenu($site->id(), 'footer');
        $this->assertNotNull($retrievedMenu);
        $this->assertCount(2, $retrievedMenu);
        $this->assertEquals('Home', $retrievedMenu[0]['data']['label']);
        $this->assertEquals('About', $retrievedMenu[1]['data']['label']);
    }

    /**
     * Test that menu browse route exists.
     */
    public function testMenuBrowseRouteExists(): void
    {
        // Create a test site.
        $site = $this->createSite('test-route-site', 'Test Route Site');

        $this->dispatch('/admin/site/s/test-route-site/menu/browse');
        $this->assertControllerName('Menu\Controller\SiteAdmin\MenuController');
        $this->assertActionName('browse');
    }

    /**
     * Test that menu add route exists.
     */
    public function testMenuAddRouteExists(): void
    {
        // Create a test site.
        $site = $this->createSite('test-add-site', 'Test Add Site');

        $this->dispatch('/admin/site/s/test-add-site/menu/add');
        $this->assertControllerName('Menu\Controller\SiteAdmin\MenuController');
        $this->assertActionName('add');
    }
}
