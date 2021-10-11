<?php declare(strict_types=1);

namespace Menu\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Laminas\Mvc\I18n\Translator as I18n;
use Laminas\View\Helper\Url;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Site\Navigation\Link\LinkInterface;
use Omeka\Site\Navigation\Link\Manager as LinkManager;

/**
 * Genericized from \Omeka\Site\Navigation\Translator
 * @see \Omeka\Site\Navigation\Translator
 */
class NavigationTranslator extends AbstractPlugin
{
    /**
     * @var LinkManager
     */
    protected $linkManager;

    /**
     * @var I18n
     */
    protected $i18n;

    /**
     * @var Url
     */
    protected $urlHelper;

    public function __construct(LinkManager $linkManager, I18n $i18n, Url $urlHelper)
    {
        $this->linkManager = $linkManager;
        $this->i18n = $i18n;
        $this->urlHelper = $urlHelper;
    }

    public function __invoke(): self
    {
        return $this;
    }

    /**
     * @deprecated Since Omeka v3.0 Use toLaminas() instead.
     */
    public function toZend(SiteRepresentation $site, ?array $menu = null): array
    {
        return $this->toLaminas($site, $menu);
    }

    /**
     * Translate Omeka site navigation or any other menu to Laminas Navigation format.
     *
     * @param null|array|string|bool $activeUrl Set the active url.
     *   - null (default): use the Laminas mechanism (compare with route);
     *   - true: use current url;
     *   - false: no active page;
     *   - string: If a url is set (generally a relative one), it will be
     *     checked against the real url;
     *   - array: when an array with keys "type" and "data" is set, a quick
     *     check is done against the menu element.
     */
    public function toLaminas(SiteRepresentation $site, ?array $menu = null, $activeUrl = null): array
    {
        if ($activeUrl === true) {
            // The request uri is a relative url.
            // Same as `substr($serverUrl(true), strlen($serverUrl(false)))`.
            $activeUrl = $_SERVER['REQUEST_URI'];
        } elseif (!is_string($activeUrl) && !is_array($activeUrl) && !is_bool($activeUrl)) {
            $activeUrl = null;
        }

        // Only compare common keys.
        $compareData = function ($a, $b) {
            if (is_array($a) && is_array($b)) {
                $aa = array_intersect_key($a, $b);
                $bb = array_intersect_key($b, $a);
                ksort($aa);
                ksort($bb);
                return $aa === $bb;
            }
            return $a === $b;
        };

        $buildLinks = null;
        $buildLinks = function ($linksIn) use (&$buildLinks, $site, $activeUrl, $compareData) {
            $linksOut = [];
            foreach ($linksIn as $key => $data) {
                $linkType = $this->linkManager->get($data['type']);
                $linkData = $data['data'];
                $linksOut[$key] = method_exists($linkType, 'toLaminas') ? $linkType->toLaminas($linkData, $site) : $linkType->toZend($linkData, $site);
                $linksOut[$key]['label'] = $this->getLinkLabel($linkType, $linkData, $site);
                if ($activeUrl !== null) {
                    if (is_array($activeUrl)) {
                        if (!array_key_exists('uri', $data)
                            && $data['type'] === $activeUrl['type']
                            && $compareData($linkData, $activeUrl['data'])
                        ) {
                            $linksOut[$key]['active'] = true;
                        }
                    } elseif (is_string($activeUrl)) {
                        if ($this->getLinkUrl($linkType, $data, $site) === $activeUrl) {
                            $linksOut[$key]['active'] = true;
                        }
                    } elseif ($activeUrl === false) {
                        $linksOut[$key]['active'] = false;
                    }
                }
                if (isset($data['links'])) {
                    $linksOut[$key]['pages'] = $buildLinks($data['links']);
                }
            }
            return $linksOut;
        };
        $nav = is_null($menu) ? $site->navigation() : $menu;
        $links = $buildLinks($nav);

        if (!$links && is_null($menu)) {
            // The site must have at least one page for navigation to work.
            $links = [[
                'label' => $this->i18n->translate('Home'),
                'route' => 'site',
                'params' => [
                    'site-slug' => $site->slug(),
                ],
            ]];
        }
        return $links;
    }

    /**
     * Translate Omeka site navigation or any other menu to jsTree node format.
     */
    public function toJstree(SiteRepresentation $site, ?array $menu = null): array
    {
        $buildLinks = null;
        $buildLinks = function ($linksIn) use (&$buildLinks, $site) {
            $linksOut = [];
            foreach ($linksIn as $data) {
                $linkType = $this->linkManager->get($data['type']);
                $linkData = $data['data'];
                $linksOut[] = [
                    'text' => $this->getLinkLabel($linkType, $data['data'], $site),
                    'data' => [
                        'type' => $data['type'],
                        'data' => $linkType->toJstree($linkData, $site),
                        'url' => $this->getLinkUrl($linkType, $data, $site),
                    ],
                    'children' => $data['links'] ? $buildLinks($data['links']) : [],
                ];
            }
            return $linksOut;
        };
        $nav = is_null($menu) ? $site->navigation() : $menu;
        return $buildLinks($nav);
    }

    /**
     * Translate jsTree node format to Omeka site navigation format.
     */
    public function fromJstree(array $jstree): array
    {
        $buildPages = null;
        $buildPages = function ($pagesIn) use (&$buildPages) {
            $pagesOut = [];
            foreach ($pagesIn as $page) {
                if (isset($page['data']['remove']) && $page['data']['remove']) {
                    // Remove pages set to be removed.
                    continue;
                }
                $pagesOut[] = [
                    'type' => $page['data']['type'],
                    'data' => $page['data']['data'],
                    'links' => $page['children'] ? $buildPages($page['children']) : [],
                ];
            }
            return $pagesOut;
        };
        return $buildPages($jstree);
    }

    /**
     * Get the label for a link.
     *
     * User-provided labels should be used as-is, while system-provided "backup" labels
     * should be translated.
     */
    public function getLinkLabel(LinkInterface $linkType, array $data, SiteRepresentation $site): string
    {
        $label = $linkType->getLabel($data, $site);
        if ($label) {
            return $label;
        }
        return $this->i18n->translate($linkType->getName());
    }

    /**
     * Get the url for a link.
     */
    public function getLinkUrl(LinkInterface $linkType, array $data, SiteRepresentation $site): string
    {
        static $urls = [];

        if (array_key_exists('uri', $data)) {
            return (string) $data['uri'];
        }

        $serial = serialize($data);
        if (isset($urls[$serial])) {
            return $urls[$serial];
        }

        $linkLaminas = method_exists($linkType, 'toLaminas') ? $linkType->toLaminas($data['data'], $site) : $linkType->toZend($data['data'], $site);
        if (empty($linkLaminas['route'])) {
            $urls[$serial] = '';
        } else {
            $urlRoute = $linkLaminas['route'];
            $urlParams = empty($linkLaminas['params']) ? [] : $linkLaminas['params'];
            $urlParams['site-slug'] = $site->slug();
            $urlOptions = empty($linkLaminas['query']) ? [] : ['query' => $linkLaminas['query']];
            $urls[$serial] = $this->urlHelper->__invoke($urlRoute, $urlParams, $urlOptions);
        }
        return $urls[$serial];
    }
}
