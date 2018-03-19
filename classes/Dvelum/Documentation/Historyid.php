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

use Dvelum\Orm\Record;
use \Exception;

class Historyid
{
    /**
     * @param Record $object
     * @return string
     * @throws Exception
     * @throws \Dvelum\Orm\Exception
     */
    public function getHid(Record $object)
    {
        $name = strtolower($object->getName());
        switch ($object->getName())
        {
            case 'sysdocs_class':
                return $this->getClassHid($object->get('fileHid') , $object->get('name'));
                break;
            case 'sysdocs_class_method':
                return $this->getMethodHid($object->get('classHid') , $object->get('name'));
                break;
            case 'sysdocs_class_method_param':
                return $this->getParamHid($object->get('methodHid') , $object->get('name'));
                break;
            case 'sysdocs_class_property':
                return $this->getPropertysHid($object->get('classHid') , $object->get('name'));
                break;
            case 'sysdocs_file':
                return $this->getFileHid($object->get('path') , $object->get('name'));
                break;

            default: throw new Exception('Undefined HID generator for '.$name);
        }
    }

    public function getClassHid($fileHid , $className)
    {
        return md5($fileHid. $className);
    }
    public function getMethodHid($classHid , $methodName)
    {
        return md5($classHid . $methodName);
    }
    public function getParamHid($methodHid , $paramName)
    {
        return md5($methodHid . $paramName);
    }
    public function getPropertysHid($classHid , $propertyName)
    {
        return md5($classHid . $propertyName);
    }
    public function getFileHid($path , $fileName)
    {
        return md5($path.$fileName);
    }
}