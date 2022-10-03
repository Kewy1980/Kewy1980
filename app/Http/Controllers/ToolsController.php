<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Phpoaipmh\Endpoint;
use App\Ckan\Request\PackageSearch;
use App\Models\DatasetDelete;
use App\Jobs\ProcessDatasetDelete;
use App\Models\Importer;
use App\Models\Import;
use App\Jobs\ProcessImport;
use App\Models\SourceDatasetIdentifier;
use App\Models\SourceDataset;
use App\Mappers\GfzMapper;
use App\Models\DatasetCreate;
use App\Ckan\Request\PackageCreate;
use App\Ckan\Request\PackageShow;
use App\Ckan\Response\PackageShowResponse;
use App\Ckan\Request\PackageUpdate;
use App\Ckan\Request\OrganizationList;
use App\Ckan\Response\OrganizationListResponse;
use App\Models\MappingLog;
use App\Ckan\Response\PackageSearchResponse;
use App\Mappers\Helpers\DataciteCitationHelper;
use App\Converters\MaterialsConverter;
use App\Exports\MappingLogsExport;
use App\Models\MaterialKeyword;
use App\Converters\RockPhysicsConverter;
use App\Converters\ExcelToJsonConverter;
use App\Exports\FilterTreeExport;
use App\Converters\PorefluidsConverter;
use App\Converters\AnalogueModellingConverter;
use App\Converters\GeologicalAgeConverter;
use App\Converters\GeologicalSettingConverter;
use App\Converters\PaleomagnetismConverter;
use App\Converters\GeochemistryConverter;
use App\Exports\UnmatchedKeywordsExport;
use App\Mappers\Helpers\KeywordHelper;
use App\Exports\AbstractMatchingExport;

class ToolsController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }
        
    public function convertKeywords()
    {        
        return view('convert-keywords');
    }
    
    public function processMaterialsFile(Request $request)
    {
        $request->validate([
            'materials-file' => 'required'
        ]);
                        
        if($request->hasFile('materials-file')) {
            $converter = new MaterialsConverter();
            
            return response()->streamDownload(function () use($converter, $request) {
                echo $converter->ExcelToJson($request->file('materials-file'));
            }, 'materials.json');
                        
        }
        
        return back()
            ->with('status','Error');
    }
    
    public function processPoreFluidsFile(Request $request)
    {
        $request->validate([
            'porefluids-file' => 'required'
        ]);
        
        if($request->hasFile('porefluids-file')) {
            $converter = new PorefluidsConverter();
            
            return response()->streamDownload(function () use($converter, $request) {
                echo $converter->ExcelToJson($request->file('porefluids-file'));
            }, 'porefluids.json');
                
        }
        
        return back()
        ->with('status','Error');
    }
    
    
    public function processRockPhysicsFile(Request $request)
    {        
        $request->validate([
            'rockphysics-file' => 'required'
        ]);
        
        if($request->hasFile('rockphysics-file')) {
            $converter = new RockPhysicsConverter();            
                        
            return response()->streamDownload(function () use($converter, $request) {
                echo $converter->ExcelToJson($request->file('rockphysics-file'));
            }, 'rockphysics.json');
                
        }
        
        return back()
        ->with('status','Error');
    }
    
    public function processAnalogueModellingFile(Request $request)
    {
        $request->validate([
            'analogue-file' => 'required'
        ]);
        
        if($request->hasFile('analogue-file')) {
            $converter = new AnalogueModellingConverter();
            
            return response()->streamDownload(function () use($converter, $request) {
                echo $converter->ExcelToJson($request->file('analogue-file'));
            }, 'analogue.json');
                
        }
        
        return back()
        ->with('status','Error');
    }
    
    public function processGeologicalAgeFile(Request $request)
    {
        $request->validate([
            'geological-age-file' => 'required'
        ]);
        
        if($request->hasFile('geological-age-file')) {
            $converter = new GeologicalAgeConverter();
            
            return response()->streamDownload(function () use($converter, $request) {
                echo $converter->ExcelToJson($request->file('geological-age-file'));
            }, 'geological-age.json');
                
        }
        
        return back()
        ->with('status','Error');
    }
    
    public function processGeologicalSettingFile(Request $request)
    {
        $request->validate([
            'geological-setting-file' => 'required'
        ]);
        
        if($request->hasFile('geological-setting-file')) {
            $converter = new GeologicalSettingConverter();
            
            return response()->streamDownload(function () use($converter, $request) {
                echo $converter->ExcelToJson($request->file('geological-setting-file'));
            }, 'geological-setting.json');
                
        }
        
        return back()
        ->with('status','Error');
    }
    
    public function processPaleomagnetismFile(Request $request)
    {
        $request->validate([
            'paleomagnetism-file' => 'required'
        ]);
        
        if($request->hasFile('paleomagnetism-file')) {
            $converter = new PaleomagnetismConverter();
            
            return response()->streamDownload(function () use($converter, $request) {
                echo $converter->ExcelToJson($request->file('paleomagnetism-file'));
            }, 'paleomagnetism.json');
                
        }
        
        return back()
        ->with('status','Error');
    }
    
    public function processGeochemistryFile(Request $request)
    {
        $request->validate([
            'geochemistry-file' => 'required'
        ]);
        
        if($request->hasFile('geochemistry-file')) {
            $converter = new GeochemistryConverter();
            
            return response()->streamDownload(function () use($converter, $request) {
                echo $converter->ExcelToJson($request->file('geochemistry-file'));
            }, 'geochemistry.json');
                
        }
        
        return back()
        ->with('status','Error');
    }
    
    public function convertExcel()
    {
        return view('convert-excel');
    }
    
    public function processExcelToJson(Request $request)
    {
        $request->validate([
            'excel-file' => 'required'
        ]);
        
        if($request->hasFile('excel-file')) {
            $converter = new ExcelToJsonConverter();
            
            return response()->streamDownload(function () use($converter, $request) {
                echo $converter->ExcelToJson($request->file('excel-file'));
            }, 'converted.json');
                
        }
        
        return back()
        ->with('status','Error');
    }
    
    public function filterTree()
    {
        return view('filter-tree');
    }
    
    public function filterTreeDownload()
    {
        $exporter = new FilterTreeExport();
                
        return response()->streamDownload(function () use($exporter) {
            echo $exporter->export();
        }, 'test.json');
    }
    
    public function viewUnmatchedKeywords()
    {
        $client = new \GuzzleHttp\Client();
        
        $searchRequest = new PackageSearch();
        $searchRequest->rows = 1000;
        $searchRequest->query = 'type: data-publication';
        
        try {
            $response = $client->request($searchRequest->method, $searchRequest->endPoint, $searchRequest->getAsQueryArray());
        } catch (\Exception $e) {
            
        }
        
        $content = json_decode($response->getBody(), true);
        $results = $content['result']['results'];                
                                
        $keywords = [];
        foreach ($results as $result) {
            if(count($result['tags']) > 0) {
                foreach ($result['tags'] as $tag) {
                    if(!array_key_exists($tag['name'], $keywords)) {
                        $keywords[$tag['name']] = 1;
                    } else {
                        $keywords[$tag['name']] = $keywords[$tag['name']] + 1;
                    }
                }
            }
        }
        
        uasort($keywords, function($a, $b) {
            return $b - $a;
        });
        
        return view('unmatched-keywords', ['keywords' => $keywords]);
    }
    
    public function downloadUnmatchedKeywords()
    {
        return Excel::download(new UnmatchedKeywordsExport(), 'unmatched-keywords.xlsx');
    }
    
    public function abstractMatching(Request $request)
    {
        $client = new \GuzzleHttp\Client();
        
        $OrganizationListrequest = new OrganizationList();
        
        try {
            $response = $client->request($OrganizationListrequest->method,
                $OrganizationListrequest->endPoint,
                $OrganizationListrequest->getPayloadAsArray());
        } catch (\Exception $e) {
            dd($e->getMessage());
        }
        
        $organizationListResponse = new OrganizationListResponse(json_decode($response->getBody(), true), $response->getStatusCode());
        $organizations = $organizationListResponse->getOrganizations();
        
        $filteredOrganizations = [];        
        
        foreach ($organizations as $organization) {
            if($organization['name'] !== 'epos-multi-scale-laboratories-thematic-core-service')
            {
                $filteredOrganizations[] = $organization;
            }
        }
        
        $data = [];
        $selected = '';
        
        if ($request->has('datasetSource')) {
            $datasetSource = $request->query('datasetSource');
            $selected = $datasetSource;
            
            $searchRequest = new PackageSearch();
            $searchRequest->rows = 10;
            $searchRequest->query = 'type: data-publication';
            $searchRequest->filterQuery =  'owner_org:' . $datasetSource;
            
            try {
                $response = $client->request($searchRequest->method, $searchRequest->endPoint, $searchRequest->getAsQueryArray());
            } catch (\Exception $e) {
                
            }
            
            $content = json_decode($response->getBody(), true);
            $results = $content['result']['results'];          
            
            $keywordHelper = new KeywordHelper();
                    
            foreach ($results as $result) {
                $item = [];
                $item['identifier'] = $result['msl_doi'];
                $item['title'] = $result['title'];
                $item['abstract'] = $result['notes'];
                $item['keywords'] = [];
                $keywords = $keywordHelper->extractFromText($item['abstract'] . ' ' . $item['title']);
                
                foreach ($keywords as $keyword) {
                    $item['keywords'][] = $keyword->getFullPath('>', true);
                }
                
                $data[] = $item;
            }
        }
                                
        
        return view('abstract-matching', ['data' => $data, 'organizations' => $filteredOrganizations, 'selected' => $selected]);
    }
    
    public function abstractMatchingDownload($dataRepo) 
    {
        return Excel::download(new AbstractMatchingExport($dataRepo), 'abstract-matching.xlsx');
    }
    
    public function viewStats()
    {
        $client = new \GuzzleHttp\Client();
        
        $searchRequest = new PackageSearch();
        $searchRequest->rows = 0;
        //$searchRequest->query = 'msl_subdomain:"rock and melt physics"';
        //$searchRequest->filterQuery = 'type: data-publication&owner_org:c59f7519-ca52-4c28-a7ce-315da596f686';
        $searchRequest->addFilterQuery('type: data-publication');
        $searchRequest->addFilterQuery('msl_subdomain:"rock and melt physics"');
        //$searchRequest->filterQuery = 'type: data-publication';
        //$searchRequest->facetField = 'msl_subdomains';
        
        //dd($searchRequest->getAsQueryArray());
        
        try {
            $response = $client->request($searchRequest->method, $searchRequest->endPoint, $searchRequest->getAsQueryArray());
        } catch (\Exception $e) {
            dd($e);
        }
        
        $content = json_decode($response->getBody(), true);
        $results = $content['result']['results'];
        
        dd($content['result']);
        
        return view('stats');
    }
  
}
