<?php


namespace Pimcore\Extension\Bundle\Installer;


use Pimcore\Migrations\MigrationManager;
use Pimcore\Model\Tool\SettingsStore;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class SettingsStoreAwareInstaller extends AbstractInstaller
{

    /**
     * @var BundleInterface
     */
    protected $bundle;

    /**
     * @var MigrationManager
     */
    protected $migrationManager;

    /**
     * @param BundleInterface $bundle
     */
    public function __construct(BundleInterface $bundle, MigrationManager $migrationManager)
    {
        parent::__construct();
        $this->bundle = $bundle;
        $this->migrationManager = $migrationManager;
    }

    protected function getSettingsStoreInstallationId() {
        return 'INSTALLED__' . $this->bundle->getNamespace() . '\\' . $this->bundle->getName();
    }

    public function getLastMigrationVersionClassName(): ?string
    {
        return null;
    }

    /**
     * @return string|null
     */
    private function getMigrationVersion(): ?string
    {
        $className = $this->getLastMigrationVersionClassName();

        if($className) {
            preg_match('/\d+$/', $className, $matches);
            return end($matches);
        }
        return null;
    }

    protected function markInstalled() {
        $migrationVersion = $this->getMigrationVersion();
        if($migrationVersion) {

            $version = $this->migrationManager->getBundleVersion(
                $this->bundle,
                $migrationVersion
            );
            $this->migrationManager->markVersionAsMigrated($version, true);

        }

        SettingsStore::set($this->getSettingsStoreInstallationId(), true, null, 'bool');
    }

    protected function markUninstalled() {
        $configuration = $this->migrationManager->getBundleConfiguration($this->bundle);
        if($configuration) {
            foreach($configuration->getMigratedVersions() as $migratedVersion) {
                $version = $this->migrationManager->getBundleVersion($this->bundle, $migratedVersion);
                $this->migrationManager->markVersionAsNotMigrated($version);
            }
        }

        SettingsStore::set($this->getSettingsStoreInstallationId(), false, null, 'bool');
    }

    public function install()
    {
        parent::install();
        $this->markInstalled();
    }

    public function uninstall()
    {
        parent::uninstall();
        $this->markUninstalled();
    }

    public function isInstalled()
    {
        $installSetting = SettingsStore::get($this->getSettingsStoreInstallationId());
        return $installSetting ? $installSetting->getData() : false;
    }

    public function canBeInstalled()
    {
        return !$this->isInstalled();
    }

    public function canBeUninstalled()
    {
        return $this->isInstalled();
    }

}
