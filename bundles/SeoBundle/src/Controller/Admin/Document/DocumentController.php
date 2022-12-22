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

namespace Pimcore\Bundle\SeoBundle\Controller\Admin\Document;



use Pimcore\Bundle\AdminBundle\Controller\Admin\ElementControllerBase;
use Pimcore\Bundle\AdminBundle\Controller\Traits\DocumentTreeConfigTrait;
use Pimcore\Event\AdminEvents;
use Pimcore\Model\Document;
use Pimcore\Model\Document\Page;
use Pimcore\Routing\Dynamic\DocumentRouteHandler;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;


/**
 * @Route("/document")
 *
 * @internal
 */
class DocumentController extends ElementControllerBase
{
    use DocumentTreeConfigTrait;

    final const DOCUMENT_ROOT_ID = 1;

    /**
     * @Route("/seopanel-tree-root", name="pimcore_admin_seo_document_document_seopaneltreeroot", methods={"GET"})
     *
     * @param DocumentRouteHandler $documentRouteHandler
     *
     * @return JsonResponse
     */
    public function seopanelTreeRootAction(DocumentRouteHandler $documentRouteHandler): JsonResponse
    {
        $this->checkPermission('seo_document_editor');

        /** @var Page $root */
        $root = Page::getById(self::DOCUMENT_ROOT_ID);
        if ($root->isAllowed('list')) {
            // make sure document routes are also built for unpublished documents
            $documentRouteHandler->setForceHandleUnpublishedDocuments(true);

            $nodeConfig = $this->getSeoNodeConfig($root);

            return $this->adminJson($nodeConfig);
        }

        throw $this->createAccessDeniedHttpException();
    }

    /**
     * @Route("/seopanel-tree", name="pimcore_admin_seo_document_document_seopaneltree", methods={"GET"})
     *
     * @param Request $request
     * @param EventDispatcherInterface $eventDispatcher
     * @param DocumentRouteHandler $documentRouteHandler
     *
     * @return JsonResponse
     */
    public function seopanelTreeAction(
        Request $request,
        EventDispatcherInterface $eventDispatcher,
        DocumentRouteHandler $documentRouteHandler
    ): JsonResponse {
        $allParams = array_merge($request->request->all(), $request->query->all());

        $filterPrepareEvent = new GenericEvent($this, [
            'requestParams' => $allParams,
        ]);
        $eventDispatcher->dispatch($filterPrepareEvent, AdminEvents::DOCUMENT_LIST_BEFORE_FILTER_PREPARE);

        $allParams = $filterPrepareEvent->getArgument('requestParams');

        $this->checkPermission('seo_document_editor');

        // make sure document routes are also built for unpublished documents
        $documentRouteHandler->setForceHandleUnpublishedDocuments(true);

        $document = Document::getById($allParams['node']);

        $documents = [];
        if ($document->hasChildren()) {
            $list = new Document\Listing();
            $list->setCondition('parentId = ?', $document->getId());
            $list->setOrderKey('index');
            $list->setOrder('asc');

            $beforeListLoadEvent = new GenericEvent($this, [
                'list' => $list,
                'context' => $allParams,
            ]);
            $eventDispatcher->dispatch($beforeListLoadEvent, AdminEvents::DOCUMENT_LIST_BEFORE_LIST_LOAD);
            /** @var Document\Listing $list */
            $list = $beforeListLoadEvent->getArgument('list');

            $childrenList = $list->load();

            foreach ($childrenList as $childDocument) {
                // only display document if listing is allowed for the current user
                if ($childDocument->isAllowed('list')) {
                    $list = new Document\Listing();
                    $list->setCondition('`path` LIKE ? and `type` = ?', [$list->escapeLike($childDocument->getRealFullPath()). '/%', 'page']);

                    if ($childDocument instanceof Document\Page || $list->getTotalCount() > 0) {
                        $documents[] = $this->getSeoNodeConfig($childDocument);
                    }
                }
            }
        }

        $result = ['data' => $documents, 'success' => true, 'total' => count($documents)];

        $afterListLoadEvent = new GenericEvent($this, [
            'list' => $result,
            'context' => $allParams,
        ]);
        $eventDispatcher->dispatch($afterListLoadEvent, AdminEvents::DOCUMENT_LIST_AFTER_LIST_LOAD);
        $result = $afterListLoadEvent->getArgument('list');

        return $this->adminJson($result['data']);
    }


    private function getSeoNodeConfig(Document $document): array
    {
        $nodeConfig = $this->getTreeNodeConfig($document);

        if ($document instanceof Document\Page) {
            // analyze content
            $nodeConfig['prettyUrl'] = $document->getPrettyUrl();

            $title = $document->getTitle();
            $description = $document->getDescription();

            $nodeConfig['title'] = $title;
            $nodeConfig['description'] = $description;

            $nodeConfig['title_length'] = mb_strlen($title);
            $nodeConfig['description_length'] = mb_strlen($description);
        }

        return $nodeConfig;
    }
}
