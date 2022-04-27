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

namespace Pimcore\DataObject\ClassBuilder;

use Pimcore\Model\DataObject\ClassDefinition;

class ListingClassFieldDefinitionBuilder implements ListingClassFieldDefinitionBuilderInterface
{
    public function buildListingClassFieldDefinition(ClassDefinition $classDefinition, ClassDefinition\Data $fieldDefinition): string
    {
        if ($fieldDefinition instanceof ClassDefinition\Data\Localizedfields) {
            $cd = '';
            foreach ($fieldDefinition->getFieldDefinitions() as $localizedFieldDefinition) {
                $cd .= $localizedFieldDefinition->getFilterCode();
            }

            return $cd;
        }

        if ($fieldDefinition->isFilterable()) {
            return $fieldDefinition->getFilterCode();
        }

        return '';
    }
}
