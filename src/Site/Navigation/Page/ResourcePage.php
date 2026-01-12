<?php declare(strict_types=1);

namespace Menu\Site\Navigation\Page;

use Laminas\Navigation\Page\Uri;
use Laminas\Permissions\Acl\Resource\ResourceInterface;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

/**
 * Navigation page for Omeka resources (items, item sets, media).
 *
 * This page type integrates with Laminas Navigation and provides:
 * - Proper isActive() detection based on current route and resource id
 * - Acl integration via ResourceInterface
 * - Resource metadata access for custom rendering
 *
 * Note: $omekaResource is used instead of $resource to avoid shadowing the
 * parent AbstractPage::$resource property, which is used for acl resources.
 */
class ResourcePage extends Uri implements ResourceInterface
{
    /**
     * @var AbstractResourceEntityRepresentation|null
     */
    protected $omekaResource;

    /**
     * @var string|null
     */
    protected $resourceName;

    /**
     * @var int|null
     */
    protected $omekaResourceId;

    /**
     * Set the Omeka resource for this page.
     */
    public function setOmekaResource(?AbstractResourceEntityRepresentation $resource): self
    {
        $this->omekaResource = $resource;
        if ($resource) {
            $this->resourceName = $resource->resourceName();
            $this->omekaResourceId = $resource->id();
        }
        return $this;
    }

    /**
     * Get the Omeka resource.
     */
    public function getOmekaResource(): ?AbstractResourceEntityRepresentation
    {
        return $this->omekaResource;
    }

    /**
     * Set resource name (items, item_sets, media).
     */
    public function setResourceName(?string $resourceName): self
    {
        $this->resourceName = $resourceName;
        return $this;
    }

    /**
     * Get resource name.
     */
    public function getResourceName(): ?string
    {
        return $this->resourceName;
    }

    /**
     * Set Omeka resource id.
     */
    public function setOmekaResourceId(?int $omekaResourceId): self
    {
        $this->omekaResourceId = $omekaResourceId;
        return $this;
    }

    /**
     * Get Omeka resource id.
     */
    public function getOmekaResourceId(): ?int
    {
        return $this->omekaResourceId;
    }

    /**
     * Check if this page is active.
     *
     * A resource page is active if:
     * - The current route is 'site/resource-id'
     * - The controller matches the resource type
     * - The resource id matches
     *
     * @param bool $recursive Whether to check child pages recursively
     * @return bool
     */
    public function isActive($recursive = false): bool
    {
        // Check if explicitly set.
        if ($this->active !== null) {
            return $this->active;
        }

        // Try to detect from request.
        $request = $this->getRequest();
        if (!$request) {
            return parent::isActive($recursive);
        }

        // Get route match from request attributes (laminas mvc).
        $routeMatch = null;
        if (method_exists($request, 'getAttribute')) {
            $routeMatch = $request->getAttribute('Laminas\Router\RouteMatch');
        }

        if (!$routeMatch) {
            return parent::isActive($recursive);
        }

        $matchedRoute = $routeMatch->getMatchedRouteName();

        // Check for resource routes.
        if ($matchedRoute === 'site/resource-id') {
            $controller = $routeMatch->getParam('controller');
            $id = (int) $routeMatch->getParam('id');

            // Map controller to resource name.
            $controllerToResource = [
                'item' => 'items',
                'Omeka\Controller\Site\Item' => 'items',
                'item-set' => 'item_sets',
                'Omeka\Controller\Site\ItemSet' => 'item_sets',
                'media' => 'media',
                'Omeka\Controller\Site\Media' => 'media',
            ];

            $resourceName = $controllerToResource[$controller] ?? null;

            if ($resourceName === $this->resourceName && $id === $this->omekaResourceId) {
                $this->active = true;
                return true;
            }
        }

        // Check for item-set browse (item-set/show redirects here).
        if ($matchedRoute === 'site/item-set' && $this->resourceName === 'item_sets') {
            $itemSetId = (int) $routeMatch->getParam('item-set-id');
            if ($itemSetId === $this->omekaResourceId) {
                $this->active = true;
                return true;
            }
        }

        return parent::isActive($recursive);
    }

    /**
     * Get acl resource id for permission checks.
     *
     * @return string
     */
    public function getResourceId(): string
    {
        // For acl, return the Omeka resource class if available.
        if ($this->omekaResource) {
            $resourceClass = $this->omekaResource->resourceClass();
            if ($resourceClass) {
                return $resourceClass->term();
            }
        }

        // Fall back to resource name.
        return $this->resourceName ?? 'Menu\Page\Resource';
    }
}
