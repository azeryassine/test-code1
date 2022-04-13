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

namespace Pimcore\Model\Element;

use Pimcore\Model;
use Pimcore\Model\User;

/**
 * @internal
 *
 * @property Model\Document|Model\Asset|Model\DataObject\AbstractObject $model
 */
abstract class Dao extends Model\Dao\AbstractDao
{
    /**
     * @return int[]
     *
     * @throws \Exception
     */
    public function getParentIds()
    {
        // collect properties via parent - ids
        $parentIds = [1];
        $obj = $this->model->getParent();

        if ($obj) {
            while ($obj) {
                if ($obj->getId() == 1) {
                    break;
                }
                if (in_array($obj->getId(), $parentIds)) {
                    throw new \Exception('detected infinite loop while resolving all parents from ' . $this->model->getId() . ' on ' . $obj->getId());
                }

                $parentIds[] = $obj->getId();
                $obj = $obj->getParent();
            }
        }

        return $parentIds;
    }

    /**
     * @param string $fullpath
     *
     * @return array
     */
    protected function extractKeyAndPath($fullpath)
    {
        $key = '';
        $path = $fullpath;
        if ($fullpath !== '/') {
            $lastPart = strrpos($fullpath, '/') + 1;
            $key = substr($fullpath, $lastPart);
            $path = substr($fullpath, 0, $lastPart);
        }

        return [
            'key' => $key,
            'path' => $path,
        ];
    }

    /**
     * @return int
     */
    abstract public function getVersionCountForUpdate(): int;

    /**
     * @param string $type
     * @param array $userIds
     * @param string $tableSuffix
     *
     * @return int
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function InheritingPermission(string $type, array $userIds, string $tableSuffix): int
    {
        $current = $this->model;

        if (!$current->getId()) {
            return 0;
        }
        $fullPath = $current->getPath().$current->getKey();

        $sql = 'SELECT ' . $this->db->quoteIdentifier($type) . ' FROM users_workspaces_'.$tableSuffix.' WHERE LOCATE(cpath,"'.$fullPath.'")=1 AND
        userId IN (' . implode(',', $userIds) . ')
        ORDER BY LENGTH(cpath) DESC, FIELD(userId, ' . end($userIds) . ') DESC, ' . $this->db->quoteIdentifier($type) . ' DESC LIMIT 1';

        return (int)$this->db->fetchOne($sql);
    }

    /**
     * @param array $columns
     * @param User $user
     * @param string $tableSuffix
     *
     * @return array
     */
    public function permissionByTypes(array $columns, User $user, string $tableSuffix){
        $permissions = [];
        $parentIds = $this->getParentIds();
        $parentIds[] = $this->model->getId();

        $currentUserId = $user->getId();
        $userIds = $user->getRoles();
        $userIds[] = $currentUserId;

        $highestWorkspaceQuery = '
            SELECT userId,cid,`'. implode('`,`', $columns) .'` FROM users_workspaces_'.$tableSuffix.'
            WHERE cid IN (' . implode(',', $parentIds) . ') AND userId IN (' . implode(',', $userIds) . ')
            ORDER BY LENGTH(cpath) DESC, FIELD(userId, ' . $currentUserId . ') DESC LIMIT 1
        ';

        $highestWorkspace = $this->db->fetchRow($highestWorkspaceQuery);

        if ($highestWorkspace) {
            //if it's the current user, this is the permission that rules them all, no need to check others
            if ($highestWorkspace['userId'] == $currentUserId){
                foreach ($columns as $type) {
                    $permissions[$type] = $highestWorkspace[$type];
                }
                return $permissions;
            }

            //if not found, then we have role permission set, we already have the longest cpath from first query, so can use the same cid
            $roleWorkspaceSql = '
             SELECT userId,`'. implode('`,`', $columns) .'` FROM users_workspaces_'.$tableSuffix.'
             WHERE cid = ' . $highestWorkspace['cid'] . ' AND userId IN (' . implode(',', $userIds) . ')
             ORDER BY FIELD(userId, ' . $currentUserId . ') DESC
             ';
            $objectPermissions = $this->db->fetchAll($roleWorkspaceSql);

            if ($objectPermissions[0]['userId'] == $currentUserId){
                foreach ($columns as $type) {
                    $objectPermissions[0][$type] = $highestWorkspace[$type];
                }
                return $permissions;
            }

            //this applies the additive rule when conflicting rules when multiple roles,
            //breaks the loop when permission=1 is found and move on to check next permission type.
            foreach ($columns as $type) {
                foreach ($objectPermissions as $workspace) {
                    if ($workspace[$type] == 1) {
                        $permissions[$type] = 1;
                        break;
                    }
                    $permissions[$type] = 0;
                }
            }
        }
        return $permissions;
    }
}
