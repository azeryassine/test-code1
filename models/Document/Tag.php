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
 * @package    Document
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\Document;

@trigger_error(sprintf('Class "%s" is deprecated since v6.8 and will be removed in Pimcore 10. Use "%s" instead.', Tag::class, Editable::class), E_USER_DEPRECATED);

class_exists(Editable::class);

if (false) {
    /**
     * @deprecated use \Pimcore\Model\Document\Editable instead.
     */
    abstract class Tag extends Editable
    {
    }
}
