<?php declare(strict_types=1);

/**
 * Menu
 *
 * Display multiple menus in a site, for example a top menu, a sidebar menu and a
 * footer menu, or any structure anywhere.
 *
 * @copyright Daniel Berthereau, 2021-2023
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */
namespace Menu;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Generic\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        /**
         * @var \Omeka\Permissions\Acl $acl
         * @see \Omeka\Service\AclFactory
         */
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');

        $roles = $acl->getRoles();
        // This is a static list in Omeka, but not gettable.
        $admins = [
            \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
            \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
        ];
        $notAdmins = array_diff($roles, $admins);

        // TODO Manage rights of the site owner (not clear in Omeka, since the site roles are not real roles).

        // Only admin and site admins can edit menu, other can view it.
        $acl
            ->allow(
                // TODO Except Guest.
                $notAdmins,
                [Controller\SiteAdmin\MenuController::class],
                [
                    'index', 'browse', 'show', 'show-details',
                ]
            )
            // By default, admins can do anything anyway.
            ->allow(
                $admins,
                [Controller\SiteAdmin\MenuController::class],
                [
                    'index', 'browse', 'show', 'show-details', 'add', 'edit',
                    'delete', 'delete-confirm', 'batch-delete',
                    'jstree', 'tops-to-menus',
                ]
            )
        ;
    }

    protected function postInstall(): void
    {
        // TODO Manage settings sub keys in Generic AbstractModule.
        // Remove the automatic install of "menu_menu:".
        $this->getServiceLocator()->get('Omeka\Connection')
            ->executeStatement('DELETE FROM `site_setting` WHERE `id` = "menu_menu:";');
    }

    protected function postUninstall(): void
    {
        // TODO Manage settings sub keys in Generic AbstractModule.
        $this->getServiceLocator()->get('Omeka\Connection')
            ->executeStatement('DELETE FROM `site_setting` WHERE `id` LIKE "menu\_menu:%";');
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        // Main settings.
        $sharedEventManager->attach(
            \Omeka\Form\SettingForm::class,
            'form.add_elements',
            [$this, 'handleMainSettings']
        );

        // Site settings.
        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_elements',
            [$this, 'handleSiteSettings']
        );

        $sharedEventManager->attach(
            // \Omeka\Form\ResourceTemplatePropertyFieldset::class,
            \AdvancedResourceTemplate\Form\ResourceTemplatePropertyFieldset::class,
            'form.add_elements',
            [$this, 'addResourceTemplatePropertyFieldsetElements']
        );
    }

    public function addResourceTemplatePropertyFieldsetElements(Event $event): void
    {
        /** @var \AdvancedResourceTemplate\Form\ResourceTemplatePropertyFieldset $fieldset */
        $fieldset = $event->getTarget();
        $fieldset
            ->add([
                'name' => 'menu_broader',
                'type' => \Laminas\Form\Element\Checkbox::class,
                'options' => [
                    'label' => 'Fill as broader when saving menu', // @translate
                ],
                'attributes' => [
                    // 'id' => 'menu_broader',
                    'class' => 'setting',
                    'data-setting-key' => 'menu_broader',
                ],
            ])
            ->add([
                'name' => 'menu_narrower',
                'type' => \Laminas\Form\Element\Checkbox::class,
                'options' => [
                    'label' => 'Fill as narrower when saving menu', // @translate
                ],
                'attributes' => [
                    // 'id' => 'menu_narrower',
                    'class' => 'setting',
                    'data-setting-key' => 'menu_narrower',
                ],
            ]);
    }
}
