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
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\AdminBundle\Controller\Admin\DataObject;

use Pimcore\Bundle\AdminBundle\Helper\GridHelperService;
use Pimcore\Event\AdminEvents;
use Pimcore\Localization\LocaleServiceInterface;
use Pimcore\Logger;
use Pimcore\Model\DataObject;
use Pimcore\Tool;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
trait DataObjectActionsTrait
{
    /**
     * @param DataObject\Concrete|null $object
     * @param $key
     *
     * @return array
     */
    private function renameObject(?DataObject\Concrete $object, $key): array
    {
        try {
            if (!$object instanceof DataObject\Concrete) {
                $object->setKey($key);
                $object->save();

                return ['success' => true];
            } else {
                throw new \Exception('No Object found for given id.');
            }
        } catch (\Exception $e) {
            Logger::error($e);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }


    private function gridProxy(
        array $allParams,
        string $type,
        Request $request,
        EventDispatcherInterface $eventDispatcher,
        GridHelperService $gridHelperService,
        LocaleServiceInterface $localeService
    )
    {
        $action = $allParams['xaction'];
        $csvMode = $allParams['csvMode'] ?? false;

        $requestedLanguage = $allParams['language'] ?? null;
        if ($requestedLanguage) {
            if ($requestedLanguage != 'default') {
                $request->setLocale($requestedLanguage);
            }
        } else {
            $requestedLanguage = $request->getLocale();
        }

        if ($action === 'update') {
            try {
                $data = $this->decodeJson($allParams['data']);
                $object = DataObject::getById($data['id']);

                if (!$object instanceof DataObject\Concrete) {
                    throw $this->createNotFoundException('Object not found');
                }

                if (!$object->isAllowed('publish')) {
                    throw $this->createAccessDeniedException("Permission denied. You don't have the rights to save this object.");
                }

                $objectData = $this->prepareObjectData($data, $object, $requestedLanguage, $localeService);
                $object->setValues($objectData);

                if ($object->getPublished() == false) {
                    $object->setOmitMandatoryCheck(true);
                }
                $object->save();

                return $this->adminJson(['data' => DataObject\Service::gridObjectData($object, $allParams['fields'], $requestedLanguage), 'success' => true]);
            } catch (\Exception $e) {
                return $this->adminJson(['success' => false, 'message' => $e->getMessage()]);
            }
        } else { // get list of objects/variants
            $list = $gridHelperService->prepareListingForGrid($allParams, $requestedLanguage, $this->getAdminUser());

            if ($type === DataObject::OBJECT_TYPE_OBJECT) {
                $beforeListLoadEvent = new GenericEvent($this, [
                    'list' => $list,
                    'context' => $allParams,
                ]);
                $eventDispatcher->dispatch($beforeListLoadEvent, AdminEvents::OBJECT_LIST_BEFORE_LIST_LOAD);
                /** @var DataObject\Listing\Concrete $list */
                $list = $beforeListLoadEvent->getArgument('list');
            }

            $list->load();

            $objects = [];
            foreach ($list->getObjects() as $object) {
                if ($csvMode) {
                    $o = DataObject\Service::getCsvDataForObject($object, $requestedLanguage, $request->get('fields'), DataObject\Service::getHelperDefinitions(), $localeService, false, $allParams['context']);
                } else {
                    $o = DataObject\Service::gridObjectData($object, $allParams['fields'] ?? null, $requestedLanguage,
                        ['csvMode' => $csvMode]);
                }

                // Like for treeGetChildsByIdAction, so we respect isAllowed method which can be extended (object DI) for custom permissions, so relying only users_workspaces_object is insufficient and could lead security breach
                if ($object->isAllowed('list')) {
                    $objects[] = $o;
                }
            }

            $result = ['data' => $objects, 'success' => true, 'total' => $list->getTotalCount()];

            if ($type === DataObject::OBJECT_TYPE_OBJECT) {
                $afterListLoadEvent = new GenericEvent($this, [
                    'list' => $result,
                    'context' => $allParams,
                ]);
                $eventDispatcher->dispatch($afterListLoadEvent, AdminEvents::OBJECT_LIST_AFTER_LIST_LOAD);
                $result = $afterListLoadEvent->getArgument('list');
            }

            return $this->adminJson($result);
        }
    }

    private function prepareObjectData($data, $object, $requestedLanguage, $local)
    {
        $user = Tool\Admin::getCurrentUser();
        $allLanguagesAllowed = false;
        $languagePermissions = [];
        if (!$user->isAdmin()) {
            $languagePermissions = $object->getPermissions('lEdit', $user);

            //sets allowed all languages modification when the lEdit column is empty
            $allLanguagesAllowed = $languagePermissions['lEdit'] == '';
            $languagePermissions = explode(',', $languagePermissions['lEdit']);
        }

        $class = $object->getClass();
        $objectData = [];
        foreach ($data as $key => $value) {
            $parts = explode('~', $key);
            if (substr($key, 0, 1) == '~') {
                $type = $parts[1];
                $field = $parts[2];
                $keyid = $parts[3];

                if ($type == 'classificationstore') {
                    $groupKeyId = explode('-', $keyid);
                    $groupId = $groupKeyId[0];
                    $keyid = $groupKeyId[1];

                    $getter = 'get' . ucfirst($field);
                    if (method_exists($object, $getter)) {

                        /** @var DataObject\ClassDefinition\Data\Classificationstore $csFieldDefinition */
                        $csFieldDefinition = $object->getClass()->getFieldDefinition($field);
                        $csLanguage = $requestedLanguage;
                        if (!$csFieldDefinition->isLocalized()) {
                            $csLanguage = 'default';
                        }

                        /** @var DataObject\Classificationstore $classificationStoreData */
                        $classificationStoreData = $object->$getter();

                        $keyConfig = DataObject\Classificationstore\KeyConfig::getById($keyid);
                        if ($keyConfig) {
                            $fieldDefinition = $keyDef = DataObject\Classificationstore\Service::getFieldDefinitionFromJson(
                                json_decode($keyConfig->getDefinition()),
                                $keyConfig->getType()
                            );
                            if ($fieldDefinition && method_exists($fieldDefinition, 'getDataFromGridEditor')) {
                                $value = $fieldDefinition->getDataFromGridEditor($value, $object, []);
                            }
                        }

                        $activeGroups = $classificationStoreData->getActiveGroups() ? $classificationStoreData->getActiveGroups() : [];
                        $activeGroups[$groupId] = true;
                        $classificationStoreData->setActiveGroups($activeGroups);
                        $classificationStoreData->setLocalizedKeyValue($groupId, $keyid, $value, $csLanguage);
                    }
                }
            } elseif (count($parts) > 1) {
                $brickType = $parts[0];
                $brickDescriptor = null;

                if (strpos($brickType, '?') !== false) {
                    $brickDescriptor = substr($brickType, 1);
                    $brickDescriptor = json_decode($brickDescriptor, true);
                    $brickType = $brickDescriptor['containerKey'];
                }
                $brickKey = $parts[1];
                $brickField = DataObject\Service::getFieldForBrickType($object->getClass(), $brickType);

                $fieldGetter = 'get' . ucfirst($brickField);
                $brickGetter = 'get' . ucfirst($brickType);
                $valueSetter = 'set' . ucfirst($brickKey);

                $brick = $object->$fieldGetter()->$brickGetter();
                if (empty($brick)) {
                    $classname = '\\Pimcore\\Model\\DataObject\\Objectbrick\\Data\\' . ucfirst($brickType);
                    $brickSetter = 'set' . ucfirst($brickType);
                    $brick = new $classname($object);
                    $object->$fieldGetter()->$brickSetter($brick);
                }

                if ($brickDescriptor) {
                    $brickDefinition = DataObject\Objectbrick\Definition::getByKey($brickType);
                    /** @var DataObject\ClassDefinition\Data\Localizedfields $fieldDefinitionLocalizedFields */
                    $fieldDefinitionLocalizedFields = $brickDefinition->getFieldDefinition('localizedfields');
                    $fieldDefinition = $fieldDefinitionLocalizedFields->getFieldDefinition($brickKey);
                } else {
                    $fieldDefinition = $this->getFieldDefinitionFromBrick($brickType, $brickKey);
                }

                if ($fieldDefinition && method_exists($fieldDefinition, 'getDataFromGridEditor')) {
                    $value = $fieldDefinition->getDataFromGridEditor($value, $object, []);
                }

                if ($brickDescriptor) {
                    /** @var DataObject\Localizedfield $localizedFields */
                    $localizedFields = $brick->getLocalizedfields();
                    $localizedFields->setLocalizedValue($brickKey, $value);
                } else {
                    $brick->$valueSetter($value);
                }
            } else {
                if (!$user->isAdmin() && $languagePermissions) {
                    $fd = $class->getFieldDefinition($key);
                    if (!$fd) {
                        // try to get via localized fields
                        $localized = $class->getFieldDefinition('localizedfields');
                        if ($localized instanceof DataObject\ClassDefinition\Data\Localizedfields) {
                            $field = $localized->getFieldDefinition($key);
                            if ($field) {
                                $currentLocale = $localeService->findLocale();
                                if (!$allLanguagesAllowed && !in_array($currentLocale, $languagePermissions)) {
                                    continue;
                                }
                            }
                        }
                    }
                }

                $fieldDefinition = $this->getFieldDefinition($class, $key);
                if ($fieldDefinition && method_exists($fieldDefinition, 'getDataFromGridEditor')) {
                    $value = $fieldDefinition->getDataFromGridEditor($value, $object, []);
                }

                $objectData[$key] = $value;
            }
        }

        return $data;
    }
}
