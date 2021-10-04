<?php declare(strict_types=1);

namespace Menu\Controller\SiteAdmin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

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
        $menus = $this->siteSettings()->get('menu_menus', []);
        return new ViewModel([
            'site' => $site,
            'menus' => $menus,
        ]);
    }
}
