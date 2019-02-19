<?php
/**
 *  DVelum project https://github.com/dvelum/dvelum
 *  Copyright (C) 2011-2018  Kirill Yegorov
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
namespace Dvelum\Documentation;

use Dvelum\Config;
use Dvelum\Config\ConfigInterface;
use Dvelum\Orm\Model;
use Dvelum\Lang;
use Dvelum\Request;
use Dvelum\Response;
use Dvelum\Filter;

class Controller
{
    /**
     * Documentation configuration object
     * @var ConfigInterface $docConfig
     */
    protected $docConfig;
    /**
     * Documentation version
     * @var string
     */
    protected $version;
    /**
     * Documentation version index
     * @var int $versionIndex
     */
    protected $versionIndex;
    /**
     * @var Model $fileModel
     */
    protected $fileModel;

    /**
     * System configuration object
     * @var ConfigInterface $configMain
     */
    protected $configMain;

    /**
     * Index of first url param
     * @var int
     */
    protected $paramsIndex;
    /**
     * Documentation language
     * @var string
     */
    protected $language;
    /**
     * Documentation version
     * @var string
     */
    /**
     * @var Lang
     */
    protected $lang;
    /**
     * Edit permissions
     * @var boolean
     */
    protected $canEdit = false;
    /**
     * Cache adapter
     * @var \Cache_Abstract
     */
    protected $cache = false;
    /**
     * @var Request $request
     */
    protected $request;
    /**
     * @var Response $response
     */
    protected $response;

    public function __construct($mainConfig , $paramsIndex = 0, $container = false)
    {
        $this->request = Request::factory();
        $this->response = Response::factory();

        $this->container = $container;
        $this->configMain = Config::storage()->get('main.php');
        $this->fileModel = Model::factory('Sysdocs_File');
        $this->lang = Lang::lang();
        $this->paramsIndex = $paramsIndex;
        $this->docConfig = Config::storage()->get('sysdocs.php');

        $langDictionary = \Dictionary::factory('sysdocs_language');

        $request = Request::factory();

        $lang = $request->getPart($this->paramsIndex);
        $version = $request->getPart(($this->paramsIndex+1));

        if($lang && $langDictionary->isValidKey($lang)){
            $this->language = $lang;
        }else{
            $this->language = $this->docConfig->get('default_languge');
        }

        if($version!==false && array_key_exists($version, $this->docConfig->get('versions'))){
            $this->version = $version;
        }else{
            $this->version = $this->docConfig->get('default_version');
        }
        $vList = $this->docConfig->get('versions');

        $this->versionIndex = $vList[$this->version];

        // change theme
        $page = \Page::getInstance();
        $page->setTemplatesPath('system/gray/');
    }
    /**
     * Set edit permissions
     * @param boolean $flag
     */
    public function setCanEdit($flag)
    {
        $this->canEdit = (boolean) $flag;
    }
    /**
     * Set Cache adapter
     * @param \Cache_Abstract $cache
     */
    public function setCacheAdapter(\Cache_Abstract $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Run controller
     */
    public function run()
    {
        $action = $this->request->getPart(($this->paramsIndex+2));

        if($action && method_exists($this, $action.'Action')){
            $this->{$action.'Action'}();
        }else{
            if(strlen($action) && $this->request->isAjax()){
                $this->response->error(Lang::lang()->get('WRONG_REQUEST').' ' . $this->request->getUri());
            }else{
                $this->indexAction();
            }
        }
    }

    /**
     * Default action. Load UI
     */
    public function indexAction()
    {
        $this->includeScripts();
        \Dvelum\Resource::factory()->addInlineJs('
           app.docLang = "'.$this->language.'";
           app.docVersion = "'.$this->version.'";
           var canEdit = '.intval($this->canEdit).';    
      ');
        $this->runDesignerProject('DVelum/documentation.designer.dat', $this->container);

    }

    public function setDefaultVersion($versNum)
    {
        $this->version = $versNum;
    }

    /**
     * Get API tree.Panel data
     */
    public function apitreeAction()
    {
        $this->response->json($this->fileModel->getTreeList($this->versionIndex));
    }
    /**
     * Get class info
     */
    public function infoAction()
    {
        $fileHid = $this->request->post('fileHid', Filter::FILTER_STRING, false);
        $info = new Info();
        $classInfo = $info->getClassInfoByFileHid($fileHid, $this->language , $this->versionIndex);
        $this->response->success($classInfo);
    }
    /**
     * Set class desctiption
     */
    public function setdescriptionAction()
    {
        if(!$this->canEdit){
            $this->response->error($this->lang->get('CANT_MODIFY'));
            return;
        }
        $fileHid = $this->request->post('hid', Filter::FILTER_STRING, false);
        $text = $this->request->post('text', 'raw', '');
        $objectId = $this->request->post('object_id', Filter::FILTER_INTEGER, false);
        $objectClass = $this->request->post('object_class', Filter::FILTER_STRING, false);

        if(!$objectId){
            $this->response->error($this->lang->get('WRONG_REQUEST'));
            return;
        }

        $info = new Info();
        if($info->setDescription($objectId , $fileHid, $this->versionIndex , $this->language , $text , $objectClass)){
            $this->response->success();
            return;
        }
        $this->response->error($this->lang->get('CANT_EXEC'));
    }
    /**
     * Get interface config
     */
    public function configAction()
    {
        $versionsList = array_keys($this->docConfig->get('versions'));
        $preparedVersions = array();

        foreach ($versionsList as $k=>$v){
            $preparedVersions[] = array('id'=>$v,'title'=>$v);
        }

        $langs = \Dictionary::factory('sysdocs_language')->getData();
        $langData = array();

        foreach ($langs as $k=>$v){
            $langData[] = array('id'=>$k,'title'=>$v);
        }

        $result = array(
            'version' => $this->version,
            'language' => $this->language,
            'languages' => $langData,
            'versions' => $preparedVersions,
        );

       $this->response->success($result);
    }

    /**
     * Include required JavaScript files defined in the configuration file
     */
    public function includeScripts()
    {
        $resource = \Dvelum\Resource::factory();

        $media = Model::factory('Medialib');
        $media->includeScripts();
        $cfg = Config::storage()->get('js_inc_backend.php');

        $theme = 'gray';
        $lang = $this->configMain->get('language');

        $resource->addJs('/js/lang/' . $lang . '.js', 1, true, 'head');
        $resource->addJs('/js/app/system/common.js', 3, false, 'head');

        if ($this->configMain->get('development')) {
            $resource->addJs('/js/lib/extjs/build/ext-all-debug.js', 2, true, 'head');
        } else {
            $resource->addJs('/js/lib/extjs/build/ext-all.js', 2, true, 'head');
        }

        $resource->addJs('/js/lib/extjs/build/classic/theme-' . $theme . '/theme-' . $theme . '.js', 3, true, 'head');
        $resource->addJs('/js/lib/extjs/build/classic/locale/locale-' . $this->configMain->get('language') . '.js', 4, true, 'head');

        $resource->addInlineJs('var developmentMode = ' . intval($this->configMain->get('development')) . ';');

        $resource->addCss('/js/lib/extjs/build/classic/theme-' . $theme . '/resources/theme-' . $theme . '-all.css', 1);
        $resource->addCss('/css/system/style.css', 2);
        $resource->addCss('/css/system/' . $theme . '/style.css', 3);
        $resource->addCss('/resources/dvelum-module-documentation/css/docs.css', 3);

        if($cfg->getCount())
        {
            $js = $cfg->get('js');
            if(!empty($js))
                foreach($js as $file => $config)
                    $resource->addJs($file , $config['order'] , $config['minified']);

            $css = $cfg->get('css');
            if(!empty($css))
                foreach($css as $file => $config)
                    $resource->addCss($file , $config['order']);
        }

    }

    /**
     * Run Layout project
     *
     * @param string $project - path to project file
     */
    protected function runDesignerProject($project, $renderTo = false)
    {
        $manager = new \Designer_Manager($this->configMain);
        $project = $manager->findWorkingCopy($project);
        $manager->renderProject($project , $renderTo);
    }

    /**
     * Search request from UI
     */
    public function searchAction()
    {
        $query = $this->request->post('search' , Filter::FILTER_STRING , '');

        if(empty($query)){
            $this->response->success([]);
            return;
        }

        $search = new Search();
        $result = $search->find($query , $this->versionIndex);

        $this->response->success($result);
    }
}