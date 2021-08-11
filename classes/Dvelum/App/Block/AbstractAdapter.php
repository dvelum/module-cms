<?php
/**
 *  DVelum project https://github.com/dvelum/dvelum
 *  Copyright (C) 2011-2017  Kirill Yegorov
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
 */
declare(strict_types=1);

namespace Dvelum\App\Block;
use Dvelum\Orm\Orm;
use Dvelum\Template\Engine\EngineInterface;
use Dvelum\Template\Service;

/**
 * Base class for page blocks
 * @author Kirill Yegorov 2011 DVelum project
 */
abstract class AbstractAdapter
{
    /**
     * Block config
     * @var array
     */
    protected $config;
    /**
     * Block template. The path is relative to theme location
     * @var string
     */
    protected $template = 'block.php';
    protected $params = [];

    /**
     * Allow cache for block content (frontend hard cache)
     * @var bool
     */
    const CAN_USE_CACHE = false;
    /**
     * Block render result depends on the page on which it is located
     * @var boolean
     */
    const DEPENDS_ON_PAGE = false;

    protected Orm $orm;
    protected Service $templateFactory;

    /**
     * Block constructor
     * @param array $config - block config
     */
    public function __construct(array $config, Orm $orm, Service $template)
    {
        $this->config = $config;
        $this->templateFactory = $template;
        $this->orm = $orm;

        if(!isset($config['params']) || !strlen($config['params']))
            return;

        $parts = explode(',' , $config['params']);

        if(!empty($parts))
        {
            foreach($parts as $item)
            {
                $config = explode('=' , str_replace(' ' , '' , $item));
                if(is_array($config) && count($config) == 2)
                    $this->params[$config[0]] = $config[1];
            }
        }
    }

    /**
     * Render block content
     * @return string
     */
    abstract public function render() : string ;

    /**
     * String representation for object
     * Returns rendered html
     * @return string
     */
    public function __toString() : string
    {
        return $this->render();
    }
}