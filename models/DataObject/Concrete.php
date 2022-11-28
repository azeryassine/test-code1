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

namespace Pimcore\Model\DataObject;

use Pimcore\Db;
use Pimcore\Event\DataObjectEvents;
use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Logger;
use Pimcore\Messenger\VersionDeleteMessage;
use Pimcore\Model;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition\Data\LazyLoadingSupportInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data\Relations\AbstractRelations;
use Pimcore\Model\DataObject\Exception\InheritanceParentNotFoundException;
use Pimcore\Model\Element\DirtyIndicatorInterface;

/**
 * @method \Pimcore\Model\DataObject\Concrete\Dao getDao()
 * @method \Pimcore\Model\Version|null getLatestVersion(?int $userId = null)
 */
class Concrete extends DataObject implements LazyLoadedFieldsInterface
{
    use Model\DataObject\Traits\LazyLoadedRelationTrait;
    use Model\Element\Traits\ScheduledTasksTrait;

    /**
     * @internal
     *
     * @var array|null
     */
    protected ?array $__rawRelationData = null;

    /**
     * @internal
     *
     * Necessary for assigning object reference to corresponding fields while wakeup
     *
     * @var array
     */
    public array $__objectAwareFields = [];

    /**
     * @internal
     *
     * @var array
     */
    public const SYSTEM_COLUMN_NAMES = ['id', 'fullpath', 'key', 'published', 'creationDate', 'modificationDate', 'filename', 'classname', 'index'];

    /**
     * @internal
     *
     * @var bool
     */
    protected bool $o_published = false;

    /**
     * @internal
     */
    protected ?ClassDefinition $o_class = null;

    /**
     * @internal
     *
     * @var string|null
     */
    protected $o_classId = null;

    /**
     * @internal
     *
     * @var string|null
     */
    protected $o_className = null;

    /**
     * @internal
     *
     * @var array|null
     */
    protected ?array $o_versions = null;

    /**
     * @internal
     *
     * @var bool|null
     */
    protected ?bool $omitMandatoryCheck = null;

    /**
     * @internal
     *
     * @var bool
     */
    protected bool $allLazyKeysMarkedAsLoaded = false;

    /**
     * returns the class ID of the current object class
     *
     * @return string
     */
    public static function classId(): string
    {
        $v = get_class_vars(get_called_class());

        return $v['o_classId'];
    }

    /**
     * {@inheritdoc}
     */
    protected function update(bool $isUpdate = null, array $params = [])
    {
        $fieldDefinitions = $this->getClass()->getFieldDefinitions();

        $validationExceptions = [];

        foreach ($fieldDefinitions as $fd) {
            try {
                $getter = 'get' . ucfirst($fd->getName());

                if (method_exists($this, $getter)) {
                    $value = $this->$getter();
                    $omitMandatoryCheck = $this->getOmitMandatoryCheck();

                    //check throws Exception
                    try {
                        $fd->checkValidity($value, $omitMandatoryCheck, $params);
                    } catch (\Exception $e) {
                        if ($this->getClass()->getAllowInherit() && $fd->supportsInheritance() && $fd->isEmpty($value)) {
                            //try again with parent data when inheritance is activated
                            try {
                                $getInheritedValues = DataObject::doGetInheritedValues();
                                DataObject::setGetInheritedValues(true);

                                $value = $this->$getter();
                                $fd->checkValidity($value, $omitMandatoryCheck, $params);

                                DataObject::setGetInheritedValues($getInheritedValues);
                            } catch (\Exception $e) {
                                if (!$e instanceof Model\Element\ValidationException) {
                                    throw $e;
                                }
                                $exceptionClass = get_class($e);
                                $newException = new $exceptionClass($e->getMessage() . ' fieldname=' . $fd->getName(), $e->getCode(), $e->getPrevious());
                                $newException->setSubItems($e->getSubItems());

                                throw $newException;
                            }
                        } else {
                            throw $e;
                        }
                    }
                }
            } catch (Model\Element\ValidationException $ve) {
                $validationExceptions[] = $ve;
            }
        }

        $preUpdateEvent = new DataObjectEvent($this, [
            'validationExceptions' => $validationExceptions,
            'message' => 'Validation failed: ',
            'separator' => ' / ',
        ]);
        \Pimcore::getEventDispatcher()->dispatch($preUpdateEvent, DataObjectEvents::PRE_UPDATE_VALIDATION_EXCEPTION);
        $validationExceptions = $preUpdateEvent->getArgument('validationExceptions');

        if ($validationExceptions) {
            $message = $preUpdateEvent->getArgument('message');
            $errors = [];

            /** @var Model\Element\ValidationException $e */
            foreach ($validationExceptions as $e) {
                $errors[] = $e->getAggregatedMessage();
            }
            $message .= implode($preUpdateEvent->getArgument('separator'), $errors);

            throw new Model\Element\ValidationException($message);
        }

        $isDirtyDetectionDisabled = self::isDirtyDetectionDisabled();

        try {
            $oldVersionCount = $this->getVersionCount();

            parent::update($isUpdate, $params);

            $newVersionCount = $this->getVersionCount();

            if (($newVersionCount != $oldVersionCount + 1) || ($this instanceof DirtyIndicatorInterface && $this->isFieldDirty('o_parentId'))) {
                self::disableDirtyDetection();
            }

            $this->getDao()->update($isUpdate);

            // scheduled tasks are saved in $this->saveVersion();

            $this->saveVersion(false, false, isset($params['versionNote']) ? $params['versionNote'] : null);
            $this->saveChildData();
        } finally {
            self::setDisableDirtyDetection($isDirtyDetectionDisabled);
        }
    }

    private function saveChildData(): void
    {
        if ($this->getClass()->getAllowInherit()) {
            $this->getDao()->saveChildData();
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete()
    {
        // Dispatch Symfony Message Bus to delete versions
        \Pimcore::getContainer()->get('messenger.bus.pimcore-core')->dispatch(
            new VersionDeleteMessage(Model\Element\Service::getElementType($this), $this->getId())
        );

        $this->getDao()->deleteAllTasks();

        parent::doDelete();
    }

    /**
     * $callPluginHook is true when the method is called from outside (eg. directly in the controller "save only version")
     * it is false when the method is called by $this->update()
     *
     * @param bool $setModificationDate
     * @param bool $saveOnlyVersion
     * @param string|null $versionNote version note
     * @param bool $isAutoSave
     *
     * @return Model\Version|null
     */
    public function saveVersion(bool $setModificationDate = true, bool $saveOnlyVersion = true, string $versionNote = null, bool $isAutoSave = false): ?Model\Version
    {
        try {
            if ($setModificationDate) {
                $this->setModificationDate(time());
            }

            // hook should be also called if "save only new version" is selected
            if ($saveOnlyVersion) {
                $preUpdateEvent = new DataObjectEvent($this, [
                    'saveVersionOnly' => true,
                    'isAutoSave' => $isAutoSave,
                ]);
                \Pimcore::getEventDispatcher()->dispatch($preUpdateEvent, DataObjectEvents::PRE_UPDATE);
            }

            // scheduled tasks are saved always, they are not versioned!
            $this->saveScheduledTasks();

            $version = null;

            // only create a new version if there is at least 1 allowed
            // or if saveVersion() was called directly (it's a newer version of the object)
            $objectsConfig = \Pimcore\Config::getSystemConfiguration('objects');
            if ((is_null($objectsConfig['versions']['days'] ?? null) && is_null($objectsConfig['versions']['steps'] ?? null))
                || (!empty($objectsConfig['versions']['steps']))
                || !empty($objectsConfig['versions']['days'])
                || $setModificationDate) {
                $saveStackTrace = !($objectsConfig['versions']['disable_stack_trace'] ?? false);
                $version = $this->doSaveVersion($versionNote, $saveOnlyVersion, $saveStackTrace, $isAutoSave);
            }

            // hook should be also called if "save only new version" is selected
            if ($saveOnlyVersion) {
                $postUpdateEvent = new DataObjectEvent($this, [
                    'saveVersionOnly' => true,
                    'isAutoSave' => $isAutoSave,
                ]);
                \Pimcore::getEventDispatcher()->dispatch($postUpdateEvent, DataObjectEvents::POST_UPDATE);
            }

            return $version;
        } catch (\Exception $e) {
            $postUpdateFailureEvent = new DataObjectEvent($this, [
                'saveVersionOnly' => true,
                'exception' => $e,
                'isAutoSave' => $isAutoSave,
            ]);
            \Pimcore::getEventDispatcher()->dispatch($postUpdateFailureEvent, DataObjectEvents::POST_UPDATE_FAILURE);

            throw $e;
        }
    }

    /**
     * @return Model\Version[]
     */
    public function getVersions(): array
    {
        if ($this->o_versions === null) {
            $this->setVersions($this->getDao()->getVersions());
        }

        return $this->o_versions;
    }

    /**
     * @param Model\Version[] $o_versions
     *
     * @return $this
     */
    public function setVersions(array $o_versions): static
    {
        $this->o_versions = $o_versions;

        return $this;
    }

    public function getValueForFieldName(string $key): mixed
    {
        if (isset($this->$key)) {
            return $this->$key;
        }

        if ($this->getClass()->getFieldDefinition($key) instanceof Model\DataObject\ClassDefinition\Data\CalculatedValue) {
            $value = new Model\DataObject\Data\CalculatedValue($key);
            $value = Service::getCalculatedFieldValue($this, $value);

            return $value;
        }

        return null;
    }

    public function getCacheTags(array $tags = []): array
    {
        $tags = parent::getCacheTags($tags);

        $tags['class_' . $this->getClassId()] = 'class_' . $this->getClassId();
        foreach ($this->getClass()->getFieldDefinitions() as $name => $def) {
            // no need to add lazy-loading fields to the cache tags
            if (!$def instanceof LazyLoadingSupportInterface || !$def->getLazyLoading()) {
                $tags = $def->getCacheTags($this->getValueForFieldName($name), $tags);
            }
        }

        return $tags;
    }

    /**
     * {@inheritdoc}
     */
    protected function resolveDependencies(): array
    {
        $dependencies = [parent::resolveDependencies()];

        // check in fields
        if ($this->getClass() instanceof ClassDefinition) {
            foreach ($this->getClass()->getFieldDefinitions() as $field) {
                $key = $field->getName();
                $dependencies[] = $field->resolveDependencies($this->$key ?? null);
            }
        }

        return array_merge(...$dependencies);
    }

    /**
     * @return $this
     */
    public function setClass(?ClassDefinition $o_class): static
    {
        $this->o_class = $o_class;

        return $this;
    }

    public function getClass(): ?ClassDefinition
    {
        if (!$this->o_class) {
            $this->setClass(ClassDefinition::getById($this->getClassId()));
        }

        return $this->o_class;
    }

    public function getClassId(): ?string
    {
        if(isset($this->o_classId)) {
            return (string)$this->o_classId;
        }
        return null;
    }

    /**
     * @return $this
     */
    public function setClassId(string $o_classId): static
    {
        $this->o_classId = $o_classId;

        return $this;
    }

    public function getClassName(): ?string
    {
        return $this->o_className;
    }

    /**
     * @return $this
     */
    public function setClassName(string $o_className): static
    {
        $this->o_className = $o_className;

        return $this;
    }

    public function getPublished(): bool
    {
        return $this->o_published;
    }

    public function isPublished(): bool
    {
        return (bool) $this->getPublished();
    }

    /**
     * @return $this
     */
    public function setPublished(bool $o_published): static
    {
        $this->o_published = $o_published;

        return $this;
    }

    public function setOmitMandatoryCheck(bool $omitMandatoryCheck): static
    {
        $this->omitMandatoryCheck = $omitMandatoryCheck;

        return $this;
    }

    public function getOmitMandatoryCheck(): bool
    {
        if ($this->omitMandatoryCheck === null) {
            return !$this->isPublished();
        }

        return $this->omitMandatoryCheck;
    }

    /**
     * @param string $key
     * @param mixed $params
     *
     * @return mixed
     *
     * @throws InheritanceParentNotFoundException
     */
    public function getValueFromParent(string $key, mixed $params = null): mixed
    {
        $parent = $this->getNextParentForInheritance();
        if ($parent) {
            $method = 'get' . $key;
            if (method_exists($parent, $method)) {
                return $parent->$method($params);
            }

            throw new InheritanceParentNotFoundException(sprintf('Parent object does not have a method called `%s()`, unable to retrieve value for key `%s`', $method, $key));
        }

        throw new InheritanceParentNotFoundException('No parent object available to get a value from');
    }

    /**
     * @internal
     *
     * @return Concrete|null
     */
    public function getNextParentForInheritance(): ?Concrete
    {
        return $this->getClosestParentOfClass($this->getClassId());
    }

    public function getClosestParentOfClass(string $classId): ?self
    {
        $parent = $this->getParent();
        if ($parent instanceof AbstractObject) {
            while ($parent && (!$parent instanceof Concrete || $parent->getClassId() !== $classId)) {
                $parent = $parent->getParent();
            }

            if ($parent && in_array($parent->getType(), [self::OBJECT_TYPE_OBJECT, self::OBJECT_TYPE_VARIANT], true)) {
                /** @var Concrete $parent */
                if ($parent->getClassId() === $classId) {
                    return $parent;
                }
            }
        }

        return null;
    }

    /**
     * get object relation data as array for a specific field
     *
     * @param string $fieldName
     * @param bool $forOwner
     * @param string|null $remoteClassId
     *
     * @return array
     */
    public function getRelationData(string $fieldName, bool $forOwner, ?string $remoteClassId = null): array
    {
        $relationData = $this->getDao()->getRelationData($fieldName, $forOwner, $remoteClassId);

        return $relationData;
    }

    /**
     * @param string $method
     * @param array $arguments
     *
     * @return Model\Listing\AbstractListing|Concrete|null
     *
     * @throws \Exception
     */
    public static function __callStatic(string $method, array $arguments)
    {
        // check for custom static getters like DataObject::getByMyfield()
        $propertyName = lcfirst(preg_replace('/^getBy/i', '', $method));
        $classDefinition = ClassDefinition::getById(self::classId());

        // get real fieldname (case sensitive)
        $fieldnames = [];
        $defaultCondition = '';
        foreach ($classDefinition->getFieldDefinitions() as $fd) {
            $fieldnames[] = $fd->getName();
        }
        $realPropertyName = implode('', preg_grep('/^' . preg_quote($propertyName, '/') . '$/i', $fieldnames));

        if (!$classDefinition->getFieldDefinition($realPropertyName) instanceof Model\DataObject\ClassDefinition\Data) {
            $localizedField = $classDefinition->getFieldDefinition('localizedfields');
            if ($localizedField instanceof Model\DataObject\ClassDefinition\Data\Localizedfields) {
                $fieldnames = [];
                foreach ($localizedField->getFieldDefinitions() as $fd) {
                    $fieldnames[] = $fd->getName();
                }
                $realPropertyName = implode('', preg_grep('/^' . preg_quote($propertyName, '/') . '$/i', $fieldnames));
                $localizedFieldDefinition = $localizedField->getFieldDefinition($realPropertyName);
                if ($localizedFieldDefinition instanceof Model\DataObject\ClassDefinition\Data) {
                    $realPropertyName = 'localizedfields';
                    \array_unshift($arguments, $localizedFieldDefinition->getName());
                }
            }
        }

        if ($classDefinition->getFieldDefinition($realPropertyName) instanceof Model\DataObject\ClassDefinition\Data) {
            $field = $classDefinition->getFieldDefinition($realPropertyName);
            if (!$field->isFilterable()) {
                throw new \Exception("Static getter '::getBy".ucfirst($realPropertyName)."' is not allowed for fieldtype '" . $field->getFieldType() . "'");
            }

            $db = Db::get();

            if ($field instanceof Model\DataObject\ClassDefinition\Data\Localizedfields) {
                $localizedPropertyName = empty($arguments[0]) ? throw new \InvalidArgumentException('Mandatory argument $field not set.') : $arguments[0];
                $value = array_key_exists(1, $arguments) ? $arguments[1] : throw new \InvalidArgumentException('Mandatory argument $value not set.');
                $locale = $arguments[2] ?? null;
                $limit = $arguments[3] ?? null;
                $offset = $arguments[4] ?? 0;
                $objectTypes = $arguments[5] ?? null;

                $localizedField = $field->getFieldDefinition($localizedPropertyName);

                if (!$localizedField instanceof Model\DataObject\ClassDefinition\Data) {
                    Logger::error('Class: DataObject\\Concrete => call to undefined static method ' . $method);

                    throw new \Exception('Call to undefined static method ' . $method . ' in class DataObject\\Concrete');
                }

                if (!$localizedField->isFilterable()) {
                    throw new \Exception("Static getter '::getBy".ucfirst($realPropertyName)."' is not allowed for fieldtype '" . $localizedField->getFieldType() . "'");
                }

                $defaultCondition = $db->quoteIdentifier($localizedPropertyName) . ' = ' . $db->quote($value) . ' ';
                $listConfig = [
                    'condition' => $defaultCondition,
                ];

                $listConfig['locale'] = $locale;
            } else {
                $value = array_key_exists(0, $arguments) ? $arguments[0] : throw new \InvalidArgumentException('Mandatory argument $value not set.');
                $limit = $arguments[1] ?? null;
                $offset = $arguments[2] ?? 0;
                $objectTypes = $arguments[3] ?? null;

                if (!$field instanceof AbstractRelations) {
                    $defaultCondition = $db->quoteIdentifier($realPropertyName) . ' = ' . $db->quote($value) . ' ';
                }
                $listConfig = [
                    'condition' => $defaultCondition,
                ];
            }

            if (!is_array($limit)) {
                $listConfig['limit'] = $limit;
                $listConfig['offset'] = $offset;
            } else {
                $listConfig = array_merge($listConfig, $limit);
                $limitCondition = $limit['condition'] ?? '';
                $listConfig['condition'] = $defaultCondition . $limitCondition;
            }

            $list = static::makeList($listConfig, $objectTypes);

            if ($field instanceof AbstractRelations) {
                $list = $field->addListingFilter($list, $value);
            }

            if (isset($listConfig['limit']) && $listConfig['limit'] == 1) {
                $elements = $list->getObjects();

                return isset($elements[0]) ? $elements[0] : null;
            }

            return $list;
        }

        try {
            return call_user_func_array([parent::class, $method], $arguments);
        } catch (\Exception $e) {
            // there is no property for the called method, so throw an exception
            Logger::error('Class: DataObject\\Concrete => call to undefined static method '.$method);

            throw new \Exception('Call to undefined static method '.$method.' in class DataObject\\Concrete');
        }
    }

    /**
     * @return $this
     *
     * @throws \Exception
     */
    public function save(array $parameters = []): static
    {
        $isDirtyDetectionDisabled = DataObject::isDirtyDetectionDisabled();

        // if the class is newer then better disable the dirty detection. This should fix issues with the query table if
        // the inheritance enabled flag has been changed in the meantime
        if ($this->getClass()->getModificationDate() >= $this->getModificationDate() && $this->getId()) {
            DataObject::disableDirtyDetection();
        }

        try {
            parent::save($parameters);
            if ($this instanceof DirtyIndicatorInterface) {
                $this->resetDirtyMap();
            }
        } finally {
            DataObject::setDisableDirtyDetection($isDirtyDetectionDisabled);
        }

        return $this;
    }

    /**
     * @internal
     *
     * @return array
     */
    public function getLazyLoadedFieldNames(): array
    {
        $lazyLoadedFieldNames = [];
        $fields = $this->getClass()->getFieldDefinitions(['suppressEnrichment' => true]);
        foreach ($fields as $field) {
            if ($field instanceof LazyLoadingSupportInterface && $field->getLazyLoading()) {
                $lazyLoadedFieldNames[] = $field->getName();
            }
        }

        return $lazyLoadedFieldNames;
    }

    /**
     * {@inheritdoc}
     */
    public function isAllLazyKeysMarkedAsLoaded(): bool
    {
        if (!$this->getId()) {
            return true;
        }

        return $this->allLazyKeysMarkedAsLoaded;
    }

    public function markAllLazyLoadedKeysAsLoaded()
    {
        $this->allLazyKeysMarkedAsLoaded = true;
    }

    public function __sleep()
    {
        $parentVars = parent::__sleep();

        $finalVars = [];

        $blockedVars = [];

        if (!$this->isInDumpState()) {
            $blockedVars = ['loadedLazyKeys', 'allLazyKeysMarkedAsLoaded'];
            // do not dump lazy loaded fields for caching
            $lazyLoadedFields = $this->getLazyLoadedFieldNames();
            $blockedVars = array_merge($lazyLoadedFields, $blockedVars);
        }

        foreach ($parentVars as $key) {
            if (!in_array($key, $blockedVars)) {
                $finalVars[] = $key;
            }
        }

        return $finalVars;
    }

    public function __wakeup()
    {
        parent::__wakeup();

        // renew localized fields
        // do not use the getter ($this->getLocalizedfields()) as it somehow slows down the process around a sec
        // no clue why this happens
        if (property_exists($this, 'localizedfields') && $this->localizedfields instanceof Localizedfield) {
            $this->localizedfields->setObject($this, false);
        }

        // renew object reference to other object aware fields
        foreach ($this->__objectAwareFields as $objectAwareField => $exists) {
            if (isset($this->$objectAwareField) && $this->$objectAwareField instanceof ObjectAwareFieldInterface) {
                $this->$objectAwareField->setObject($this);
            }
        }
    }

    /**
     * load lazy loaded fields before cloning
     */
    public function __clone()
    {
        parent::__clone();
        $this->o_class = null;
        $this->o_versions = null;
        $this->scheduledTasks = null;
    }

    /**
     * @internal
     *
     * @param array $descriptor
     * @param string $table
     *
     * @return array
     */
    protected function doRetrieveData(array $descriptor, string $table): array
    {
        $db = Db::get();
        $conditionParts = Service::buildConditionPartsFromDescriptor($descriptor);

        $query = 'SELECT * FROM ' . $table . ' WHERE ' . implode(' AND ', $conditionParts);
        $result = $db->fetchAllAssociative($query);

        return $result;
    }

    /**
     * @param array $descriptor
     *
     * @return array
     *
     *@internal
     *
     */
    public function retrieveSlugData(array $descriptor): array
    {
        $descriptor['objectId'] = $this->getId();

        return $this->doRetrieveData($descriptor, DataObject\Data\UrlSlug::TABLE_NAME);
    }

    /**
     * @param array $descriptor
     *
     * @return array
     *
     *@internal
     *
     */
    public function retrieveRelationData(array $descriptor): array
    {
        $descriptor['src_id'] = $this->getId();

        $unfilteredData = $this->__getRawRelationData();

        $likes = [];
        foreach ($descriptor as $column => $expectedValue) {
            if (is_string($expectedValue)) {
                $trimmed = rtrim($expectedValue, '%');
                if (strlen($trimmed) < strlen($expectedValue)) {
                    $likes[$column] = $trimmed;
                }
            }
        }

        $filterFn = static function ($row) use ($descriptor, $likes) {
            foreach ($descriptor as $column => $expectedValue) {
                $actualValue = $row[$column];
                if (isset($likes[$column])) {
                    $expectedValue = $likes[$column];
                    if (strpos($actualValue, $expectedValue) !== 0) {
                        return false;
                    }
                } elseif ($actualValue != $expectedValue) {
                    return false;
                }
            }

            return true;
        };

        $filteredData = array_filter($unfilteredData, $filterFn);

        return $filteredData;
    }

    /**
     * @internal
     *
     * @return array
     */
    public function __getRawRelationData(): array
    {
        if ($this->__rawRelationData === null) {
            $db = Db::get();
            $this->__rawRelationData = $db->fetchAllAssociative('SELECT * FROM object_relations_' . $this->getClassId() . ' WHERE src_id = ?', [$this->getId()]);
        }

        return $this->__rawRelationData;
    }
}
