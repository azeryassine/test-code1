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

namespace Pimcore\Bundle\AdminBundle;

use Pimcore\Bundle\AdminBundle\DependencyInjection\Compiler\ContentSecurityPolicyUrlsPass;
use Pimcore\Bundle\AdminBundle\DependencyInjection\Compiler\GDPRDataProviderPass;
use Pimcore\Bundle\AdminBundle\DependencyInjection\Compiler\ImportExportLocatorsPass;
use Pimcore\Bundle\AdminBundle\DependencyInjection\Compiler\LegacyAuthenticationSecurityPass;
use Pimcore\Bundle\AdminBundle\DependencyInjection\Compiler\SerializerPass;
use Pimcore\Bundle\AdminBundle\DependencyInjection\Compiler\TranslationServicesPass;
use Pimcore\Bundle\AdminBundle\GDPR\DataProvider\DataProviderInterface;
use Pimcore\Bundle\AdminBundle\Security\Factory\PreAuthenticatedAdminSessionFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @internal
 */
class PimcoreAdminBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        // auto-tag GDPR data providers
        $container
            ->registerForAutoconfiguration(DataProviderInterface::class)
            ->addTag('pimcore.gdpr.data-provider');

        $container->addCompilerPass(new SerializerPass());
        $container->addCompilerPass(new GDPRDataProviderPass());
        $container->addCompilerPass(new ImportExportLocatorsPass());
        $container->addCompilerPass(new TranslationServicesPass());
        $container->addCompilerPass(new ContentSecurityPolicyUrlsPass());
        $container->addCompilerPass(new LegacyAuthenticationSecurityPass());

        /** @var SecurityExtension $extension */
        $extension = $container->getExtension('security');
        $extension->addAuthenticatorFactory(new PreAuthenticatedAdminSessionFactory());
    }
}
