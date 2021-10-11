<?php declare(strict_types=1);

namespace Menu\Form\Element;

use Laminas\Form\Element\Select;
use Omeka\Settings\AbstractSettings;

class MenuSelect extends Select
{
    protected $settings;

    /**
     * @see https://github.com/zendframework/zendframework/issues/2761#issuecomment-14488216
     *
     * {@inheritDoc}
     * @see \Laminas\Form\Element\Select::getInputSpecification()
     */
    public function getInputSpecification()
    {
        $inputSpecification = parent::getInputSpecification();
        $inputSpecification['required'] = isset($this->attributes['required'])
            && $this->attributes['required'];
        return $inputSpecification;
    }

    public function setOptions($options)
    {
        if (!array_key_exists('value_options', $options)) {
            $options['value_options'] = $this->listMenus();
        }
        return parent::setOptions($options);
    }

    protected function listMenus(): array
    {
        if (!$this->settings) {
            return [];
        }
        $menus = $this->settings->get('menu_menus', []);
        return array_combine(array_keys($menus), array_keys($menus));
    }

    public function setSettings(AbstractSettings $settings)
    {
        $this->settings = $settings;
        return $this;
    }
}
