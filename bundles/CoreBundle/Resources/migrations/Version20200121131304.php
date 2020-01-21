<?php

namespace Pimcore\Bundle\CoreBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Pimcore\Migrations\Migration\AbstractPimcoreMigration;

class Version20200121131304 extends AbstractPimcoreMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        $this->addSql('ALTER TABLE `documents_email` ADD COLUMN `requireContent` tinyint(1) unsigned DEFAULT NULL;');
        $this->addSql('ALTER TABLE `documents_newsletter` ADD COLUMN `requireContent` tinyint(1) unsigned DEFAULT NULL;');
        $this->addSql('ALTER TABLE `documents_page` ADD COLUMN `requireContent` tinyint(1) unsigned DEFAULT NULL;');
        $this->addSql('ALTER TABLE `documents_printpage` ADD COLUMN `requireContent` tinyint(1) unsigned DEFAULT NULL;');
        $this->addSql('ALTER TABLE `documents_snippet` ADD COLUMN `requireContent` tinyint(1) unsigned DEFAULT NULL;');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        $this->addSql('ALTER TABLE `documents_email` DROP COLUMN `requireContent`;');
        $this->addSql('ALTER TABLE `documents_newsletter` DROP COLUMN `requireContent`;');
        $this->addSql('ALTER TABLE `documents_page` DROP COLUMN `requireContent`;');
        $this->addSql('ALTER TABLE `documents_printpage` DROP COLUMN `requireContent`;');
        $this->addSql('ALTER TABLE `documents_snippet` DROP COLUMN `requireContent`;');
    }
}
