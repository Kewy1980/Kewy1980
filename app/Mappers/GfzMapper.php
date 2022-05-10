<?php
namespace App\Mappers;

use App\Models\SourceDataset;
use App\Datasets\RockPhysicsDataset;
use App\Datasets\AnalogueModelingDataset;
use App\Datasets\PaleoMagneticDataset;
use App\Models\MappingLog;
use App\Ckan\Request\PackageSearch;
use App\Ckan\Response\PackageSearchResponse;
use App\Mappers\Helpers\DataciteCitationHelper;
use App\Models\MaterialKeyword;
use App\Models\ApparatusKeyword;
use App\Models\AncillaryEquipmentKeyword;
use App\Models\PoreFluidKeyword;
use App\Models\MeasuredPropertyKeyword;
use App\Models\InferredDeformationBehaviorKeyword;
use App\Datasets\BaseDataset;
use App\Mappers\Helpers\KeywordHelper;

class GfzMapper
{
    protected $client;
    
    protected $dataciteHelper;
    
    protected $keywordHelper;
    
    public function __construct()
    {
        $this->client = new \GuzzleHttp\Client();
        $this->dataciteHelper = new DataciteCitationHelper();
        $this->keywordHelper = new KeywordHelper();
    }
    
    private function createDatasetNameFromDoi($doiString) 
    {        
        return md5($doiString);
    }
    
    private function getDatasetType($xml, $sourceDataset) {
        $result = $xml->xpath("//*/gmd:MD_Metadata/gmd:identificationInfo/gmd:MD_DataIdentification/gmd:descriptiveKeywords/gmd:MD_Keywords/gmd:keyword/gco:CharacterString[(./node()='analogue models of geologic processes') or (./node()='rock and melt physical properties') or (./node()='paleomagnetic and magnetic data' )]/node()");
        
        $resultCount = count($result);
        if($resultCount == 0) {
            $sourceDataset->status = 'error';
            $sourceDataset->save();
            $this->log('ERROR', 'No keyword found to match dataset type', $sourceDataset);
            throw new \Exception('No keyword found to match dataset type');
        } elseif ($resultCount == 1) {
            $typeKeyword = (string)$result[0];
            switch ($typeKeyword) {
                case 'analogue models of geologic processes':
                    return 'analogue';
                    break;
                                   
                case 'rock and melt physical properties':
                    return 'rockphysics';
                    break;
                    
                case 'paleomagnetic and magnetic data':
                    return 'paleomagnetic';
                    break;
            }
        } elseif ($resultCount > 1) {
            $sourceDataset->status = 'error';
            $sourceDataset->save();
            $this->log('ERROR', 'Multiple keywords indicating dataset found', $sourceDataset);
            throw new \Exception('Multiple dataset types matched');
        }
                       
    }
    
    private function getSubDomains($xml, $sourceDataset) {
        $xmlResults = $xml->xpath("//*/gmd:MD_Metadata/gmd:identificationInfo/gmd:MD_DataIdentification/gmd:descriptiveKeywords/gmd:MD_Keywords/gmd:keyword/gco:CharacterString[(./node()='analogue models of geologic processes') or (./node()='rock and melt physical properties') or (./node()='paleomagnetic and magnetic data' )]/node()");
        $results = [];
        
        foreach ($xmlResults as $xmlResult) {
            switch ((string)$xmlResult) {
                case 'analogue models of geologic processes':
                    $results[] = ['msl_subdomain' => 'analogue'];
                    break;
                    
                case 'rock and melt physical properties':
                    $results[] = ['msl_subdomain' => 'rock_physics'];
                    break;
                    
                case 'paleomagnetic and magnetic data':
                    $results[] = ['msl_subdomain' => 'paleomagnetic'];
                    break;
            }            
        }
             
        if(count($results) == 0) {
            $this->log('WARNING', 'No keyword found to set subdomain', $sourceDataset);
        }
        
        return $results;
    }
    
    private function log($severity, $text, $sourceDataset)
    {
        $levels = ['ERROR', 'WARNING'];
        if(in_array($severity, $levels)) {
            MappingLog::create([
                'type' => $severity,
                'message' => $text,
                'source_dataset_id' => $sourceDataset->id,
                'import_id' => $sourceDataset->source_dataset_identifier->import->id
            ]);
        } else {
            throw new \Exception('invalid log type');
        }
    }
    
    private function getLabNames()
    {
        $searchRequest = new PackageSearch();
        
        $searchRequest->rows = 1000;
        $searchRequest->query = 'type: lab';
        try {
            $response = $this->client->request($searchRequest->method, $searchRequest->endPoint, $searchRequest->getAsQueryArray());
        } catch (\Exception $e) {
            
        }
        
        $packageSearchResponse = new PackageSearchResponse(json_decode($response->getBody(), true), $response->getStatusCode());
        
        return $packageSearchResponse->getNameList();
    }
    
    private function getFTPDirectoryListing($path, $sourceDataset)
    {
        $ftpHost = "datapub.gfz-potsdam.de";
        $ftpUser = "anonymous";
        $ftpPassword = "";
        
        $ftp = ftp_connect($ftpHost);
        
        if(!$ftp) {
            $this->log('ERROR', 'Could not connect to ftphost: "' . $ftpHost . '"', $sourceDataset);
            return [];
        }
        
        if (@ftp_login($ftp, $ftpUser, $ftpPassword)) {
            $mode = ftp_pasv($ftp, true);
            
            try {
                ftp_chdir($ftp, $path);
            } catch (\Exception $e) {
                $this->log('ERROR', 'Path: "' . $path . '"could not be found on ftp server.', $sourceDataset);
                ftp_close($ftp);
                return [];
            }                        
            
            $contents = ftp_mlsd($ftp, $path);
            
            $files = [];
            if(count($contents) > 0) {
                foreach ($contents as $content) {
                    if($content['type'] == 'file') {
                        $files[] = $content;
                    }
                }
            }
            
            return $files;
            
        } else {
            $this->log('ERROR', 'Could not connect to ftphost as user: "' . $ftpUser . '"', $sourceDataset);
            return [];
        }
    }
    
    private function extractPath($ftpLink)
    {
        $parts = parse_url($ftpLink);
        
        if($parts) {
            if(isset($parts['path'])) {
                return $parts['path'];
            }
        }
        
        return '';        
    }
    
    private function extractExtension($filename)
    {
        $fileInfo = pathinfo($filename);
        if(isset($fileInfo['extension'])) {
            return $fileInfo['extension'];
        }
        
        return '';
    }
    
    private function getYear($date)
    {
        $datetime = new \DateTime($date);
        $result = $datetime->format('Y');
        
        if($result) {
            return $result;
        }
        return '';
    }
    
    private function getMonth($date)
    {
        $datetime = new \DateTime($date);
        $result = $datetime->format('m');
        
        if($result) {
            return $result;
        }
        return '';
    }
    
    private function getDay($date)
    {
        $datetime = new \DateTime($date);
        $result = $datetime->format('d');
        
        if($result) {
            return $result;
        }
        return '';
    }
    
    private function formatDate($date)
    {
        $datetime = new \DateTime($date);
        $result = $datetime->format('Y-m-d');
        
        if($result) {
            return $result;
        }
        return '';
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
    
    public function map(SourceDataset $sourceDataset)
    {
        //load xml file
        $xmlDocument = simplexml_load_string($sourceDataset->source_dataset);
        
        //dd($xmlDocument->getNamespaces(true));
        
        //declare xpath namespaces
        $xmlDocument->registerXPathNamespace('oai', 'http://www.openarchives.org/OAI/2.0/');
        $xmlDocument->registerXPathNamespace('gmd', 'http://www.isotc211.org/2005/gmd');
        $xmlDocument->registerXPathNamespace('gco', 'http://www.isotc211.org/2005/gco');
        $xmlDocument->registerXPathNamespace('xlink', 'http://www.w3.org/1999/xlink');
                                
        $dataset = new BaseDataset();
        
        // set subdomains
        $dataset->msl_subdomains = $this->getSubDomains($xmlDocument, $sourceDataset);
        
        //set owner_org
        $dataset->owner_org = $sourceDataset->source_dataset_identifier->import->importer->data_repository->ckan_name;
        
        //extract publisher
        $result = $xmlDocument->xpath("/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata/gmd:MD_Metadata/gmd:identificationInfo/gmd:MD_DataIdentification/gmd:citation/gmd:CI_Citation/gmd:citedResponsibleParty/gmd:CI_ResponsibleParty[./gmd:role/gmd:CI_RoleCode='publisher']/gmd:organisationName/gco:CharacterString/node()");
        if(isset($result[0])) {
            $dataset->msl_publisher = (string)$result[0];
        }
        
        //extract title
        $result = $xmlDocument->xpath('/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata/gmd:MD_Metadata/gmd:identificationInfo/gmd:MD_DataIdentification/gmd:citation/gmd:CI_Citation/gmd:title/gco:CharacterString/node()');
        if(isset($result[0])) {
            $dataset->title = (string)$result[0];
        }
        
        //extract name
        $result = $xmlDocument->xpath('/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata[1]/gmd:MD_Metadata/gmd:fileIdentifier/gco:CharacterString/node()');
        if(isset($result[0])) {
            $dataset->name = $this->createDatasetNameFromDoi((string)$result[0]);
        }
        
        //extract msl_pids
        $result = $xmlDocument->xpath('/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata/gmd:MD_Metadata/gmd:fileIdentifier/gco:CharacterString/node()');
        if(isset($result[0])) {
            $dataset->msl_pids[] = [
                'msl_pid' => (string)$result[0],
                'msl_identifier_type' => 'doi'
            ];
        }
        
        $result = $xmlDocument->xpath('/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata/gmd:MD_Metadata/gmd:fileIdentifier/gco:CharacterString/node()');
        if(isset($result[0])) {
            $doi = $result[0];
            if(str_contains($doi, 'doi:')) {
                $doi = str_replace('doi:', '', $doi);
            }
            
            $citationString = $this->dataciteHelper->getCitationString($doi);
            if(strlen($citationString > 0)) {
                $dataset->msl_citation = $citationString;
            }
        }
        
        //extract msl_publication_date        
        $result = $xmlDocument->xpath('/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata[1]/gmd:MD_Metadata/gmd:identificationInfo/gmd:MD_DataIdentification/gmd:citation/gmd:CI_Citation/gmd:date/gmd:CI_Date/gmd:date/gco:Date[1]/node()');
        if(isset($result[0])) {
            $dataset->msl_publication_day = $this->getDay((string)$result[0]);
            $dataset->msl_publication_month = $this->getMonth((string)$result[0]);
            $dataset->msl_publication_year = $this->getYear((string)$result[0]);                        
        }
        
        //extract authors
        $authorsResult = $xmlDocument->xpath("/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata[1]/gmd:MD_Metadata[1]/gmd:identificationInfo/gmd:MD_DataIdentification/gmd:citation/gmd:CI_Citation/gmd:citedResponsibleParty[./gmd:CI_ResponsibleParty/gmd:role/gmd:CI_RoleCode='author']");
        if(count($authorsResult) > 0) {                        
            foreach ($authorsResult as $authorResult) {                
                $author = [
                    'msl_author_name' => '',
                    'msl_author_identifier' => '',
                    'msl_author_affiliation' => ''
                ];
                
                $nameNode = $authorResult->xpath(".//gmd:CI_ResponsibleParty[./gmd:role/gmd:CI_RoleCode='author']/gmd:individualName/gco:CharacterString/node()");
                $identifierNode =  $authorResult->xpath(".//@xlink:href");
                $affiliationNode = $authorResult->xpath(".//gmd:CI_ResponsibleParty[./gmd:role/gmd:CI_RoleCode='author']/gmd:organisationName/gco:CharacterString/node()");
                if(isset($nameNode[0])) {
                    $author['msl_author_name'] = (string)$nameNode[0];
                }
                if(isset($identifierNode[0])) {
                    $author['msl_author_identifier'] = (string)$identifierNode[0];
                }
                if(isset($affiliationNode[0])) {
                    $author['msl_author_affiliation'] = (string)$affiliationNode[0];
                }
                $dataset->msl_authors[] = $author;
            }            
        }
        
        //extract references
        $referencesResult = $xmlDocument->xpath("/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata[1]/gmd:MD_Metadata/gmd:identificationInfo/gmd:MD_DataIdentification/gmd:aggregationInfo");
        if(count($referencesResult) > 0) {
            foreach ($referencesResult as $referenceResult) {
                $reference = [
                    'msl_reference_identifier' => '',
                    'msl_reference_identifier_type' => '',
                    'msl_reference_title' => '',
                    'msl_reference_type' => ''
                ];
                
                $identifierNode = $referenceResult->xpath(".//gmd:MD_AggregateInformation/gmd:aggregateDataSetIdentifier/gmd:RS_Identifier/gmd:code/gco:CharacterString/node()");
                $identifierTypeNode = $referenceResult->xpath(".//gmd:MD_AggregateInformation/gmd:aggregateDataSetIdentifier/gmd:RS_Identifier/gmd:codeSpace/gco:CharacterString/node()");
                $referenceTypeNode = $referenceResult->xpath(".//gmd:MD_AggregateInformation/gmd:associationType/gmd:DS_AssociationTypeCode/node()");
                
                if(isset($identifierNode[0])) {
                    $reference['msl_reference_identifier'] = (string)$identifierNode[0];
                }
                if(isset($identifierTypeNode[0])) {
                    $reference['msl_reference_identifier_type'] = (string)$identifierTypeNode[0];
                }
                if(isset($referenceTypeNode[0])) {
                    $reference['msl_reference_type'] = (string)$referenceTypeNode[0];
                }
                
                if($reference['msl_reference_identifier_type'] == 'DOI') {
                    if($reference['msl_reference_identifier']) {
                        $citationString = $this->dataciteHelper->getCitationString($reference['msl_reference_identifier']);
                        if(strlen($citationString) == 0) {
                            $this->log('WARNING', "datacite citation returned empty for DOI: " . $reference['msl_reference_identifier'], $sourceDataset);
                        } else {
                            $reference['msl_reference_title'] = $citationString;
                        }                                                
                    }
                }
                
                $dataset->msl_references[] = $reference;                
            }
        }
        
        //extract notes
        $result = $xmlDocument->xpath('/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata[1]/gmd:MD_Metadata/gmd:identificationInfo/gmd:MD_DataIdentification/gmd:abstract/gco:CharacterString/node()');
        if(isset($result[0])) {
            $dataset->notes = (string)$result[0];
        }
        
        //extract labs
        $labResults = $xmlDocument->xpath("/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata[1]/gmd:MD_Metadata/gmd:identificationInfo/gmd:MD_DataIdentification/gmd:citation/gmd:CI_Citation/gmd:citedResponsibleParty[./gmd:CI_ResponsibleParty/gmd:role/gmd:CI_RoleCode/node()='originator']");
        if(count($labResults) > 0) {
            foreach ($labResults as $labResult) {
                $lab = [
                    'msl_lab_name' => '',
                    'msl_lab_id' => ''
                ];
                
                $nameNode = $labResult->xpath(".//gmd:CI_ResponsibleParty/gmd:organisationName/gco:CharacterString[1]/node()");
                if(isset($nameNode[0])) {
                    $lab['msl_lab_name'] = (string)$nameNode[0];                    
                }
                $idNode = $labResult->xpath(".//@uuidref");
                if(isset($idNode[0])) {
                    $lab['msl_lab_id'] = (string)$idNode[0];
                    
                    // check if lab id is present in ckan                    
                    if(!in_array($lab['msl_lab_id'], $this->getLabNames())) {
                        $this->log('WARNING', "LabId: \"" . $lab['msl_lab_id'] . "\" not found in ckan.", $sourceDataset);
                    }                    
                } else {
                    $this->log('WARNING', "Lab with name: \"" . $lab['msl_lab_name'] . "\" has no id.", $sourceDataset);
                }
                
                $dataset->msl_laboratories[] = $lab;
            }
        }
               
        //extract tags/keywords
        $results = $xmlDocument->xpath('/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata[1]/gmd:MD_Metadata/gmd:identificationInfo/gmd:MD_DataIdentification/gmd:descriptiveKeywords/gmd:MD_Keywords/gmd:keyword/gco:CharacterString/node()');
        if(count($results) > 0) {
            
            $keywords = [];
            foreach ($results as $result) {
                $keywords[] = (string)$result[0];
            }            
            
            $dataset = $this->keywordHelper->mapKeywords($dataset, $keywords, true, '>');
        }                                       
        
        //extract spatial coordinates
        $spatialResults = $xmlDocument->xpath("/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata[1]/gmd:MD_Metadata/gmd:identificationInfo/gmd:MD_DataIdentification/gmd:extent/gmd:EX_Extent/gmd:geographicElement");
        if(count($spatialResults) > 0) {
            foreach ($spatialResults as $spatialResult) {
                $spatial = [
                    'msl_elong' => '',
                    'msl_nLat' => '',
                    'msl_sLat' => '',
                    'msl_wLong' => ''
                ];
                
                $elongNode = $spatialResult->xpath(".//gmd:EX_GeographicBoundingBox/gmd:eastBoundLongitude/gco:Decimal/node()");
                $nlatNode = $spatialResult->xpath(".//gmd:EX_GeographicBoundingBox/gmd:northBoundLatitude/gco:Decimal/node()");
                $slatNode = $spatialResult->xpath(".//gmd:EX_GeographicBoundingBox/gmd:southBoundLatitude/gco:Decimal/node()");
                $wlongNode = $spatialResult->xpath(".//gmd:EX_GeographicBoundingBox/gmd:westBoundLongitude/gco:Decimal/node()");
                
                if(isset($elongNode[0])) {
                    $spatial['msl_elong'] = (string)$elongNode[0];
                }
                if(isset($nlatNode[0])) {
                    $spatial['msl_nLat'] = (string)$nlatNode[0];
                }
                if(isset($slatNode[0])) {
                    $spatial['msl_sLat'] = (string)$slatNode[0];
                }
                if(isset($wlongNode[0])) {
                    $spatial['msl_wLong'] = (string)$wlongNode[0];
                }
                
                $dataset->msl_spatial_coordinates[] = $spatial;
            }
        }
                        
        //extract license id
        $result = $xmlDocument->xpath('/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata[1]/gmd:MD_Metadata[1]/gmd:identificationInfo[1]/gmd:MD_DataIdentification[1]/gmd:resourceConstraints[1]/gmd:MD_Constraints[1]/gmd:useLimitation[1]/gco:CharacterString[1]/node()[1]');
        if(isset($result[0])) {            
            $dataset->license_id = (string)$result[0];
        }
        
        //extract point of contact
        $contactResults = $xmlDocument->xpath("/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata[1]/gmd:MD_Metadata/gmd:identificationInfo/gmd:MD_DataIdentification/gmd:pointOfContact");
        if(count($contactResults) > 0) {
            foreach ($contactResults as $contactResult) {
                $contact = [
                    'msl_contact_name' => '',
                    'msl_contact_organisation' => '',
                    'msl_contact_electronic_address' => ''
                ];
                
                $nameNode = $contactResult->xpath(".//gmd:CI_ResponsibleParty/gmd:individualName/gco:CharacterString/node()");
                $organisationNode = $contactResult->xpath(".//gmd:CI_ResponsibleParty/gmd:organisationName/gco:CharacterString/node()");
                $electronicAddressNode = $contactResult->xpath(".//gmd:CI_ResponsibleParty/gmd:contactInfo/gmd:CI_Contact/gmd:address/gmd:CI_Address/gmd:electronicMailAddress/gco:CharacterString/node()");
                
                if(isset($nameNode[0])) {
                    $contact['msl_contact_name'] = (string)$nameNode[0];
                }
                if(isset($organisationNode[0])) {
                    $contact['msl_contact_organisation'] = (string)$organisationNode[0];
                }
                if(isset($electronicAddressNode[0])) {
                    $contact['msl_contact_electronic_address'] = (string)$electronicAddressNode[0];
                }
                
                $dataset->msl_points_of_contact[] = $contact;
            }
        }
              
        //extract source
        $result = $xmlDocument->xpath('/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata[1]/gmd:MD_Metadata[1]/gmd:distributionInfo[1]/gmd:MD_Distribution[1]/gmd:transferOptions[1]/gmd:MD_DigitalTransferOptions[1]/gmd:onLine[1]/gmd:CI_OnlineResource[1]/gmd:linkage[1]/gmd:URL[1]/node()[1]');
        if(isset($result[0])) {
            $dataset->msl_source = (string)$result[0];
        }
        
        //extract ftp information
        $result = $xmlDocument->xpath("/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata[1]/gmd:MD_Metadata/gmd:distributionInfo/gmd:MD_Distribution/gmd:transferOptions/gmd:MD_DigitalTransferOptions/gmd:onLine/gmd:CI_OnlineResource[./gmd:description/gco:CharacterString/node()='FTP Access']/gmd:linkage/gmd:URL/node()");
        if(isset($result[0])) {
            $path = $this->extractPath((string)$result[0]);
            if($path !== '') {
                $files = $this->getFTPDirectoryListing($path, $sourceDataset);
                                
                if(count($files) > 0) {
                    foreach($files as $fileResult) {
                        $file = [
                            'msl_file_name' => $fileResult['name'],
                            'msl_download_link' => (string)$result[0] . '/' . $fileResult['name'],
                            'msl_extension' => $this->extractExtension($fileResult['name']),
                            'msl_timestamp' => $fileResult['modify']
                        ];
                        
                        $dataset->msl_downloads[] = $file;
                    }
                }                               
            }
        }
                                                       
        return $dataset;
    }
}

