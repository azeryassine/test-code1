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

namespace Pimcore\Event;

final class ObjectbrickDefinitionEvents
{
    /**
     * @Event("Pimcore\Event\Model\DataObject\ClassDefinitionEvent")
     *
     * @var string
     */
    const PRE_ADD = 'pimcore.objectbrick.preAdd';

    /**
     * @Event("Pimcore\Event\Model\DataObject\ClassDefinitionEvent")
     *
     * @var string
     */
    const POST_ADD = 'pimcore.objectbrick.postAdd';

    /**
     * @Event("Pimcore\Event\Model\DataObject\ClassDefinitionEvent")
     *
     * @var string
     */
    const PRE_UPDATE = 'pimcore.objectbrick.preUpdate';

    /**
     * @Event("Pimcore\Event\Model\DataObject\ClassDefinitionEvent")
     *
     * @var string
     */
    const POST_UPDATE = 'pimcore.objectbrick.postUpdate';

    /**
     * @Event("Pimcore\Event\Model\DataObject\ClassDefinitionEvent")
     *
     * @var string
     */
    const PRE_DELETE = 'pimcore.objectbrick.preDelete';

    /**
     * @Event("Pimcore\Event\Model\DataObject\ClassDefinitionEvent")
     *
     * @var string
     */
    const POST_DELETE = 'pimcore.objectbrick.postDelete';
}
