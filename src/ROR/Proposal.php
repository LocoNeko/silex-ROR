<?php
namespace ROR;

class Proposal {
    
    public static $VALID_PROPOSAL_TYPES = array ('Consuls' , 'Pontifex Maximus' , 'Dictator' , 'Censor' , 'Prosecutions' , 'Governors' , 'Concessions' , 'Land Bills' , 'Forces' , 'Garrison' , 'Deploy' , 'Recall Proconsul' , 'Recall Pontifex' , 'Priests' , 'Consul for life' , 'Minor');
    public static $DEFAULT_PROPOSAL_DESCRIPTION = array('Consuls Election' , 'Pontifex Maximus Election' , 'Dictator Election' , 'Censor Election' , 'Prosecutions' , 'Governorships' , 'Assignment of Concessions' , 'Passage & Repeal of Land Bills' , 'Raising & Disbanding Forces' , 'Assignment & Recall of Legions to a Garrison' , 'Assignment & Recall of Legions to fight a Conflict' , 'Recall Proconsul' , 'Recall Pontifex' , 'Appointment of Priests' , 'Election of Consul for life' , '') ;
    
    public $type ;
    public $description ;
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
    /**
     * The parameters array is validated by the type of proposal (e.g. 'Consuls' expects the senatorIDs of 2 Senators present in Rome)
     * @var type 
     */
    public $parameters = array() ;
    /**
     *
     * @var string 'Adopted'|'Rejected'|NULL (default : not yet voted on) 
     */
    public $outcome = NULL ;
    
    /**
     * 
     * @param string $type A valid proposal type, as in self::$VALID_PROPOSAL_TYPES
     * @param string $description A proposal's description
     * @param array $parties an array of the parties in this game
     * @param array $parameters an array of parameters
     * @return type
     */
    public function init ($type , $description , $parties , $parameters) {
        $key = array_search($type, self::$VALID_PROPOSAL_TYPES) ;
        if ($key===FALSE) {
            return array('Error with proposal type.' , 'error') ;
        }
        if ($type=='' && !is_string($description)) {
            return array('Minor proposal must have a valid description.' , 'error') ;
        }
        $this->type = $type ;
        // Default descriptions
        $this->description = self::$DEFAULT_PROPOSAL_DESCRIPTION[$key] ;
        /*
         *  TO DO : validate the $parameters array.
         * This will be a painful process !
         * Note : Parameters may change after the vote, when proposals require Senators to agree upon themselves on who does what, or prosecutors to be picked, or Master of Horse to be appointed
         */
        // Initialise voting array
        foreach ($parties as $party) {
            foreach ($party->senators->card as $senator) {
                // Always 0 for senators not in Rome
                $thisSenatorsVotes = ($senator->inRome ? $senator->ORA + $senator->knights : 0) ;
                // TO DO : compute the votes the senator has for this specific type of proposal, as some senators earn more votes for certain proposals (consul for life, war, etc)
                $this->voting[] = array ('senatorID' => $senator->senatorID , 'party' => $party->user_id , 'ballot' => NULL  , 'votes' => $thisSenatorsVotes) ;
            }
        }
        $this->outcome = NULL ;
    }
    
    /**
     * Casts the vote of a single Senator or a whole party
     * 
     * @param string $type 'Senator' or 'Party', so this function can be used to have the whole party vote or just one Senator
     * @param string $id user_id in case of a Party, senatorID in case of a Senator
     * @param int $ballot Against : -1 , For : 1 , Abstain : 0 , Default : NULL (hasn't voted yet)
     * @return array messages
     */
    public function castVote ($type , $id , $ballot) {
        $ballot = (int)$ballot ;
        if ($ballot<-1 || $ballot>1) {
            return array('Error with ballot, it should be for(1), against(-1) or abstain(0), not "'.$ballot.'"' , 'error');
        }
        if ($type=='Senator') {
            foreach ($this->voting as $key => $vote) {
                if ($vote['SenatorID']==$id && $vote['ballot']===NULL) {
                    $this->voting['key']['ballot'] = $ballot*$vote['votes'];
                }
            }
        } elseif ($type=='Party') {
            foreach ($this->voting as $key => $vote) {
                if ($vote['party']==$id && $vote['ballot']===NULL) {
                    $this->voting['key']['ballot'] = $ballot*$vote['votes'];
                }
            }
        } else {
            return array('Error with type of voting, only Senators or Parties can vote, not "'.$type.'"' , 'error');
        }
    }
}