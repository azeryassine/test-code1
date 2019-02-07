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
 * @category   Pimcore
 * @package    Object
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\DataObject;

use Pimcore\Db\ZendCompatibility\Expression;
use Pimcore\Model;
use Zend\Paginator\Adapter\AdapterInterface;
use Zend\Paginator\AdapterAggregateInterface;

/**
 * @method Model\DataObject[] load()
 * @method int getTotalCount()
 * @method int getCount()
 * @method int[] loadIdList()
 * @method \Pimcore\Model\DataObject\Listing\Dao getDao()
 * @method onCreateQuery(callable $callback)
 */
class Listing extends Model\Listing\AbstractListing implements \Zend_Paginator_Adapter_Interface, \Zend_Paginator_AdapterAggregate, \Iterator, AdapterInterface, AdapterAggregateInterface
{
    /**
     * @var array
     */
    public $objects = null;

    /**
     * @var bool
     */
    public $unpublished = false;

    /**
     * @var array
     */
    public $objectTypes = [AbstractObject::OBJECT_TYPE_OBJECT, AbstractObject::OBJECT_TYPE_FOLDER];

    /**
     * @return array
     */
    public function getObjects()
    {
        if ($this->objects === null) {
            $this->load();
        }

        return $this->objects;
    }

    /**
     * @param array $objects
     *
     * @return $this
     */
    public function setObjects($objects)
    {
        $this->objects = $objects;

        return $this;
    }

    /**
     * @return bool
     */
    public function getUnpublished()
    {
        return $this->unpublished;
    }

    /**
     * @param $unpublished
     *
     * @return $this
     */
    public function setUnpublished($unpublished)
    {
        $this->unpublished = (bool) $unpublished;

        return $this;
    }

    /**
     * @param  $objectTypes
     *
     * @return $this
     */
    public function setObjectTypes($objectTypes)
    {
        $this->objectTypes = $objectTypes;

        return $this;
    }

    /**
     * @return array
     */
    public function getObjectTypes()
    {
        return $this->objectTypes;
    }

    /**
     * @param $key
     * @param null $value
     * @param string $concatenator
     *
     * @return $this
     */
    public function addConditionParam($key, $value = null, $concatenator = 'AND')
    {
        return parent::addConditionParam($key, $value, $concatenator); // TODO: Change the autogenerated stub
    }

    /**
     * @return $this
     */
    public function resetConditionParams()
    {
        return parent::resetConditionParams(); // TODO: Change the autogenerated stub
    }

    /**
     * @param $condition
     * @param null $conditionVariables
     *
     * @return $this
     */
    public function setCondition($condition, $conditionVariables = null)
    {
        return parent::setCondition($condition, $conditionVariables);
    }

    /**
     * @param $groupBy
     * @param bool $qoute
     *
     * @return $this
     */
    public function setGroupBy($groupBy, $qoute = true)
    {
        if ($groupBy) {
            $this->groupBy = $groupBy;

            if (!$qoute) {
                $this->groupBy = new Expression($groupBy);
            }
        }

        return $this;
    }

    /**
     *
     * Methods for \Zend_Paginator_Adapter_Interface | AdapterInterface
     */

    /**
     * @return int
     */
    public function count()
    {
        return $this->getTotalCount();
    }

    /**
     * @param int $offset
     * @param int $itemCountPerPage
     *
     * @return Model\DataObject[]
     */
    public function getItems($offset, $itemCountPerPage)
    {
        $this->setOffset($offset);
        $this->setLimit($itemCountPerPage);

        return $this->load();
    }

    /**
     * @return Model\DataObject\Listing|\Zend_Paginator_Adapter_Interface|AdapterInterface
     */
    public function getPaginatorAdapter()
    {
        return $this;
    }

    /**
     * Methods for Iterator
     */
    public function rewind()
    {
        $this->getObjects();
        reset($this->objects);
    }

    /**
     * @return mixed
     */
    public function current()
    {
        $this->getObjects();
        $var = current($this->objects);

        return $var;
    }

    /**
     * @return mixed
     */
    public function key()
    {
        $this->getObjects();
        $var = key($this->objects);

        return $var;
    }

    /**
     * @return mixed|null
     */
    public function next()
    {
        $this->getObjects();
        $var = next($this->objects);

        return $var;
    }

    /**
     * @return bool
     */
    public function valid()
    {
        $this->getObjects();
        $var = $this->current() !== false;

        return $var;
    }

    /**
     * @return bool
     */
    public function addDistinct()
    {
        return false;
    }
}
