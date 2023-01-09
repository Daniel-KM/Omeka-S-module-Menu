<?php declare(strict_types=1);

namespace Menu\Controller\SiteAdmin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
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
        $formDeleteSelected = $this->getConfirmFormMultiple();
        return new ViewModel([
            'site' => $site,
            'menus' => $menus,
            'formDeleteSelected' => $formDeleteSelected,
        ]);
    }

    public function showAction()
    {
        $name = $this->params()->fromRoute('menu-slug');
        $menu = $this->siteSettings()->get('menu_menu:' . $name);
        if (!is_array($menu)) {
            throw new NotFoundException();
        }

        $site = $this->currentSite();
        $confirmForm = $this->getConfirmForm($name);
        return new ViewModel([
            'site' => $site,
            'confirmForm' => $confirmForm,
            'name' => $name,
            // Get jstree dynamically.
            // 'jstree' => $this->navigationTranslator()->toJstree($site, $menu),
            'jstree' => null,
        ]);
    }

    public function addAction()
    {
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
            $jstree = empty($formData['jstree']) ? [] : json_decode($formData['jstree'], true);
        } else {
            $name = '';
            $jstree = [];
        }

        $site = $this->currentSite();
        $menuSite = $this->navigationTranslator()->fromJstree($jstree);
        return new ViewModel([
            'site' => $site,
            'form' => $form,
            'name' => $name,
            // The default jstree shouldn't be null because it can't be fetched.
            'jstree' => $jstree,
            'linkedPages' => $this->linkedPagesInMenu($site, $menuSite),
            'notLinkedPages' => $this->notLinkedPagesInMenu($site, $menuSite),
        ]);
    }

    public function editAction()
    {
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
            $jstree = empty($formData['jstree']) ? [] : json_decode($formData['jstree'], true);
        } else {
            $jstree = null;
            $form->setData([
                'name' => $name,
                'jstree' => '',
            ]);
        }

        $site = $this->currentSite();
        $confirmForm = $this->getConfirmForm($name);
        $menuSite = $this->navigationTranslator()->fromJstree($jstree);
        return new ViewModel([
            'site' => $site,
            'form' => $form,
            'confirmForm' => $confirmForm,
            'name' => $name,
            'jstree' => $jstree,
            'linkedPages' => $this->linkedPagesInMenu($site, $menuSite),
            'notLinkedPages' => $this->notLinkedPagesInMenu($site, $menuSite),
        ]);
    }

    public function deleteConfirmAction()
    {
        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);

        $name = $this->params()->fromRoute('menu-slug');
        $menu = $this->siteSettings()->get('menu_menu:' . $name);
        if (!is_array($menu)) {
            throw new NotFoundException();
        }

        // Cannot use default confirm details: menu is not a resource.
        $site = $this->currentSite();
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

    public function batchDeleteAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $menus = $this->params()->fromPost('menus', []);
        if (!$menus) {
            $this->messenger()->addError('You must select at least one menu to batch delete.'); // @translate
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $form = $this->getConfirmFormMultiple();
        $form->setData($this->getRequest()->getPost());
        if ($form->isValid()) {
            /** @var \Omeka\Mvc\Controller\Plugin\Settings $siteSettings */
            $siteSettings = $this->siteSettings();
            foreach ($menus as $menu) {
                $siteSettings->delete('menu_menu:' . $menu);
            }
            $this->messenger()->addSuccess(sprintf(
                $this->translate('%d menus successfully deleted'), // @translate
                count($menus)
            ));
        } else {
            $this->messenger()->addFormErrors($form);
        }
        return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
    }

    public function showDetailsAction()
    {
        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);
        $name = $this->params()->fromRoute('menu-slug');
        $menu = $this->siteSettings()->get('menu_menu:' . $name);
        if (!is_array($menu)) {
            throw new NotFoundException();
        }

        $site = $this->currentSite();
        $view = new ViewModel([
            'site' => $site,
            'linkTitle' => $linkTitle,
            'name' => $name,
            'menu' => $menu,
        ]);
        return $view
            ->setTerminal(true);
    }

    public function topsToMenusAction()
    {
        /** @var \Omeka\Mvc\Controller\Plugin\Settings $siteSettings */
        $siteSettings = $this->siteSettings();

        $name = $this->params()->fromRoute('menu-slug');
        $menu = $siteSettings->get('menu_menu:' . $name);
        if (!is_array($menu)) {
            throw new NotFoundException();
        }

        if (!count($menu)) {
            $this->messenger()->addWarning(new Message(
                'This menu is empty and cannot be divided.' // @translate
            ));
            $params = $this->params()->fromRoute();
            $params['action'] = 'browse';
            return $this->redirect()->toRoute('admin/site/slug/menu-id', $params, true);
        }

        /**
         * @var \Omeka\Api\Representation\SiteRepresentation $site
         * @var \Omeka\Site\NavigationLinkManager$linkManager
         * @var \Menu\Mvc\Controller\Plugin\NavigationTranslator $navTranslator
         */
        $site = $this->currentSite();
        $linkManager = $site->getServiceLocator()->get('Omeka\Site\NavigationLinkManager');
        $navTranslator = $this->navigationTranslator();

        $newMenuNames = [];
        foreach ($menu as $name => $subMenuData) {
            if ($linkManager->has($subMenuData['type'] ?? '')) {
                $linkType = $linkManager->get($subMenuData['type']);
                $menuName = $navTranslator->getLinkLabel($linkType, $subMenuData['data'], $site);
            } elseif (!empty($subMenuData['data']['label'])) {
                $menuName = $subMenuData['data']['label'];
            } else {
                $menuName = $this->translate('[no label]'); // @translate
            }
            $newMenuNames[] = $this->checkAndSaveMenu($menuName, $subMenuData['links'] ?? []);
        }

        if (count($menu) <= 1) {
            $this->messenger()->addSuccess(new Message(
                'Menu "%s" successfully created', // @translate
                reset($newMenuNames)
            ));
        } else {
            $this->messenger()->addSuccess(new Message(
                'Menus "%s" successfully created', // @translate
                implode('", "', $newMenuNames)
            ));
        }

        $params = $this->params()->fromRoute();
        $params['action'] = 'browse';
        return $this->redirect()->toRoute('admin/site/slug/menu-id', $params, true);
    }

    /**
     * @see \Thesaurus\Controller\Admin\ThesaurusController::jstreeAction()
     */
    public function jstreeAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            throw new NotFoundException;
        }

        $name = $this->params()->fromRoute('menu-slug');
        $menu = $this->siteSettings()->get('menu_menu:' . $name);
        if (!is_array($menu)) {
            throw new NotFoundException();
        }

        return new JsonModel(
            $this->navigationTranslator()->toJstree($this->currentSite(), $menu)
        );
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

        // Update the resources if wanted.
        /** @var \Omeka\Mvc\Controller\Plugin\Settings $settings */
        $settings = $this->settings();

        $updateResources = $settings->get('menu_update_resources');
        if (!in_array($updateResources, ['yes', 'template_intersect', 'template_properties'])) {
            return $newName;
        }

        $broaderTerms = $settings->get('menu_properties_broader') ?: [];
        $narrowerTerms = $settings->get('menu_properties_narrower') ?: [];
        if (!$broaderTerms && !$narrowerTerms) {
            $this->messenger()->addWarning(new Message(
                'The settings require to update the resources when saving menu, but no properties are defined.' // @translate
            ));
            return $newName;
        }

        $job = $this->jobDispatcher()->dispatch(\Menu\Job\MenuUpdateTreeInResources::class, [
            'siteId' => $this->currentSite()->id(),
            'menu' => $newName,
        ]);

        $urlHelper = $this->url();
        // TODO Don't use PsrMessage for now to fix issues with Doctrine and inexisting file to remove.
        $message = new Message(
            'Processing update of relations of the resources, if any, in background (job %1$s#%2$d%3$s, %4$slogs%3$s).', // @translate
            sprintf(
                '<a href="%s">',
                htmlspecialchars($urlHelper->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))
            ),
            $job->getId(),
            '</a>',
            sprintf(
                '<a href="%s">',
                // Check if module Log is enabled (avoid issue when disabled).
                htmlspecialchars(class_exists(\Log\Stdlib\PsrMessage::class)
                    ? $urlHelper->fromRoute('admin/log/default', [], ['query' => ['job_id' => $job->getId()]])
                    : $urlHelper->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId(), 'action' => 'log'])
            ))
        );
        $message->setEscapeHtml(false);
        $this->messenger()->addSuccess($message);

        return $newName;
    }

    /**
     * @return string The cleaned name.
     */
    protected function checkAndSaveMenu(string $name, array $menu): string
    {
        /** @var \Omeka\Mvc\Controller\Plugin\Settings $siteSettings */
        $siteSettings = $this->siteSettings();
        $newName = $this->slugifyName($name);
        $existingMenu = $siteSettings->get('menu_menu:' . $newName);
        if (is_array($existingMenu)) {
            $newName .= '-' . substr(bin2hex(\Laminas\Math\Rand::getBytes(20)), 0, 8);
        }
        $siteSettings->set('menu_menu:' . $newName, $menu);
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
            ->setAttribute('action', $this->url()->fromRoute('admin/site/slug/menu-id', ['menu-slug' => $menuName, 'action' => 'delete'], true));
        $confirmForm
            ->setButtonLabel('Confirm delete'); // @translate
        return $confirmForm;
    }

    /**
     * deleteConfirm() cannot be use when not a resource, so prepare it here.
     */
    protected function getConfirmFormMultiple(): \Omeka\Form\ConfirmForm
    {
        /** @var \Omeka\Form\ConfirmForm $confirmForm */
        $confirmForm = $this
            ->getForm(\Omeka\Form\ConfirmForm::class)
            ->setAttribute('action', $this->url()->fromRoute('admin/site/slug/menu', ['action' => 'batch-delete'], true));
        $confirmForm
            ->setButtonLabel('Confirm delete'); // @translate
        return $confirmForm
            ->setAttribute('id', 'confirm-delete-selected');
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
        $iterate = function ($linksIn) use (&$iterate, &$linkedPages, $pages): void {
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
            'index', 'browse', 'show', 'show-details', 'add', 'edit',
            'delete', 'delete-confirm', 'batch-edit', 'batch-delete',
            'jstree', 'menu', 'tops-to-menus',
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
