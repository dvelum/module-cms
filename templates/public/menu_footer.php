<?php

$createFooterNode = function (\Dvelum\Tree\Tree $tree , $parent , \Dvelum\Page\Page $page , \Dvelum\Tree\Tree $pagesTree) use(&$createFooterNode)
{
    $s = '';

    if(!$tree->hasChildren($parent))
        return '';

    $childs = $tree->getChildren($parent);

    ($parent === 0) ? $isSection = true : $isSection = false;


    foreach($childs as $k => $v)
    {
        if(!$v['data']['published'])
            continue;

        if($isSection){
            $s .= '<div class="section">';
        }else{
            $s .= '<li>';
        }

        $class='';

        if($page->getCode() === $v['data']['page_code'] || in_array($v['data']['page_id'] , $pagesTree->getParentsList($page->getId()) , true))
            $class.='active';

        if($v['data']['link_url'] !== false){
            $s .= '<a  href="' . $v['data']['link_url'] . '" class="'.$class.'">' . $v['data']['title'] . '</a>';
        }else{
            $s .=  '<span class="item">' . $v['data']['title'] . '</span>';
        }

        if($tree->hasChildren($v['id'])){
            $s .='<ul>';
            $s .= $createFooterNode($tree , $v['id'] , $page , $pagesTree);
            $s .= '</ul>';
        }



        if($isSection){
            $s .= '</div>';
        }else{
            $s .= '</li>';
        }
    }

    return $s;
};

$pagesTree = $this->get('pagesTree');

$tree = new \Dvelum\Tree\Tree();
$menuData = $this->get('menuData');

if(!empty($menuData))
    foreach($menuData as $k => $v)
        $tree->addItem($v['tree_id'] , $v['parent_id'] , $v , $v['order']);

echo '<div class="menu">'.$createFooterNode($tree , 0 , $this->get('page') , $pagesTree).'</div>';

