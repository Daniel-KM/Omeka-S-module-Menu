<?php declare(strict_types=1);

namespace MenuTest\View\Helper;

use MenuTest\MenuTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests for the NavMenu view helper.
 */
class NavMenuTest extends AbstractHttpControllerTestCase
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
     * Test that the NavMenu helper is registered.
     */
    public function testHelperIsRegistered(): void
    {
        $viewHelperManager = $this->getServiceLocator()->get('ViewHelperManager');
        $this->assertTrue($viewHelperManager->has('navMenu'));
    }

    /**
     * Test that the helper can be instantiated.
     */
    public function testHelperCanBeInstantiated(): void
    {
        $viewHelperManager = $this->getServiceLocator()->get('ViewHelperManager');
        $helper = $viewHelperManager->get('navMenu');
        $this->assertInstanceOf(\Menu\View\Helper\NavMenu::class, $helper);
    }
}
