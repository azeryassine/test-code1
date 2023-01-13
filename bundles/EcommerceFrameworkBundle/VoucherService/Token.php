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

namespace Pimcore\Bundle\EcommerceFrameworkBundle\VoucherService;

use Pimcore\Bundle\EcommerceFrameworkBundle\CartManager\CartInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\VoucherService\Token\Dao;
use Pimcore\Db;
use Pimcore\Model\AbstractModel;
use Pimcore\Model\Exception\NotFoundException;

/**
 * @method Dao getDao()
 * @method bool isReserved()
 */
class Token extends AbstractModel
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $voucherSeriesId;

    /**
     * @var string
     */
    public $token;

    /**
     * @var int
     */
    public $length;

    /**
     * @var string
     */
    public $type;

    /**
     * @var int
     */
    public $usages;

    /**
     * @var string
     */
    public $timestamp;

    /**
     * @param string $code
     *
     * @return Token|null
     */
    public static function getByCode($code)
    {
        try {
            $config = new self();
            $config->getDao()->getByCode($code);

            return $config;
        } catch (NotFoundException $ex) {
            return null;
        }
    }

    /**
     * @param int $maxUsages
     *
     * @return bool
     */
    public function isUsed($maxUsages = 1)
    {
        if ($this->usages >= $maxUsages) {
            return true;
        }

        return false;
    }

    /**
     * @param string $code
     * @param int $maxUsages
     * @return bool
     */
    public static function isUsedToken($code, $maxUsages = 1)
    {
        $db = Db::get();
        $query = 'SELECT usages FROM ' . Dao::TABLE_NAME . ' WHERE token = ? ';
        $params[] = $code;

        try {
            $tokenUsed = $db->fetchOne($query, $params);

            return $tokenUsed >= $maxUsages;
            // If an Error occurs the token is defined as used.
        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * @param null|int $maxUsages
     * @param bool $isCheckout In the checkout there is one reservation more, the one of the current order.
     *
     * @return bool
     */
    public function check($maxUsages = null, $isCheckout = false)
    {
        if (isset($maxUsages)) {
            if ($this->getUsages() + Reservation::getReservationCount($this->getToken()) - (int)$isCheckout <= $maxUsages) {
                return true;
            }

            return false;
        } else {
            return !$this->isUsed() && !$this->isReserved();
        }
    }

    /**
     * @param string $code
     *
     * @return bool
     */
    public static function tokenExists($code)
    {
        $db = Db::get();
        $query = 'SELECT EXISTS(SELECT id FROM ' . Dao::TABLE_NAME . ' WHERE token = ?)';
        $result = $db->fetchOne($query, [$code]);

        if ($result == 0) {
            return false;
        }

        return true;
    }

    /**
     * @param CartInterface $cart
     * @return bool
     */
    public function release($cart)
    {
        return Reservation::releaseToken($this->getToken(), $cart);
    }

    public function apply()
    {
        if ($this->getDao()->apply()) {
            Statistic::increaseUsageStatistic($this->getVoucherSeriesId());

            return true;
        }

        return false;
    }

    public function unuse()
    {
        if ($this->getDao()->unuse()) {
            return true;
        }

        return false;
    }

    /**
     * @return string
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * @param string $timestamp
     */
    public function setTimestamp($timestamp)
    {
        $this->timestamp = $timestamp;
    }

    /**
     * @return int
     */
    public function getVoucherSeriesId()
    {
        return $this->voucherSeriesId;
    }

    /**
     * @param int $voucherSeriesId
     */
    public function setVoucherSeriesId($voucherSeriesId)
    {
        $this->voucherSeriesId = $voucherSeriesId;
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @param string $token
     */
    public function setToken($token)
    {
        $this->token = $token;
    }

    /**
     * @return int
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     * @param int $length
     */
    public function setLength($length)
    {
        $this->length = $length;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return int
     */
    public function getUsages()
    {
        return $this->usages;
    }

    /**
     * @param int $usages
     */
    public function setUsages($usages)
    {
        $this->usages = $usages;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return void
     */
    public function setId($id)
    {
        $this->id = $id;
    }
}
