<?php
namespace ROR;

function numberToRoman($num) {
    $n = intval($num);
    $result = '';
    $lookup = array('M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400,'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40,'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1);
    foreach ($lookup as $roman => $value) {
            $matches = intval($n / $value);
            $result .= str_repeat($roman, $matches);
            $n = $n % $value;
    }
    return $result;
}

/**
 * @param string $location NULL if Legion doesn't exist
 * $location is equal to the SenatorID of their commander 
 */
class Legion
{
    public $name, $veteran , $loyalty , $location ;
    
    public function __construct() {
    }
        
    public function create ($nb) {
        $romanName = numberToRoman($nb) ;
        $this->name = ( (strlen($romanName)>0) ? $romanName : NULL) ;
        $this->veteran = FALSE ;
        $this->loyalty = NULL ;
        /* 
         * Location can be :
         * - NULL : The legion doesn't exist
         * - Rome : The legion is in Rome
         * - released : The legion has been released by its commander, so the HRAO has a chance to pay its maintenance
         * - <SenatorID> : The legion is commanded by this Senator
         * - <card ID> : THe legion is in garrison in the province with this id
         */
        $this->location = NULL ;
    }
    
    public function canBeRecruited() {
        return ($this->location == NULL) ;
    }

    public function canBeDisbanded() {
        return ($this->location == 'Rome' || $this->location == 'released') ;
    }
    
    public function recruit() {
        $this->location = 'Rome' ;
        $this->veteran = FALSE ;
        $this->loyalty = NULL ;
    }

    public function disband() {
        $this->location = NULL ;
        $this->veteran = FALSE ;
        $this->loyalty = NULL ;
    }

}