<?php

namespace Pimcore\Bundle\CoreBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Pimcore\Migrations\Migration\AbstractPimcoreMigration;
use Pimcore\Model\DataObject\ClassDefinition;

class Version20191230104529 extends AbstractPimcoreMigration
{
    public function doesSqlMigrations(): bool
    {
        return false;
    }

    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        $class = ClassDefinition::getById('EF_OSO');
        if($class) {
            /**
             * @var $cartIdField ClassDefinition\Data\Input
             */
            $cartIdField = $class->getFieldDefinition('cartId');
            $cartIdField->setColumnLength(190);
            $class->save();
        }
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        $class = ClassDefinition::getById('EF_OSO');
        if($class) {
            /**
             * @var $cartIdField ClassDefinition\Data\Input
             */
            $cartIdField = $class->getFieldDefinition('cartId');
            $cartIdField->setColumnLength(255);
            $class->save();
        }
    }
}
