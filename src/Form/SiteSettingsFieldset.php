<?php declare(strict_types=1);

namespace Menu\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Menu\Form\Element\DataTextarea;

class SiteSettingsFieldset extends Fieldset
{
    protected $label = 'Menu & Breadcrumbs'; // @translate

    public function init(): void
    {
        $this
            ->add([
                'name' => 'menu_breadcrumbs_crumbs',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'label' => 'Crumbs', // @translate
                    'value_options' => [
                        'home' => 'Prepend home', // @translate
                        'collections' => 'Include "Collections"', // @translate,
                        'itemset' => 'Include main item set for item', // @translate,
                        'current' => 'Append current resource', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'menu_breadcrumbs_crumbs',
                ],
            ])
            ->add([
                'name' => 'menu_breadcrumbs_prepend',
                'type' => DataTextarea::class,
                'options' => [
                    'label' => 'Prepended links', // @translate
                    'info' => 'List of urls followed by a label, separated by a "=", one by line, that will be prepended to the breadcrumb.', // @translate
                    'as_key_value' => false,
                    'data_keys' => [
                        'uri',
                        'label',
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
                    'label' => 'Display on home page', // @translate
                ],
                'attributes' => [
                    'id' => 'menu_breadcrumbs_homepage',
                ],
            ]);
    }
}
