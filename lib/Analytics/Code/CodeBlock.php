<?php

declare(strict_types=1);

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

namespace Pimcore\Analytics\Code;

/**
 * Represents a single template block. Parts are represented as array and concatenated
 * with newlines on render.
 */
final class CodeBlock
{
    /**
     * @var array
     */
    private $parts = [];

    /**
     * CodeBlock constructor.
     * @param array $parts
     */
    public function __construct(array $parts = [])
    {
        $this->parts = $parts;
    }

    /**
     * @param array $parts
     */
    public function setParts(array $parts)
    {
        $this->parts = $parts;
    }

    /**
     * @return array
     */
    public function getParts(): array
    {
        return $this->parts;
    }

    /**
     * @param array|string $parts
     */
    public function append($parts)
    {
        $parts = (array)$parts;

        foreach ($parts as $part) {
            $this->parts[] = $part;
        }
    }

    /**
     * @param array|string $parts
     */
    public function prepend($parts)
    {
        $parts = (array)$parts;
        $parts = array_reverse($parts); // prepend parts in the order they were passed

        foreach ($parts as $part) {
            array_unshift($this->parts, $part);
        }
    }

    /**
     * @return string
     */
    public function asString(): string
    {
        $string = implode("\n", $this->parts);
        $string = trim($string);

        return $string;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->asString();
    }
}
