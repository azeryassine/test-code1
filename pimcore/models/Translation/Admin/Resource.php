<?php
/**
 * Pimcore
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.pimcore.org/license
 *
 * @category   Pimcore
 * @package    Translation
 * @copyright  Copyright (c) 2009-2014 pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     New BSD License
 */

namespace Pimcore\Model\Translation\Admin;

use Pimcore\Model;

class Resource extends Model\Translation\AbstractTranslation\Resource {

    /**
     * @var string
     */
    public static $_tableName = "translations_admin";

    /**
     * @return string
     */
    public static function getTableName(){
        return Model\Translation\Admin\Resource::$_tableName;
    }
}
