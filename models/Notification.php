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

namespace Pimcore\Model;

use Pimcore\Cache;
use Pimcore\Event\Model\NotificationEvent;
use Pimcore\Event\NotificationEvents;
use Pimcore\Event\Traits\RecursionBlockingEventDispatchHelperTrait;
use Pimcore\Model\Exception\NotFoundException;

/**
 * @method Notification\Dao getDao()
 */
class Notification extends AbstractModel
{
    use RecursionBlockingEventDispatchHelperTrait;

    /**
     * @internal
     *
     * @var int
     */
    protected int $id;

    /**
     * @internal
     *
     * @var string|null
     */
    protected ?string $creationDate = null;

    /**
     * @internal
     *
     * @var string|null
     */
    protected ?string $modificationDate = null;

    /**
     * @internal
     *
     * @var User|null
     */
    protected ?User $sender = null;

    /**
     * @internal
     *
     * @var User|null
     */
    protected ?User $recipient = null;

    /**
     * @internal
     *
     * @var string
     */
    protected string $title;

    /**
     * @internal
     *
     * @var string|null
     */
    protected ?string $type = null;

    /**
     * @internal
     *
     * @var string|null
     */
    protected ?string $message = null;

    /**
     * @internal
     *
     * @var Element\ElementInterface|null
     */
    protected ?Element\ElementInterface $linkedElement = null;

    /**
     * @internal
     *
     * @var string|null
     */
    protected ?string $linkedElementType = null;

    /**
     * @internal
     *
     * @var bool
     */
    protected bool $read = false;

    public static function getById(int $id): ?Notification
    {
        $cacheKey = sprintf('notification_%d', $id);

        try {
            $notification = Cache\RuntimeCache::get($cacheKey);
        } catch (\Exception $ex) {
            try {
                $notification = new self();
                $notification->getDao()->getById($id);
                Cache\RuntimeCache::set($cacheKey, $notification);
            } catch (NotFoundException $e) {
                $notification = null;
            }
        }

        return $notification;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return $this
     */
    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getCreationDate(): ?string
    {
        return $this->creationDate;
    }

    /**
     * @return $this
     */
    public function setCreationDate(string $creationDate): static
    {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function getModificationDate(): ?string
    {
        return $this->modificationDate;
    }

    /**
     * @return $this
     */
    public function setModificationDate(string $modificationDate): static
    {
        $this->modificationDate = $modificationDate;

        return $this;
    }

    public function getSender(): ?User
    {
        return $this->sender;
    }

    /**
     * @return $this
     */
    public function setSender(?User $sender): static
    {
        $this->sender = $sender;

        return $this;
    }

    public function getRecipient(): ?User
    {
        return $this->recipient;
    }

    /**
     * @return $this
     */
    public function setRecipient(?User $recipient): static
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @return $this
     */
    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * @return $this
     */
    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * @return $this
     */
    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getLinkedElement(): ?Element\ElementInterface
    {
        return $this->linkedElement;
    }

    /**
     * @return $this
     */
    public function setLinkedElement(?Element\ElementInterface $linkedElement): static
    {
        $this->linkedElement = $linkedElement;
        $this->linkedElementType = Element\Service::getElementType($linkedElement);

        return $this;
    }

    /**
     * enum('document','asset', 'object) nullable
     *
     * @return string|null
     */
    public function getLinkedElementType(): ?string
    {
        return $this->linkedElementType;
    }

    public function isRead(): bool
    {
        return $this->read;
    }

    /**
     * @return $this
     */
    public function setRead(bool $read): static
    {
        $this->read = $read;

        return $this;
    }

    /**
     * Save notification
     */
    public function save(): void
    {
        $this->dispatchEvent(new NotificationEvent($this), NotificationEvents::PRE_SAVE);
        $this->getDao()->save();
        $this->dispatchEvent(new NotificationEvent($this), NotificationEvents::POST_SAVE);
    }

    /**
     * Delete notification
     */
    public function delete(): void
    {
        $this->dispatchEvent(new NotificationEvent($this), NotificationEvents::PRE_DELETE);
        $this->getDao()->delete();
        $this->dispatchEvent(new NotificationEvent($this), NotificationEvents::POST_DELETE);
    }
}
