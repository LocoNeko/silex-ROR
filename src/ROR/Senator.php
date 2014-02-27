<?php
namespace ROR;

class Senator extends Card
{
    /**
     *
     * @var bool corrupt : This is only used for Provincial spoils corruption
     * @var bool major : Whether or not this Senator held an office before the current Senate phase
     */
    public static $VALID_OFFICES = array('Dictator', 'Rome Consul' , 'Field Consul' , 'Censor' , 'Master of Horse' , 'Pontifex Maximus');

    /*
     *
    List of special abilities :
    'Punic' : no D/S result during punic wars
    'Macedonian' : no D/S result during Macedonian wars
    'HalvesLosses' : Halves the losses in a combat where he's not master of horses
    'Tribune' : 1 free tribune per turn
    'Law6' : +6 votes for laws
    */

    public $senatorID ;
    public $baseMIL ;
    public $baseORA ;
    public $baseLOY ;
    public $baseINF ;
    public $MIL ;
    public $ORA ;
    public $LOY ;
    public $INF ;
    public $specialLOY ;
    public $specialAbility ;
    public $knights ;
    public $treasury ;
    public $POP ;
    public $office ;
    public $priorConsul ;
    public $corrupt ;
    public $controls ;
    public $major ;
    public $rebel ;
    public $captive ;
    public $freeTribune ;
    public $returningGovernor ;

    /*
     * Creates a Senator card from an array
     * 
     * @param data(Array) See csv scenario files for data format
     */
    public function create ($data) {
            // Inherited from Card
            $this->id = (int)$data[0] ;
            $this->name = ( is_string($data[1]) ? $data[1] : NULL) ;
            $this->type = ( ($data[2]=='Family' || $data[2]=='Statesman') ?  $data[2] : NULL ); // "Family" or "Statesman"
            $this->senatorID = (string)( preg_match('/\d?\d\w?/i',$data[3]) ? $data[3] : NULL) ;
            $this->baseMIL = (int)($data[4]) ;
            $this->baseORA = (int)($data[5]) ;
            $this->baseLOY = (int)($data[6]) ;
            $this->baseINF = (int)($data[7]) ;
            $this->MIL = $this->baseMIL ;
            $this->ORA = $this->baseORA ;
            $this->LOY = $this->baseLOY ;
            $this->INF = $this->baseINF ;
            $this->specialLOY = ( is_string($data[8]) ? $data[8] : NULL) ; /* A list of senatorID with + or - separated by ,. +X means : only loyal if X exists and is in the same party, -X : means loyalty 0 if in the same party as X*/
            $this->specialAbility = ( is_string($data[9]) ? $data[9] : NULL) ; ; /* A list of abilities separated by ,  */
            $this->knights = 0 ;
            $this->treasury = 0 ;
            $this->POP = 0 ;
            $this->office = NULL ;
            $this->priorConsul = FALSE ;
            $this->corrupt = FALSE ;
            $this->controls = new Deck ; // cards the senator controls.
            $this->controls->name = sprintf(_('%s \'cards') , $this->name) ;
            $this->major = FALSE ;
            $this->freeTribune = 0 ;
            $this->rebel = FALSE ;
            $this->captive = FALSE ;
            $this->returningGovernor = FALSE ;
    }

    public function resetSenator() {
            $this->MIL = $this->baseMIL ;
            $this->ORA = $this->baseORA ;
            $this->LOY = $this->baseLOY ;
            $this->INF = $this->baseINF ;
            $this->knights = 0 ;
            $this->treasury = 0 ;
            $this->POP = 0 ;
            $this->office = NULL ;
            $this->priorConsul = false ;		
            $this->corrupt = FALSE ;
            $this->major = FALSE ;
            $this->freeTribune = 0 ;
            $this->rebel = FALSE ;
            $this->captive = FALSE ;
            $this->returningGovernor = FALSE ;
    }
    
    public function statesmanFamily () {
        if ($this->type != 'Statesman') {
            return FALSE ;
        } else {
            return str_replace ( Array('A' , 'B' , 'C') , Array('' , '' , '') , $this->senatorID);
        }
    }
    
    public function changePOP ($value) {
        $this->POP+=$value ;
        if ( $this->POP < -9 ) { $this->POP = -9 ; }
        if ( $this->POP > 9  ) { $this->POP = 9  ; }
    }

    public function changeINF ($value) {
        $this->INF+=$value ;
        if ( $this->INF < 0 ) { $this->INF = 0 ; }
    }
    
    public function inRome() {
        $result = TRUE ;
        // Captive
        if ($this->captive!==FALSE) {
            $result = FALSE ;
        }
        // Governor
        // TO DO : What about legates ?
        foreach ($this->controls->cards as $card) {
            if($card->type=='Province') {
                $result = FALSE ;
            }
        }
        // TO DO : commander of an army & Proconsul
        return $result ;
    }
    
}