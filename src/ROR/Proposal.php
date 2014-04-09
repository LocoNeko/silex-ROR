<?php
namespace ROR;

class Proposal {
    // Mandatory proposal types are matched with game->subPhase, as the subPhase can be set to the type by some functions. Optional proposal types are matched to the 'Other business' subPhase
    public static $VALID_PROPOSAL_TYPES = array ('Consuls' , 'Pontifex Maximus' , 'Dictator' , 'Censor' , 'Prosecutions' , 'Governors' , 'Concessions' , 'Land Bills' , 'Forces' , 'Garrison' , 'Deploy' , 'Recall Proconsul' , 'Recall Pontifex' , 'Priests' , 'Consul for life' , 'Assassin prosecution' , 'Minor');
    public static $DEFAULT_PROPOSAL_DESCRIPTION = array('Consuls Election' , 'Pontifex Maximus Election' , 'Dictator Election' , 'Censor Election' , 'Prosecutions' , 'Governorships' , 'Assignment of Concessions' , 'Passage & Repeal of Land Bills' , 'Raising & Disbanding Forces' , 'Assignment & Recall of Legions to a Garrison' , 'Assignment & Recall of Legions to fight a Conflict' , 'Recall Proconsul' , 'Recall Pontifex' , 'Appointment of Priests' , 'Election of Consul for life' , 'Special prosecution for assassin' , '') ;
    
    public $type ;
    public $description ;
    public $proposedBy;
    /*
     * An array in the form :
     * ('senatorID' , 'party' , 'ballot' , 'votes')
     * - SenatorID is self-explanatory
     * - party is a user_id
     * - ballot is : -1 (against) , 0 (abstain) , 1 (for)
     * - votes is the number of votes the senator has for this specific type of vote (consul for life adds his INF to the vote, priests have more votes on military questions, etc...)
     * 0 votes means no right to vote (e.g. not in Rome)
     */
    public $voting = array() ;
    /*
     * An array of user_id, which holds the voting order 
     */
    public $votingOrder = array() ;
    /**
     * The parameters array is validated by the type of proposal (e.g. 'Consuls' expects the senatorIDs of 2 Senators present in Rome)
     * @var type 
     */
    public $parameters = array() ;
    /**
     *
     * @var string TRUE|FALSE|NULL (default : not yet voted on) 
     */
    public $outcome = NULL ;
    
    /**
     * Returns TRUE if successfully initiated or a message array with an error otherwise.
     * @param string $type A valid proposal type, as in self::$VALID_PROPOSAL_TYPES
     * @param string $description A proposal's description
     * @param array $parties an array of the parties in this game
     * @param array $parameters an array of parameters
     * @return type
     */
    public function init ($type , $proposedBy , $description , $parties , $parameters , $votingOrder) {
        
        //type
        $key = array_search($type, self::$VALID_PROPOSAL_TYPES) ;
        if ($key===FALSE) {
            return array(_('Error with proposal type.') , 'error') ;
        }
        $this->type = $type ;
        
        // Description
        if ($type=='Minor') {
            if (is_string($description)) {
                $this->description = $description ;
            } else {
                return array(_('Minor proposals must have a valid description.') , 'error') ;
            }
        } else {
            
            // Default descriptions
            $this->description = self::$DEFAULT_PROPOSAL_DESCRIPTION[$key] ;
        }

        // Initialise voting array
        foreach ($parties as $party) {
            foreach ($party->senators->cards as $senator) {
                // Always 0 for senators not in Rome
                $thisSenatorsVotes = ($senator->inRome() ? $senator->ORA + $senator->knights : 0) ;
                // TO DO : compute the votes the senator has for this specific type of proposal, as some senators earn more votes for certain proposals (consul for life, war, etc)
                $this->voting[] = array ('senatorID' => $senator->senatorID , 'name' => $senator->name , 'user_id' => $party->user_id , 'ballot' => NULL  , 'votes' => $thisSenatorsVotes , 'talents' => 0) ;
            }
        }
        
        // Set voting order, outcome, proposedBy and parameters
        $this->votingOrder = $votingOrder ;
        $this->outcome = NULL ;
        $this->proposedBy = $proposedBy ;

        // Check number of parameters. FALSE means a variable number
        $nbParameters = $this->nbOfParameters() ;
        if ($nbParameters!==FALSE && $nbParameters['given']!=count($parameters)) {
            return array(sprintf(_('Received %d parameters, expected %d.') , count($parameters) , $nbParameters) , 'error') ;
        }
        $this->parameters = $parameters ;

        // Set all remaining parameters to NULL
        if ($nbParameters!==FALSE) {
            for ($i=$nbParameters['given'];$i<$nbParameters['total'];$i++) {
                $this->parameters[$i] = NULL ;
            }
        }

        return TRUE;
    }
    
    /**
     * Returns TRUE if everything that needs to be done with this proposal has been dealt with. Which means either:
     * - The proposal has been rejected
     * - The proposal has been adopted and all parameters are known
     * @return boolean
     */
    public function resolved() {
        $result = TRUE ;
        if ($this->outcome===FALSE) {
            return TRUE ;
        }
        foreach ($this->parameters as $parameter) {
            if ($parameter===NULL) {
                $result = FALSE ;
            }
        }
        return $result ;
    }
    
    /**
     * Returns the number of parameters that must be passed to the init() function, and the total number of parameters
     * @return array ('given' => X , 'total' => Y)
     */
    public function nbOfParameters() {
        switch($this->type) {
            case 'Prosecutions' :
                $result = array ('given' => 3 , 'total' => 5) ;
                break ;
            case 'Consuls' :
                $result = array ('given' => 2 , 'total' => 4);
                break ;
            case 'Governors' :
                $result = array ('given' => 2 , 'total' => 2) ;
                break ;
            case 'Dictator' :
            case 'Censor' :
                $result = array ('given' => 1 , 'total' => 1) ;
                break ;
            case 'Land Bills' :
                $result = array ('given' => 3 , 'total' => 5) ;
                break ;
            case 'Assassin prosecution' :
                $result = array ('given' => 1 , 'total' => 1) ;
                break ;
            default :
                $result = FALSE ;
                break ;
        }
        return $result ;
    }
    
    /**
     * Returns an array of validation rules to use to validate a proposal of this type<br>
     * The array elements are arrays : ('rule name' , index of the parameter to be checked )
     * @return array
     */
    public static function validationRules($type) {
        $result = array() ;
        switch($type) {
            case 'Consuls' :
                $result[0] = array('pair' , NULL);
                $result[1] = array('inRome' , 0);
                $result[2] = array('inRome' , 1);
                $result[3] = array('office' , 0);
                $result[4] = array('office' , 1);
                break ;
            case 'Censor' :
                $result[0] = array('inRome' , 0);
                $result[1] = array('office' , 0);
                $result[2] = array('censorRejected' , 0);
                break ;
            case 'Prosecutions' :
                $result[0] = array('inRome' , 0) ;
                $result[1] = array('inRome' , 2) ;
                $result[2] = array('cantProsecuteSelf' , NULL) ;
                $result[3] = array('censorCantBeProsecutor' , NULL) ;
                $result[4] = array('prosecutionRejected' , NULL) ;
                break ;
            case 'Governors' :
                $result[0] = array('possibleGovernors',NULL) ;
                $result[1] = array('possibleProvinces',NULL) ;
                break ;
        }
        return $result ;
    }

}