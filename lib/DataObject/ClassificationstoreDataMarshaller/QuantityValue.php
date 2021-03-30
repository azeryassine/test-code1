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

namespace Pimcore\DataObject\ClassificationstoreDataMarshaller;

use Pimcore\DataObject\FielddefinitionMarshaller\Traits\RgbaColorTrait;
use Pimcore\Marshaller\MarshallerInterface;
use Pimcore\Tool\Serialize;

class QuantityValue implements MarshallerInterface
{
    /** { @inheritDoc } */
    public function marshal($value, $params = [])
    {
        if (is_array($value)) {
            return ['value' => $value['value'],
                'value2' => $value['unitId']];
        }
        return null;

    }

    /** { @inheritDoc } */
    public function unmarshal($value, $params = [])
    {
        if (is_array($value)) {
            $result = [
                "value" => $value["value"],
                "unitId" => $value["value2"]

            ];
            return $result;
        }
        return null;

    }
}
