<?php declare(strict_types=1);

namespace Menu;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'navMenu' => Service\ViewHelper\NavMenuFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\MenuForm::class => Form\MenuForm::class,
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
            'resource' => Site\Navigation\Link\Resource::class,
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
                                                'action' => 'index|browse|add',
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
                                                'menu-slug' => '(?!index|browse|add)[\w-]+',
                                                'action' => '[a-zA-Z][\w-]*',
                                            ],
                                            'defaults' => [
                                                '__NAMESPACE__' => 'Menu\Controller\SiteAdmin',
                                                'controller' => Controller\SiteAdmin\MenuController::class,
                                                'action' => 'edit',
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
                'class' => 'navigation',
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
        'site_settings' => [
            // This site setting is not managed in site settings, but in site menu "Menu".
            'menu_menus' => [],
        ],
    ],
];
