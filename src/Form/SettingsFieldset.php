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
