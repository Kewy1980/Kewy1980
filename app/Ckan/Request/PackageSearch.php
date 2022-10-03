<?php

namespace App\Ckan\Request;

class PackageSearch
{
    public $endPoint;
    
    public $method = 'GET';

    public $query = '';

    public $filterQuery = '';
    
    public $filterQueries = [];

    public $rows;

    public $start;
    
    public $facetField = '';
    
    public function __construct() {
        $this->endPoint = config('ckan.ckan_api_url') . 'action/package_search';
    }

    public function getAsQueryArray() {
        if($this->facetField !== "") {
            return [
                'query' => [
                    //'q' => $this->query,
                    //'fq' => $this->filterQuery,
                    'rows' => $this->rows,
                    //'start' => $this->start,
                    'facet.field' => "[\"" . $this->facetField . "\"]"
                    /*
                    'facet' => [
                        'field' => $this->facetField
                    ]
                    */
                ]
            ];
        } else {
            $queryArr = [
                'query' => [
                    'q' => $this->query,
                    //'fq' => $this->filterQuery,
                    'rows' => $this->rows,
                    'start' => $this->start,
                ]
            ];
            
            
            if(count($this->filterQueries) > 0) {
                $queryArr['query']['fq'] = $this->filterQueries[0];
                if(count($this->filterQueries) > 1) {
                    $parts = array_slice($this->filterQueries, 1);
                    $fqlist = '';
                    foreach ($parts as $part) {
                        $fqlist .= "[" . $part . "]";
                    }
                    
                    $queryArr['query']['fq_list'] = $fqlist;
                                                            
                }
            }
            
            
            return $queryArr;
        }        
    }

    public function setbyRequest($request, $processedQuery = '') {
        $this->rows = (int)$request->get('rows');
        if($this->rows < 1) {
            $this->rows = 10;
        }

        $this->start = (int)$request->get('start');
        if($this->start < 0) {
            $this->start = 0;
        }

        if($processedQuery !== '') {
            $this->query = $processedQuery;
        } else {
            $this->query = $request->get('query');        
        }
        
        if(!$this->query) {
            $this->query = "";
        }
    }
    
    public function addFilterQuery($query) {
        $this->filterQueries[] = $query;
    }
    
    public function getFilterQueryAsArray() {
        if(count($this->filterQueries) == 1) {
            return ['fq' => $this->filterQueries[0]];
        } elseif (count($this->filterQueries) > 1) {
            
        }
    }
}
