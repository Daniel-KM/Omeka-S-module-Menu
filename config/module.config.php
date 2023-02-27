<?php declare(strict_types=1);

namespace Menu;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'breadcrumbs' => View\Helper\Breadcrumbs::class,
            'primaryItemSet' => View\Helper\PrimaryItemSet::class,
        ],
        'factories' => [
            'navMenu' => Service\ViewHelper\NavMenuFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\Element\DataTextarea::class => Form\Element\DataTextarea::class,
            Form\Element\OptionalMultiCheckbox::class => Form\Element\OptionalMultiCheckbox::class,
            Form\Element\OptionalRadio::class => Form\Element\OptionalRadio::class,
            Form\MenuForm::class => Form\MenuForm::class,
            Form\SettingsFieldset::class => Form\SettingsFieldset::class,
            Form\SiteSettingsFieldset::class => Form\SiteSettingsFieldset::class,
        ],
        'factories' => [
            Form\Element\MenuSelect::class => Service\Form\Element\MenuSelectFactory::class,
            Form\Element\OptionalPropertySelect::class => Service\Form\Element\OptionalPropertySelectFactory::class,
            Form\Element\OptionalResourceTemplateSelect::class => Service\Form\Element\OptionalResourceTemplateSelectFactory::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            Controller\SiteAdmin\MenuController::class => Controller\SiteAdmin\MenuController::class,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'navigationTranslator' => Service\ControllerPlugin\NavigationTranslatorFactory::class,
        ],
    ],
    'navigation_links' => [
        'invokables' => [
            'page' => Site\Navigation\Link\Page::class,
            'resource' => Site\Navigation\Link\Resource::class,
            'structure' => Site\Navigation\Link\Structure::class,
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'site' => [
                        'child_routes' => [
                            'slug' => [
                                'child_routes' => [
                                    'menu' => [
                                        'type' => \Laminas\Router\Http\Segment::class,
                                        'options' => [
                                            'route' => '/menu[/:action]',
                                            'constraints' => [
                                                'action' => 'index|browse|add|batch-delete',
                                            ],
                                            'defaults' => [
                                                '__NAMESPACE__' => 'Menu\Controller\SiteAdmin',
                                                'controller' => Controller\SiteAdmin\MenuController::class,
                                                'action' => 'browse',
                                            ],
                                        ],
                                    ],
                                    'menu-id' => [
                                        'type' => \Laminas\Router\Http\Segment::class,
                                        'options' => [
                                            'route' => '/menu/:menu-slug[/:action]',
                                            'constraints' => [
                                                // The slug cannot be one of the defaut actions.
                                                'menu-slug' => '(?!index|browse|add|batch-delete)[\w-]+',
                                                'action' => '[a-zA-Z][\w-]*',
                                            ],
                                            'defaults' => [
                                                '__NAMESPACE__' => 'Menu\Controller\SiteAdmin',
                                                'controller' => Controller\SiteAdmin\MenuController::class,
                                                'action' => 'show',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'navigation' => [
        'site' => [
            [
                'label' => 'Menus', // @translate
                'class' => 'o-icon- fa-bars',
                'route' => 'admin/site/slug/menu',
                'action' => 'browse',
                'useRouteMatch' => true,
                'pages' => [
                    [
                        'route' => 'admin/site/slug/menu',
                        'visible' => false,
                    ],
                    [
                        'route' => 'admin/site/slug/menu-id',
                        'visible' => false,
                    ],
                ],
            ],
        ],
    ],
    'menu' => [
        'settings' => [
            'menu_update_resources' => 'no',
            'menu_update_templates' => [],
            'menu_properties_broader' => [],
            'menu_properties_narrower' => [],
            'menu_property_itemset' => '',
        ],
        'site_settings' => [
            'menu_breadcrumbs_crumbs' => [
                'home',
                'collections',
                'itemset',
                'current',
            ],
            'menu_breadcrumbs_prepend' => [],
            'menu_breadcrumbs_collections_url' => '',
            'menu_breadcrumbs_separator' => '&gt;',
            'menu_breadcrumbs_homepage' => false,
            // This site setting is not managed in site settings, but in site menu "Menu".
            // There may be many "menu_menu:xxx".
            'menu_menu:' => [],
        ],
    ],
];
