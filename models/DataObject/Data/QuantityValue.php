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
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\DataObject\Data;

use Pimcore\Model\DataObject\OwnerAwareFieldInterface;
use Pimcore\Model\DataObject\QuantityValue\Unit;
use Pimcore\Model\DataObject\Traits\OwnerAwareFieldTrait;

class QuantityValue implements OwnerAwareFieldInterface
{
    use OwnerAwareFieldTrait;

    /**
     * @var float | string
     */
    protected $value;

    /**
     * @var int
     */
    protected $unitId;

    /**
     * @var \Pimcore\Model\DataObject\QuantityValue\Unit
     */
    protected $unit;

    /**
     * QuantityValue constructor.
     *
     * @param null $value
     * @param null $unitId
     */
    public function __construct($value = null, $unitId = null)
    {
        $this->value = $value;
        $this->unitId = $unitId;
        $this->unit = '';

        if ($unitId) {
            $this->unit = Unit::getById($this->unitId);
        }
        $this->markMeDirty();
    }

    /**
     * @param  $unitId
     */
    public function setUnitId($unitId)
    {
        $this->unitId = $unitId;
        $this->unit = null;
        $this->markMeDirty();
    }

    /**
     * @return int
     */
    public function getUnitId()
    {
        return $this->unitId;
    }

    /**
     * @return Unit
     */
    public function getUnit()
    {
        if (empty($this->unit)) {
            $this->unit = Unit::getById($this->unitId);
        }

        return $this->unit;
    }

    /**
     * @param  $value
     */
    public function setValue($value)
    {
        $this->value = $value;
        $this->markMeDirty();
    }

    /**
     * @return float
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param Unit $toUnit
     *
     * @return QuantityValue
     * @throws \Exception
     */
    public function convert(Unit $toUnit) {
        $fromUnit = $this->getUnit();
        if(!$fromUnit instanceof Unit) {
            throw new \Exception('Quantity value has no unit');
        }

        $fromBaseUnit = $fromUnit->getBaseunit();
        if($fromBaseUnit === null) {
            $fromUnit = clone $fromUnit;
            $fromBaseUnit = $fromUnit;
            $fromUnit->setFactor(1);
            $fromUnit->setConversionOffset(0);
        }

        $toBaseUnit = $toUnit->getBaseunit();
        if($toBaseUnit === null) {
            $toUnit = clone $toUnit;
            $toBaseUnit = $toUnit;
            $toUnit->setFactor(1);
            $toUnit->setConversionOffset(0);
        }

        if($fromBaseUnit === null || $toBaseUnit === null || $fromBaseUnit->getId() !== $toBaseUnit->getId()) {
            throw new \Exception($fromUnit.' must have same base unit as '.$toUnit.' to be able to convert values');
        }

        $convertedValue = ($this->getValue()*$fromUnit->getFactor() - $fromUnit->getConversionOffset()) / $toUnit->getFactor() + $toUnit->getConversionOffset();
        return new QuantityValue($convertedValue, $toUnit->getId());
    }

    /**
     * @return string
     *
     * @throws \Exception
     */
    public function __toString()
    {
        $value = $this->getValue();
        if (is_numeric($value)) {
            $locale = \Pimcore::getContainer()->get('pimcore.locale')->findLocale();

            if ($locale) {
                $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
                $value = $formatter->format($value);
            }
        }

        if ($this->getUnit() instanceof Unit) {
            $value .= ' ' . $this->getUnit()->getAbbreviation();
        }

        return $value ? $value : '';
    }
}
