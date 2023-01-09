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

namespace Pimcore\Bundle\SimpleBackendSearchBundle\Controller;

use Pimcore\Model\DataObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Pimcore\Bundle\AdminBundle\Controller\AdminController;

class DataObjectController extends AdminController
{
    /**
     * TODO:
     *  - ask: should we add a additional hock (event) to let users register their own "display modes"
     *
     * @Route("/relation-objects-list", name="pimcore_bundle_search_dataobject_relation_objects_list", methods={"GET"})
     */
    public function optionsAction(Request $request): JsonResponse
    {
        $fieldConfig = json_decode($request->get('fieldConfig'), true);

        $options = [];
        $classes = [];
        if (count($fieldConfig['classes']) > 0) {
            foreach ($fieldConfig['classes'] as $classData) {
                $classes[] = $classData['classes'];
            }
        }

        $visibleFields = is_array($fieldConfig['visibleFields']) ? $fieldConfig['visibleFields'] : explode(',', $fieldConfig['visibleFields']);

        if (!$visibleFields) {
            $visibleFields = ['id', 'fullpath', 'classname'];
        }

        $searchRequest = $request;
        $searchRequest->request->set('type', 'object');
        $searchRequest->request->set('subtype', 'object,variant');
        $searchRequest->request->set('class', implode(',', $classes));
        $searchRequest->request->set('fields', $visibleFields);
        $searchRequest->attributes->set('unsavedChanges', $request->get('unsavedChanges', ''));
        $res = $this->forward(SearchController::class.'::findAction', ['request' => $searchRequest]);
        $objects = json_decode($res->getContent(), true)['data'];

        if ($request->get('data')) {
            foreach (explode(',', $request->get('data')) as $preSelectedElementId) {
                $objects[] = ['id' => $preSelectedElementId];
            }
        }

        foreach ($objects as $objectData) {
            $option = [
                'id' => $objectData['id'],
            ];

            $visibleFieldValues = [];
            foreach ($visibleFields as $visibleField) {
                if (isset($objectData[$visibleField])) {
                    $visibleFieldValues[] = $objectData[$visibleField];
                } else {
                    $inheritValues = DataObject\Concrete::getGetInheritedValues();
                    $fallbackValues = DataObject\Localizedfield::getGetFallbackValues();

                    DataObject\Concrete::setGetInheritedValues(true);
                    DataObject\Localizedfield::setGetFallbackValues(true);

                    $object = DataObject\Concrete::getById($objectData['id']);
                    if (!$object instanceof DataObject\Concrete) {
                        continue;
                    }

                    $getter = 'get'.ucfirst($visibleField);
                    $visibleFieldValue = $object->$getter();
                    if (count($classes) > 1 && $visibleField == 'key') {
                        $visibleFieldValue .= ' ('.$object->getClassName().')';
                    }
                    $visibleFieldValues[] = $visibleFieldValue;

                    DataObject\Concrete::setGetInheritedValues($inheritValues);
                    DataObject\Localizedfield::setGetFallbackValues($fallbackValues);
                }
            }

            $option['label'] = implode(', ', $visibleFieldValues);

            $options[] = $option;
        }

        return new JsonResponse($options);
    }
}
