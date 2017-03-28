<?php

namespace TestSuite\Pimcore\Cache\Adapter\SymfonyProxy;

use Cache\IntegrationTests\TaggableCachePoolTest;
use Pimcore\Cache\Pool\PimcoreCacheItemPoolInterface;
use TestSuite\Pimcore\Cache\Factory;
use TestSuite\Pimcore\Cache\Pool\SymfonyProxy\Traits\SymfonyProxyTestTrait;
use TestSuite\Pimcore\Cache\Pool\Traits\CacheItemPoolTestTrait;

class TaggableFilesystemAdapterProxyTest extends TaggableCachePoolTest
{
    use SymfonyProxyTestTrait;
    use CacheItemPoolTestTrait;

    protected $skippedTests = [
        'testPreviousTag'              => 'Previous tags are not loaded for performance reasons.',
        'testPreviousTagDeferred'      => 'Previous tags are not loaded for performance reasons.',
        'testTagAccessorDuplicateTags' => 'Previous tags are not loaded for performance reasons.',
    ];

    /**
     * @return PimcoreCacheItemPoolInterface
     */
    protected function buildCachePool()
    {
        return (new Factory())->createFilesystemAdapterProxyItemPool($this->defaultLifetime);
    }
}
