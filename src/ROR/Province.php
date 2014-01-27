<?php
namespace ROR;

class Province extends Card {
	public $mandate ;
        public $developed ;
        public $overrun ;
        public $income ;
        public $forces ;
        public $frontier ;
        public $governor ;

	public function create ($data) {
                // Inherited from Card    
		$this->id = (int)$data[0] ;
                $this->name = ( is_string($data[1]) ? $data[1] : null) ;
                $this->type = ( ($data[2]=='Province') ?  $data[2] : null );
                // Unique to Provinces
		$this->mandate = 0 ;
		$this->developed = FALSE ;
		$this->overrun = FALSE ;
		$this->income['undeveloped']['senator']	['variable'] = (int)$data[3] ;
		$this->income['undeveloped']['senator']	['fixed']    = (int)$data[4] ;
		$this->income['undeveloped']['rome']	['variable'] = (int)$data[5] ;
		$this->income['undeveloped']['rome']	['fixed']    = (int)$data[6] ;
		$this->income['developed']  ['senator']	['variable'] = (int)$data[7] ;
		$this->income['developed']  ['senator']	['fixed']    = (int)$data[8] ;
		$this->income['developed']  ['rome']	['variable'] = (int)$data[9] ;
		$this->income['developed']  ['rome']	['fixed']    = (int)$data[10] ;
                $this->forces['undeveloped']['land'] = (int)$data[11] ;
                $this->forces['undeveloped']['sea']  = (int)$data[12] ;
                $this->forces['developed']  ['land'] = (int)$data[13] ;
                $this->forces['developed']  ['sea']  = (int)$data[14] ;
                $this->frontier = (bool)$data[15] ;
		$this->governor = null ;
	}
        
        /**
         * Returns the Province's revenues either for Rome or the Senator (in case of Provincial spoils)<br>
         * Evil Omens do not affect the revenue roll, but the total, so the current Evil Omens event level is passed as $modifier
         * @param string $type 'rome'|'senator'
         * @param int $modifier
         * @return type
         */
        public function rollRevenues($type , $modifier) {
            $status = ($this->developed) ? 'developed' : 'undeveloped' ;
            return $this->income[$status][$type]['variable']*mt_rand(1,6) + $this->income[$status][$type]['fixed'] + $modifier;
        }
        
        /**
         * Returns the value of local land forces, based on current status
         * @return integer
         */
        public function land() {
            $status = ($this->developed) ? 'developed' : 'undeveloped' ;
            return $this->forces[$status]['land'] ;
        }
        
        /**
         * Returns the value of local sea forces, based on current status
         * @return integer
         */
        public function sea() {
            $status = ($this->developed) ? 'developed' : 'undeveloped' ;
            return $this->forces[$status]['sea'] ;
        }

}