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

use Dvelum\Orm\Model;
use Dvelum\Db\Select;
use Dvelum\Utils;

class Search
{
    protected $searchLimit = 25;
    /**
     * @param $query
     * @param $version
     * @return array
     */
    public function find($query , $version)
    {
        $classModel = Model::factory('sysdocs_class');
        $methodModel = Model::factory('sysdocs_class_method');
        $fileModel = Model::factory('sysdocs_file');

        $result = $classModel->getList(
            array(
                'start'=>0 ,
                'limit'=>$this->searchLimit,
                'sort'=>'name',
                'dir'=>'ASC'
            ),
            array(
                 new Select\Filter('name' , '%'.$query.'%' , Select\Filter::LIKE),
                'vers'=>$version,
            ),
            array('id','name','fileId')
        );

        if(!empty($result)){
            foreach($result as &$v){
                $v['itemType'] = 'class';
                $v['classId'] = $v['id'];
                $v['id'] = 'c'.$v['id'];
                $v['title'] = $v['name'];
                $v['methodId'] = 0;
            }unset($v);
        }


        if(count($result) < $this->searchLimit)
        {
             $methodData =  $methodModel->getList(
                 array(
                     'start'=>0 ,
                     'limit'=>($this->searchLimit - count($result)),
                     'sort'=>'name',
                     'dir'=>'ASC'
                 ),
                 array(
                     new Select\Filter('name' , $query.'%' , Select\Filter::LIKE),
                     'vers'=>$version,
                 ),
                 array('id','name','classId')
             );

             if(!empty($methodData)){

                 $classes = Utils::fetchCol('classId' , $methodData);
                 $classes = $classModel->getList(false, array('id'=>$classes), array('id','name','fileId'));

                 if(!empty($classes))
                     $classes = Utils::rekey('id'  , $classes);


                 foreach($methodData as $k=>&$v){
                     if(!isset($classes[$v['classId']])){
                         unset($methodData[$k]);
                         continue;
                     }
                     $v['methodId'] = $v['id'];
                     $v['id'] = 'm'.$v['id'];
                     $v['title'] = $classes[$v['classId']]['name'].'::'.$v['name'];
                     $v['itemType'] = 'method';
                     $v['fileId'] = $classes[$v['classId']]['fileId'];
                 }unset($v);

                 if(!empty($methodData)) {
                     $result = array_merge($result, $methodData);
                 }
             }
        }

        if(empty($result))
            return array();

        $fileIds = Utils::fetchCol('fileId' , $result);
        $files = $fileModel->getList(false , array('id'=>$fileIds) , array('path','name','id','hid'));
        if(!empty($files)){
            $files = Utils::rekey('id' , $files);
            foreach($result as $k=>&$v){
                if(!isset($files[$v['fileId']])){
                    unset($result[$k]);
                    continue;
                }
                $v['id'] = $k;
                $v['fname'] = $files[$v['fileId']]['name'];
                $v['path'] = $files[$v['fileId']]['path'];
                $v['hid'] = $files[$v['fileId']]['hid'];
                unset($v['file_id']);
            }unset($v);
        }
        return $result;
    }
}