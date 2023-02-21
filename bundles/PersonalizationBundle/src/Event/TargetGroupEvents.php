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

namespace Pimcore\Bundle\PersonalizationBundle\Event;

final class TargetGroupEvents
{
    /**
     * @Event("Pimcore\Bundle\PersonalizationBundle\Event\Model\TargetGroupEvent")
     *
     * @var string
     */
    const POST_ADD = 'pimcore.targetgroup.postAdd';

    /**
     * @Event("Pimcore\Bundle\PersonalizationBundle\Event\Model\TargetGroupEvent")
     *
     * @var string
     */
    const POST_UPDATE = 'pimcore.targetgroup.postUpdate';

    /**
     * @Event("Pimcore\Bundle\PersonalizationBundle\Event\Model\TargetGroupEvent")
     *
     * @var string
     */
    const POST_DELETE = 'pimcore.targetgroup.postDelete';
}
