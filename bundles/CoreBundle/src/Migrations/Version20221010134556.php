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

final class Version20221010134556 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds isDeletable column to notes table';
    }

    public function up(Schema $schema): void
    {
        $notes = $schema->getTable('notes');

        if (!$notes->hasColumn('isDeletable')) {
            $this->addSql('ALTER TABLE `notes` ADD COLUMN `isDeletable` TINYINT(1) NOT NULL DEFAULT 0');
        }
    }

    public function down(Schema $schema): void
    {
        $notes = $schema->getTable('notes');

        if ($notes->hasColumn('isDeletable')) {
            $this->addSql('ALTER TABLE `notes` DROP COLUMN `isDeletable`');
        }
    }
}
