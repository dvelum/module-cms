<?php

$createNode = function (\Dvelum\Tree\Tree $tree , $parent , \Dvelum\Page\Page $page , \Dvelum\Tree\Tree $pagesTree) use(&$createNode)
{
    $s = '';

    if(!$tree->hasChildren($parent))
        return '';

    $items = $tree->getChildren($parent);

    $s .= '<ul>';

    foreach($items as $k => $v)
    {
        if(!$v['data']['published'])
            continue;

        $class='';

        if($page->getCode() === $v['data']['page_code'] || in_array($v['data']['page_id'] , $pagesTree->getParentsList($page->getId()) , true))
            $class = 'active';

        $s .= '<li><a href="' . $v['data']['link_url'] . '" class="'.$class.'">' . $v['data']['title'] . '</a></li>';

        // if($tree->hasChilds($v['id']))
        // $s.='<li>' . $createNode($tree , $v['id'] , $page, $pagesTree) . '</li>';
    }
    $s .= '</ul>';
    return $s;
};

$pagesTree = $this->get('pagesTree');
$tree = new \Dvelum\Tree\Tree();
$menuData = $this->get('menuData');

if(!empty($menuData))
  foreach($menuData as $k => $v)
    $tree->addItem($v['tree_id'] , $v['parent_id'] , $v , $v['order']);

echo '<nav class="nav top">' . $createNode($tree , 0 , $this->get('page') , $pagesTree) . '</nav>';