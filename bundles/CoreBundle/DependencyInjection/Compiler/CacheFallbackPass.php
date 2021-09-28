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

namespace Pimcore\Bundle\CoreBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
final class CacheFallbackPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('pimcore.cache.pool')) {
            $alias = new Alias('pimcore.cache.adapter.pdo_tag_aware', true);
            $container->setAlias('pimcore.cache.pool', $alias);
        }

        // set default cache.app to Pimcore default cache, if not configured differently
        $appCache = $container->findDefinition('cache.app');
        if ($appCache instanceof ChildDefinition && $appCache->getParent() === 'cache.adapter.filesystem') {

            $this->replaceCacheDefinition('cache.app', $container);

            foreach($container->findTaggedServiceIds('cache.pool') as $id => $arguments) {
                $cacheDef = $container->findDefinition($id);
                if($cacheDef instanceof ChildDefinition && $cacheDef->getParent() === 'cache.app') {
                    $this->replaceCacheDefinition($id, $container);
                }
            }
        }
    }

    private function replaceCacheDefinition(string $id, ContainerBuilder $container): void
    {
        $container->removeDefinition($id);
        $def = new ChildDefinition('pimcore.cache.pool.app');
        $container->setDefinition($id, $def);
    }
}
