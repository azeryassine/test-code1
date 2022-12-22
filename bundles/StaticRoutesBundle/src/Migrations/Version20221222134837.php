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

namespace Pimcore\Bundle\StaticRoutesBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Pimcore\Bundle\StaticRoutesBundle\StaticRoutesBundle;
use Pimcore\Model\Tool\SettingsStore;

/**
 * Staticroutes will be enabled by default
 */
final class Version20221222134837 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Install static routes bundle by default';
    }

    public function up(Schema $schema): void
    {
        if(!SettingsStore::get('BUNDLE_INSTALLED__Pimcore\\Bundle\\StaticRoutesBundle\\StaticRoutesBundle', 'pimcore')) {
            SettingsStore::set('BUNDLE_INSTALLED__Pimcore\\Bundle\\StaticRoutesBundle\\StaticRoutesBundle', true, 'bool', 'pimcore');
        }

        $this->warnIf(
            null !== SettingsStore::get('BUNDLE_INSTALLED__Pimcore\\Bundle\\StaticRoutesBundle\\StaticRoutesBundle', 'pimcore'),
            sprintf('Please make sure to enable the %s manually in config/bundles.php', StaticRoutesBundle::class)
        );
    }
}
