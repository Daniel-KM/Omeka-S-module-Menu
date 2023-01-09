<?php declare(strict_types=1);

namespace Menu\Service\Form\Element;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Menu\Form\Element\MenuSelect;

class MenuSelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $currentSite = $services->get('ControllerPluginManager')->get('currentSite');
        $currentSite = $currentSite();
        if ($currentSite) {
            /** @var \Doctrine\DBAL\Connection $connection */
            $connection = $services->get('Omeka\Connection');
            $sql = <<<'SQL'
SELECT 'SUBSTRING(id, 11), 'SUBSTRING(id, 11)
FROM `site_setting`
WHERE `site_id` = :site_id;
    AND `id` LIKE :menu
SQL;
            $menuNames = $connection->executeQuery($sql, [
                'site_id' => $currentSite->id(),
                'menu' => 'menu\_menu:%',
            ])->fetchAllKeyValue();
        } else {
            $menuNames = [];
        }
        $options['value_options'] = $menuNames;
        return new MenuSelect(null, $options ?? []);
    }
}
