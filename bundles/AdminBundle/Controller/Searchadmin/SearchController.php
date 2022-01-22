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

namespace Pimcore\Bundle\AdminBundle\Controller\Searchadmin;

use Pimcore\Bundle\AdminBundle\Controller\AdminController;
use Pimcore\Bundle\AdminBundle\Controller\Traits\AdminStyleTrait;
use Pimcore\Bundle\AdminBundle\Helper\GridHelperService;
use Pimcore\Config;
use Pimcore\Event\Admin\ElementAdminStyleEvent;
use Pimcore\Event\AdminEvents;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Document;
use Pimcore\Model\Element;
use Pimcore\Model\Search\Backend\Data;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @Route("/search")
 *
 * @internal
 */
class SearchController extends AdminController
{
    use AdminStyleTrait;

    /**
     * @Route("/find", name="pimcore_admin_searchadmin_search_find", methods={"GET", "POST"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @todo: $conditionTypeParts could be undefined
     * @todo: $conditionSubtypeParts could be undefined
     * @todo: $conditionClassnameParts could be undefined
     * @todo: $data could be undefined
     */
    public function findAction(Request $request, EventDispatcherInterface $eventDispatcher, GridHelperService $gridHelperService)
    {
        $allParams = array_merge($request->request->all(), $request->query->all());

        $requestedLanguage = $allParams['language'] ?? null;
        if ($requestedLanguage) {
            if ($requestedLanguage != 'default') {
                $request->setLocale($requestedLanguage);
            }
        }

        $filterPrepareEvent = new GenericEvent($this, [
            'requestParams' => $allParams,
        ]);
        $eventDispatcher->dispatch($filterPrepareEvent, AdminEvents::SEARCH_LIST_BEFORE_FILTER_PREPARE);

        $allParams = $filterPrepareEvent->getArgument('requestParams');

        $query = $this->filterQueryParam($allParams['query']);

        $types = explode(',', $allParams['type'] ?? '');
        $subtypes = explode(',', $allParams['subtype'] ?? '');
        $classnames = explode(',', $allParams['class'] ?? '');

        $offset = (int)$allParams['start'];
        $limit = (int)$allParams['limit'];

        $offset = $offset ? $offset : 0;
        $limit = $limit ? $limit : 50;

        $searcherList = new Data\Listing();
        $conditionParts = [];
        $db = \Pimcore\Db::get();

        $forbiddenConditions = $this->getForbiddenCondition($types);

        if ($forbiddenConditions) {
            $conditionParts[] = '(' . implode(' AND ', $forbiddenConditions) . ')';
        }

        $queryCondition = '';
        if (!empty($query)) {
            $queryCondition = '( MATCH (`data`,`properties`) AGAINST (' . $db->quote($query) . ' IN BOOLEAN MODE) )';

            // the following should be done with an exact-search now "ID", because the Element-ID is now in the fulltext index
            // if the query is numeric the user might want to search by id
            //if(is_numeric($query)) {
            //$queryCondition = "(" . $queryCondition . " OR id = " . $db->quote($query) ." )";
            //}

            $conditionParts[] = $queryCondition;
        }

        //For objects - handling of bricks
        $fields = [];
        $bricks = [];
        if (!empty($allParams['fields'])) {
            $fields = $allParams['fields'];

            foreach ($fields as $f) {
                $parts = explode('~', $f);
                if (substr($f, 0, 1) == '~') {
                    //                    $type = $parts[1];
//                    $field = $parts[2];
//                    $keyid = $parts[3];
                    // key value, ignore for now
                } elseif (count($parts) > 1) {
                    $bricks[$parts[0]] = $parts[0];
                }
            }
        }

        // filtering for objects
        if (!empty($allParams['filter']) && !empty($allParams['class'])) {
            $class = DataObject\ClassDefinition::getByName($allParams['class']);

            // add Localized Fields filtering
            $params = $this->decodeJson($allParams['filter']);
            $unlocalizedFieldsFilters = [];
            $localizedFieldsFilters = [];

            foreach ($params as $paramConditionObject) {
                //this loop divides filter parameters to localized and unlocalized groups
                $definitionExists = in_array('o_' . $paramConditionObject['property'], DataObject\Service::getSystemFields())
                    || $class->getFieldDefinition($paramConditionObject['property']);
                if ($definitionExists) { //TODO: for sure, we can add additional condition like getLocalizedFieldDefinition()->getFieldDefiniton(...
                    $unlocalizedFieldsFilters[] = $paramConditionObject;
                } else {
                    $localizedFieldsFilters[] = $paramConditionObject;
                }
            }

            //get filter condition only when filters array is not empty

            //string statements for divided filters
            $conditionFilters = count($unlocalizedFieldsFilters)
                ? $gridHelperService->getFilterCondition($this->encodeJson($unlocalizedFieldsFilters), $class)
                : null;
            $localizedConditionFilters = count($localizedFieldsFilters)
                ? $gridHelperService->getFilterCondition($this->encodeJson($localizedFieldsFilters), $class)
                : null;

            $join = '';
            $localizedJoin = '';
            foreach ($bricks as $ob) {
                $join .= ' LEFT JOIN object_brick_query_' . $ob . '_' . $class->getId();
                $join .= ' `' . $ob . '`';

                if ($localizedConditionFilters) {
                    $localizedJoin = $join . ' ON `' . $ob . '`.o_id = `object_localized_data_' . $class->getId() . '`.ooo_id';
                }

                $join .= ' ON `' . $ob . '`.o_id = `object_' . $class->getId() . '`.o_id';
            }

            if (null !== $conditionFilters) {
                //add condition query for non localised fields
                $conditionParts[] = '( id IN (SELECT `object_' . $class->getId() . '`.o_id FROM object_' . $class->getId()
                    . $join . ' WHERE ' . $conditionFilters . ') )';
            }

            if (null !== $localizedConditionFilters) {
                //add condition query for localised fields
                $conditionParts[] = '( id IN (SELECT `object_localized_data_' . $class->getId()
                    . '`.ooo_id FROM object_localized_data_' . $class->getId() . $localizedJoin . ' WHERE '
                    . $localizedConditionFilters . ' GROUP BY ooo_id ' . ') )';
            }
        }

        if (is_array($types) && !empty($types[0])) {
            $conditionTypeParts = [];
            foreach ($types as $type) {
                $conditionTypeParts[] = $db->quote($type);
            }
            if (in_array('folder', $subtypes)) {
                $conditionTypeParts[] = $db->quote('folder');
            }
            $conditionParts[] = '( maintype IN (' . implode(',', $conditionTypeParts) . ') )';
        }

        if (is_array($subtypes) && !empty($subtypes[0])) {
            $conditionSubtypeParts = [];
            foreach ($subtypes as $subtype) {
                $conditionSubtypeParts[] = $db->quote($subtype);
            }
            $conditionParts[] = '( type IN (' . implode(',', $conditionSubtypeParts) . ') )';
        }

        if (is_array($classnames) && !empty($classnames[0])) {
            if (in_array('folder', $subtypes)) {
                $classnames[] = 'folder';
            }
            $conditionClassnameParts = [];
            foreach ($classnames as $classname) {
                $conditionClassnameParts[] = $db->quote($classname);
            }
            $conditionParts[] = '( subtype IN (' . implode(',', $conditionClassnameParts) . ') )';
        }

        //filtering for tags
        if (!empty($allParams['tagIds'])) {
            $tagIds = $allParams['tagIds'];
            foreach ($tagIds as $tagId) {
                foreach ($types as $type) {
                    if (($allParams['considerChildTags'] ?? 'false') === 'true') {
                        $tag = Element\Tag::getById($tagId);
                        if ($tag) {
                            $tagPath = $tag->getFullIdPath();
                            $conditionParts[] = 'id IN (SELECT cId FROM tags_assignment INNER JOIN tags ON tags.id = tags_assignment.tagid WHERE ctype = ' . $db->quote($type) . ' AND (id = ' .(int)$tagId. ' OR idPath LIKE ' . $db->quote($db->escapeLike($tagPath) . '%') . '))';
                        }
                    } else {
                        $conditionParts[] = 'id IN (SELECT cId FROM tags_assignment WHERE ctype = ' . $db->quote($type) . ' AND tagid = ' .(int)$tagId. ')';
                    }
                }
            }
        }

        if (count($conditionParts) > 0) {
            $condition = implode(' AND ', $conditionParts);
            $searcherList->setCondition($condition);
        }

        $searcherList->setOffset($offset);
        $searcherList->setLimit($limit);

        $searcherList->setOrderKey($queryCondition, false);
        $searcherList->setOrder('DESC');

        $sortingSettings = \Pimcore\Bundle\AdminBundle\Helper\QueryParams::extractSortingSettings($allParams);
        if ($sortingSettings['orderKey']) {
            // we need a special mapping for classname as this is stored in subtype column
            $sortMapping = [
                'classname' => 'subtype',
            ];

            $sort = $sortingSettings['orderKey'];
            if (array_key_exists($sortingSettings['orderKey'], $sortMapping)) {
                $sort = $sortMapping[$sortingSettings['orderKey']];
            }
            $searcherList->setOrderKey($sort);
        }
        if ($sortingSettings['order']) {
            $searcherList->setOrder($sortingSettings['order']);
        }

        $beforeListLoadEvent = new GenericEvent($this, [
            'list' => $searcherList,
            'context' => $allParams,
        ]);
        $eventDispatcher->dispatch($beforeListLoadEvent, AdminEvents::SEARCH_LIST_BEFORE_LIST_LOAD);
        /** @var Data\Listing $searcherList */
        $searcherList = $beforeListLoadEvent->getArgument('list');

        if (in_array('asset', $types)) {
            // Global asset list event (same than the SEARCH_LIST_BEFORE_LIST_LOAD event, but this last one is global for search, list, tree)
            $beforeListLoadEvent = new GenericEvent($this, [
                'list' => $searcherList,
                'context' => $allParams,
            ]);
            $eventDispatcher->dispatch($beforeListLoadEvent, AdminEvents::ASSET_LIST_BEFORE_LIST_LOAD);
            /** @var Data\Listing $searcherList */
            $searcherList = $beforeListLoadEvent->getArgument('list');
        }

        if (in_array('document', $types)) {
            // Global document list event (same than the SEARCH_LIST_BEFORE_LIST_LOAD event, but this last one is global for search, list, tree)
            $beforeListLoadEvent = new GenericEvent($this, [
                'list' => $searcherList,
                'context' => $allParams,
            ]);
            $eventDispatcher->dispatch($beforeListLoadEvent, AdminEvents::DOCUMENT_LIST_BEFORE_LIST_LOAD);
            /** @var Data\Listing $searcherList */
            $searcherList = $beforeListLoadEvent->getArgument('list');
        }

        if (in_array('object', $types)) {
            // Global object list event (same than the SEARCH_LIST_BEFORE_LIST_LOAD event, but this last one is global for search, list, tree)
            $beforeListLoadEvent = new GenericEvent($this, [
                'list' => $searcherList,
                'context' => $allParams,
            ]);
            $eventDispatcher->dispatch($beforeListLoadEvent, AdminEvents::OBJECT_LIST_BEFORE_LIST_LOAD);
            /** @var Data\Listing $searcherList */
            $searcherList = $beforeListLoadEvent->getArgument('list');
        }

        $hits = $searcherList->load();

        $elements = [];
        foreach ($hits as $hit) {
            $element = Element\Service::getElementById($hit->getId()->getType(), $hit->getId()->getId());
            if ($element->isAllowed('list')) {
                $data = null;
                if ($element instanceof DataObject\AbstractObject) {
                    $data = DataObject\Service::gridObjectData($element, $fields);
                } elseif ($element instanceof Document) {
                    $data = Document\Service::gridDocumentData($element);
                } elseif ($element instanceof Asset) {
                    $data = Asset\Service::gridAssetData($element);
                }

                if ($data) {
                    $elements[] = $data;
                }
            } else {
                //TODO: any message that view is blocked?
                //$data = Element\Service::gridElementData($element);
            }
        }

        // only get the real total-count when the limit parameter is given otherwise use the default limit
        if ($allParams['limit']) {
            $totalMatches = $searcherList->getTotalCount();
        } else {
            $totalMatches = count($elements);
        }

        $result = ['data' => $elements, 'success' => true, 'total' => $totalMatches];

        $afterListLoadEvent = new GenericEvent($this, [
            'list' => $result,
            'context' => $allParams,
        ]);
        $eventDispatcher->dispatch($afterListLoadEvent, AdminEvents::SEARCH_LIST_AFTER_LIST_LOAD);
        $result = $afterListLoadEvent->getArgument('list');

        return $this->adminJson($result);
    }

    /**
     * @param array $types
     *
     * @return array
     */
    protected function getForbiddenCondition($types = ['assets', 'documents', 'objects'])
    {
        $user = $this->getAdminUser();
        $db = \Pimcore\Db::get();

        $forbiddenConditions = [];

        //exclude forbidden assets
        if (in_array('asset', $types)) {
            if (!$user->isAllowed('assets')) {
                $forbiddenConditions[] = " `type` != 'asset' ";
            } else {
                $forbiddenAssetPaths = Element\Service::findForbiddenPaths('asset', $user);
                if (count($forbiddenAssetPaths) > 0) {
                    for ($i = 0; $i < count($forbiddenAssetPaths); $i++) {
                        $forbiddenAssetPaths[$i] = " (maintype = 'asset' AND fullpath not like " . $db->quote($forbiddenAssetPaths[$i] . '%') . ')';
                    }
                    $forbiddenConditions[] = implode(' AND ', $forbiddenAssetPaths) ;
                }
            }
        }

        //exclude forbidden documents
        if (in_array('document', $types)) {
            if (!$user->isAllowed('documents')) {
                $forbiddenConditions[] = " `type` != 'document' ";
            } else {
                $forbiddenDocumentPaths = Element\Service::findForbiddenPaths('document', $user);
                if (count($forbiddenDocumentPaths) > 0) {
                    for ($i = 0; $i < count($forbiddenDocumentPaths); $i++) {
                        $forbiddenDocumentPaths[$i] = " (maintype = 'document' AND fullpath not like " . $db->quote($forbiddenDocumentPaths[$i] . '%') . ')';
                    }
                    $forbiddenConditions[] = implode(' AND ', $forbiddenDocumentPaths) ;
                }
            }
        }

        //exclude forbidden objects
        if (in_array('object', $types)) {
            if (!$user->isAllowed('objects')) {
                $forbiddenConditions[] = " `type` != 'object' ";
            } else {
                $forbiddenObjectPaths = Element\Service::findForbiddenPaths('object', $user);
                if (count($forbiddenObjectPaths) > 0) {
                    for ($i = 0; $i < count($forbiddenObjectPaths); $i++) {
                        $forbiddenObjectPaths[$i] = " (maintype = 'object' AND fullpath not like " . $db->quote($forbiddenObjectPaths[$i] . '%') . ')';
                    }
                    $forbiddenConditions[] = implode(' AND ', $forbiddenObjectPaths);
                }
            }
        }

        return $forbiddenConditions;
    }

    /**
     * @param string $query
     *
     * @return string
     */
    protected function filterQueryParam(string $query)
    {
        if ($query == '*') {
            $query = '';
        }

        $query = str_replace('&quot;', '"', $query);
        $query = str_replace('%', '*', $query);
        $query = str_replace('@', '#', $query);
        $query = preg_replace("@([^ ])\-@", '$1 ', $query);

        $query = str_replace(['<', '>', '(', ')', '~'], ' ', $query);

        // it is not allowed to have * behind another *
        $query = preg_replace('#[*]+#', '*', $query);

        // no boolean operators at the end of the query
        $query = rtrim($query, '+- ');

        return $query;
    }

    /**
     * @Route("/quicksearch", name="pimcore_admin_searchadmin_search_quicksearch", methods={"GET"})
     *
     * @param Request $request
     * @param EventDispatcherInterface $eventDispatcher
     * @param Config $config
     *
     * @return JsonResponse
     */
    public function quicksearchAction(Request $request, EventDispatcherInterface $eventDispatcher, Config $config)
    {
        $query = $this->filterQueryParam($request->get('query'));
        if (!preg_match('/[\+\-\*"]/', $query)) {
            // check for a boolean operator (which was not filtered by filterQueryParam()),
            // if present, do not add asterisk at the end of the query
            $query = $query . '*';
        }

        $db = \Pimcore\Db::get();
        $searcherList = new Data\Listing();

        $conditionParts = [];

        $forbiddenConditions = $this->getForbiddenCondition();
        if ($forbiddenConditions) {
            $conditionParts[] = '(' . implode(' AND ', $forbiddenConditions) . ')';
        }

        $matchCondition = '( MATCH (`data`,`properties`) AGAINST (' . $db->quote($query) . ' IN BOOLEAN MODE) )';
        $conditionParts[] = '(' . $matchCondition . " AND type != 'folder') ";

        $queryCondition = implode(' AND ', $conditionParts);

        $searcherList->setCondition($queryCondition);
        $searcherList->setLimit(50);
        $searcherList->setOrderKey($matchCondition, false);
        $searcherList->setOrder('DESC');

        $beforeListLoadEvent = new GenericEvent($this, [
            'list' => $searcherList,
            'query' => $query,
        ]);
        $eventDispatcher->dispatch($beforeListLoadEvent, AdminEvents::QUICKSEARCH_LIST_BEFORE_LIST_LOAD);
        $searcherList = $beforeListLoadEvent->getArgument('list');

        $hits = $searcherList->load();

        $elements = [];
        foreach ($hits as $hit) {
            $element = Element\Service::getElementById($hit->getId()->getType(), $hit->getId()->getId());
            if ($element->isAllowed('list')) {
                $data = [
                    'id' => $element->getId(),
                    'type' => $hit->getId()->getType(),
                    'subtype' => $element->getType(),
                    'className' => ($element instanceof DataObject\Concrete) ? $element->getClassName() : '',
                    'fullpath' => htmlspecialchars($element->getRealFullPath()),
                    'fullpathList' => htmlspecialchars($this->shortenPath($element->getRealFullPath())),
                    'iconCls' => 'pimcore_icon_asset_default',
                ];

                $this->addAdminStyle($element, ElementAdminStyleEvent::CONTEXT_SEARCH, $data);

                $validLanguages = \Pimcore\Tool::getValidLanguages();

                $data['preview'] = $this->renderView('@PimcoreAdmin/SearchAdmin/Search/Quicksearch/' . $hit->getId()->getType() . '.html.twig', [
                    'element' => $element,
                    'iconCls' => $data['iconCls'],
                    'config' => $config,
                    'validLanguages' => $validLanguages,
                ]);

                $elements[] = $data;
            }
        }

        $afterListLoadEvent = new GenericEvent($this, [
            'list' => $elements,
            'context' => $query,
        ]);
        $eventDispatcher->dispatch($afterListLoadEvent, AdminEvents::QUICKSEARCH_LIST_AFTER_LIST_LOAD);
        $elements = $afterListLoadEvent->getArgument('list');

        $result = ['data' => $elements, 'success' => true];

        return $this->adminJson($result);
    }

    /**
     * @param string $path
     *
     * @return string
     */
    protected function shortenPath($path)
    {
        $parts = explode('/', trim($path, '/'));
        $count = count($parts) - 1;

        for ($i = $count; ; $i--) {
            $shortPath = '/' . implode('/', array_unique($parts));
            if ($i === 0 || strlen($shortPath) <= 50) {
                break;
            }
            array_splice($parts, $i - 1, 1, '…');
        }

        if (mb_strlen($shortPath) > 50) {
            $shortPath = mb_substr($shortPath, 0, 49) . '…';
        }

        return $shortPath;
    }
}
