<?php declare(strict_types=1);

namespace Menu\Form;

use Laminas\Form\Fieldset;
use Menu\Form\Element as MenuElement;

class SettingsFieldset extends Fieldset
{
    protected $label = 'Menu'; // @translate

    public function init(): void
    {
        $this
            ->setAttribute('id', 'menu')
            ->add([
                'name' => 'menu_property_broader',
                'type' => MenuElement\OptionalPropertySelect::class,
                'options' => [
                    'label' => 'Property to store a broader linked resource', // @translate
                    'info' => 'Automatically update resources by adding a value to it when the menu uses resources. It may be dcterms:isPartOf or skos:broader or any other property. If empty, the resource won’t be updated.', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'menu_property_broader',
                    'class' => 'chosen-select',
                    'multiple' => false,
                    'data-placeholder' => 'Select a property…', // @translate
                ],
            ])
            ->add([
                'name' => 'menu_property_narrower',
                'type' => MenuElement\OptionalPropertySelect::class,
                'options' => [
                    'label' => 'Property to store a narrower linked resource', // @translate
                    'info' => 'Automatically update resources by adding a value to it when the menu uses resources. It may be dcterms:hasPart or skos:narrower or any other property. If empty, the resource won’t be updated.', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'menu_property_narrower',
                    'class' => 'chosen-select',
                    'multiple' => false,
                    'data-placeholder' => 'Select a property…', // @translate
                ],
            ])
            ->add([
                'name' => 'menu_property_itemset',
                'type' => MenuElement\OptionalPropertySelect::class,
                'options' => [
                    'label' => 'Property to set primary item set', // @translate
                    'info' => 'When an item is included in multiple item sets, the main one may be determined by this property.', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'menu_property_itemset',
                    'class' => 'chosen-select',
                    'multiple' => false,
                    'data-placeholder' => 'Select a property…', // @translate
                ],
            ])
        ;
    }
}
