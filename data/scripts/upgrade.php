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
if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.91')) {
    $message = new \Omeka\Stdlib\Message(
        'The module "%1$s" requires the module "%2$s", version %3$s or above.', // @translate
        'Menu', 'Common', '3.4.91'
    );
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
}

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
        '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-Next" target="_blank" rel="noopener">',
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

if (version_compare($oldVersion, '3.4.12', '<')) {
    // Migrate breadcrumbs settings from BlockPlus to Menu module.
    $sites = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
    foreach ($sites as $siteId) {
        $siteSettings->setTargetId($siteId);
        // Don't process upgrade twice: skip the sites already migrated.
        if ($siteSettings->get('menu_breadcrumbs_crumbs') !== null) {
            continue;
        }
        $crumbs = $siteSettings->get('blockplus_breadcrumbs_crumbs');
        if ($crumbs !== null) {
            $siteSettings->set('menu_breadcrumbs_crumbs', $crumbs);
        }
        $prepend = $siteSettings->get('blockplus_breadcrumbs_prepend');
        if ($prepend !== null) {
            $siteSettings->set('menu_breadcrumbs_prepend', $prepend);
        }
        $collectionsUrl = $siteSettings->get('blockplus_breadcrumbs_collections_url');
        if ($collectionsUrl !== null) {
            $siteSettings->set('menu_breadcrumbs_collections_url', $collectionsUrl);
        }
        $separator = $siteSettings->get('blockplus_breadcrumbs_separator');
        if ($separator !== null) {
            $siteSettings->set('menu_breadcrumbs_separator', $separator);
        }
        $homepage = $siteSettings->get('blockplus_breadcrumbs_homepage');
        if ($homepage !== null) {
            $siteSettings->set('menu_breadcrumbs_homepage', $homepage);
        }
    }
    $message = new Message(
        'Breadcrumbs were moved from module Block Plus to module Menu. Settings were migrated.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.15', '<')) {
    $sites = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
    foreach ($sites as $siteId) {
        $siteSettings->setTargetId($siteId);
        if ($siteSettings->get('menu_breadcrumbs_collections_label', null) === null) {
            $siteSettings->set('menu_breadcrumbs_collections_label', 'Collections');
        }
    }
}

if (version_compare($oldVersion, '3.4.16', '<')) {
    // The separator of the breadcrumbs is now escaped and output in a span, so
    // a html entity or a tag set in an older version would be displayed as is.
    // Convert the old values into the plain character they were rendering.
    $updatedSeparators = [];
    $sites = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
    foreach ($sites as $siteId) {
        $siteSettings->setTargetId($siteId);
        $separator = $siteSettings->get('menu_breadcrumbs_separator');
        if (!is_string($separator) || $separator === '') {
            continue;
        }
        // Remove the tags first, else decoding may create new ones.
        $newSeparator = trim(html_entity_decode(strip_tags($separator), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($newSeparator !== $separator) {
            $siteSettings->set('menu_breadcrumbs_separator', $newSeparator);
            $updatedSeparators[] = sprintf('#%d: "%s" => "%s"', $siteId, $separator, $newSeparator);
        }
    }
    if ($updatedSeparators) {
        $message = new Message(
            'The separator of the breadcrumbs is now a plain string, escaped and not rendered as html. It was converted for these sites: %s.', // @translate
            implode('; ', $updatedSeparators)
        );
        $messenger->addWarning($message);
    }

    $themesPath = OMEKA_PATH . '/themes';

    // Warn about themes overriding the breadcrumbs partial with an outdated
    // version. The up-to-date partial builds the markup from the "links"
    // variable prepared by the helper, so an override without it misses the new
    // accessibility markup and separator handling.
    $outdatedThemes = [];
    foreach (glob($themesPath . '/*/view/common/breadcrumbs.phtml') ?: [] as $file) {
        $theme = basename(dirname($file, 3));
        if (substr($theme, -4) === '_bkp') {
            continue;
        }
        if (strpos((string) file_get_contents($file), '$links') === false) {
            $outdatedThemes[$theme] = true;
        }
    }
    if ($outdatedThemes) {
        $message = new Message(
            'The following themes override "view/common/breadcrumbs.phtml" with an outdated version and should be updated to the new Breadcrumbs helper: %s.', // @translate
            implode(', ', array_keys($outdatedThemes))
        );
        $messenger->addWarning($message);
    }

    // Warn about theme templates calling breadcrumbs() with unknown options.
    // The only valid keys for a crumb inside "prepend" are "uri" and "label"; a
    // common mistake is to use "url" instead of "uri", which is then ignored.
    $validOptions = [
        'home', 'collections', 'collections_url', 'collections_label',
        'collections_item_set_property', 'collections_item_set_value',
        'itemset', 'itemsetstree', 'current', 'homepage', 'separator',
        'prepend', 'property_itemset', 'aria_label', 'schema_org', 'partial',
        'template', 'linkLast', 'minDepth',
        // Valid sub-keys for a "prepend" crumb.
        'uri', 'label',
    ];
    $badCalls = [];
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($themesPath, \FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->getExtension() !== 'phtml'
            || strpos($fileInfo->getPathname(), '/src/') !== false
        ) {
            continue;
        }
        $content = (string) file_get_contents($fileInfo->getPathname());
        $offset = 0;
        $length = strlen($content);
        $unknown = [];
        while (($pos = strpos($content, 'breadcrumbs(', $offset)) !== false) {
            // Walk the call arguments tracking parenthesis depth so keys are
            // only read at the options-array level (depth 1). Keys nested in a
            // function call such as $url(['controller' => …]) are at a deeper
            // depth and must be ignored; only breadcrumbs option keys and the
            // "prepend" crumb sub-keys ("uri", "label") are checked.
            $depth = 1;
            $i = $pos + strlen('breadcrumbs(');
            while ($i < $length && $depth > 0) {
                $char = $content[$i];
                if ($char === "'" || $char === '"') {
                    // Read a quoted string, honouring backslash escapes.
                    $quote = $char;
                    $j = $i + 1;
                    $string = '';
                    while ($j < $length) {
                        if ($content[$j] === '\\') {
                            $j += 2;
                            continue;
                        }
                        if ($content[$j] === $quote) {
                            break;
                        }
                        $string .= $content[$j];
                        $j++;
                    }
                    // A key is a string directly followed by "=>" at depth 1.
                    $k = $j + 1;
                    while ($k < $length && ctype_space($content[$k])) {
                        $k++;
                    }
                    if ($depth === 1
                        && substr($content, $k, 2) === '=>'
                        && preg_match('/^[a-zA-Z_]+$/', $string)
                        && !in_array($string, $validOptions, true)
                    ) {
                        $unknown[$string] = true;
                    }
                    $i = $j + 1;
                    continue;
                }
                if ($char === '(') {
                    $depth++;
                } elseif ($char === ')') {
                    $depth--;
                }
                $i++;
            }
            $offset = $i;
        }
        if ($unknown) {
            $relative = ltrim(str_replace($themesPath, '', $fileInfo->getPathname()), '/');
            $badCalls[] = sprintf('%s (%s)', $relative, implode(', ', array_keys($unknown)));
        }
    }
    if ($badCalls) {
        $message = new Message(
            'The breadcrumbs() helper is called with unknown options in these theme templates (check for typos, for example "url" instead of "uri"): %s.', // @translate
            implode('; ', $badCalls)
        );
        $messenger->addWarning($message);
    }
}
