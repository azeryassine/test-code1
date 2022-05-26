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

namespace Pimcore\Event\Cache\FullPage;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Determines if a response can be cached.
 */
class CacheResponseEvent extends Event
{
    /**
     * @var Response
     */
    private $response;

    /**
     * @var bool
     */
    private $cache;

    /**
     * @param Response $response
     * @param bool $cache
     */
    public function __construct(Response $response, bool $cache)
    {
        $this->response = $response;
        $this->cache = $cache;
    }

    /**
     * @return Response
     */
    public function getResponse(): Response
    {
        return $this->response;
    }

    /**
     * @return bool
     */
    public function getCache(): bool
    {
        return $this->cache;
    }

    /**
     * @param bool $cache
     * @return void
     */
    public function setCache(bool $cache)
    {
        $this->cache = $cache;
    }
}
