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

declare(strict_types=1);

class Model_Sysdocs_Class extends \Dvelum\Orm\Model
{
    /**
     * Get class hierarchy
     * @param integer $vers
     * @return Tree
     */
    public function getTree($vers)
    {
        $data = $this->query()->filters(['vers'=>$vers])->fields(['id','parentId','name'])->fetchAll();
        $tree = new \Tree();

        if(!empty($data))
        {
            foreach ($data as $k=>$v)
            {
                if(empty($v['parentId']))
                    $v['parentId'] = 0;

                $tree->addItem($v['id'], $v['parentId'], $v['name']);
            }
        }
        return $tree;
    }
}