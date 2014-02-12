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
        $this->location = NULL ;
    }
        
}