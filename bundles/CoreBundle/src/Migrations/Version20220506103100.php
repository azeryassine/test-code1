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

namespace Pimcore\Bundle\CoreBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220506103100 extends AbstractMigration
{

    public function getDescription(): string
    {
        return 'Modify `storageType` column in `version` database table';
    }

    public function up(Schema $schema): void
    {
        if ($schema->getTable('versions')->hasColumn('storageType') === true) {
            $this->addSql('ALTER TABLE `versions` MODIFY COLUMN `storageType` varchar(5) NOT NULL;');
        }
    }

    public function down(Schema $schema): void
    {
    }
}
