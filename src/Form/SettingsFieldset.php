<?php declare(strict_types=1);

namespace Menu\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Fieldset;

class SettingsFieldset extends Fieldset
{
    protected $label = 'Menu'; // @translate

    protected $elementGroups = [
        'menu' => 'Menu', // @translate
    ];

    public function init(): void
    {
        $this
            // Avoid to duplicate with page menu.
            ->setAttribute('id', 'module-menu')
            ->setOption('element_groups', $this->elementGroups)
            ->add([
                'name' => 'menu_update_resources',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'menu',
                    'label' => 'Update resources on menu saving', // @translate
                    'value_options' => [
                        'no' => 'No', // @translate
                        'yes' => 'Yes', // @translate
                        'template_intersect' => 'Only when properties below exist in resource template', // @translate
                        'template_properties' => 'According to template property settings (requires module Advanced Resource Template)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'menu_update_resources',
                ],
            ])
            ->add([
                'name' => 'menu_update_templates',
                'type' => CommonElement\OptionalResourceTemplateSelect::class,
                'options' => [
                    'element_group' => 'menu',
                    'label' => 'Limit update to specific templates', // @translate
                ],
                'attributes' => [
                    'id' => 'menu_update_templates',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select templates…', // @translate
                ],
            ])
            ->add([
                'name' => 'menu_properties_broader',
                'type' => CommonElement\OptionalPropertySelect::class,
                'options' => [
                    'element_group' => 'menu',
                    'label' => 'Properties to store a broader linked resource', // @translate
                    'info' => 'Automatically update resources by adding a value to it when the menu uses resources. It may be dcterms:isPartOf or skos:broader or any other property.', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'menu_properties_broader',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select properties…', // @translate
                ],
            ])
            ->add([
                'name' => 'menu_properties_narrower',
                'type' => CommonElement\OptionalPropertySelect::class,
                'options' => [
                    'element_group' => 'menu',
                    'label' => 'Properties to store a narrower linked resource', // @translate
                    'info' => 'Automatically update resources by adding a value to it when the menu uses resources. It may be dcterms:hasPart or skos:narrower or any other property.', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'menu_properties_narrower',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select properties…', // @translate
                ],
            ])
        ;
    }
}
