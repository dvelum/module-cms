<?php
/**
 *  DVelum project https://github.com/dvelum/dvelum , https://github.com/k-samuel/dvelum , http://dvelum.net
 *  Copyright (C) 2011-2019  Kirill Yegorov
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Dvelum\App\Model;

use Dvelum\Orm;
use Dvelum\Orm\Model;
use Dvelum\Config;
use Dvelum\Resource;
use \Exception;

class Medialib extends Model
{
    static protected $scriptsIncluded = false;

    /**
     * Get item record by its path
     * @param string $path
     * @return int
     */
    public function getIdByPath($path) : int
    {
        $recId = $this->db->fetchOne(
            $this->db->select()
                ->from($this->table(), ['id'])
                ->where('`path` =?', $path)
        );

        return intval($recId);
    }

    /**
     * Add media item
     * @param string $name
     * @param string $path
     * @param integer $size (bytes)
     * @param string $type
     * @param string $ext - extension
     * @param string $hash - file hash, optional default null
     * @return integer
     */
    public function addItem($name, $path, $size, $type, $ext, $category = null, $hash = null)
    {
        $size = number_format(($size / 1024 / 1024), 3);

        $data = [
            'title' => $name,
            'path' => $path,
            'size' => $size,
            'type' => $type,
            'user_id' => \Dvelum\App\Session\User::factory()->getId(),
            'ext' => $ext,
            'date' => date('Y-m-d H:i:s'),
            'category' => $category,
            'hash' => $hash
        ];

        $obj = Orm\Record::factory($this->name);
        $obj->setValues($data);

        if ($obj->save()) {
            return $obj->getId();
        } else {
            return false;
        }
    }

    /**
     * Delete record
     * @param mixed $id record ID
     * @return bool
     */
    public function remove($id): bool
    {
        if (!$id) {
            return false;
        }

        $obj = Orm\Record::factory($this->name, $id);
        $data = $obj->getData();

        if (empty($data)) {
            return false;
        }

        $docRoot = Config::storage()->get('main.php')->get('docRoot');

        if (strlen($data['path'])) {
            @unlink($docRoot . $data['path']);
            if ($data['type'] == 'image') {
                $conf = $this->getConfig()->__toArray();
                foreach ($conf['image']['sizes'] as $k => $v) {
                    @unlink($docRoot . $this->getImgPath($data['path'], $data['ext'], $k));
                }
            }
        }
        $obj->delete();
        return true;
    }

    /**
     * Calculate image path
     * @param string $path
     * @param string $ext
     * @param string $type
     * @param bool $prependWebRoot add wwwRoot prefix, optional
     * @return string
     */
    public function getImgPath(string $path, string $ext, string $type, bool $prependWebRoot = false) : string
    {
        if (empty($ext)) {
            $ext = \Dvelum\File::getExt($path);
        }

        $str = str_replace($ext, '-' . $type . $ext, $path);

        if ($prependWebRoot) {
            return $this->addWebRoot($str);
        } else {
            return $str;
        }
    }

    /**
     * Create url for media file add wwwRoot prefix
     * @param string $itemPath
     * @return string
     */
    public function addWebRoot(string $itemPath) : string
    {
        $request = \Dvelum\Request::factory();
        if ($request->wwwRoot() !== '/') {
            if ($itemPath[0] === '/') {
                $itemPath = substr($itemPath, 1);
            }
            $itemPath = $request->wwwRoot() . $itemPath;
        }
        return $itemPath;
    }

    /**
     * Add author selection join to the query.
     * Used with rev_control objects
     * @param \Dvelum\Db\Select\ | \Zend\Db\Sql\Select $sql
     * @param string $fieldAlias
     * @deprecated
     * @return void
     */
    protected function _queryAddAuthor($sql, $fieldAlias): void
    {
        $sql->joinLeft(
            array(
                'u1' => Model::factory('User')->table()
            ),
            'user_id = u1.id',
            array(
                $fieldAlias => 'u1.name')
        );
    }

    /**
     * Update media item
     * @param integer $id
     * @param array $data
     * @return bool
     */
    public function update($id, array $data) : bool
    {
        if (!$id) {
            return false;
        }
        try {
            $obj = Orm\Record::factory($this->name, $id);
            $obj->setValues($data);
            $obj->save();
            $hLog = Model::factory('Historylog');
            $hLog->log(\Dvelum\App\Session\User::getInstance()->getId(), $id, Historylog::Update, $this->table());
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param \Dvelum\Resource|null $resource
     * @throws \Exception
     */
    public function includeScripts(?\Dvelum\Resource $resource = null) : void
    {
        if(empty($resource)){
            $resource = Resource::factory();
        }

        $version = Config::storage()->get('versions.php')->get('medialib');
        $appConfig = Config::storage()->get('main.php');

        if (self::$scriptsIncluded) {
            return;
        }

        $conf = $this->getConfig()->__toArray();

        $resource->addCss('/js/lib/jquery.Jcrop.css');

        $editor = $appConfig->get('html_editor');

        if ($editor === 'tinymce') {
            $resource->addJs('/js/lib/tiny_mce/tiny_mce.js', 0, true);
            $resource->addJs('/js/lib/ext_ux/Ext.ux.TinyMCE.js', 1, true);
            $resource->addJs('/resources/dvelum-module-cms/js/medialib/HtmlPanel_tinymce.js', 3);
        } elseif ($editor === 'ckeditor') {
            $resource->addJs('/js/lib/ckeditor/ckeditor.js', 100, true, 'external');
            $resource->addJs('/js/lib/ext_ux/ckplugin.js', 1, true);
            $resource->addJs('/resources/dvelum-module-cms/js/medialib/HtmlPanel_ckeditor.js', 3, false);
        }

        // $resource->addJs('/js/lib/ext_ux/AjaxFileUpload.js',1,false);
        $resource->addJs('/js/app/system/SearchPanel.js', 1);
        $resource->addJs('/js/lib/ext_ux/AjaxFileUpload.js', 1);
        $resource->addJs('/js/app/system/ImageField.js', 1);
        $resource->addJs('/resources/dvelum-module-cms/js/Medialib.js?v=' . $version, 2);
        $resource->addJs('/js/lib/jquery.js', 1 , true, 'external');
        $resource->addJs('/js/lib/jquery.Jcrop.min.js', 2, true, 'external');

        $resource->addInlineJs('
            Ext.ns("app");
            app.maxFileSize = "' . ini_get('upload_max_filesize') . '";
            app.mediaConfig = ' . json_encode($conf) . ';
            app.imageSize = ' . json_encode($conf['image']['sizes']) . ';
            app.medialibControllerName = "medialib";
        ');
        self::$scriptsIncluded = true;
    }

    /**
     * Resize action
     * @param array $types
     * @return int
     */
    public function resizeImages($types = false): int
    {
        $data = Model::factory('Medialib')->query()
            ->filters(['type' => 'image'])
            ->fields(['path', 'ext'])
            ->fetchAll();

        ini_set('max_execution_time', 18000);
        ini_set('ignore_user_abort', 'On');
        ini_set('memory_limit', '384M');

        $conf = $this->getConfig()->__toArray();

        $thumbSizes = $conf['image']['sizes'];
        $count = 0;

        $appConfig = Config::storage()->get('main.php');

        $uploadsDir =  $appConfig->get('wwwpath');

        foreach ($data as $v) {

            $path = str_replace('//','/',$uploadsDir . $v['path']);
            if (!file_exists($path)) {
                continue;
            }

            if ($types && is_array($types)) {
                foreach ($types as $typename)
                {
                    if (isset($thumbSizes[$typename]))
                    {
                        $saveName = str_replace($v['ext'], '-' . $typename . $v['ext'], $path);
                        switch($typename){
                            case 'crop' :
                                \Dvelum\Image\Resize::resize($path, $thumbSizes[$typename][0], $thumbSizes[$typename][1], $saveName, true,true);
                                break;
                            case 'resize_fit':
                                \Dvelum\Image\Resize::resize($path, $thumbSizes[$typename][0], $thumbSizes[$typename][1], $saveName, true, false);
                                break;
                            case 'resize':
                                \Dvelum\Image\Resize::resize($path, $thumbSizes[$typename][0], $thumbSizes[$typename][1], $saveName, false ,false);
                                break;
                        }
                    }
                }
            } else {
                foreach ($thumbSizes as $k => $item)
                {
                    $saveName = str_replace($v['ext'], '-' . $k . $v['ext'], $path);

                    switch($conf['image']['thumb_types'][$k]){
                        case 'crop' :
                            \Dvelum\Image\Resize::resize($path, $item[0], $item[1], $saveName, true,true);
                            break;
                        case 'resize_fit':
                            \Dvelum\Image\Resize::resize($path, $item[0], $item[1], $saveName,true, false);
                            break;
                        case 'resize':
                            \Dvelum\Image\Resize::resize($path, $item[0], $item[1], $saveName, false ,false);
                            break;
                    }
                }
            }
            $count++;
        }
        return $count;
    }

    /**
     * Crop image and create thumbs
     * @param array $srcData - media library record
     * @param integer $x
     * @param integer $y
     * @param integer $w
     * @param integer $h
     * @return  bool
     */
    public function cropAndResize($srcData, $x, $y, $w, $h, $type): bool
    {
        $appConfig = Config::storage()->get('main.php');
        ini_set('max_execution_time', 18000);
        ini_set('memory_limit', '384M');
        $docRoot = $appConfig['wwwpath'];
        $conf = $this->getConfig()->__toArray();
        $thumbSizes = $conf['image']['sizes'];

        $path = $docRoot . $srcData['path'];

        if (!file_exists($path)) {
            false;
        }

        $tmpPath = $appConfig['tmp'] . basename($path);

        $path = str_replace('//', '/', $path);

        if (!\Dvelum\Image\Resize::cropImage($path, $tmpPath, $x, $y, $w, $h)) {
            return false;
        }

        if (!isset($thumbSizes[$type])) {
            return false;
        }

        $saveName = str_replace($srcData['ext'], '-' . $type . $srcData['ext'], $path);
        if (!\Dvelum\Image\Resize::resize($tmpPath, $thumbSizes[$type][0], $thumbSizes[$type][1], $saveName, true, false)) {
            return false;
        }

        unlink($tmpPath);
        return true;
    }

    /**
     * Update modification date
     * @param integer $id
     * @return void
     */
    public function updateModifyDate($id)
    {
        $obj = Orm\Record::factory($this->name, $id);
        $obj->set('modified', date('Y-m-d h:i:s'));
        $obj->save();
    }

    /**
     * Mark object as hand croped
     * @param integer $id
     * @return void
     */
    public function markCroped($id) : void
    {
        $obj = Orm\Record::factory($this->name, $id);
        $obj->set('croped', 1);
        $obj->save();
    }

    /**
     * Get media library config
     * @return Config\ConfigInterface
     */
    public function getConfig() : Config\ConfigInterface
    {
        return Config::storage()->get('media_library.php', true, false);
    }

    /**
     * Update media items, set category to null
     * @param int $id
     */
    public function categoryRemoved($id) : void
    {
        $this->db->update($this->table(), array('category' => null), '`category` = ' . intval($id));
    }

    /**
     * Update category for set of items
     * @param array $items
     * @param integer $catalog
     * @return bool
     */
    public function updateItemsCategory(array $items, $catalog) : bool
    {
        $items = array_map('intval', $items);

        if ($catalog == 0) {
            $catalog = null;
        }

        try {
            $this->db->update(
                $this->table(),
                ['category' => $catalog],
                ' `' . $this->getPrimaryKey() . '` IN(' . implode(',', $items) . ')'
            );
            return true;
        } catch (Exception $e) {
            $this->logError('updateItemsCategory: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get file icon
     * @param $filename
     * @return string
     */
    static public function getFilePic($filename)
    {
        $ext = \Dvelum\File::getExt($filename);
        $icon = 'i/system/file.png';
        switch ($ext) {
            case '.jpg':
            case '.jpeg':
            case '.gif':
            case '.bmp':
            case '.png':
                $icon = 'i/system/folder-image.png';
                break;
            case  '.doc':
            case  '.docx':
            case  '.odt':
            case  '.txt':
                $icon = 'i/system/doc.png';
                break;

            case  '.xls':
            case  '.xlsx':
            case  '.ods':
            case  '.csv':
                $icon = 'i/system/excel.png';
                break;
            case '.pdf':
                $icon = 'i/system/pdf.png';
                break;

            case '.zip':
            case '.rar':
            case '.7z':
                $icon = 'i/system/archive.png';
                break;

            default :
                $icon = 'i/system/file.png';
        }
        return $icon;
    }
}