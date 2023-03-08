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

namespace Pimcore\Model\Tool\CustomReport\Config;

use Pimcore\Config\LocationAwareConfigRepository;
use Pimcore\Model;

/**
 * @internal
 *
 * @property \Pimcore\Model\Tool\CustomReport\Config $model
 */
class Dao extends Model\Dao\PimcoreLocationAwareConfigDao
{
    private const CONFIG_KEY = 'custom_reports';

    public function configure()
    {
        $config = \Pimcore::getContainer()->getParameter('pimcore.config');

        $storageConfig = LocationAwareConfigRepository::getStorageConfigurationCompatibilityLayer(
            $config,
            self::CONFIG_KEY,
            'PIMCORE_CONFIG_STORAGE_DIR_CUSTOM_REPORTS',
            'PIMCORE_WRITE_TARGET_CUSTOM_REPORTS'
        );

        parent::configure([
            'containerConfig' => $config['custom_report']['definitions'],
            'settingsStoreScope' => 'pimcore_custom_reports',
            'storageDirectory' => $storageConfig,
            'legacyConfigFile' => 'custom-reports.php'
        ]);
    }

    /**
     * @param string|null $id
     *
     * @throws Model\Exception\NotFoundException
     */
    public function getByName($id = null)
    {
        if ($id != null) {
            $this->model->setName($id);
        }

        $data = $this->getDataByName($this->model->getName());

        if ($data && $id != null) {
            $data['id'] = $id;
        }

        if ($data) {
            $this->assignVariablesToModel($data);
            $this->model->setName($data['id']);
        } else {
            throw new Model\Exception\NotFoundException(sprintf(
                'Custom report config with name "%s" does not exist.',
                $this->model->getName()
            ));
        }
    }

    /**
     * @throws \Exception
     */
    public function save()
    {
        $ts = time();
        if (!$this->model->getCreationDate()) {
            $this->model->setCreationDate($ts);
        }
        $this->model->setModificationDate($ts);

        $dataRaw = $this->model->getObjectVars();
        $data = [];
        $allowedProperties = ['name', 'sql', 'dataSourceConfig', 'columnConfiguration', 'niceName', 'group', 'xAxis',
            'groupIconClass', 'iconClass', 'reportClass', 'creationDate', 'modificationDate', 'menuShortcut', 'chartType', 'pieColumn',
            'pieLabelColumn', 'yAxis', 'shareGlobally', 'sharedUserNames', 'sharedRoleNames', ];

        foreach ($dataRaw as $key => $value) {
            if (in_array($key, $allowedProperties)) {
                $data[$key] = $value;
            }
        }
        $this->saveData($this->model->getName(), $data);
    }

    /**
     * Deletes object from database
     */
    public function delete()
    {
        $this->deleteData($this->model->getName());
    }

    /**
     * {@inheritdoc}
     */
    protected function prepareDataStructureForYaml(string $id, $data)
    {
        return [
            'pimcore' => [
                'custom_report' => [
                    'definitions' => [
                        $id => $data,
                    ],
                ],
            ],
        ];
    }
}
