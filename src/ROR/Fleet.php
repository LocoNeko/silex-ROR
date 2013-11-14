<?php
namespace ROR;

class Fleet
{
    public static $VALID_LOCATIONS = array('nonexistent', 'Rome');

    public $name, $location ;
    
    public function __construct() {
    }
        
    public function create ($nb) {
        $romanName = numberToRoman($nb) ;
        $this->name = ( (strlen($romanName)>0) ? $romanName : NULL) ;
        $this->location = 'nonexistent' ;
    }
        
}