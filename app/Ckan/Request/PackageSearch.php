<?php

namespace App\Ckan\Request;

class PackageSearch
{
    public $method = 'GET';

    public $query = '';

    public $filterQuery = '';

    public $rows;

    public $start;

    public function getAsQueryArray() {
        return [
            'query' => [
                'q' => $this->query,
                'fq' => $this->filterQuery,
                'rows' => $this->rows,
                'start' => $this->start,
            ]
        ];
    }

    public function setbyRequest($request) {
        $this->rows = (int)$request->get('rows');
        if($this->rows < 1) {
            $this->rows = 10;
        }

        $this->start =  (int)$request->get('start');
        if($this->start < 0) {
            $this->start = 0;
        }

        $this->query = $request->get('query');
        if(!$this->query) {
            $this->query = "";
        }
    }
}
