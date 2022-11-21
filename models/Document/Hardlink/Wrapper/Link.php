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

namespace Pimcore\Model\Document\Hardlink\Wrapper;

use Pimcore\Model;

/**
 * @method \Pimcore\Model\Document\Hardlink\Dao getDao()
 */
class Link extends Model\Document\Link implements Model\Document\Hardlink\Wrapper\WrapperInterface
{
    use Model\Document\Hardlink\Wrapper;

    /**
     * {@inheritdoc}
     */
    public function getHref(): string
    {
        if ($this->getLinktype() === 'internal' && $this->getInternalType() === 'document') {
            $element = $this->getElement();
            if (
                $element instanceof Model\Document &&
                (
                    str_starts_with($element->getRealFullPath(), $this->getHardLinkSource()->getSourceDocument()->getRealFullPath() . '/') ||
                    $this->getHardLinkSource()->getSourceDocument()->getRealFullPath() === $element->getRealFullPath()
                )
            ) {
                // link target is child of hardlink source
                $c = Model\Document\Hardlink\Service::wrap($element);
                if ($c instanceof WrapperInterface) {
                    $hardLink = $this->getHardLinkSource();
                    $c->setHardLinkSource($hardLink);

                    if ($hardLink->getSourceDocument()->getRealFullpath() == $c->getRealFullPath()) {
                        $c->setPath($hardLink->getPath());
                        $c->setKey($hardLink->getKey());
                    } else {
                        $c->setPath(preg_replace('@^' . preg_quote($hardLink->getSourceDocument()->getRealFullpath(), '@') . '@', $hardLink->getRealFullpath(), $c->getRealPath()));
                    }

                    $this->setElement($c);
                }
            }
        }

        return parent::getHref();
    }
}
