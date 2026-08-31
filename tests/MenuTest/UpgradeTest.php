<?php declare(strict_types=1);

namespace MenuTest;

use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests for the upgrade of the settings of the breadcrumbs.
 *
 * The script data/scripts/upgrade.php is included with "require_once" by the
 * module trait, so it can be run only once by process: all the cases are
 * prepared on separate sites, then a single upgrade is checked, like a real
 * upgrade does for all the sites at once.
 */
class UpgradeTest extends AbstractHttpControllerTestCase
{
    use MenuTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        parent::tearDown();
    }

    /**
     * Read a site setting directly in the database.
     *
     * The service of the site settings is shared and keeps the values of the
     * last target id in memory, so a value updated by the upgrade script is not
     * always visible through it inside a single process.
     */
    protected function readSiteSetting(int $siteId, string $id)
    {
        $sql = 'SELECT `value` FROM `site_setting` WHERE `site_id` = :site_id AND `id` = :id';
        $value = $this->getServiceLocator()->get('Omeka\Connection')
            ->executeQuery($sql, ['site_id' => $siteId, 'id' => $id])
            ->fetchOne();
        return $value === false || $value === null
            ? null
            : json_decode($value, true);
    }

    protected function setSiteSettings(int $siteId, array $settings): void
    {
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($siteId);
        foreach ($settings as $id => $value) {
            $siteSettings->set($id, $value);
        }
    }

    public function testUpgradeBreadcrumbsSettings(): void
    {
        // A site still using the settings of module Block Plus.
        $toMigrate = $this->createSite('upgrade-migrate', 'Upgrade migrate');
        $this->setSiteSettings($toMigrate->id(), [
            'blockplus_breadcrumbs_crumbs' => ['home', 'current'],
            'blockplus_breadcrumbs_separator' => '>',
            'blockplus_breadcrumbs_homepage' => true,
        ]);

        // A site already migrated: its settings must not be overwritten.
        $migrated = $this->createSite('upgrade-migrated', 'Upgrade migrated');
        $this->setSiteSettings($migrated->id(), [
            'blockplus_breadcrumbs_crumbs' => ['home', 'current'],
            'menu_breadcrumbs_crumbs' => ['itemset'],
        ]);

        // Sites with a separator set as html in a previous version.
        $separators = [
            'entity' => ['&gt;', '>'],
            'named' => ['&raquo;', '»'],
            'numeric' => ['&#8250;', '›'],
            'tag' => ['<span class="sep">›</span>', '›'],
            'plain' => ['>', '>'],
            'unicode' => ['›', '›'],
        ];
        $separatorSites = [];
        foreach ($separators as $key => [$old, $expected]) {
            $site = $this->createSite('upgrade-sep-' . $key, 'Upgrade separator ' . $key);
            $separatorSites[$key] = $site->id();
            $this->setSiteSettings($site->id(), [
                // Mark the site as already migrated to keep the separator.
                'menu_breadcrumbs_crumbs' => ['home', 'current'],
                'menu_breadcrumbs_separator' => $old,
            ]);
        }

        // A single upgrade, like a real one.
        (new \Menu\Module())->upgrade('3.4.11', '3.4.16', $this->getServiceLocator());

        // The settings of Block Plus are migrated.
        $this->assertSame(['home', 'current'], $this->readSiteSetting($toMigrate->id(), 'menu_breadcrumbs_crumbs'));
        $this->assertSame('>', $this->readSiteSetting($toMigrate->id(), 'menu_breadcrumbs_separator'));
        $this->assertTrue((bool) $this->readSiteSetting($toMigrate->id(), 'menu_breadcrumbs_homepage'));

        // An already migrated site keeps its settings: the guard against a
        // second run must not be inverted.
        $this->assertSame(
            ['itemset'],
            $this->readSiteSetting($migrated->id(), 'menu_breadcrumbs_crumbs'),
            'The settings of an already migrated site should not be overwritten'
        );

        // The html separators are converted into the character they rendered.
        foreach ($separators as $key => [$old, $expected]) {
            $this->assertSame(
                $expected,
                $this->readSiteSetting($separatorSites[$key], 'menu_breadcrumbs_separator'),
                sprintf('Separator "%s" (%s) should be converted to "%s"', $old, $key, $expected)
            );
        }
    }
}
