<?php
/**
 * Background task
 * Recrop medialibrary images
 * @author Kirill Egorov
 *
 * Requires config
 *    types - array of image typecodes like array('icon','thumb',...)
 *  notCroped - boolean flag  - crop only autocroped images
 */
namespace Dvelum\App\Task;

use Dvelum\BackgroundTask\AbstractTask;

use Dvelum\Config;
use \Dvelum\Image\Resize;
use Dvelum\Lang;

class Recrop extends AbstractTask
{
    /**
     * (non-PHPdoc)
     * @see Bgtask_Abstract::getDescription()
     */
    public function getDescription()
    {
        $lang = Lang::lang();
        Return $lang->get('TASK_MEDIALIB_RECROP') . ': ' . implode(',', $this->config['types']);
    }

    /**
     * (non-PHPdoc)
     * @see Bgtask_Abstract::run()
     */
    public function run()
    {
        /**
         * @var \Dvelum\App\Model\Medialib $mediaModel
         */
        $mediaModel = \Dvelum\Orm\Model::factory('Medialib');
        $types = $this->config['types'];
        $nonCroped = $this->config['notCroped'];

        $wwwPath = Config::storage()->get('main.php')->get('wwwPath');

        $filter = ['type' => 'image'];

        if ($nonCroped) {
            $filter['croped'] = 0;
        }
        $data = $mediaModel->query()->filters($filter)->fields(['path', 'ext', 'croped'])->fetchAll();

        if (empty($data)) {
            $this->finish();
        }

        $conf = $mediaModel->getConfig()->__toArray();

        $thumbSizes = $conf['image']['sizes'];

        if (!$types || !is_array($types)) {
            return;
        }

        $this->setTotalCount(sizeof($data));

        foreach ($data as $v) {
            // sub dir fix
            if ($v['path'][0] !== '/') {
                $v['path'] = '/' . $v['path'];
            }

            $path = $wwwPath . $v['path'];

            if (!file_exists($path)) {
                $this->log('Skip  non existent file: ' . $path);
                continue;
            }

            foreach ($types as $typename) {
                if (!isset($thumbSizes[$typename])) {
                    continue;
                }

                $saveName = str_replace($v['ext'], '-' . $typename . $v['ext'], $path);


                switch ($conf['image']['thumb_types'][$typename]) {
                    case 'crop' :
                        Resize::resize($path, $thumbSizes[$typename][0], $thumbSizes[$typename][1], $saveName, true,
                            true);
                        break;
                    case 'resize_fit':
                        Resize::resize($path, $thumbSizes[$typename][0], $thumbSizes[$typename][1], $saveName, true,
                            false);
                        break;
                    case 'resize':
                        Resize::resize($path, $thumbSizes[$typename][0], $thumbSizes[$typename][1], $saveName, false,
                            false);
                        break;
                    case 'resize_to_frame':
                        Resize::resizeToFrame($path, $thumbSizes[$typename][0], $thumbSizes[$typename][1], $saveName);
                        break;
                }
            }
            /*
             * Update task status and check for signals 
             */
            $this->incrementCompleted();
            $this->updateState();
            $this->processSignals();
        }
        $this->finish();
    }
}