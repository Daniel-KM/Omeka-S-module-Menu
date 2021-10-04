<?php declare(strict_types=1);

namespace Menu\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class NavMenu extends AbstractHelper
{
    /**
     * Render a menu.
     */
    public function __invoke(?string $name = null): string
    {
        return '';
    }
}
