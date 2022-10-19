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

namespace Pimcore\Bundle\EcommerceFrameworkBundle\PaymentManager\V7\Payment\StartPaymentRequest;

class AbstractRequest implements \ArrayAccess
{
    public function __construct($data = [])
    {
        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }
    }

    public function set(string $name, mixed $value)
    {
        $this->{$name} = $value;
    }

    public function &get(string $name): mixed
    {
        return $this->{$name};
    }

    #[\ReturnTypeWillChange]
    public function offsetGet(mixed $offset): mixed// : mixed
    {
        return $this->get($offset);
    }

    #[\ReturnTypeWillChange]
    public function offsetSet(mixed $offset, mixed $value): void// : void
    {
        $this->set($offset, $value);
    }

    #[\ReturnTypeWillChange]
    public function offsetExists(mixed $offset): bool// : bool
    {
        return isset($this->{$offset});
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset(mixed $offset): void// : void
    {
        $this->{$offset} = null;
    }

    public function asArray(): array
    {
        return get_object_vars($this);
    }
}
