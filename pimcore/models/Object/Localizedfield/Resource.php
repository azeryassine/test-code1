<?php
/**
 * Pimcore
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.pimcore.org/license
 *
 * @category   Pimcore
 * @package    Object
 * @copyright  Copyright (c) 2009-2010 elements.at New Media Solutions GmbH (http://www.elements.at)
 * @license    http://www.pimcore.org/license     New BSD License
 */

class Object_Localizedfield_Resource extends Pimcore_Model_Resource_Abstract {

    public function getTableName () {
        return "object_localized_data_" . $this->model->getClass()->getId();
    }

    public function getQueryTableName () {
        return "object_localized_query_" . $this->model->getClass()->getId();
    }


    public function save () {
        $this->delete();

        $object = $this->model->getObject();

        foreach ($this->model->getItems() as $language => $items) {
            $inheritedValues = Object_Abstract::doGetInheritedValues();
            Object_Abstract::setGetInheritedValues(false);

            $insertData = array(
                "ooo_id" => $this->model->getObject()->getId(),
                "language" => $language
            );

            foreach ($this->model->getClass()->getFielddefinition("localizedfields")->getFielddefinitions() as $fd) {
                if (method_exists($fd, "save")) {
                    // for fieldtypes which have their own save algorithm eg. objects, multihref, ...
                    $fd->save($this->model, array("language" => $language));

                } else {
                    if (is_array($fd->getColumnType())) {
                        $insertDataArray = $fd->getDataForResource($this->model->getLocalizedValue($fd->getName(), $language), $object);
                        $insertData = array_merge($insertData, $insertDataArray);
                    } else {
                        $insertData[$fd->getName()] = $fd->getDataForResource($this->model->getLocalizedValue($fd->getName(), $language), $object);
                    }
                }
            }

            $storeTable = $this->getTableName();
            $queryTable = $this->getQueryTableName() . "_" . $language;

            $this->db->insert($this->getTableName(), $insertData);

            Object_Abstract::setGetInheritedValues(true);

            $data = array();
            $data["ooo_id"] = $this->model->getObject()->getId();
            $data["language"] = $language;

            $this->inheritanceHelper = new Object_Concrete_Resource_InheritanceHelper($object->getClassId(), "ooo_id", $storeTable, $queryTable);

            if($this->model->getClass()->getAllowInherit()) {

                $currentData = $this->model->items[$language];
                if (!$currentData) {
                    continue;
                }

                $this->inheritanceHelper->resetFieldsToCheck();
                $sql = "SELECT * FROM " . $queryTable . " WHERE ooo_id = " . $object->getId() . " AND language = '" . $language . "'";
                $oldData = $this->db->fetchRow($sql);


                // get fields which shouldn't be updated
                $fd = $this->model->getClass()->getFieldDefinitions();
                $untouchable = array();
                foreach ($fd as $key => $value) {
                    if (method_exists($value, "getLazyLoading") && $value->getLazyLoading()) {
                        if (!in_array($key, $this->model->getLazyLoadedFields())) {
                            //this is a relation subject to lazy loading - it has not been loaded
                            $untouchable[] = $key;
                        }
                    }
                }

                foreach ($currentData as $key => $value) {
                    $fd = $this->model->getClass()->getFielddefinition("localizedfields")->getFieldDefinition($key);

                    if ($fd) {
                        if ($fd->getQueryColumnType()) {

                            if($fd->isRelationType()) {
                                // TODO, Hmm ... for relation types there is not even a column, so skip them for now.
                                continue;
                            }

                            // exclude untouchables if value is not an array - this means data has not been loaded
                            if (!(in_array($key, $untouchable) and !is_array($this->model->$key))) {
                                $localizedValue = $this->model->getLocalizedValue($key, $language);
                                $insertData = $fd->getDataForQueryResource($localizedValue, $object);

                                if (is_array($insertData)) {
                                    $data = array_merge($data, $insertData);
                                }
                                else {
                                    $data[$key] = $insertData;
                                }

                                //get changed fields for inheritance
                                if($fd->isRelationType()) {

// TODO, Hmm ... for relation types there is not even a column, so skip them for now.
//                                    if (is_array($insertData)) {
//                                        $doInsert = false;
//                                        foreach($insertData as $insertDataKey => $insertDataValue) {
//                                            if($oldData[$insertDataKey] != $insertDataValue) {
//                                                $doInsert = true;
//                                            }
//                                        }
//
//                                        if($doInsert) {
//                                            $this->inheritanceHelper->addRelationToCheck($key, array_keys($insertData));
//                                        }
//                                    } else {
//                                        if($oldData[$key] != $insertData) {
//                                            $this->inheritanceHelper->addRelationToCheck($key);
//                                        }
//                                    }

                                } else {
                                    if (is_array($insertData)) {
                                        foreach($insertData as $insertDataKey => $insertDataValue) {
                                            if($oldData[$insertDataKey] != $insertDataValue) {
                                                $this->inheritanceHelper->addFieldToCheck($insertDataKey);
                                            }
                                        }
                                    } else {
                                        if($oldData[$key] != $insertData) {
                                            $this->inheritanceHelper->addFieldToCheck($key);
                                        }
                                    }
                                }

                            } else {
                                Logger::debug("Excluding untouchable query value for object [ " . $this->model->getId() . " ]  key [ $key ] because it has not been loaded");
                            }
                        }
                    }
                }
            }

            $queryTable = $this->getQueryTableName() . "_" . $language;
            $this->db->insertOrUpdate($queryTable, $data);
            $this->inheritanceHelper->doUpdate($object->getId());
            $this->inheritanceHelper->resetFieldsToCheck();

            Object_Abstract::setGetInheritedValues($inheritedValues);

        } // foreach language
    }

    public function delete ($deleteQuery = false) {

        try {
            $id = $this->model->getObject()->getId();
            $tablename = $this->getTableName();
            $this->db->delete($tablename, $this->db->quoteInto("ooo_id = ?", $id));
            if ($deleteQuery) {
                $querytable = $this->getQueryTableName();
                $this->db->delete($querytable, $this->db->quoteInto("ooo_id = ?", $id));
            }
        } catch (Exception $e) {
            $this->createUpdateTable();
        }

        // remove relations
        $this->db->delete("object_relations_" . $this->model->getObject()->getO_classId(), $this->db->quoteInto("ownertype = 'localizedfield' AND ownername = 'localizedfield' AND src_id = ?", $this->model->getObject()->getId()));
    }

    public function load () {
        $validLanguages = Pimcore_Tool::getValidLanguages();
        foreach ($validLanguages as &$language) {
            $language = $this->db->quote($language);
        }

        $data = $this->db->fetchAll("SELECT * FROM " . $this->getTableName() . " WHERE ooo_id = ? AND language IN (" . implode(",",$validLanguages) . ")", $this->model->getObject()->getId());
        foreach ($data as $row) {
            foreach ($this->model->getClass()->getFielddefinition("localizedfields")->getFielddefinitions() as $key => $fd) {
                if($fd) {
                    if (method_exists($fd, "load")) {
                        // datafield has it's own loader
                        $value = $fd->load($this->model, array("language" => $row["language"]));
                        if($value === 0 || !empty($value)) {
                            $this->model->setLocalizedValue($key, $value, $row["language"]);
                        }
                    } else {
                        if (is_array($fd->getColumnType())) {
                            $multidata = array();
                            foreach ($fd->getColumnType() as $fkey => $fvalue) {
                                $multidata[$key . "__" . $fkey] = $row[$key . "__" . $fkey];
                            }
                            $this->model->setLocalizedValue($key, $fd->getDataFromResource($multidata), $row["language"]);
                        } else {
                            $this->model->setLocalizedValue($key, $fd->getDataFromResource($row[$key]), $row["language"]);
                        }
                    }
                }
            }
        }
    }

    public function createLocalizedViews () {

        $languages = array();
        $conf = Pimcore_Config::getSystemConfig();
        if($conf->general->validLanguages) {
            $languages = explode(",",$conf->general->validLanguages);
        }

        $defaultTable = 'object_query_' . $this->model->getClass()->getId();

        $classDef = $this->model->getClass();
        $localizedFieldDef = $classDef->getFieldDefinition("localizedfields");

        foreach ($languages as $language) {
            try {
                $tablename = $this->getQueryTableName() . "_" . $language;
                $this->db->query('CREATE OR REPLACE VIEW `object_localized_' . $this->model->getClass()->getId() . '_' . $language . '` AS SELECT * FROM `' . $defaultTable . '` JOIN `objects` ON `objects`.`o_id` = `' . $defaultTable . '`.`oo_id` left JOIN `' . $tablename . '` ON `' . $defaultTable . '`.`oo_id` = `' . $tablename . '`.`ooo_id` AND `' . $tablename . '`.`language` = \'' . $language . '\';');
            }
            catch (Exception $e) {
                Logger::error($e);
            }
        }

        $concats = array();
        if($this->model->getClass()->getFielddefinition("localizedfields")) {
            foreach ($this->model->getClass()->getFielddefinition("localizedfields")->getFielddefinitions() as $fd) {
                // only add non-relational fields with one column to the group-concat
                if(!$fd->isRelationType() && !is_array($fd->getColumnType())) {
                    $concats[] = "group_concat(" . $this->getTableName() . "." . $fd->getName() . ") AS `" . $fd->getName() . "`";
                }
            }
        }

        // and now the default view for query where the locale is missing

        $furtherSelects = implode(",",$concats);
        if(!empty($furtherSelects)) {
            $furtherSelects = "," . $furtherSelects;
        }

        $this->db->query('CREATE OR REPLACE VIEW `object_localized_' . $this->model->getClass()->getId() . '_default` AS SELECT `' . $defaultTable . '`.*,objects.* ' . $furtherSelects . ' FROM `' . $defaultTable . '` JOIN `objects` ON `objects`.`o_id` = `' . $defaultTable . '`.`oo_id` left JOIN `' . $this->getTableName() . '` ON `' . $defaultView . '`.`o_id` = `' . $this->getTableName() . '`.`ooo_id` GROUP BY `' . $defaultView . '`.`o_id`;');
    }

    public function createUpdateTable () {

        $table = $this->getTableName();

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . $table . "` (
		  `ooo_id` int(11) NOT NULL default '0',
		  `language` varchar(10) NOT NULL DEFAULT '',
		  PRIMARY KEY (`ooo_id`,`language`),
          INDEX `ooo_id` (`ooo_id`),
          INDEX `language` (`language`)
		) DEFAULT CHARSET=utf8;");

        $existingColumns = $this->getValidTableColumns($table, false); // no caching of table definition
        $columnsToRemove = $existingColumns;
        $protectedColumns = array("ooo_id", "language");

        foreach ($this->model->getClass()->getFielddefinition("localizedfields")->getFielddefinitions() as $value) {

            // continue to the next field if the current one is a relational field
            if($value->isRelationType()) {
                continue;
            }

            $key = $value->getName();



            if (is_array($value->getColumnType())) {
                // if a datafield requires more than one field
                foreach ($value->getColumnType() as $fkey => $fvalue) {
                    $this->addModifyColumn($table, $key . "__" . $fkey, $fvalue,"", "NULL");
                    $protectedColumns[] = $key . "__" . $fkey;
                }
            }
            else {
                if ($value->getColumnType()) {
                    $this->addModifyColumn($table, $key, $value->getColumnType(), "", "NULL");
                    $protectedColumns[] = $key;
                }
            }
            $this->addIndexToField($value,$table);
        }

        $this->removeUnusedColumns($table, $columnsToRemove, $protectedColumns);

        $this->createLocalizedViews();


        $validLanguages = Pimcore_Tool::getValidLanguages();

        foreach ($validLanguages as &$language) {
            $queryTable = $this->getQueryTableName();
            $queryTable .= "_" . $language;

            $this->db->query("CREATE TABLE IF NOT EXISTS `" . $queryTable . "` (
                  `ooo_id` int(11) NOT NULL default '0',
                  `language` varchar(10) NOT NULL DEFAULT '',
                  PRIMARY KEY (`ooo_id`,`language`),
                  INDEX `ooo_id` (`ooo_id`),
                  INDEX `language` (`language`)
                ) DEFAULT CHARSET=utf8;");

            // create object table if not exists
            $protectedColumns = array("ooo_id", "language");

            $existingColumns = $this->getValidTableColumns($queryTable, false); // no caching of table definition
            $columnsToRemove = $existingColumns;

            $fieldDefinitions = $this->model->getClass()->getFielddefinition("localizedfields")->getFielddefinitions();

            // add non existing columns in the table
            if (is_array($fieldDefinitions) && count($fieldDefinitions)) {
                foreach ($fieldDefinitions as $value) {
                    // continue to the next field if the current one is a relational field
                    if($value->isRelationType()) {
                        continue;
                    }

                    $key = $value->getName();

                    // if a datafield requires more than one column in the query table
                    if (is_array($value->getQueryColumnType())) {
                        foreach ($value->getQueryColumnType() as $fkey => $fvalue) {
                            $this->addModifyColumn($queryTable, $key . "__" . $fkey, $fvalue, "", "NULL");
                            $protectedColumns[] = $key . "__" . $fkey;
                        }
                    }

                    // everything else
                    if (!is_array($value->getQueryColumnType()) && $value->getQueryColumnType()) {
                        $this->addModifyColumn($queryTable, $key, $value->getQueryColumnType(), "", "NULL");
                        $protectedColumns[] = $key;
                    }

                    // add indices
                    $this->addIndexToField($value, $queryTable);
                }
            }

            // remove unused columns in the table
            $this->removeUnusedColumns($queryTable, $columnsToRemove, $protectedColumns);
        }
    }



    private function addIndexToField ($field, $table) {

        if ($field->getIndex()) {
            if (is_array($field->getColumnType())) {
                // multicolumn field
                foreach ($field->getColumnType() as $fkey => $fvalue) {
                    $columnName = $field->getName() . "__" . $fkey;
                    try {
                        $this->db->query("ALTER TABLE `" . $table . "` ADD INDEX `p_index_" . $columnName . "` (`" . $columnName . "`);");
                    } catch (Exception $e) {}
                }
            } else {
                // single -column field
                $columnName = $field->getName();
                try {
                    $this->db->query("ALTER TABLE `" . $table . "` ADD INDEX `p_index_" . $columnName . "` (`" . $columnName . "`);");
                } catch (Exception $e) {}
            }
        } else {
            if (is_array($field->getColumnType())) {
                // multicolumn field
                foreach ($field->getColumnType() as $fkey => $fvalue) {
                    $columnName = $field->getName() . "__" . $fkey;
                    try {
                        $this->db->query("ALTER TABLE `" . $table . "` DROP INDEX `p_index_" . $columnName . "`;");
                    } catch (Exception $e) {}
                }
            } else {
                // single -column field
                $columnName = $field->getName();
                try {
                    $this->db->query("ALTER TABLE `" . $table . "` DROP INDEX `p_index_" . $columnName . "`;");
                } catch (Exception $e) {
                }
            }
        }
    }

    private function addModifyColumn ($table, $colName, $type, $default, $null) {

        $existingColumns = $this->getValidTableColumns($table, false);
        $existingColName = null;

        // check for existing column case insensitive eg a rename from myInput to myinput
        $matchingExisting = preg_grep('/^' . preg_quote($colName, '/') . '$/i', $existingColumns);
        if(is_array($matchingExisting) && !empty($matchingExisting)) {
            $existingColName = current($matchingExisting);
        }

        if ($existingColName === null) {
            $this->db->query('ALTER TABLE `' . $table . '` ADD COLUMN `' . $colName . '` ' . $type . $default . ' ' . $null . ';');
            $this->resetValidTableColumnsCache($table);
        } else {
            $this->db->query('ALTER TABLE `' . $table . '` CHANGE COLUMN `' . $existingColName . '` `' . $colName . '` ' . $type . $default . ' ' . $null . ';');
        }
    }

    private function removeUnusedColumns ($table, $columnsToRemove, $protectedColumns) {
        if (is_array($columnsToRemove) && count($columnsToRemove) > 0) {
            foreach ($columnsToRemove as $value) {
                //if (!in_array($value, $protectedColumns)) {
                if (!in_array(strtolower($value), array_map('strtolower', $protectedColumns))) {
                    $this->db->query('ALTER TABLE `' . $table . '` DROP COLUMN `' . $value . '`;');
                }
            }
        }
    }
}
