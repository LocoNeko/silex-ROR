<?php
namespace ROR;

class Province extends Card {
	public $mandate ;
        public $developed ;
        public $doneThisTurn ;
        public $governor ;
        public $income ;

	public function create ($data) {
                // Inherited from Card    
		$this->id = (int)$data[0] ;
                $this->name = ( is_string($data[1]) ? $data[1] : null) ;
                $this->type = ( ($data[2]=='Province') ?  $data[2] : null );
                // Unique to Provinces
		$this->mandate = 0 ;
		$this->developed = FALSE ;
		$this->doneThisTurn = FALSE ;
		$this->income['undeveloped']['senator']	['variable'] = (int)$data[3] ;
		$this->income['undeveloped']['senator']	['fixed']    = (int)$data[4] ;
		$this->income['undeveloped']['rome']	['variable'] = (int)$data[5] ;
		$this->income['undeveloped']['rome']	['fixed']    = (int)$data[6] ;
		$this->income['developed']  ['senator']	['variable'] = (int)$data[7] ;
		$this->income['developed']  ['senator']	['fixed']    = (int)$data[8] ;
		$this->income['developed']  ['rome']	['variable'] = (int)$data[9] ;
		$this->income['developed']  ['rome']	['fixed']    = (int)$data[10] ;
		$this->governor = null ;
	}
        
        public function rollRevenues($modifier) {
            $result = Array() ;
            $status = ($this->developed) ? 'developed' : 'undeveloped' ;
            $result['rome'] = $this->income[$status]['rome']['variable']*mt_rand(1,6) + $this->income[$status]['rome']['fixed'] + $modifier;
            $result['senator'] = $this->income[$status]['senator']['variable']*mt_rand(1,6) + $this->income[$status]['senator']['fixed'] + $modifier;
            return $result ;
        }
}