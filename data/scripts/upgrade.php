<?php declare(strict_types=1);

namespace Menu;

use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Message;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $serviceLocator
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\Settings\SiteSettings $siteSettings
 */
$services = $serviceLocator;
$connection = $services->get('Omeka\Connection');
$entityManager = $services->get('Omeka\EntityManager');
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
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
        $messenger = new Messenger();
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
