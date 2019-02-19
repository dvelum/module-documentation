<?php
/**
 *  DVelum project https://github.com/dvelum/dvelum
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
 */

class Model_Sysdocs_File extends \Dvelum\Orm\Model
{
    /**
     * Get data for Tree.Panel
     * @param array $version
     */
    public function getTreeList($version)
    {
        /*
         * Add required fields
         */
        $fields = array('id','parentId','path','isDir','name','hid');
        $data = $this->query()
            ->params([
                'sort'=>['isDir'=>'DESC','path','name'],
                'dir'=>'ASC'
            ])
            ->filters([
                'vers'=>$version
            ])
            ->fields($fields)
            ->fetchAll();

        if(empty($data))
            return array();

        $tree = new Tree();

        foreach($data as $value)
        {
            if(!$value['parentId'])
                $value['parentId'] = 0;

            $tree->addItem($value['id'], $value['parentId'], $value);
        }

        return $this->fillItems($tree , 0);
    }

    /**
     * Fill children data array for tree panel
     * @param Tree $tree
     * @param mixed $root
     * @return array
     */
    protected function fillItems(Tree $tree , $root = 0 )
    {
        $result = [];
        $childs = $tree->getChilds($root);

        if(empty($childs))
            return [];

        foreach($childs as $k=>$v)
        {
            $row = $v['data'];
            $obj = new stdClass();

            $obj->id = $row['id'];
            $obj->text = $row['name'];
            $obj->expanded = false;
            $obj->isDir = $row['isDir'];
            $obj->path = $row['path'];
            $obj->name = $row['name'];
            $obj->hid = $row['hid'];

            if($row['isDir'])
                $obj->leaf = false;
            else
                $obj->leaf = true;

            $cld= array();
            if($tree->hasChilds($row['id']))
                $cld = $this->fillItems($tree ,  $row['id']);

            $obj->children=$cld;
            $result[] = $obj;
        }
        return $result;
    }
}