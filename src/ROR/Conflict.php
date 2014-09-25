<?php
namespace ROR;

/**
 * string $causes : a list of effects caused by the war, separated by ','
 */
class Conflict extends Card {
    	public $matches ;
        public $nbOfMatch ;
        public $description ;
        public $active ;
        public $causes ;
        public $attacks ;
        public $revolt ;
        public $creates ;
        public $land ;
        public $support ;
        public $fleet ;
        public $disaster ;
        public $standoff ;
        public $spoils ;
        /*
        * Leaders is a deck of cards with the enemy leaders linked to the war card
        * For example :
        * - Hannibal and Hasdrubal in the "leaders" deck of the second Punic War
        */
        public $leaders ;

	public function create ($data) {
                // Inherited from Card    
		$this->id = (int)$data[0] ;
                $this->name = ( is_string($data[1]) ? $data[1] : null) ;
                $this->type = ( ($data[2]=='Conflict') ?  $data[2] : null );
                // Unique to Conflicts
                $this->matches = ( is_string($data[3]) ? $data[3] : null) ;
                $this->nbOfMatch = (int)$data[4] ;
                $this->description = ( is_string($data[5]) ? $data[5] : null) ;
                $this->active = (bool)$data[6] ;
                $this->causes = ( is_string($data[7]) ? $data[7] : null) ;
                $this->attacks = ( is_string($data[8]) ? $data[8] : null) ;
                $this->revolt = ( is_string($data[9]) ? $data[9] : null) ;
                $this->creates = ( is_string($data[10]) ? $data[10] : null) ;
                $this->land = (int)$data[11] ;
                $this->support = (int)$data[12] ;
                $this->fleet = (int)$data[13] ;
                $this->disaster = $data[14];
                $this->standoff = $data[15];
                $this->spoils = (int)$data[16] ;
                $this->leaders = new Deck() ;
	}
}