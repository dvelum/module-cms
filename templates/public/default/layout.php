<?php
/**
 * @var \Dvelum\Page\Page $page
 */
$page = $this->get('page');
/**
 * @var \Dvelum\Request $request
 */
$request = $this->get('request');
/**
 * @var \Dvelum\Resource $resource
 */
$resource = $this->get('resource');

$wwwRoot = $request->wwwRoot();
$robots = $page->getRobots();
$htmlTitle = $page->getHtmlTitle();
$metaDescription = $page->getMetaDescription();
$metaKeywords = $page->getMetaKeywords();
$canonical = $page->getCanonical();
$securityToken = $page->getCsrfToken();

$resource->addCss('/resources/dvelum-module-cms/css/public/main/reset.css' ,0);
$resource->addCss('/resources/dvelum-module-cms/css/public/main/style.css' ,100);
$resource->addJs('/resources/dvelum-module-cms/js/app/frontend/common.js',10);

/**
 * @var \Dvelum\App\BlockManager $blockManager
 */
$blockManager = $this->get('blockManager');

$layoutCls = '';
$hasSideLeft = $blockManager->hasBlocks('left-blocks');
$hasSideRight = $blockManager->hasBlocks('right-blocks');

if($hasSideLeft && !$hasSideRight){
    $resource->addCss('/resources/dvelum-module-cms/css/public/main/side-left.css' ,101);
}elseif(!$hasSideLeft && $hasSideRight){
    $resource->addCss('/resources/dvelum-module-cms/css/public/main/side-right.css' ,101);
}elseif($hasSideLeft && $hasSideRight){
    $resource->addCss('/resources/dvelum-module-cms/css/public/main/side-left-right.css' ,101);
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width; initial-scale=1.0"/>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <?php

    echo $page->openGraph()->__toString();

    if (!empty($securityToken)) {
        echo '<meta name="csrf-token" content="' . $securityToken . '"/>' . PHP_EOL;
    }

    if (!empty($metaDescription)) {
        echo '<meta name="description" content="' . $metaDescription . '"/>' . PHP_EOL;
    }

    if (!empty($metaKeywords)) {
        echo '<meta name="keywords" content="' . $metaKeywords . '"/>' . PHP_EOL;
    }

    if (!empty($robots)) {
        echo '<meta name="robots" content="' . $robots . '" />';
    }
    if (!empty($canonical)) {
        echo '<link rel="canonical" href="' . $canonical . '"/>';
    }
    ?>
    <title><?php echo $page->getHtmlTitle(); ?></title>
    <link rel="shortcut icon" href="<?php echo $wwwRoot; ?>i/favicon.png"/>
    <?php echo $this->resource->includeCss(); ?>
    <?php echo $this->get('resource')->includeJsByTag(true, false, 'head'); ?>
    <?php echo $this->get('resource')->includeJs(true, false); ?>
</head>
<body>
    <div class="page">
<?php
            $t = \Dvelum\View::factory();
            echo $this->renderTemplate(
                'public/default/header.php',
                [
                    'blocks' => $blockManager->getBlocksHtml('top-blocks')
                ]
            );
        ?>

            <div class="layout">

                <?php
                if($hasSideLeft){
                    echo $this->renderTemplate(
                        'public/default/side_left.php',
                        [
                            'blocks' => $blockManager->getBlocksHtml('left-blocks')
                        ]
                    );
                }
                ?>

                <div class="content-wrap">
                    <section id="content" class=" content">
                        <?php
                        if(empty($page->func_code)){
                            echo '<header><h1>'.$page->getTitle().'</h1></header>';
                        }
                        ?>
                        <div class="text"><?php echo $page->getText();?></div>
                    </section>
                </div>

                <?php
                if($hasSideRight){
                    echo $this->renderTemplate(
                        'public/default/side_right.php',
                        [
                            'blocks' => $blockManager->getBlocksHtml('right-blocks')
                        ]
                    );
                }
                ?>
            </div>
    </div><!--end:page-->
    <?php
    echo $this->renderTemplate(
        'public/default/footer.php',
        [
            'blocks' => $blockManager->getBlocksHtml('bottom-blocks')
        ]
    );
    ?>
<?php echo $resource->includeJs(true , true); ?>
</body>
</html>