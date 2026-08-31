<?php declare(strict_types=1);

namespace MenuTest\View\Helper;

use MenuTest\MenuTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests for the Breadcrumbs view helper.
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
     * Test that the Breadcrumbs helper is registered.
     */
    public function testHelperIsRegistered(): void
    {
        $viewHelperManager = $this->getServiceLocator()->get('ViewHelperManager');
        $this->assertTrue($viewHelperManager->has('breadcrumbs'));
    }

    /**
     * Test that the helper can be instantiated.
     */
    public function testHelperCanBeInstantiated(): void
    {
        $viewHelperManager = $this->getServiceLocator()->get('ViewHelperManager');
        $helper = $viewHelperManager->get('breadcrumbs');
        $this->assertInstanceOf(\Menu\View\Helper\Breadcrumbs::class, $helper);
    }

    /**
     * Test that the ContainerBuilder service is registered.
     */
    public function testContainerBuilderServiceIsRegistered(): void
    {
        $serviceManager = $this->getServiceLocator();
        $this->assertTrue($serviceManager->has('Menu\Site\Navigation\Breadcrumb\ContainerBuilder'));
    }

    /**
     * Test that the ContainerBuilder can be instantiated.
     */
    public function testContainerBuilderCanBeInstantiated(): void
    {
        $serviceManager = $this->getServiceLocator();
        $containerBuilder = $serviceManager->get('Menu\Site\Navigation\Breadcrumb\ContainerBuilder');
        $this->assertInstanceOf(\Menu\Site\Navigation\Breadcrumb\ContainerBuilder::class, $containerBuilder);
    }

    protected function renderer()
    {
        return $this->getServiceLocator()->get('ViewHelperManager')->getRenderer();
    }

    protected function invokePrepareLinks($container, array $options): array
    {
        $helper = $this->getServiceLocator()->get('ViewHelperManager')->get('breadcrumbs');
        $helper->setView($this->renderer());
        $method = new \ReflectionMethod($helper, 'prepareLinks');
        $method->setAccessible(true);
        return $method->invoke($helper, $container, $options);
    }

    /**
     * prepareLinks() returns the active branch, root to leaf, as plain data.
     */
    public function testPrepareLinksNormalizesActiveChain(): void
    {
        $container = new \Laminas\Navigation\Navigation([
            [
                'type' => 'uri',
                'label' => 'Home',
                'uri' => '/',
                'pages' => [
                    [
                        'type' => 'uri',
                        'label' => 'Section',
                        'uri' => '',
                        'pages' => [
                            [
                                'type' => 'uri',
                                'label' => 'Current',
                                'uri' => '/current',
                                'active' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $links = $this->invokePrepareLinks($container, []);

        $this->assertCount(3, $links);
        $this->assertSame(['Home', 'Section', 'Current'], array_column($links, 'label'));
        $this->assertSame([false, false, true], array_column($links, 'active'));
        $this->assertSame([false, true, false], array_column($links, 'no_link'));
        $this->assertSame('/', $links[0]['uri']);
        $this->assertSame('', $links[1]['uri']);
    }

    /**
     * The partial builds all the markup from the prepared links.
     */
    public function testPartialRendersMarkup(): void
    {
        $html = $this->renderer()->partial('common/breadcrumbs', [
            'site' => null,
            'options' => [],
            'links' => [
                ['label' => 'Home', 'title' => '', 'uri' => '/', 'active' => false, 'no_link' => false, 'resource' => null],
                ['label' => 'No link', 'title' => '', 'uri' => '', 'active' => false, 'no_link' => true, 'resource' => null],
                ['label' => 'Current', 'title' => '', 'uri' => '/current', 'active' => true, 'no_link' => false, 'resource' => null],
            ],
            'separator' => '/',
            'ariaLabel' => 'Breadcrumb',
            'schema' => '',
            'breadcrumbs' => null,
            'crumbs' => [],
        ]);

        $this->assertStringContainsString('>Home</a>', $html);
        $this->assertStringContainsString('href="' . $this->renderer()->escapeHtmlAttr('/') . '"', $html);
        $this->assertStringContainsString('<span class="no-link">No link</span>', $html);
        $this->assertStringContainsString('<span class="active" aria-current="page">Current</span>', $html);
        $this->assertStringContainsString('class="breadcrumb-separator" aria-hidden="true"', $html);
        $this->assertStringContainsString('aria-label="Breadcrumb"', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    /**
     * With linkLast, the current crumb stays a link but keeps aria-current.
     */
    public function testPartialLinkLastRendersLastAsLink(): void
    {
        $html = $this->renderer()->partial('common/breadcrumbs', [
            'site' => null,
            'options' => ['linkLast' => true],
            'links' => [
                ['label' => 'Current', 'title' => '', 'uri' => '/current', 'active' => true, 'no_link' => false, 'resource' => null],
            ],
            'separator' => '/',
            'ariaLabel' => 'Breadcrumb',
            'schema' => '',
            'breadcrumbs' => null,
            'crumbs' => [],
        ]);

        $expectedHref = $this->renderer()->escapeHtmlAttr('/current');
        $this->assertStringContainsString('<a href="' . $expectedHref . '" aria-current="page">Current</a>', $html);
    }

    /**
     * The schema.org script is output only when provided.
     */
    public function testPartialRendersSchema(): void
    {
        $html = $this->renderer()->partial('common/breadcrumbs', [
            'site' => null,
            'options' => [],
            'links' => [
                ['label' => 'Current', 'title' => '', 'uri' => '/current', 'active' => true, 'no_link' => false, 'resource' => null],
            ],
            'separator' => '/',
            'ariaLabel' => 'Breadcrumb',
            'schema' => '{"@type":"BreadcrumbList"}',
            'breadcrumbs' => null,
            'crumbs' => [],
        ]);

        $this->assertStringContainsString('<script type="application/ld+json">{"@type":"BreadcrumbList"}</script>', $html);
    }

    /**
     * The separator is decorative: escaped and hidden from screen readers.
     *
     * Html is not interpreted any more, so an entity set in the site settings
     * is displayed as is: the character itself must be used.
     */
    public function testPartialEscapesSeparator(): void
    {
        $html = $this->renderer()->partial('common/breadcrumbs', [
            'site' => null,
            'options' => [],
            'links' => [
                ['label' => 'Home', 'title' => '', 'uri' => '/', 'active' => false, 'no_link' => false, 'resource' => null],
                ['label' => 'Current', 'title' => '', 'uri' => '/current', 'active' => true, 'no_link' => false, 'resource' => null],
            ],
            'separator' => '&gt;',
            'ariaLabel' => 'Breadcrumb',
            'schema' => '',
            'breadcrumbs' => null,
            'crumbs' => [],
        ]);

        $this->assertStringContainsString(
            '<span class="breadcrumb-separator" aria-hidden="true">' . $this->renderer()->escapeHtml('&gt;') . '</span>',
            $html
        );
        // The entity is not interpreted: no raw ">" is added by the separator.
        $this->assertStringNotContainsString('aria-hidden="true">&gt;<', $html);
    }

    /**
     * No links means no output at all.
     */
    public function testPartialEmptyLinksRendersNothing(): void
    {
        $html = $this->renderer()->partial('common/breadcrumbs', [
            'site' => null,
            'options' => [],
            'links' => [],
            'separator' => '/',
            'ariaLabel' => 'Breadcrumb',
            'schema' => '',
            'breadcrumbs' => null,
            'crumbs' => [],
        ]);

        $this->assertSame('', trim($html));
    }
}
