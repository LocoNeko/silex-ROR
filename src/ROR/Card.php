<?php
namespace ROR;

class Card
{
    public static $VALID_TYPES = array('Family', 'Statesman' , 'Concession' , 'Province' , 'War' , 'Leader' ,'Faction' ,'Era ends');

    public $id, $type, $name ;
    
    public function __construct() {
    }
        
    public function create ($data) {
        $this->id = (int)$data[0] ;
        $this->name = ( is_string($data[1]) ? $data[1] : null) ;
        $this->type = ( in_array($data[2],self::$VALID_TYPES) ? $data[2] : null ) ;
    }
    
}