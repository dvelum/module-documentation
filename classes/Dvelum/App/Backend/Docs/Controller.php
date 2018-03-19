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
namespace Dvelum\App\Backend\Docs;

use Dvelum\App;
use Dvelum\App\Router\RouterInterface;
use Dvelum\Request;
use Dvelum\Response;
use Dvelum\Documentation;

class Controller extends App\Backend\Controller implements RouterInterface
{
    public function getModule(): string
    {
        return 'Docs';
    }

    /**
     * (non-PHPdoc)
     * @see Router::run()
     */
    public function route(Request $request , Response $response) : void
    {
        $controller = new Documentation\Controller($this->appConfig ,2 , false);
        $controller->setCanEdit($this->user->getModuleAcl()->canEdit($this->getModule()));
        $controller->run();
    }
    /**
     * (non-PHPdoc)
     * @see Backend_Controller::indexAction()
     */
    public function indexAction(){}
    /**
     * Find url
     * @param string $module
     * @return string
     */
    public function findUrl(string $module): string
    {
        return '';
    }
}