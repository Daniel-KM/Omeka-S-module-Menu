<?php declare(strict_types=1);

namespace MenuTest;

use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Integration tests for the Menu module.
 *
 * Tests that verify the module is properly installed and configured.
 */
class ModuleIntegrationTest extends AbstractHttpControllerTestCase
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
        $this->assertNotNull($module, 'Menu module should be found');
        $this->assertEquals('active', $module->getState(), 'Menu module should be active');
    }

    /**
     * Test that all expected block layouts are registered.
     */
    public function testAllBlockLayoutsAreRegistered(): void
    {
        $config = $this->getServiceLocator()->get('Config');
        $blockLayouts = $config['block_layouts']['invokables'] ?? [];

        $expectedLayouts = [
            'breadcrumbs',
            'menu',
        ];

        foreach ($expectedLayouts as $layout) {
            $this->assertArrayHasKey($layout, $blockLayouts, "Block layout '$layout' should be registered");
        }
    }

    /**
     * Test that all expected resource page block layouts are registered.
     */
    public function testAllResourcePageBlockLayoutsAreRegistered(): void
    {
        $config = $this->getServiceLocator()->get('Config');
        $resourceBlockLayouts = $config['resource_page_block_layouts']['invokables'] ?? [];

        $expectedLayouts = [
            'breadcrumbs',
            'menu',
        ];

        foreach ($expectedLayouts as $layout) {
            $this->assertArrayHasKey($layout, $resourceBlockLayouts, "Resource block layout '$layout' should be registered");
        }
    }

    /**
     * Test that all expected view helpers are registered.
     */
    public function testAllViewHelpersAreRegistered(): void
    {
        $viewHelperManager = $this->getServiceLocator()->get('ViewHelperManager');

        $expectedHelpers = [
            'navMenu',
            'breadcrumbs',
        ];

        foreach ($expectedHelpers as $helper) {
            $this->assertTrue(
                $viewHelperManager->has($helper),
                "View helper '$helper' should be registered"
            );
        }
    }

    /**
     * Test that navigation link types are registered.
     */
    public function testNavigationLinkTypesAreRegistered(): void
    {
        $config = $this->getServiceLocator()->get('Config');
        $navLinks = $config['navigation_links']['invokables'] ?? [];

        $expectedLinks = [
            'resource',
            'structure',
        ];

        foreach ($expectedLinks as $link) {
            $this->assertArrayHasKey($link, $navLinks, "Navigation link type '$link' should be registered");
        }
    }

    /**
     * Test that form elements are registered.
     */
    public function testFormElementsAreRegistered(): void
    {
        $formElementManager = $this->getServiceLocator()->get('FormElementManager');

        $this->assertTrue(
            $formElementManager->has(\Menu\Form\Element\MenuSelect::class),
            'MenuSelect form element should be registered'
        );
    }

    /**
     * Test that controller plugins are registered.
     */
    public function testControllerPluginsAreRegistered(): void
    {
        $controllerPluginManager = $this->getServiceLocator()->get('ControllerPluginManager');

        $this->assertTrue(
            $controllerPluginManager->has('navigationTranslator'),
            'navigationTranslator controller plugin should be registered'
        );
    }

    /**
     * Test creating and retrieving menus.
     */
    public function testMenuCreationAndRetrieval(): void
    {
        // Create a test site.
        $site = $this->createSite('test-menu-crud', 'Test Menu CRUD');

        // Create a menu with nested links.
        $menuLinks = [
            $this->createMenuLink('url', 'Home', ['url' => '/']),
            [
                'type' => 'url',
                'data' => [
                    'label' => 'About',
                    'url' => '/about',
                ],
                'links' => [
                    $this->createMenuLink('url', 'Team', ['url' => '/about/team']),
                    $this->createMenuLink('url', 'History', ['url' => '/about/history']),
                ],
            ],
            $this->createMenuLink('url', 'Contact', ['url' => '/contact']),
        ];
        $this->createMenu($site->id(), 'main', $menuLinks);

        // Retrieve and verify.
        $retrievedMenu = $this->getMenu($site->id(), 'main');
        $this->assertNotNull($retrievedMenu);
        $this->assertCount(3, $retrievedMenu);

        // Verify nested links.
        $aboutLink = $retrievedMenu[1];
        $this->assertEquals('About', $aboutLink['data']['label']);
        $this->assertCount(2, $aboutLink['links']);
        $this->assertEquals('Team', $aboutLink['links'][0]['data']['label']);
        $this->assertEquals('History', $aboutLink['links'][1]['data']['label']);
    }

    /**
     * Test that multiple menus can be created for the same site.
     */
    public function testMultipleMenusPerSite(): void
    {
        // Create a test site.
        $site = $this->createSite('test-multi-menu', 'Test Multi Menu');

        // Create header menu.
        $this->createMenu($site->id(), 'header', [
            $this->createMenuLink('url', 'Home', ['url' => '/']),
            $this->createMenuLink('url', 'About', ['url' => '/about']),
        ]);

        // Create footer menu.
        $this->createMenu($site->id(), 'footer', [
            $this->createMenuLink('url', 'Privacy', ['url' => '/privacy']),
            $this->createMenuLink('url', 'Terms', ['url' => '/terms']),
        ]);

        // Create sidebar menu.
        $this->createMenu($site->id(), 'sidebar', [
            $this->createMenuLink('url', 'Quick Links', ['url' => '/links']),
        ]);

        // Verify all menus exist and are independent.
        $headerMenu = $this->getMenu($site->id(), 'header');
        $footerMenu = $this->getMenu($site->id(), 'footer');
        $sidebarMenu = $this->getMenu($site->id(), 'sidebar');

        $this->assertCount(2, $headerMenu);
        $this->assertCount(2, $footerMenu);
        $this->assertCount(1, $sidebarMenu);

        $this->assertEquals('Home', $headerMenu[0]['data']['label']);
        $this->assertEquals('Privacy', $footerMenu[0]['data']['label']);
        $this->assertEquals('Quick Links', $sidebarMenu[0]['data']['label']);
    }

    /**
     * Test that page can be accessed with breadcrumbs block.
     */
    public function testPageWithBreadcrumbsCanBeAccessed(): void
    {
        // Create a test site.
        $site = $this->createSite('test-breadcrumbs-access', 'Test Breadcrumbs Access');

        // Create a page with breadcrumbs block.
        $response = $this->api()->create('site_pages', [
            'o:site' => ['o:id' => $site->id()],
            'o:slug' => 'breadcrumbs-page',
            'o:title' => 'Breadcrumbs Page',
            'o:block' => [
                [
                    'o:layout' => 'breadcrumbs',
                    'o:data' => [],
                ],
            ],
        ]);

        // Access the page.
        $this->dispatch('/s/test-breadcrumbs-access/page/breadcrumbs-page');
        $this->assertResponseStatusCode(200);
    }

    /**
     * Test that site settings for breadcrumbs can be set.
     */
    public function testBreadcrumbsSiteSettingsCanBeSet(): void
    {
        // Create a test site.
        $site = $this->createSite('test-breadcrumbs-settings', 'Test Breadcrumbs Settings');

        // Set breadcrumbs settings.
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($site->id());
        $siteSettings->set('menu_breadcrumbs_crumbs', ['home', 'current']);
        $siteSettings->set('menu_breadcrumbs_separator', ' > ');
        $siteSettings->set('menu_breadcrumbs_homepage', true);

        // Verify settings were saved.
        $crumbs = $siteSettings->get('menu_breadcrumbs_crumbs');
        $separator = $siteSettings->get('menu_breadcrumbs_separator');
        $homepage = $siteSettings->get('menu_breadcrumbs_homepage');

        $this->assertEquals(['home', 'current'], $crumbs);
        $this->assertEquals(' > ', $separator);
        $this->assertTrue($homepage);
    }

    /**
     * Test that menu resource setting can be set.
     */
    public function testMenuResourceSettingCanBeSet(): void
    {
        // Create a test site.
        $site = $this->createSite('test-menu-resource-setting', 'Test Menu Resource Setting');

        // Create a menu.
        $this->createMenu($site->id(), 'resource-nav', [
            $this->createMenuLink('url', 'Related Items', ['url' => '/items']),
        ]);

        // Set the resource menu setting.
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($site->id());
        $siteSettings->set('menu_resource_menu', 'resource-nav');

        // Verify setting was saved.
        $resourceMenu = $siteSettings->get('menu_resource_menu');
        $this->assertEquals('resource-nav', $resourceMenu);
    }
}
