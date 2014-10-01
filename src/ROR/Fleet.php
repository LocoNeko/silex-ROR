<?php
namespace ROR;

/**
 * @param string $Location NULL if Fleet doesn't exist
 */

class Fleet
{
    public $name, $location ;
    
    public function __construct() {
    }
        
    public function create ($nb) {
        $romanName = numberToRoman($nb) ;
        $this->name = ( (strlen($romanName)>0) ? $romanName : NULL) ;
        $this->location = NULL ;
    }

    public function canBeRecruited() {
        return ($this->location == NULL) ;
    }

    public function canBeDisbanded() {
        return ($this->location == 'Rome') ;
    }
        
}