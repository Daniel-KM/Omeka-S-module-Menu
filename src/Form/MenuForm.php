<?php declare(strict_types=1);

namespace Menu\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

class MenuForm extends Form
{
    public function init(): void
    {
        $this
            // Keep the id of the navigation form to simplify js.
            ->setAttribute('id', 'site-form')
            ->add([
                'name' => 'name',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Menu name', // @translate
                ],
                'attributes' => [
                    'id' => 'name',
                    'required' => true,
                ],
            ])
            ->add([
                'name' => 'jstree',
                // Managed via js, but included for laminas.
                'type' => Element\Hidden::class,
            ])
        ;

        $inputFilter = $this->getInputFilter();
        $inputFilter
            ->add([
                'name' => 'name',
                'required' => true,
                'filters' => [
                    [
                        'name' => \Laminas\Filter\StringTrim::class,
                        'name' => \Laminas\Filter\StripTags::class,
                        'name' => \Laminas\Filter\StripNewlines::class,
                    ],
                ],
                'validators' => [
                    [
                        'name' => \Laminas\Validator\StringLength::class,
                        'options' => [
                            'min' => 1,
                            // Max id: 190 - "menu_menu:" (but may be unicode).
                            'max' => 180,
                        ],
                    ],
                ],
            ]);
    }
}
