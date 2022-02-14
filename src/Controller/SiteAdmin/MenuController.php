<?php declare(strict_types=1);

namespace Menu\Controller\SiteAdmin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Menu\Form\MenuForm;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Mvc\Exception\NotFoundException;
use Omeka\Stdlib\Message;

class MenuController extends AbstractActionController
{
    public function indexAction()
    {
        $params = $this->params()->fromRoute();
        $params['action'] = 'browse';
        return $this->forward()->dispatch(__CLASS__, $params);
    }

    public function browseAction()
    {
        $site = $this->currentSite();
        $menus = $this->listMenus($site);
        return new ViewModel([
            'site' => $site,
            'menus' => $menus,
        ]);
    }

    public function showAction()
    {
        $site = $this->currentSite();
        $name = $this->params()->fromRoute('menu-slug');
        $menu = $this->siteSettings()->get('menu_menu:' . $name);
        if (!is_array($menu)) {
            throw new NotFoundException();
        }

        $menu = $this->navigationTranslator()->toJstree($site, $menu);
        $confirmForm = $this->getConfirmForm($name);
        return new ViewModel([
            'site' => $site,
            'confirmForm' => $confirmForm,
            'name' => $name,
            // JsTree menu.
            'menu' => $menu,
        ]);
    }

    public function addAction()
    {
        $site = $this->currentSite();
        $form = $this->getForm(MenuForm::class);
        if ($this->getRequest()->isPost()) {
            $name = $this->checkAndSaveMenuFromPost($form, true);
            if (is_string($name)) {
                $params = $this->params()->fromRoute();
                $params['menu-slug'] = $name;
                $params['action'] = 'edit';
                return $this->redirect()->toRoute('admin/site/slug/menu-id', $params, true);
            }
            $formData = $this->params()->fromPost();
            $name = $formData['name'];
            $menu = empty($formData['jstree']) ? [] : json_decode($formData['jstree'], true);
        } else {
            $name = '';
            $menu = [];
        }
        $menuSite = $this->navigationTranslator()->fromJstree($menu);
        return new ViewModel([
            'site' => $site,
            'form' => $form,
            'name' => $name,
            // JsTree menu.
            'menu' => $menu,
            'linkedPages' => $this->linkedPagesInMenu($site, $menuSite),
            'notLinkedPages' => $this->notLinkedPagesInMenu($site, $menuSite),
        ]);
    }

    public function editAction()
    {
        $site = $this->currentSite();
        $name = $this->params()->fromRoute('menu-slug');
        $menu = $this->siteSettings()->get('menu_menu:' . $name);
        if (!is_array($menu)) {
            throw new NotFoundException();
        }

        /** @var \Menu\Form\MenuForm $form */
        $form = $this->getForm(MenuForm::class);

        if ($this->getRequest()->isPost()) {
            $name = $this->checkAndSaveMenuFromPost($form, false);
            if (is_string($name)) {
                // Do a redirect in case of a new name.
                $params = $this->params()->fromRoute();
                $params['menu-slug'] = $name;
                $params['action'] = 'edit';
                return $this->redirect()->toRoute('admin/site/slug/menu-id', $params, true);
            }
            $formData = $this->params()->fromPost();
            $name = $formData['name'];
            $menu = empty($formData['jstree']) ? [] : json_decode($formData['jstree'], true);
        } else {
            $menu = $this->navigationTranslator()->toJstree($site, $menu);
            $form->setData([
                'name' => $name,
                'jstree' => json_encode($menu),
            ]);
        }

        $confirmForm = $this->getConfirmForm($name);

        $menuSite = $this->navigationTranslator()->fromJstree($menu);
        return new ViewModel([
            'site' => $site,
            'form' => $form,
            'confirmForm' => $confirmForm,
            'name' => $name,
            // JsTree menu.
            'menu' => $menu,
            'linkedPages' => $this->linkedPagesInMenu($site, $menuSite),
            'notLinkedPages' => $this->notLinkedPagesInMenu($site, $menuSite),
        ]);
    }

    public function deleteConfirmAction()
    {
        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);

        $site = $this->currentSite();
        $name = $this->params()->fromRoute('menu-slug');
        $menu = $this->siteSettings()->get('menu_menu:' . $name);
        if (!is_array($menu)) {
            throw new NotFoundException();
        }

        // Cannot use default confirm details: menu is not a resource.
        $view = new ViewModel([
            'site' => $site,
            'form' => $this->getConfirmForm($name),
            'name' => $name,
            'menu' => $menu,
            'resourceLabel' => 'menu', // @translate
            'partialPath' => 'menu/site-admin/menu/show-details',
            'linkTitle' => $linkTitle,
        ]);
        return $view
            ->setTerminal(true);
    }

    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            $name = $this->params()->fromRoute('menu-slug');
            $form = $this->getConfirmForm($name);
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $this->siteSettings()->delete('menu_menu:' . $name);
                $this->messenger()->addSuccess(new Message(
                    'Menu "%s" successfully deleted', // @translate
                    $name
                ));
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }
        return $this->redirect()->toRoute(
            'admin/site/slug/menu',
            ['action' => 'browse'],
            true
        );
    }

    public function showDetailsAction()
    {
        $site = $this->currentSite();
        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);
        $name = $this->params()->fromRoute('menu-slug');
        $menu = $this->siteSettings()->get('menu_menu:' . $name);
        if (!is_array($menu)) {
            throw new NotFoundException();
        }

        $view = new ViewModel([
            'site' => $site,
            'linkTitle' => $linkTitle,
            'name' => $name,
            'menu' => $menu,
        ]);
        return $view
            ->setTerminal(true);
    }

    protected function checkAndSaveMenuFromPost(MenuForm $form, $isNew = false): ?string
    {
        $formData = $this->params()->fromPost();
        $form->setData($formData);
        if (!$form->isValid()) {
            $this->messenger()->addFormErrors($form);
            return null;
        }

        /** @var \Omeka\Mvc\Controller\Plugin\Settings $siteSettings */
        $siteSettings = $this->siteSettings();
        $name = $this->params()->fromRoute('menu-slug');
        $data = $form->getData();
        $newName = $this->slugifyName($data['name']);
        if ($isNew) {
            $name = $newName;
        } else {
            $menu = $siteSettings->get('menu_menu:' . $name);
            if (!is_array($menu)) {
                throw new NotFoundException();
            }
        }

        $oldName = $name;
        if ($oldName !== $newName) {
            $existingMenu = $siteSettings->get('menu_menu:' . $newName);
            if (is_array($existingMenu)) {
                $newName .= '-' . substr(bin2hex(\Laminas\Math\Rand::getBytes(20)), 0, 8);
                $this->messenger()->addWarning(new Message(
                    'Menu "%s" uses an existing name and was renamed "%s".', // @translate
                    $name, $newName
                ));
            }
        }

        $jstree = empty($data['jstree']) ? [] : json_decode($data['jstree'], true);
        if (!is_array($jstree)) {
            $jstree = [];
        }

        $siteSettings->delete('menu_menu:' . $oldName);
        $menu = $this->navigationTranslator()->fromJstree($jstree);
        $siteSettings->set('menu_menu:' . $newName, $menu);
        $this->messenger()->addSuccess(new Message(
            'Menu "%s" was saved successfully.', // @translate
            $newName
        ));
        return $newName;
    }

    /**
     * deleteConfirm() cannot be use when not a resource, so prepare it here.
     */
    protected function getConfirmForm(string $menuName): \Omeka\Form\ConfirmForm
    {
        /** @var \Omeka\Form\ConfirmForm $confirmForm */
        $confirmForm = $this
            ->getForm(\Omeka\Form\ConfirmForm::class)
            ->setAttribute('action', $this->viewHelpers()->get('url')->__invoke('admin/site/slug/menu-id', ['menu-slug' => $menuName, 'action' => 'delete'], true));
        $confirmForm
            ->setButtonLabel('Confirm delete'); // @translate
        return $confirmForm;
    }

    /**
     * List all pages of the site that are included in the menu.
     *
     * This is the equivalent of SiteRepresentation::linkedPages(), but for any menu.
     *
     * @see \Omeka\Api\Representation\SiteRepresentation::linkedPages()
     */
    protected function linkedPagesInMenu(SiteRepresentation $site, array $menu): array
    {
        static $menus = [];

        if (isset($menus[$site->id()])) {
            return $menus[$site->id()];
        }
        $linkedPages = [];
        $pages = $site->pages();
        $iterate = null;
        $iterate = function ($linksIn) use (&$iterate, &$linkedPages, $pages) {
            foreach ($linksIn as $data) {
                if ('page' === $data['type'] && isset($pages[$data['data']['id']])) {
                    $linkedPages[$data['data']['id']] = $pages[$data['data']['id']];
                }
                if (isset($data['links'])) {
                    $iterate($data['links']);
                }
            }
        };
        $iterate($menu);
        $menus[$site->id()] = $linkedPages;
        return $linkedPages;
    }

    /**
     * List all pages of the site that are not included in the menu.
     *
     * This is the equivalent of SiteRepresentation::notLinkedPages(), but for any menu.
     *
     * @see \Omeka\Api\Representation\SiteRepresentation::notLinkedPages()
     */
    protected function notLinkedPagesInMenu(SiteRepresentation $site, array $menu): array
    {
        return array_diff_key($site->pages(), $this->linkedPagesInMenu($site, $menu));
    }

    /**
     * Get all menus of a site.
     */
    protected function listMenus(SiteRepresentation $site): array
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $site->getServiceLocator()->get('Omeka\Connection');
        $qb = $connection->createQueryBuilder();
        $expr = $qb->expr();
        $qb
            ->select('id', 'SUBSTRING(id, 11)')
            ->from('site_setting', 'site_setting')
            ->where($expr->eq('site_id', ':site_id'))
            ->andWhere($expr->like('id', ':menu'))
            ->orderBy('id', 'asc');
        $menuNames = $connection->executeQuery($qb, [
            'site_id' => $site->id(),
            'menu' => 'menu\_menu:%',
        ])->fetchAllKeyValue();
        $menus = [];
        $siteSettings = $this->siteSettings();
        foreach ($menuNames as $key => $menuName) {
            $menus[$menuName] = $siteSettings->get($key);
        }
        return $menus;
    }

    /**
     * Get all menus of a site.
     *
     * Note: to use a direct sql requires more memory than site settings.
     */
    protected function listMenusViaSql(SiteRepresentation $site): array
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $site->getServiceLocator()->get('Omeka\Connection');
        $qb = $connection->createQueryBuilder();
        $expr = $qb->expr();
        $qb
            ->select('SUBSTRING(id, 10)', 'value')
            ->from('site_setting', 'site_setting')
            ->where($expr->eq('site_id', ':site_id'))
            ->orderBy('id', 'asc');
        $menus = $connection->executeQuery($qb, ['site_id' => $site->id()])->fetchAllKeyValue();
        return array_map(function ($v) {
            return json_decode($v, true);
        }, $menus);
    }

    protected function slugifyName(string $name): string
    {
        $string = $this->slugify($name);
        $reserved = [
            'index', 'browse', 'show', 'show-details', 'add', 'edit', 'delete', 'delete-confirm', 'batch-edit', 'batch-delete',
            'menu',
        ];
        if (in_array($string, $reserved)) {
            $string .= '-' . substr(bin2hex(\Laminas\Math\Rand::getBytes(20)), 0, 8);
            $this->messenger()->addWarning(new Message(
                'Menu "%s" uses a reserved name and was renamed "%s".', // @translate
                $name, $string
            ));
        }
        return $string;
    }

    /**
     * Transform the given string into a valid URL slug
     *
     * Copy from \Omeka\Api\Adapter\SiteSlugTrait::slugify().
     */
    protected function slugify(string $input): string
    {
        if (extension_loaded('intl')) {
            $transliterator = \Transliterator::createFromRules(':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;');
            $slug = $transliterator->transliterate($input);
        } elseif (extension_loaded('iconv')) {
            $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $input);
        } else {
            $slug = $input;
        }
        $slug = mb_strtolower($slug, 'UTF-8');
        $slug = preg_replace('/[^a-z0-9_-]+/u', '-', $slug);
        $slug = preg_replace('/-{2,}/', '-', $slug);
        $slug = preg_replace('/-*$/', '', $slug);
        return $slug;
    }
}
