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
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Security;

class SecurityHelper
{
    public static function convertHtmlSpecialChars(?string $text): ?string
    {
        if(is_string($text)) {
            return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
        }

        return null;
    }

    public static function convertHtmlSpecialCharsArrayKeys(array &$array, array $keys): void
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $array)) {
                $array[$key] = self::convertHtmlSpecialChars($array[$key]);
            }
        }
    }

    public static function sanitizeHtmlAttributes(?string $text): ?string
    {
        if(is_string($text)) {
            return preg_replace('/[\/"\'\\\]/', '', $text);
        }

        return null;
    }
}
