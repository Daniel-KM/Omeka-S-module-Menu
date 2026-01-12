<?php declare(strict_types=1);

namespace Menu\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Menu\Form\Element as MenuElement;

class SiteSettingsFieldset extends Fieldset
{
    protected $label = 'Menu'; // @translate

    protected $elementGroups = [
        'menu' => 'Menu', // @translate
        'breadcrumbs' => 'Breadcrumbs', // @translate
    ];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'menu-site-settings')
            ->setOption('element_groups', $this->elementGroups)

            // Menu for resource pages.

            ->add([
                'name' => 'menu_resource_menu',
                'type' => MenuElement\MenuSelect::class,
                'options' => [
                    'element_group' => 'menu',
                    'label' => 'Menu for resource pages', // @translate
                    'info' => 'Select the menu to display on resource pages (items, media, item sets) via the resource block "Menu".', // @translate
                ],
                'attributes' => [
                    'id' => 'menu_resource_menu',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a menu…', // @translate
                ],
            ])

            // Breadcrumbs.

            ->add([
                'name' => 'menu_breadcrumbs_crumbs',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'breadcrumbs',
                    'label' => 'Crumbs', // @translate
                    'value_options' => [
                        // Copy options in view helper \Menu\View\Helper\Breadcrumbs.
                        'home' => 'Prepend home', // @translate
                        'collections' => 'Include "Collections"', // @translate,
                        'itemset' => 'Include main item set for item', // @translate,
                        'itemsetstree' => 'Include item sets tree', // @translate,
                        'current' => 'Append current resource', // @translate
                        'current_link' => 'Append current resource as a link', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'menu_breadcrumbs_crumbs',
                ],
            ])
            ->add([
                'name' => 'menu_breadcrumbs_prepend',
                'type' => CommonElement\DataTextarea::class,
                'options' => [
                    'element_group' => 'breadcrumbs',
                    'label' => 'Prepended links', // @translate
                    'info' => 'List of urls followed by a label, separated by a "=", one by line, that will be prepended to the breadcrumb.', // @translate
                    'as_key_value' => false,
                    'data_options' => [
                        'uri' => null,
                        'label' => null,
                    ],
                ],
                'attributes' => [
                    'id' => 'menu_breadcrumbs_prepend',
                    'placeholder' => '/s/my-site/page/intermediate = Example page',
                ],
            ])
            ->add([
                'name' => 'menu_breadcrumbs_collections_url',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'breadcrumbs',
                    'label' => 'Url for collections', // @translate
                    'info' => 'The url to use for the link "Collections", if set above. Let empty to use the default one.', // @translate
                ],
                'attributes' => [
                    'id' => 'menu_breadcrumbs_collections_url',
                    'placeholder' => '/s/my-site/search?resource-type=item_sets',
                ],
            ])
            ->add([
                'name' => 'menu_breadcrumbs_separator',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'breadcrumbs',
                    'label' => 'Separator', // @translate
                    'info' => 'The separator between crumbs may be set as raw text or via css. it should be set as an html text ("&gt;").', // @translate
                ],
                'attributes' => [
                    'id' => 'menu_breadcrumbs_separator',
                    'placeholder' => '&gt;',
                ],
            ])
            ->add([
                'name' => 'menu_breadcrumbs_homepage',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'breadcrumbs',
                    'label' => 'Display on home page', // @translate
                ],
                'attributes' => [
                    'id' => 'menu_breadcrumbs_homepage',
                ],
            ])
        ;
    }
}
