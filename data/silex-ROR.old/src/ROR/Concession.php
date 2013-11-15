<?php
namespace ROR;
/*
 * Although this is actually a Faction card, this class exists as a convenience
 * Warning : the type is 'Concession' NOT 'Faction'
 */

class Concession extends Card {
        public $income ;
        public $corrupt ;
        public $flipped;

	public function create ($data) {
            // Inherited from Card
            $this->id = (int)$data[0] ;
            $this->name = ( is_string($data[1]) ? $data[1] : null) ;
            $this->type = ( ($data[2]=='Faction') ?  'Concession' : null );
            // Unique to Concessions
            $this->income = (int)$data[4];
            $this->corrupt = false ;
            $this->flipped = false ;
	}
	
}