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

use Pimcore\File;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ObjectBrick\Definition;

class PHPObjectBrickContainerClassWriter implements PHPObjectBrickContainerClassWriterInterface
{
    public function __construct(protected ObjectBrickContainerClassBuilderInterface $classBuilder)
    {
    }

    public function writeContainerClasses(Definition $definition): void
    {
        $containerDefinition = [];

        $list = new Definition\Listing();
        $list = $list->load();
        foreach ($list as $def) {
            if ($definition->getKey() != $def->getKey()) {
                $classDefinitions = $def->getClassDefinitions();
                if (!empty($classDefinitions)) {
                    foreach ($classDefinitions as $cl) {
                        $containerDefinition[$cl['classname']][$cl['fieldname']][] = $def->getKey();
                    }
                }
            }
        }

        foreach ($containerDefinition as $classId => $cd) {
            $class = ClassDefinition::getByName($classId);

            if (!$class) {
                continue;
            }

            foreach ($cd as $fieldname => $brickKeys) {
                $cd = $this->classBuilder->buildContainerClass($definition, $class, $fieldname, $brickKeys);
                $folder = $definition->getContainerClassFolder($class->getName());

                if (!is_dir($folder)) {
                    File::mkdir($folder);
                }

                $file = $folder . '/' . ucfirst($fieldname) . '.php';
                File::put($file, $cd);
            }
        }
    }
}
