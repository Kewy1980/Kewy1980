<?php

namespace App\Response\Elements;

class Author
{
    public $authorName = "";

    public $authorIdentifier = "";

    public $authorAffiliation = [];

    public function __construct($data) {
        if(isset($data['msl_author_name'])) {
            $this->authorName = $data['msl_author_name'];
        }        

        if(isset($data['msl_author_identifier'])) {
            $this->authorIdentifier = $data['msl_author_identifier'];
        }

        if(isset($data['msl_author_affiliation'])) {
            $this->authorAffiliation = $data['msl_author_affiliation'];
        }
    }

}
