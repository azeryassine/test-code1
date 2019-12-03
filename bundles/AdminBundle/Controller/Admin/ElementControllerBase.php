<?php
/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Bundle\AdminBundle\Controller\Admin;

use Pimcore\Bundle\AdminBundle\Controller\AdminController;
use Pimcore\Event\AssetEvents;
use Pimcore\Event\DataObjectEvents;
use Pimcore\Event\DocumentEvents;
use Pimcore\Event\Model\AssetDeleteInfoEvent;
use Pimcore\Event\Model\DataObjectDeleteInfoEvent;
use Pimcore\Event\Model\DocumentDeleteInfoEvent;
use Pimcore\Event\Model\ElementDeleteInfoEventInterface;
use Pimcore\Logger;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\Document;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Service;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ElementControllerBase extends AdminController
{
    /**
     * @param $element
     *
     * @return array
     */
    protected function getTreeNodeConfig($element)
    {
        return [];
    }

    /**
     * @Route("/tree-get-root", methods={"GET"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function treeGetRootAction(Request $request)
    {
        $type = $request->get('elementType');
        $allowedTypes = ['asset', 'document', 'object'];

        $id = 1;
        if ($request->get('id')) {
            $id = intval($request->get('id'));
        }

        if (in_array($type, $allowedTypes)) {
            $root = Service::getElementById($type, $id);
            if ($root->isAllowed('list')) {
                return $this->adminJson($this->getTreeNodeConfig($root));
            }
        }

        return $this->adminJson(['success' => false, 'message' => 'missing_permission']);
    }

    /**
     * @Route("/delete-info", methods={"GET"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function deleteInfoAction(Request $request)
    {
        $hasDependency = false;
        $errors = false;
        $deleteJobs = [];
        $recycleJobs = [];
        $itemResults = [];

        $totalChilds = 0;

        $ids = $request->get('id');
        $ids = explode(',', $ids);
        $type = $request->get('type');

        foreach ($ids as $id) {
            try {
                $element = Service::getElementById($type, $id);
                if (!$element) {
                    continue;
                }
                $hasDependency = $element->getDependencies()->isRequired();
            } catch (\Exception $e) {
                Logger::err('failed to access element with id: ' . $id);
                continue;
            }

            // check for childs
            if ($element instanceof ElementInterface) {
                $event = null;
                $eventName = null;

                if ($element instanceof Asset) {
                    $event = new AssetDeleteInfoEvent($element);
                    $eventName = AssetEvents::DELETE_INFO;
                } elseif ($element instanceof Document) {
                    $event = new DocumentDeleteInfoEvent($element);
                    $eventName = DocumentEvents::DELETE_INFO;
                } elseif ($element instanceof AbstractObject) {
                    $event = new DataObjectDeleteInfoEvent($element);
                    $eventName = DataObjectEvents::DELETE_INFO;
                }

                if ($event instanceof ElementDeleteInfoEventInterface) {
                    $this->get('event_dispatcher')->dispatch($eventName, $event);

                    if (!$event->getDeletionAllowed()) {
                        $itemResults[] = [
                            'id' => $element->getId(),
                            'type' => $element->getType(),
                            'key' => $element->getKey(),
                            'reason' => $event->getReason(),
                            'allowed' => false,
                        ];
                        $errors |= true;
                        continue;
                    }
                }

                $itemResults[] = [
                    'id' => $element->getId(),
                    'type' => $element->getType(),
                    'key' => $element->getKey(),
                    'allowed' => true,
                ];

                $recycleJobs[] = [[
                    'url' => '/admin/recyclebin/add',
                    'method' => 'POST',
                    'params' => [
                        'type' => $type,
                        'id' => $element->getId()
                    ]
                ]];

                $hasChilds = $element->hasChildren();
                if (!$hasDependency) {
                    $hasDependency = $hasChilds;
                }

                if ($hasChilds) {
                    // get amount of childs
                    $listClass = '\Pimcore\Model\\' . Service::getBaseClassNameForElement($element) . '\Listing';
                    $list = new $listClass();
                    $pathColumn = ($type == 'object') ? 'o_path' : 'path';
                    $list->setCondition($pathColumn . ' LIKE ?', [$element->getRealFullPath() . '/%']);
                    $childs = $list->getTotalCount();
                    $totalChilds += $childs;

                    if ($childs > 0) {
                        $deleteObjectsPerRequest = 5;
                        for ($i = 0; $i < ceil($childs / $deleteObjectsPerRequest); $i++) {
                            $deleteJobs[] = [[
                                'url' => '/admin/' . $type . '/delete',
                                'method' => 'DELETE',
                                'params' => [
                                    'step' => $i,
                                    'amount' => $deleteObjectsPerRequest,
                                    'type' => 'childs',
                                    'id' => $element->getId()
                                ]
                            ]];
                        }
                    }
                }

                // the asset itself is the last one
                $deleteJobs[] = [[
                    'url' => '/admin/' . $type . '/delete',
                    'method' => 'DELETE',
                    'params' => [
                        'id' => $element->getId()
                    ]
                ]];
            }
        }

        // get the element key in case of just one
        $elementKey = false;
        if (count($ids) === 1) {
            $element = Service::getElementById($type, $ids[0])->getKey();

            if ($element instanceof ElementInterface) {
                $elementKey = $element->getKey();
            }
        }

        $deleteJobs = array_merge($recycleJobs, $deleteJobs);

        return $this->adminJson([
            'hasDependencies' => $hasDependency,
            'childs' => $totalChilds,
            'deletejobs' => $deleteJobs,
            'batchDelete' => count($ids) > 1,
            'elementKey' => $elementKey,
            'errors' => $errors,
            'itemResults' => $itemResults
        ]);
    }
}
