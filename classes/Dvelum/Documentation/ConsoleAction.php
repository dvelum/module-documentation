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

use Dvelum\App\Console\Action;
use Dvelum\Config;

/**
 * Class Console_Docs_Generate
 * Console Action
 * Generate new version of documentation
 */
class ConsoleAction extends Action
{
    /**
     * Run console action
     * @return bool
     */
    public function action() : bool
    {
        if(!$this->appConfig->get('development')){
            echo 'Use development mode';
            return false;
        }

        ini_set('memory_limit' , '256M');

        $sysDocsCfg = Config::storage()->get('sysdocs.php');
        $sysDocs = new Generator($sysDocsCfg);
        $autoloaderCfg = Config::storage()->get('autoloader.php')->__toArray();
        $sysDocs->setAutoloaderPaths($autoloaderCfg['paths']);

        if(isset($this->params[0]) && $this->params[0]==='locale'){
            $sysDocs->migrateLocale();
        }else{
            $sysDocs->run();
        }
        return true;
    }
}
