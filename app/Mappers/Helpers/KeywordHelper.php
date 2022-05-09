<?php
namespace App\Mappers\Helpers;

use App\Models\KeywordSearch;
use App\Datasets\Keywords\KeywordFactory;

class KeywordHelper
{
    private $vocabularyMapping = [
        'materials' => 'msl_materials',
        'porefluids' => 'msl_porefluids',
        'rockphysics' => 'msl_rockphysics',
        'analogue' => 'msl_analogue'
    ];
    
    
    public function mapKeywords($dataset, $keywords, $extractLastTerm = false, $lastTermDelimiter = '>')
    {                
        foreach ($keywords as $keyword) {
            if($extractLastTerm) {
                if(str_contains($keyword, $lastTermDelimiter)) {
                    $splitKeywords = explode($lastTermDelimiter, $keyword);
                    $keyword = end($splitKeywords);
                }
            }
            
            $keyword = trim($keyword);
            
            $searchKeywords = KeywordSearch::where('search_value', strtolower($keyword))->get();
            
            if(count($searchKeywords) > 0) {
                foreach ($searchKeywords as $searchKeyword) {
                    $keyword = $searchKeyword->keyword;                                                            
                    $datasetKeyword = KeywordFactory::create($keyword);
                    
                    $dataset->{$this->vocabularyMapping[$keyword->vocabulary->name]}[] = $datasetKeyword->toArray();
                }
            } else {
                $dataset->tag_string[] = $this->cleanKeyword($keyword);
            }
        }
                        
        return $dataset;
    }
    
    private function cleanKeyword($string)
    {
        $keyword = preg_replace("/[^A-Za-z0-9 ]/", '', $string);
        if(strlen($keyword) >= 100) {
            $keyword = substr($keyword, 0, 95);
            $keyword = $keyword . "...";
        }
        
        return trim($keyword);
    }
    
    
}

