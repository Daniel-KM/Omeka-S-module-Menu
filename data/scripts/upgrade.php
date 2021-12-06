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
 */
$services = $serviceLocator;
$connection = $services->get('Omeka\Connection');

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
