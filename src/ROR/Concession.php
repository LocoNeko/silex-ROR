<?php
namespace ROR;

/**
 * Although this is actually a Faction card, this class exists as a convenience
 * Warning : the type is 'Concession' NOT 'Faction'
 * @property int $income The concession income, can be 0 for special cases (armaments & ship building)
 * @property string $special If the concession has special ways of earning income (like land commissioner or grain)
 * values can be 'legions' , 'fleets' , 'drought' , 'land bill'
 * @property bool $corrupt whether or not the concession has generated revenue for its controlling senator
 * @property bool $flipped whether or not assigning the concession has already been proposed and rejected during the Senate phase
 */

class Concession extends Card {
        public $income ;
        public $special ;
        public $corrupt ;
        public $flipped ;

	public function create ($data) {
            // Inherited from Card
            $this->id = (int)$data[0] ;
            $this->name = ( is_string($data[1]) ? $data[1] : null) ;
            $this->type = ( ($data[2]=='Faction') ?  'Concession' : null );
            // Unique to Concessions
            $this->income = (int)$data[4];
            $this->special = ($data[5]=='' ? NULL : $data[5]);
            $this->corrupt = false ;
            $this->flipped = false ;
	}
	
}
