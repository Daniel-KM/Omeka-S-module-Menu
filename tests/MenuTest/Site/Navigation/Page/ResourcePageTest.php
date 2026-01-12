<?php declare(strict_types=1);

namespace MenuTest\Site\Navigation\Page;

use Menu\Site\Navigation\Page\ResourcePage;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the ResourcePage navigation page.
 */
class ResourcePageTest extends TestCase
{
    /**
     * Test that ResourcePage can be instantiated.
     */
    public function testCanBeInstantiated(): void
    {
        $page = new ResourcePage();
        $this->assertInstanceOf(ResourcePage::class, $page);
    }

    /**
     * Test that ResourcePage extends Uri page.
     */
    public function testExtendsUriPage(): void
    {
        $page = new ResourcePage();
        $this->assertInstanceOf(\Laminas\Navigation\Page\Uri::class, $page);
    }

    /**
     * Test that ResourcePage implements ResourceInterface for ACL.
     */
    public function testImplementsResourceInterface(): void
    {
        $page = new ResourcePage();
        $this->assertInstanceOf(\Laminas\Permissions\Acl\Resource\ResourceInterface::class, $page);
    }

    /**
     * Test setting and getting resource name.
     */
    public function testSetGetResourceName(): void
    {
        $page = new ResourcePage();
        $page->setResourceName('items');
        $this->assertEquals('items', $page->getResourceName());
    }

    /**
     * Test setting and getting Omeka resource ID.
     */
    public function testSetGetOmekaResourceId(): void
    {
        $page = new ResourcePage();
        $page->setOmekaResourceId(123);
        $this->assertEquals(123, $page->getOmekaResourceId());
    }

    /**
     * Test that label can be set via constructor.
     */
    public function testLabelCanBeSetViaConstructor(): void
    {
        $page = new ResourcePage([
            'label' => 'Test Item',
            'uri' => '/s/site/item/123',
        ]);
        $this->assertEquals('Test Item', $page->getLabel());
        $this->assertEquals('/s/site/item/123', $page->getUri());
    }

    /**
     * Test getResourceId returns resource name for ACL when no Omeka resource.
     */
    public function testGetResourceIdReturnsResourceNameForAcl(): void
    {
        $page = new ResourcePage();
        $page->setResourceName('items');
        $this->assertEquals('items', $page->getResourceId());
    }

    /**
     * Test getResourceId returns default when no resource name set.
     */
    public function testGetResourceIdReturnsDefaultWhenNoResourceName(): void
    {
        $page = new ResourcePage();
        $this->assertEquals('Menu\Page\Resource', $page->getResourceId());
    }

    /**
     * Test isActive returns false by default.
     */
    public function testIsActiveReturnsFalseByDefault(): void
    {
        $page = new ResourcePage();
        $this->assertFalse($page->isActive());
    }

    /**
     * Test isActive can be set explicitly.
     */
    public function testIsActiveCanBeSetExplicitly(): void
    {
        $page = new ResourcePage();
        $page->setActive(true);
        $this->assertTrue($page->isActive());
    }
}
