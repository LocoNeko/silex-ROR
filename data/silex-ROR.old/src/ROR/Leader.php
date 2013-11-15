<?php
namespace ROR;

class Leader extends Card {
    	/*
	Causes : 0 = nothing , 1 = a random tax farmer , 2 = drought , ...
	'2+1' means : if matched with war 2, causes effect 1
	*/
        public $matches ;
        public $description ;
        public $strength ;
        public $disaster ;
        public $standoff ;
        public $ability ;
        public $causes ;

	public function create ($data) {
                // Inherited from Card    
		$this->id = (int)$data[0] ;
                $this->name = ( is_string($data[1]) ? $data[1] : null) ;
                $this->type = ( ($data[2]=='Leader') ?  $data[2] : null );
                // Unique to Leaders
                $this->matches = ( is_string($data[3]) ? $data[3] : null) ;
                $this->description = ( is_string($data[4]) ? $data[4] : null) ;
                $this->strength = (int)$data[5];
                $this->disaster = (int)$data[6] ;
                $this->standoff = (int)$data[7];
                $this->ability = ( is_string($data[8]) ? $data[8] : null) ;
                $this->causes = (string)$data[9] ;
	}
}