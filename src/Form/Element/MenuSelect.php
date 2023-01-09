<?php declare(strict_types=1);

namespace Menu\Form\Element;

use Laminas\Form\Element\Select;

/**
 * @todo The menu select should be developed.
 */
class MenuSelect extends Select
{
    protected $settings;

    use TraitOptionalElement;
}
