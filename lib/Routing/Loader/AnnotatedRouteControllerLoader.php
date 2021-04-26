<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Routing\Loader;

use Symfony\Bundle\FrameworkBundle\Routing\AnnotatedRouteControllerLoader as BaseAnnotatedRouteControllerLoader;

/**
 * Normalizes autogenerated admin routes to pimcore_admin_ and pimcore_api_ prefixes
 */
class AnnotatedRouteControllerLoader extends BaseAnnotatedRouteControllerLoader
{
    /**
     * @inheritDoc
     */
    protected function getDefaultRouteName(\ReflectionClass $class, \ReflectionMethod $method)
    {
        $routeName = parent::getDefaultRouteName($class, $method);

        $replacements = [
            'pimcore_admin_rest_' => 'pimcore_api_rest_',
            'pimcore_admin_admin_' => 'pimcore_admin_',
        ];

        $routeName = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $routeName
        );

        return $routeName;
    }
}
