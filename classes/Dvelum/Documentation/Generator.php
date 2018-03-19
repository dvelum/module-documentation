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

use Dvelum\Orm;
use Dvelum\Orm\Model;
use Dvelum\Orm\Record;
use Dvelum\Config\ConfigInterface;
use Dvelum\Db\Select;
use Dvelum\File;
use Dvelum\Utils;

use \Exception;
/**
 * Documentation generator
 * @author Kirill A Egorov, 2014
 */
class Generator
{
    /**
     * Configuration
     * @var ConfigInterface
     */
    protected $config;
    /**
     * New doc version
     * @var integer
     */
    protected $vers;
    /**
     * History ID generator
     * @var Historyid
     */
    protected $historyId;
    /**
     * Autoloader class paths
     * @var array
     */
    protected $autoloaderPaths = [];

    public function __construct(ConfigInterface $config)
    {
        $this->config = $config;
        $this->vers = $config->get('gen_version');

        $hidGeneratorCfg = $config->get('hid_generator');
        $this->historyId = new $hidGeneratorCfg['adapter'];

        //		Model::factory('sysdocs_file')->getDbConnection()->getProfiler()->setEnabled(false);
    }

    /**
     * Set list of paths for autoloading
     * @param array $paths
     */
    public function setAutoloaderPaths(array $paths)
    {
        $this->autoloaderPaths = $paths;
    }

    /**
     * Update system documentation
     */
    public function run()
    {
        $this->prepareDb();
        $time = microtime(true);
        $files = $this->scanFs();
        $this->output('File System scan: '.number_format((microtime(true) - $time) , 3) .'s.');

        $time = microtime(true);

        if(!empty($files))
            foreach ($files as $file)
                $this->storeFile($file);

        $this->output('Files update: '.number_format((microtime(true) - $time) , 3) .'s.');

        $classFiles = Model::factory('sysdocs_file')->getList(false , array('isDir'=>0 , 'vers'=>$this->vers), array('id','path','name','hid'));

        $this->output('Updating classes...');
        $time = microtime(true);

        if(!empty($classFiles))
        {
            foreach ($classFiles as $item){
                $this->storeClass($item);
            }
        }
        // get extended classes
        $extFilter = new Select\Filter('extends' , '' , Select\Filter::NOT);
        $extended = Model::factory('sysdocs_class')->getList(false , array('extends'=>$extFilter,'vers'=>$this->vers), array('id','extends'));

        $this->output('Updating hierarchy...');
        if(!empty($extended)){
            foreach ($extended as $k=>$v){
                $this->findParentClass($v);
            }
        }
        $this->output('Updating localization...');
        $this->migrateLocale();

        $this->output(number_format((microtime(true) - $time) , 3) .'s.');
        $this->output('Done');
    }

    protected function preparedb()
    {
        $fileModel = Model::factory('sysdocs_file');
        $classModel = Model::factory('sysdocs_class');
        $propertyModel = Model::factory('sysdocs_class_property');
        $methodModel = Model::factory('sysdocs_class_method');
        $paramModel = Model::factory('sysdocs_class_method_param');

        //	    // disable profiler
        //	    $fileModel->getDbConnection()->getProfiler()->setEnabled(false);
        //	    $classModel->getDbConnection()->getProfiler()->setEnabled(false);
        //	    $propertyModel->getDbConnection()->getProfiler()->setEnabled(false);
        //	    $methodModel->getDbConnection()->getProfiler()->setEnabled(false);
        //	    $paramModel->getDbConnection()->getProfiler()->setEnabled(false);

        // clear version
        $fileModel->getDbConnection()->delete($fileModel->table(), '`vers`= '.$this->vers);
        $classModel->getDbConnection()->delete($classModel->table(), '`vers`= '.$this->vers);
        $propertyModel->getDbConnection()->delete($propertyModel->table(), '`vers`= '.$this->vers);
        $methodModel->getDbConnection()->delete($methodModel->table(), '`vers`= '.$this->vers);
        $paramModel->getDbConnection()->delete($paramModel->table(), '`vers`= '.$this->vers);
    }
    /**
     * Scan FS for class files
     * @return array
     */
    protected function scanFs()
    {
        $dirs = $this->config->get('locations');
        $skip = $this->config->get('skip');
        $files = $dirs;

        foreach ($dirs as $path)
        {
            $items = File::scanFiles($path , array('.php'), true);
            $files = array_merge($files , $items);
        }


        if(!empty($files))
        {
            foreach ($skip as $item)
            {
                foreach ($files as $k=>$filepath)
                {
                    if(strpos($filepath, $item)!==false){
                        unset($files[$k]);
                    }
                }
            }
        }
        $this->output('Found ' . count($files) . ' files.');
        return array_values($files);
    }
    /**
     * Store file
     * @param string $filepath
     */
    protected function storeFile(string $filepath)
    {
        $isDir = is_dir($filepath);

        $o = Record::factory('sysdocs_file');
        $o->setValues(array(
            'path'=>dirname($filepath).'/',
            'name'=>basename($filepath),
            'vers'=>$this->vers,
            'isDir'=>$isDir
        ));

        $hash = $this->historyId->getHid($o);

        $fileModel =  $exists = Model::factory('sysdocs_file');

        $parentDir = basename($o->get('path'));
        $parentPath = dirname($o->get('path')).'/';

        $filters = [
            'path'=>$parentPath,
            'name'=>$parentDir,
            'vers'=>$this->vers,
            'isDir'=>true
        ];
        $parentFile = $fileModel->getList(['limit'=>1] , $filters, ['id']);

        if(!empty($parentFile)){
            $o->set('parentId', $parentFile[0]['id']);
        }

        $o->set('hid', $hash);
        if(!$o->save(false,false)){
            throw new \Exception('cannot save sysdocs_file '. $filepath);
        }
    }
    /**
     * Update class info
     * @param array $data
     */
    protected function storeClass($data)
    {
        $time = microtime(true);
        $path = $data['path'];

        // replace basepath
        foreach ($this->autoloaderPaths as $basepath){
            $path = str_replace($basepath, '', $path);
        }

        $className = Utils::classFromPath($path . '/' . $data['name']);

        if(!$className){
            return;
        }

        if(!$className || (!class_exists($className) && !interface_exists($className) && !trait_exists($className))){

            $namespaceClass = '\\' . str_replace('_','\\', $className);
            if(!class_exists($namespaceClass) && !interface_exists($namespaceClass) && !trait_exists($namespaceClass)){
                $this->output('Skip ' . $data['path'] . '/' . $data['name']. ' '.$namespaceClass);
                return;
            }else{
                $className = $namespaceClass;
            }
        }

        $analyzer = new Analyzer($className);

        $extends = $analyzer->getExtends();
        $parentId = null;

        $o = Record::factory('sysdocs_class');
        $o->setValues(array(
            'fileHid'=>$data['hid'],
            'name'=>$className,
            'vers'=>$this->vers,
            'description'=>$this->clearDescription($analyzer->getComment()),
            'itemType'=>$analyzer->getType(),
            'abstract'=>$analyzer->isAbstract(),
            'parentId'=>$parentId,
            'namespace'=>$analyzer->getNamespace(),
            'deprecated'=>intval($analyzer->isDeprecated()),
            'implements'=>$analyzer->getImplements(),
            'extends'=>$extends,
            'hid'=>$this->historyId->getClassHid($data['hid'], $className),
            'fileId'=>$data['id']
        ));

        if(!$o->save(false,false)){
            throw new Exception('cannot save sysdocs_class '. $className);
        }

        $this->processProperties($o , $analyzer);
        $this->processMethods($o , $analyzer);

        $this->output("\t".$className.': '.number_format((microtime(true) - $time) , 3) .'s.');
    }

    protected function processProperties(Record $class , Analyzer $analyzer)
    {
        $constants = $analyzer->getConstants();

        $classId = $class->getId();
        $classHid = $class->get('hid');

        $dataList = array();
        if(!empty($constants))
        {
            foreach ($constants as $constant)
            {
                $dataList[] = array(
                    'deprecated'=> 0,
                    'hid'=> $this->historyId->getPropertysHid($classId, $constant['name']),
                    'vers'=> $this->vers,
                    'name'=> $constant['name'],
                    'description'=> '',
                    'const'=> 1,
                    'static'=> 0,
                    'visibility'=> 'public',
                    'type'=> '',
                    'classId'=> $classId,
                    'classHid'=>$classHid,
                    'constValue'=> $constant['value'],
                    'inherited'=>intval($constant['inherited'])
                );
            }
        }
        $properties = $analyzer->getProperties();

        if(!empty($properties))
        {
            foreach ($properties as $property)
            {
                $dataList[] = array(
                    'deprecated'=> intval($property['deprecated']),
                    'hid'=> $this->historyId->getPropertysHid($classId, $property['name']),
                    'vers'=> $this->vers,
                    'name'=> $property['name'],
                    'description'=> $this->clearDescription($property['description']),
                    'const'=> 0,
                    'static'=> intval($property['static']),
                    'visibility'=> $property['visibility'],
                    'type'=> $property['type'],
                    'classId'=> $classId,
                    'classHid'=> $classHid,
                    'constValue'=> null,
                    'inherited'=>intval($property['inherited'])
                );
            }
        }
        if(!Model::factory('sysdocs_class_property')->multiInsert($dataList)){
            throw new Exception('Cannot save sysdocs_class_property');
        }
    }


    protected function processMethods(Record $class , Analyzer $analyzer)
    {
        $methods = $analyzer->getMethods();
        $paramsList = array();
        if(!empty($methods))
        {
            foreach ($methods as $method)
            {
                $data = array(
                    'classId'=>$class->getId(),
                    'name'=>$method['name'],
                    'returnType'=>$method['returnType'],
                    'deprecated'=>intval($method['deprecated']),
                    'description'=>$this->clearDescription($method['description']),
                    'abstract'=>$method['abstract'],
                    'throws'=>$method['throws'],
                    'vers'=>$this->vers,
                    'hid'=>$this->historyId->getMethodHid($class->getId(), $method['name']),
                    'static'=>intval($method['static']),
                    'visibility'=>$method['visibility'],
                    'classHid'=>$class->get('hid'),
                    'inherited'=>$method['inherited'],
                    'returnsReference' => $method['returnsReference']
                );

                $methodObject = $this->storeMethod($data);

                $params = $analyzer->getParametrs($method['name']);

                if(!empty($params))
                {
                    $methodId = $methodObject->getId();
                    $methodHid = $methodObject->get('hid');
                    foreach ($params as $param)
                    {
                        $paramsList[] = array(
                            'methodId' => $methodId,
                            'hid' => $this->historyId->getParamHid($methodHid, $param['name']),
                            'name' => $param['name'],
                            'vers' => $this->vers,
                            'index' => $param['index'],
                            'default' => $param['default'],
                            'isRef' => $param['isRef'],
                            'description' => $this->clearDescription($param['description']),
                            'methodHid' => $methodObject->get('hid'),
                            'optional' => $param['optional'],
                        );
                    }
                }
            }
            if(!Model::factory('sysdocs_class_method_param')->multiInsert($paramsList)){
                throw new Exception('Cannot save sysdocs_class_method_param');
            }
        }
    }
    /**
     * Store class method
     * @param array $data
     * @throws \Exception
     * @return Record
     */
    protected function storeMethod(array $data) : Record
    {
        $o = Record::factory('sysdocs_class_method');
        $o->setValues($data);

        $hid = $this->historyId->getHid($o);

        $o->setValues($data);
        $o->set('hid', $hid);

        if(!$o->save(false,false)){
            throw new Exception('cannot save sysdocs_class_method classId:' . $data['classId'] . ' -> ' . $data['name']);
        }

        return $o;
    }

    protected function findParentClass($data)
    {
        $classModel = Model::factory('sysdocs_class');
        $objectId = $data['id'];
        $parentName = $data['extends'];

        $classData = $classModel->getList(array('limit'=>1), array('vers'=>$this->vers,'name'=>$parentName) , array('id'));

        if(!empty($classData))
        {
            $parentId = $classData[0]['id'];
            $object = Record::factory('sysdocs_class' , $objectId);
            $object->set('parentId' , $parentId);
            if(!$object->save(false,false)){
                throw new Exception('Cannot save sysdocs_class parentId '. $objectId);
            }
        }
    }

    /**
     * Remove PHPDoc info
     * @param string $lines
     * @return string
     */
    protected function clearDescription($lines)
    {
        if(!is_string($lines) || !strlen($lines))
            return $lines;

        $data = explode("\n", $lines);
        foreach ($data as $k=>$line)
        {
            $line = trim($line);

            if(strpos($line, '@') === 0 && strpos($line , '@see') === false)
                unset($data[$k]);
        }

        return implode("\n", $data);
    }

    /**
     * Output messages
     * @param string $msg
     */
    public function output($msg)
    {
        echo $msg."\n";
    }

    public function migrateLocale()
    {
        $langDictionary = \Dictionary::factory('sysdocs_language');
        $langs = $langDictionary->getData();

        $fields = $this->config->get('fields');

        foreach ($langs as $k=>$v)
        {
            foreach ($fields as $class=>$cfg)
            {
                $t = microtime(true);
                $this->output('Migrate localization...' .$class. ' '. $k);
                $this->migrateRecords($class, $this->vers , $k , $cfg);
                $this->output('OK '.number_format((microtime(true) - $t) , 3) .'s.');
            }
        }

    }
    /**
     * Copy previous class localization
     * @param integer $version
     * @param string $lang
     * @throws Exception
     */
    public function migrateRecords($objectClass , $version, $lang , $fields)
    {
        $model = Model::factory($objectClass);
        $list = $model->getList(false,array('vers'=>$version), array('id'));

        $locModel = Model::factory('sysdocs_localization');
        $locModel->getDbConnection()
            ->delete($locModel->table() , ' lang="'.$lang.'" AND vers="'.$version.'" AND object_class="'.$objectClass.'"');

        $list = Utils::fetchCol('id',$list);
        $chunks = array_chunk($list , 300);

        foreach($chunks as $items)
        {
            $newItems = array();
            $list = $model->getList(false,array('vers'=>$version,'id'=> $items));

            foreach ($list as $item)
            {
                foreach ($fields as $fieldName) {
                    $loc = $this->findLocale($objectClass, $fieldName, $lang, $item['hid']);

                    if ($loc === false)
                        continue;

                    $loc = trim($loc);

                    if (!strlen($loc))
                        continue;


                    $item =  array(
                        'field' => $fieldName,
                        'hid' => $item['hid'],
                        'lang' => $lang,
                        'object_class' => $objectClass,
                        'object_id' => $item['id'],
                        'value' => $loc,
                        'vers' => $this->vers
                    );

                    /*
                     * Multi Insert can cause PHP segfault if mysql max_allowed_packet is to small
                     */
                    // uncomment this to use multi insert
                    //$newItems[] = $item;

                    // comment this to use multi insert
                    try{
                        Model::factory('sysdocs_localization')
                            ->getDbConnection()
                            ->insert(
                                Model::factory('sysdocs_localization')->table() ,
                                $item
                            );
                    }catch (Exception $e){
                        throw new Exception('Cannot save sysdocs_localization ' . $lang. ' '.$e->getMessage());
                    }
                }

                if (count($newItems) > 100) {
                    if (!Model::factory('sysdocs_localization')->multiInsert($newItems , 50)) {
                        throw new Exception('Cannot save sysdocs_localization ' . $lang);
                    }
                    $newItems = array();
                }
            }

            if (count($newItems) && !Model::factory('sysdocs_localization')->multiInsert($newItems , 50)) {
                throw new Exception('Cannot save sysdocs_localization ' . $lang);
            }
        }
    }
    /**
     * Find previous localization
     * @param string $objectClass
     * @param string $field
     * @param string $language
     * @return string | false
     */
    public function findLocale($objectClass , $field , $language , $hid)
    {
        $data = Model::factory('sysdocs_localization')->getList(
            array('start'=>0,'limit'=>1,'sort'=>'vers','dir'=>'DESC'),
            array(
                'lang'=>$language,
                'object_class'=>$objectClass,
                'hid'=>$hid,
                'field'=>$field
            ),
            array('value')
        );

        if(empty($data))
            return false;

        return $data[0]['value'];
    }
}