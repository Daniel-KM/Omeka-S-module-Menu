<?php declare(strict_types=1);

namespace MenuTest;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Api\Manager as ApiManager;

/**
 * Shared test helpers for Menu module tests.
 */
trait MenuTestTrait
{
    /**
     * @var ServiceLocatorInterface
     */
    protected $services;

    /**
     * @var array List of created site IDs for cleanup.
     */
    protected $createdSites = [];

    /**
     * Get the API manager.
     */
    protected function api(): ApiManager
    {
        return $this->getServiceLocator()->get('Omeka\ApiManager');
    }

    /**
     * Get the service locator.
     */
    protected function getServiceLocator(): ServiceLocatorInterface
    {
        if ($this->services === null) {
            $this->services = $this->getApplication()->getServiceManager();
        }
        return $this->services;
    }

    /**
     * Get the entity manager.
     */
    protected function getEntityManager()
    {
        return $this->getServiceLocator()->get('Omeka\EntityManager');
    }

    /**
     * Login as admin user.
     */
    protected function loginAdmin(): void
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity('admin@example.com');
        $adapter->setCredential('root');
        $auth->authenticate();
    }

    /**
     * Logout current user.
     */
    protected function logout(): void
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $auth->clearIdentity();
    }

    /**
     * Create a test site.
     *
     * @param string $slug Site slug.
     * @param string $title Site title.
     * @return \Omeka\Api\Representation\SiteRepresentation
     */
    protected function createSite(string $slug, string $title)
    {
        $response = $this->api()->create('sites', [
            'o:slug' => $slug,
            'o:title' => $title,
            'o:theme' => 'default',
            'o:is_public' => true,
        ]);
        $site = $response->getContent();
        $this->createdSites[] = $site->id();
        return $site;
    }

    /**
     * Create a menu for a site.
     *
     * @param int $siteId Site ID.
     * @param string $name Menu name.
     * @param array $links Menu links.
     */
    protected function createMenu(int $siteId, string $name, array $links = []): void
    {
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($siteId);
        $siteSettings->set('menu_menu:' . $name, $links);
    }

    /**
     * Get a menu for a site.
     *
     * @param int $siteId Site ID.
     * @param string $name Menu name.
     * @return array|null
     */
    protected function getMenu(int $siteId, string $name): ?array
    {
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($siteId);
        return $siteSettings->get('menu_menu:' . $name);
    }

    /**
     * Create a menu link structure.
     *
     * @param string $type Link type (url, page, resource, structure).
     * @param string $label Link label.
     * @param array $data Additional link data.
     * @return array
     */
    protected function createMenuLink(string $type, string $label, array $data = []): array
    {
        return array_merge([
            'type' => $type,
            'data' => array_merge(['label' => $label], $data),
            'links' => [],
        ], $data);
    }

    /**
     * Clean up created resources after test.
     */
    protected function cleanupResources(): void
    {
        // Delete created sites (this also deletes menus stored as site settings).
        foreach ($this->createdSites as $siteId) {
            try {
                $this->api()->delete('sites', $siteId);
            } catch (\Exception $e) {
                // Ignore errors during cleanup.
            }
        }
        $this->createdSites = [];
    }
}
