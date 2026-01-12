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
}
