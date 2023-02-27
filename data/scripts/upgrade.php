<?php declare(strict_types=1);

namespace Menu;

use Omeka\Stdlib\Message;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$settings = $services->get('Omeka\Settings');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

$siteSettings = $services->get('Omeka\Settings\Site');

if (version_compare($oldVersion, '3.3.1.1', '<')) {
    $sql = <<<'SQL'
UPDATE `site_setting`
SET
    `id` = REPLACE(
        `id`,
        "next_breadcrumbs_",
        "menu_breadcrumbs_"
    )
WHERE
    `id` LIKE "next\_breadcrumbs\_%";
SQL;
    $result = $connection->executeQuery($sql);
    if ($result) {
        $message = new Message(
            'The settings for "Breadcrumbs" were upgraded.' // @translate
        );
        $messenger->addWarning($message);
    }
}

if (version_compare($oldVersion, '3.3.1.2', '<')) {
    $sites = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
    foreach ($sites as $siteId) {
        $siteSettings->setTargetId($siteId);
        // In some cases, menus are too big to use site settings, but in fact
        // it's ligher to use site settings because the previous menu may be
        // cached..
        /*
        $sql = <<<'SQL'
SELECT value
FROM `site_setting`
WHERE `id` = "menu_menus"
    AND `site_id` = site_id;
SQL;
        $menus = $connection->executeQuery($sql, ['site_id' => $siteId])->fetchOne();
        if ($menus === false) {
            continue;
        }
        $menus = json_decode($menus, true);
        */
        $menus = $siteSettings->get('menu_menus', []);
        foreach ($menus as $name => $menu) {
            $siteSettings->set('menu_menu:' . $name, $menu);
        }
        $siteSettings->delete('menu_menus');
    }
}

if (version_compare($oldVersion, '3.3.5', '<')) {
    $settings->set('menu_property_itemset', $settings->get('next_property_itemset', ''));

    $urlHelper = $services->get('ViewHelperManager')->get('url');
    $message = new Message(
        'The helper "PrimaryItemSet" was moved from module %1$sNext%2$s and a param is added for it in %3$smain settings%2$s.', // @translate
        '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-Next" target="_blank">',
        '</a>',
        '<a href="' . $urlHelper('admin/default', ['controller' => 'setting', 'action' => 'browse'], ['fragment' => 'module-menu']) . '">'
    );
    $message->setEscapeHtml(false);
    $messenger->addWarning($message);
}

if (version_compare($oldVersion, '3.3.6', '<')) {
    $urlHelper = $services->get('ViewHelperManager')->get('url');
    $message = new Message(
        'A %1$ssetting%2$s has been added to update related resources when saving menu.', // @translate
        '<a href="' . $urlHelper('admin/default', ['controller' => 'setting', 'action' => 'browse'], ['fragment' => 'module-menu']) . '">',
        '</a>'
    );
    $message->setEscapeHtml(false);
    $messenger->addWarning($message);
}
