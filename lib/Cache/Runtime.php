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

namespace Pimcore\Cache;

final class Runtime extends \ArrayObject
{
    private const SERVICE_ID = __CLASS__;

    /**
     * @var self|null
     */
    protected static $tempInstance;

    /**
     * @var self|null
     */
    protected static $instance;

    /**
     * Retrieves the default registry instance.
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance) {
            return self::$instance;
        }

        if (\Pimcore::hasContainer()) {
            $container = \Pimcore::getContainer();

            /** @var self $instance */
            $instance = null;
            if ($container->initialized(self::SERVICE_ID)) {
                $instance = $container->get(self::SERVICE_ID);
            } else {
                $instance = new self;
                $container->set(self::SERVICE_ID, $instance);
            }

            self::$instance = $instance;

            if (self::$tempInstance) {
                // copy values from static temp. instance to the service instance
                foreach (self::$tempInstance as $key => $value) {
                    $instance->offsetSet($key, $value);
                }

                self::$tempInstance = null;
            }

            return $instance;
        }

        // create a temp. instance
        // this is necessary because the runtime cache is sometimes in use before the actual service container
        // is initialized
        if (!self::$tempInstance) {
            self::$tempInstance = new self;
        }

        return self::$tempInstance;
    }

    /**
     * getter method, basically same as offsetGet().
     *
     * This method can be called from an object of type \Pimcore\Cache\Runtime, or it
     * can be called statically.  In the latter case, it uses the default
     * static instance stored in the class.
     *
     * @param string $index - get the value associated with $index
     *
     * @return mixed
     *
     * @throws \Exception if no entry is registered for $index.
     */
    public static function get(string $index)
    {
        $instance = self::getInstance();

        if (!$instance->offsetExists($index)) {
            throw new \Exception("No entry is registered for key '$index'");
        }

        return $instance->offsetGet($index);
    }

    /**
     * setter method, basically same as offsetSet().
     *
     * This method can be called from an object of type \Pimcore\Cache\Runtime, or it
     * can be called statically.  In the latter case, it uses the default
     * static instance stored in the class.
     *
     * @param string $index The location in the ArrayObject in which to store
     *   the value.
     * @param mixed $value The object to store in the ArrayObject.
     *
     */
    public static function set(string $index, mixed $value)
    {
        $instance = self::getInstance();
        $instance->offsetSet($index, $value);
    }

    /**
     * Returns TRUE if the $index is a named value in the registry,
     * or FALSE if $index was not found in the registry.
     *
     * @param  string $index
     *
     * @return bool
     */
    public static function isRegistered(string $index)
    {
        $instance = self::getInstance();

        return $instance->offsetExists($index);
    }

    /**
     * Constructs a parent ArrayObject with default
     * ARRAY_AS_PROPS to allow access as an object
     *
     * @param array $array data array
     * @param int $flags ArrayObject flags
     */
    public function __construct($array = [], $flags = parent::ARRAY_AS_PROPS)
    {
        parent::__construct($array, $flags);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($index, $value): void
    {
        parent::offsetSet($index, $value);
    }

    /**
     * Alias of self::set() to be compatible with Pimcore\Cache
     *
     * @param mixed $data
     * @param string $id
     */
    public static function save(mixed $data, string $id)
    {
        self::set($id, $data);
    }

    /**
     * Alias of self::get() to be compatible with Pimcore\Cache
     *
     * @param string $id
     *
     * @return mixed
     */
    public static function load(string $id)
    {
        return self::get($id);
    }

    /**
     * @param array $keepItems
     */
    public static function clear(array $keepItems = [])
    {
        self::$instance = null;
        $newInstance = new self();
        $oldInstance = self::getInstance();

        foreach ($keepItems as $key) {
            if ($oldInstance->offsetExists($key)) {
                $newInstance->offsetSet($key, $oldInstance->offsetGet($key));
            }
        }

        \Pimcore::getContainer()->set(self::SERVICE_ID, $newInstance);
        self::$instance = $newInstance;
    }
}
