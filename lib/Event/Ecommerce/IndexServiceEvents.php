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

namespace Pimcore\Event\Ecommerce;

final class IndexServiceEvents
{
    /**
     * Fired when error occurs during processing attributes for index. Event can influence handling of that error (like ignoring, throwing exceptions, etc.)
     *
     * @Event("Pimcore\Event\Model\Ecommerce\PreprocessAttributeErrorEvent")
     *
     * @var string
     */
    const ATTRIBUTE_PROCESSING_ERROR = 'pimcore.ecommerce.indexservice.preProcessAttributeError';

    /**
     * Fired when error occurs during pre processing index data. Event can influence handling of that error (like throwing exceptions, etc.)
     *
     * @Event("Pimcore\Event\Model\Ecommerce\PreprocessErrorEvent")
     *
     * @var string
     */
    const GENERAL_PREPROCESSING_ERROR = 'pimcore.ecommerce.indexservice.generalPreProcessingError';
    
      /**
     * @Event("Pimcore\Event\Model\Ecommerce\IndexService\PreSendRequestEvent")
     *
     * @var string
     */
    const PRE_SEND_REQUST_ELASTIC_SEARCH = 'pimcore.ecommerce.indexservice.productlist.pre_send_request_elastic_search';
}
