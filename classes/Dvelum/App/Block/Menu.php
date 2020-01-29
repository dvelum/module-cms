<?php
namespace Dvelum\App\Block;

use Dvelum\View;
use Dvelum\Orm\Model;

/**
 * Menu block
 * @author Kirill A Egorov 2011 DVelum project
 */
class Menu extends AbstractAdapter
{
	protected $template = 'menu.php';
	
	protected $data = [];
	
	const CAN_USE_CACHE = true;
	const DEPENDS_ON_PAGE = true;
	
	const CACHE_KEY = 'block_menu_';
	
	
	static public function getCacheKey($id){
		return md5(self::CACHE_KEY . '_' . $id);
	}
	
	protected function collectData()
	{
        /**
         * @var \Dvelum\App\Model\Menu
         */
		return Model::factory('Menu')->getCachedMenuLinks($this->config['menu_id']);
	}

	/**
	 * @inheritDoc
	 */
	public function render() : string
	{
		$data = $this->collectData();

		$tpl = View::factory();
        $tpl->setData([
            'config' => $this->config,
            'place' => $this->config['place'],
            'menuData' => $data
        ]);
        /**
         * @var \Dvelum\App\Model\Page $pageModel
         */
        $pageModel =  Model::factory('Page');
        if(static::DEPENDS_ON_PAGE){
            $tpl->set('page' , \Dvelum\Page\Page::factory());
            $tpl->set('pagesTree' ,$pageModel->getTree());
        }
        return $tpl->render('public/'. $this->template);
	}
}