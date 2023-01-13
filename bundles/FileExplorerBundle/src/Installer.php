<?php

namespace Pimcore\Bundle\FileExplorerBundle;

use Pimcore\Extension\Bundle\Installer\SettingsStoreAwareInstaller;

class Installer extends SettingsStoreAwareInstaller
{
    const USER_PERMISSIONS = [
        'fileexplorer'
    ];

    public function install(): void
    {
        $this->addPermissions();
        parent::install();
    }

    public function uninstall(): void
    {
        $this->removePermissions();
        parent::uninstall();
    }

    protected function addPermissions(): void
    {
        $db = \Pimcore\Db::get();


        foreach (self::USER_PERMISSIONS as $permission) {
            $db->insert('users_permission_definitions', [
                $db->quoteIdentifier('key') => $permission,
            ]);
        }
    }

    protected function removePermissions(): void
    {
        $db = \Pimcore\Db::get();

        foreach (self::USER_PERMISSIONS as $permission) {
            $db->delete('users_permission_definitions', [
                $db->quoteIdentifier('key') => $permission,
            ]);
        }
    }
}
