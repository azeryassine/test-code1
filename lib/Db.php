<?php
/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore;

use Psr\Log\LoggerInterface;

class Db
{
    /**
     * @static
     *
     * @return \Pimcore\Db\Connection
     */
    public static function getConnection()
    {
        return self::get();
    }

    /**
     * @return Db\Connection
     */
    public static function reset()
    {
        self::close();

        return self::get();
    }

    /**
     * @static
     *
     * @return \Pimcore\Db\Connection
     */
    public static function get()
    {
        /**
         * @var \Pimcore\Db\Connection $db
         */
        $db = \Pimcore::getContainer()->get('database_connection');

        return $db;
    }

    /**
     * @static
     *
     * @return LoggerInterface
     */
    public static function getLogger()
    {
        return \Pimcore::getContainer()->get('monolog.logger.doctrine');
    }

    /**
     * @static
     */
    public static function close()
    {
        $db = \Pimcore::getContainer()->get('database_connection');
        $db->close();
    }

    /**
     * check if autogenerated views (eg. localized fields, ...) are still valid, if not, they're removed
     *
     * @static
     */
    public static function cleanupBrokenViews()
    {
        $db = self::get();

        $tables = $db->fetchAll('SHOW FULL TABLES');
        foreach ($tables as $table) {
            reset($table);
            $name = current($table);
            $type = next($table);

            if ($type == 'VIEW') {
                try {
                    $createStatement = $db->fetchRow('SHOW FIELDS FROM ' . $name);
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'references invalid table') !== false) {
                        Logger::err('view ' . $name . ' seems to be a broken one, it will be removed');
                        Logger::err('error message was: ' . $e->getMessage());

                        $db->query('DROP VIEW ' . $name);
                    } else {
                        Logger::error($e);
                    }
                }
            }
        }
    }
}
