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

namespace Pimcore\Bundle\CustomReportsBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class PimcoreCustomReportsExtension extends ConfigurableExtension
{
    private function configureAdapterFactories(ContainerBuilder $container, array $factories, string $serviceLocatorId): void
    {
        $serviceLocator = $container->getDefinition($serviceLocatorId);
        $arguments = [];

        foreach ($factories as $key => $serviceId) {
            $arguments[$key] = new Reference($serviceId);
        }

        $serviceLocator->setArgument(0, $arguments);
    }

    public function loadInternal(array $config, ContainerBuilder $container)
    {
        \Pimcore::disableShutdown();

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../config')
        );

        $loader->load('services.yaml');

        $this->configureAdapterFactories($container, $config['adapters'], 'pimcore.custom_report.adapter.factories');
        $container->setParameter('pimcore_custom_reports.definitions', $config['definitions'] ?? []);
    }
}