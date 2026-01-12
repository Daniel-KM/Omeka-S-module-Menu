<?php declare(strict_types=1);

namespace Menu\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Site\BlockLayout\TemplateableBlockLayoutInterface;

class Menu extends AbstractBlockLayout implements TemplateableBlockLayoutInterface
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/block-layout/menu';

    public function getLabel()
    {
        return 'Menu'; // @translate
    }

    public function form(
        PhpRenderer $view,
        SiteRepresentation $site,
        SitePageRepresentation $page = null,
        SitePageBlockRepresentation $block = null
    ) {
        // Factory is not used to make rendering simpler.
        $services = $site->getServiceLocator();
        $formElementManager = $services->get('FormElementManager');
        $defaultSettings = $services->get('Config')['menu']['block_settings']['menu'];

        $data = $block ? ($block->data() ?? []) + $defaultSettings : $defaultSettings;

        /** @var \Menu\Form\Element\MenuSelect $menuSelect */
        $menuSelect = $formElementManager->get(\Menu\Form\Element\MenuSelect::class);
        $menuSelect->setName('o:block[__blockIndex__][o:data][menu]');
        $menuSelect->setOptions([
            'label' => 'Menu', // @translate
            'info' => 'Select the menu to display.', // @translate
        ]);
        $menuSelect->setValue($data['menu'] ?? '');

        return $view->formRow($menuSelect);
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block, $templateViewScript = self::PARTIAL_NAME)
    {
        $data = $block->data();
        $menuName = $data['menu'] ?? '';
        if ($menuName === '') {
            return '';
        }

        $vars = [
            'block' => $block,
            'menuName' => $menuName,
        ];

        return $view->partial($templateViewScript, $vars);
    }

    public function getFulltextText(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        return '';
    }
}
