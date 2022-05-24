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

namespace Pimcore\Model\User\Workspace;

class DataObject extends AbstractWorkspace
{
    /**
     * @internal
     *
     * @var bool
     */
    protected $save = false;

    /**
     * @internal
     *
     * @var bool
     */
    protected $unpublish = false;

    /**
     * @internal
     *
     * @var string
     */
    protected $lEdit = null;

    /**
     * @internal
     *
     * @var string
     */
    protected $lView = null;

    /**
     * @internal
     *
     * @var string
     */
    protected $layouts = null;

    /**
     * @param bool $save
     *
     * @return $this
     */
    public function setSave(bool $save)
    {
        $this->save = $save;

        return $this;
    }

    /**
     * @return bool
     */
    public function getSave()
    {
        return $this->save;
    }

    /**
     * @param bool $unpublish
     *
     * @return $this
     */
    public function setUnpublish(bool $unpublish)
    {
        $this->unpublish = $unpublish;

        return $this;
    }

    /**
     * @return bool
     */
    public function getUnpublish()
    {
        return $this->unpublish;
    }

    /**
     * @param string $lEdit
     */
    public function setLEdit(string $lEdit)
    {
        //@TODO - at the moment disallowing all languages is not possible - the empty lEdit value means that every language is allowed to edit...
        $this->lEdit = $lEdit;
    }

    /**
     * @return string
     */
    public function getLEdit()
    {
        return $this->lEdit;
    }

    /**
     * @param string $lView
     */
    public function setLView(string $lView)
    {
        $this->lView = $lView;
    }

    /**
     * @return string
     */
    public function getLView()
    {
        return $this->lView;
    }

    /**
     * @param string $layouts
     */
    public function setLayouts(string $layouts)
    {
        $this->layouts = $layouts;
    }

    /**
     * @return string
     */
    public function getLayouts()
    {
        return $this->layouts;
    }
}
