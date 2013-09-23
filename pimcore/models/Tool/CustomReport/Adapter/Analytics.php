<?php
/**
 * Pimcore
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.pimcore.org/license
 *
 * @copyright  Copyright (c) 2009-2013 pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     New BSD License
 */

class Tool_CustomReport_Adapter_Analytics {


    public function __construct($config) {
        $this->config = $config;
    }

    /**
     *
     */
    public function getData($filters, $sort, $dir, $offset, $limit, $fields = null) {

        $this->setFilters($filters);

        if($sort) {
            $dir = $dir == 'DESC' ? '-' : '';
            $this->config->sort = $dir.$sort;
        }

        if($offset) {
            $this->config->startIndex = $offset;
        }

        if($limit) {
            $this->config->maxResults = $limit;
        }


        $results = $this->getDataHelper();
        $data = array();

        if($results['rows']) {
            foreach($results['rows'] as $row) {
                $entry = array();
                foreach($results['columnHeaders'] as $key => $header) {
                    $entry[$header['name']] = $row[$key];
                }
                $data[] = $entry;
            }
        }
        return array("data" => $data, "total" => $results['totalResults']);
    }

    public function getColumns($configuration) {
        $result = $this->getDataHelper();
        $columns = array();

        foreach($result['columnHeaders'] as $col) {
            $columns[] = $col['name'];
        }

        return $columns;
    }

    protected function setFilters($filters) {

        if(!sizeof($filters) ) {
            return;
        }

        $gaFilters = array($this->config->filters);
        foreach($filters as $filter) {
            if($filter['type'] == 'string') {
                $value = str_replace(';', '', addslashes($filter['value']));
                $gaFilters[] = "{$filter['field']}=~{$value}";
            } else if($filter["type"] == "numeric") {
                $value = floatval($filter['value']);
                $compMapping = array(
                    "lt" => "<",
                    "gt" => ">",
                    "eq" => "=="
                );
                if($compMapping[$filter["comparison"]]) {
                    $gaFilters[] = "{$filter['field']}{$compMapping[$filter["comparison"]]}{$value}";
                }
            } else if ($filter["type"] == "boolean") {

                $value = $filter['value'] ? 'Yes' : 'No';
                $gaFilters[] = "{$filter['field']}=={$value}";
            }
        }

        foreach($gaFilters as $key => $filter) {
            if(!$filter) {
                unset($gaFilters[$key]);
            }
        }

        $this->config->filters = implode(';', $gaFilters);

    }

    protected function getDataHelper() {
        $configuration = $this->config;

        $client = Pimcore_Google_Api::getServiceClient();
        if(!$client) {
            throw new Exception("Google Analytics is not configured");
        }

        $service = new Google_AnalyticsService($client);

        if(!$configuration->profileId) {
            throw new Exception("no profileId given");
        }

        if(!$configuration->metric) {
            throw new Exception("no metric given");
        }


        $options = array();

        if($configuration->filters) {
            $options['filters'] = $configuration->filters;
        }
        if($configuration->dimension) {
            $options['dimensions'] = $configuration->dimension;
        }
        if($configuration->sort) {
            $options['sort'] = $configuration->sort;
        }
        if($configuration->startIndex) {
            $options['start-index'] = $configuration->startIndex;
        }
        if($configuration->maxResults) {
            $options['max-results'] = $configuration->maxResults;
        }
        if($configuration->segment) {
            $options['segment'] = $configuration->segment;
        }

        $configuration->startDate = $this->calcDate($configuration->startDate, $configuration->relativeStartDate);
        $configuration->endDate = $this->calcDate($configuration->endDate, $configuration->relativeEndDate);


        if(!$configuration->startDate) {
            throw new Exception("no start date given");
        }

        if(!$configuration->endDate) {
            throw new Exception("no end date given");
        }

        return $service->data_ga->get('ga:'.$configuration->profileId, date('Y-m-d', $configuration->startDate), date('Y-m-d', $configuration->endDate), $configuration->metric, $options);

    }


    protected function calcDate($date, $relativeDate) {


        if(strpos($relativeDate, '-') !== false || strpos($relativeDate, '+') !== false) {

            $modifiers = explode(' ', str_replace('  ', ' ', $relativeDate));

            $applyModifiers = array();
            foreach ($modifiers as $modifier) {
                $modifier = trim($modifier);
                if (preg_match('/^([+-])(\d+)([dmy])$/', $modifier, $matches)) {
                    if (in_array($matches[1], array('+', '-')) && is_numeric($matches[2])
                        && in_array($matches[3], array('d', 'm', 'y'))
                    ) {
                        $applyModifiers[] = array('sign' => $matches[1], 'number' => $matches[2],
                                                  'type' => $matches[3]);
                    }
                }
            }

            if(sizeof($applyModifiers)) {
                $date = new Zend_Date();

                foreach($applyModifiers as $modifier) {

                    if($modifier['sign'] == '-') {
                        $modifier['number'] *= -1;
                    }

                    $typeMap = array('d' => Zend_Date::DAY, 'm' => Zend_Date::MONTH, 'y' => Zend_Date::YEAR);

                    $date->add($modifier['number'], $typeMap[$modifier['type']]);

                }

                return $date->getTimestamp();
            }
        }

        return $date/1000;
    }

}