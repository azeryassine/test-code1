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
 * @package    Dependency
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model;

/**
 * @method \Pimcore\Model\Dependency\Dao getDao()
 */
class Dependency extends AbstractModel
{
    /**
     * The ID of the object to get dependencies for
     *
     * @var int
     */
    public $sourceId;

    /**
     * The type of the object to get dependencies for
     *
     * @var string
     */
    public $sourceType;

    /**
     * Contains the ID/type of objects which are required for the given source object (sourceId/sourceType)
     *
     * @var int
     */
    public $requires = [];

    /**
     * Contains the ID/type of objects that need the given source object (sourceId/sourceType)
     *
     * @var int
     */
    public $requiredBy = [];

    /**
     * Total count of objects which are required for the given source object (sourceId/sourceType)
     *
     * @var int
     */
    public $requiresTotalCount;

    /**
     * Total count of objects that need the given source object (sourceId/sourceType)
     *
     * @var int
     */
    public $requiredByTotalCount;

    /**
     * Static helper to get the dependencies for the given sourceId & type
     *
     * @param int $id
     * @param string $type
     *
     * @return Dependency
     */
    public static function getBySourceId($id, $type)
    {
        $d = new self();
        $d->setSourceId($id);
        $d->setSourceType($type);

        return $d;
    }

    /**
     * Add a requirement to the source object
     *
     * @param int $id
     * @param string $type
     */
    public function addRequirement($id, $type)
    {
        $this->requires[] = [
            'type' => $type,
            'id' => $id
        ];
    }

    /**
     * @param  Element\ELementInterface $element
     */
    public function cleanAllForElement($element)
    {
        $this->getDao()->cleanAllForElement($element);
    }

    /**
     * Cleanup the dependencies for current source id
     */
    public function clean()
    {
        $this->requires = [];
        $this->getDao()->clear();
    }

    /**
     * @return int
     */
    public function getSourceId()
    {
        return $this->sourceId;
    }

    /**
     * @return array
     */
    public function getRequires($offset = null, $limit = null)
    {
        $this->getDao()->getRequires($offset, $limit);

        return $this->requires;
    }

    /**
     * @return array
     */
    public function getRequiredBy($offset = null, $limit = null)
    {
        $this->getDao()->getRequiredBy($offset, $limit);
        
        return $this->requiredBy;
    }

    /**
     * @param int $sourceId
     *
     * @return $this
     */
    public function setSourceId($sourceId)
    {
        $this->sourceId = (int) $sourceId;

        return $this;
    }

    /**
     * @param array $requires
     *
     * @return $this
     */
    public function setRequires($requires)
    {
        $this->requires = $requires;

        return $this;
    }

    /**
     * @param array $requiredBy
     *
     * @return $this
     */
    public function setRequiredBy($requiredBy)
    {
        $this->requiredBy = $requiredBy;

        return $this;
    }

    /**
     * @return string
     */
    public function getSourceType()
    {
        return $this->sourceType;
    }

    /**
     * @param string $sourceType
     *
     * @return $this
     */
    public function setSourceType($sourceType)
    {
        $this->sourceType = $sourceType;

        return $this;
    }


    /**
     * @return int
     */
    public function getRequiresTotalCount()
    {
        return $this->requiresTotalCount;
    }

    /**
     * @param int $requiresTotalCount
     *
     * @return $this
     */
    public function setRequiresTotalCount($requiresTotalCount)
    {
        $this->requiresTotalCount = $requiresTotalCount;

        return $this;
    }

    /**
     * @return int
     */
    public function getRequiredByTotalCount()
    {
        return $this->requiredByTotalCount;
    }

    /**
     * @param int $requiresTotalCount
     *
     * @return $this
     */
    public function setRequiredByTotalCount($requiredByTotalCount)
    {
        $this->requiredByTotalCount = $requiredByTotalCount;

        return $this;
    }

    /**
     * Check if the source object is required by an other object (an other object depends on this object)
     *
     * @return bool
     */
    public function isRequired()
    {
        if (is_array($this->getRequires(0,1)) && count($this->getRequires(0,1)) > 0) {
            return true;
        }

        return false;
    }
}
