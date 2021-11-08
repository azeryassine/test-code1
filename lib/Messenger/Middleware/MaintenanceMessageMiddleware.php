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
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Messenger\Middleware;


use Pimcore\Messenger\Stamp\MaintenanceTagStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

class MaintenanceMessageMiddleware implements MiddlewareInterface
{

    public function __construct(protected array $skipMessages = [])
    {
    }

    /**
     * @param $message
     */
    public function addSkipMessage($message): void
    {
        $this->skipMessages[] = $message;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        /** @var MaintenanceTagStamp $stamp */
        if (null !== $stamp = $envelope->last(MaintenanceTagStamp::class)) {
            if (in_array($stamp->getTag(), array_values($this->skipMessages))) {
                return $envelope->with(new DispatchAfterCurrentBusStamp());
            }
        }

       return $stack->next()->handle($envelope, $stack);
    }
}
