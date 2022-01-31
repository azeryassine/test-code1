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

namespace Pimcore\Model\DataObject\Classificationstore;

use Pimcore\Cache;
use Pimcore\Event\DataObjectClassificationStoreEvents;
use Pimcore\Event\Model\DataObject\ClassificationStore\CollectionConfigEvent;
use Pimcore\Model;

/**
 * @method \Pimcore\Model\DataObject\Classificationstore\CollectionConfig\Dao getDao()
 */
final class CollectionConfig extends Model\AbstractModel
{
    /**
     * @var bool
     */
    public static $cacheEnabled = false;

    /**
     * @var int|null
     */
    protected $id;

    /**
     * Store ID
     *
     * @var int
     */
    protected $storeId = 1;

    /**
     * @var string
     */
    protected $name;

    /**
     * The collection description.
     *
     * @var string
     */
    protected $description;

    /**
     * @var int|null
     */
    protected $creationDate;

    /**
     * @var int|null
     */
    protected $modificationDate;

    /**
     * @param int $id
     *
     * @return self|null
     */
    public static function getById($id)
    {
        $id = (int)$id;
        $cacheKey = self::getCacheKey($id);

        try {
            if ($config = self::getCache($cacheKey)) {
                return $config;
            }

            $config = new self();
            $config->getDao()->getById($id);

            self::setCache($config, $cacheKey);

            return $config;
        } catch (Model\Exception\NotFoundException $e) {
            return null;
        }
    }

    /**
     * @param string $name
     * @param int $storeId
     *
     * @return self|null
     *
     * @throws \Exception
     */
    public static function getByName($name, $storeId = 1)
    {
        $cacheKey = self::getCacheKey($storeId, $name);

        try {
            if ($config = self::getCache($cacheKey)) {
                return $config;
            }

            $config = new self();
            $config->setName($name);
            $config->setStoreId($storeId ? $storeId : 1);
            $config->getDao()->getByName();

            self::setCache($config, $cacheKey);

            return $config;
        } catch (Model\Exception\NotFoundException $e) {
            return null;
        }
    }

    /**
     * @return Model\DataObject\Classificationstore\CollectionConfig
     */
    public static function create()
    {
        $config = new self();
        $config->save();

        return $config;
    }

    /**
     * @param bool $cacheEnabled
     */
    public static function setCacheEnabled($cacheEnabled)
    {
        self::$cacheEnabled = $cacheEnabled;
    }

    /**
     * @return bool
     */
    public static function getCacheEnabled()
    {
        return self::$cacheEnabled;
    }

    /**
     * @param int $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = (int) $id;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Sets the description.
     *
     * @param string $description
     *
     * @return Model\DataObject\Classificationstore\CollectionConfig
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Deletes the key value collection configuration
     */
    public function delete()
    {
        \Pimcore::getEventDispatcher()->dispatch(new CollectionConfigEvent($this), DataObjectClassificationStoreEvents::COLLECTION_CONFIG_PRE_DELETE);
        if ($this->getId()) {
            self::removeCache(self::getCacheKey($this->getId()));
            self::removeCache(self::getCacheKey($this->getStoreId(), $this->getName()));
        }

        $this->getDao()->delete();
        \Pimcore::getEventDispatcher()->dispatch(new CollectionConfigEvent($this), DataObjectClassificationStoreEvents::COLLECTION_CONFIG_POST_DELETE);
    }

    /**
     * Saves the collection config
     */
    public function save()
    {
        $isUpdate = false;

        if ($this->getId()) {
            self::removeCache(self::getCacheKey($this->getId()));
            self::removeCache(self::getCacheKey($this->getStoreId(), $this->getName()));

            $isUpdate = true;
            \Pimcore::getEventDispatcher()->dispatch(new CollectionConfigEvent($this), DataObjectClassificationStoreEvents::COLLECTION_CONFIG_PRE_UPDATE);
        } else {
            \Pimcore::getEventDispatcher()->dispatch(new CollectionConfigEvent($this), DataObjectClassificationStoreEvents::COLLECTION_CONFIG_PRE_ADD);
        }

        $model = $this->getDao()->save();

        if ($isUpdate) {
            \Pimcore::getEventDispatcher()->dispatch(new CollectionConfigEvent($this), DataObjectClassificationStoreEvents::COLLECTION_CONFIG_POST_UPDATE);
        } else {
            \Pimcore::getEventDispatcher()->dispatch(new CollectionConfigEvent($this), DataObjectClassificationStoreEvents::COLLECTION_CONFIG_POST_ADD);
        }

        return $model;
    }

    /**
     * @param int $modificationDate
     *
     * @return $this
     */
    public function setModificationDate($modificationDate)
    {
        $this->modificationDate = (int) $modificationDate;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getModificationDate()
    {
        return $this->modificationDate;
    }

    /**
     * @param int $creationDate
     *
     * @return $this
     */
    public function setCreationDate($creationDate)
    {
        $this->creationDate = (int) $creationDate;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getCreationDate()
    {
        return $this->creationDate;
    }

    /**
     * Returns all group belonging to this collection
     *
     * @return CollectionGroupRelation[]
     */
    public function getRelations()
    {
        $list = new CollectionGroupRelation\Listing();
        $list->setCondition('colId = ' . $this->id);
        $list = $list->load();

        return $list;
    }

    /**
     * @return int
     */
    public function getStoreId()
    {
        return $this->storeId;
    }

    /**
     * @param int $storeId
     */
    public function setStoreId($storeId)
    {
        $this->storeId = $storeId;
    }

    /**
     * Set cache item for a given cache key
     *
     * @param CollectionConfig $config
     * @param string $cacheKey
     */
    private static function setCache(CollectionConfig $config, string $cacheKey): void
    {
        if (self::$cacheEnabled) {
            Cache\Runtime::set($cacheKey, $config);
        }

        Cache::save($config, $cacheKey, [], null, 0, true);
    }

    /**
     * Remove a cache item for a given cache key
     *
     * @param string $cacheKey
     */
    private static function removeCache(string $cacheKey): void
    {
        Cache::remove($cacheKey);
        Cache\Runtime::set($cacheKey, null);
    }

    /**
     * Get a cache item for a given cache key
     *
     * @param string $cacheKey
     *
     * @return CollectionConfig|bool
     */
    private static function getCache(string $cacheKey): CollectionConfig|bool
    {
        if (self::$cacheEnabled && Cache\Runtime::isRegistered($cacheKey) && $config = Cache\Runtime::get($cacheKey)) {
            return $config;
        }

        return Cache::load($cacheKey);
    }

    /**
     * Calculate cache key
     *
     * @param int $id
     * @param string|null $name
     *
     * @return string
     */
    private static function getCacheKey(int $id, string $name = null): string
    {
        $cacheKey = 'cs_collectionconfig_' . $id;
        if ($name !== null) {
            $cacheKey .= '_' . md5($name);
        }

        return $cacheKey;
    }
}
