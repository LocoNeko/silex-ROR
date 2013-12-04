<?php
namespace ROR;

/** 
 * Game Variables :
 * --- GLOBAL ---
 * $id (int) : game id
 * $name (string) : game name
 * $turn (int)
 * $phase (string) : the current turn phase, can have any value defined in $VALID_PHASES
 * $subPhase (string) : the current sub phase of the current phase
 * $initiative (int) : Current initiative from 1 to 6
 * $scenario (string), can have any value defined in $VALID_SCENARIOS
 * $unrest (int) : current unrest level
 * $treasury (int) : current treasury
 * $nbPlayers (int) : number of human players from 1 to 6
 * --- FORUM PHASE ---
 * $currentBidder (user_id) : The user_id of the party which turn it currently is to bid
 * $persuasionTarget (Senator) : The senator being persuaded
 * --- THE PARTIES ---
 * $party array (Party) /!\ key : user_id ; value : party_name
 * --- THE DECKS ---
 * $earlyRepublic
 * $middleRepublic
 * $lateRepublic
 * $discard
 * $unplayedProvinces
 * $inactiveWars
 * $activeWars
 * $imminentWars
 * $unprosecutedWars
 * $forum
 * $curia
 * --- LAND BILLS, EVENTS, LEGIONS & FLEETS ---
 * $landBill array (int)=>(int) : landBill[X]=Y means there is Y land bills of type X
 * $events array (string => int) : array of events in the form 'Name of event' => level, 0 means event is not in play
 * $legion array of legion objects
 * $fleet array of fleet objects
 * --- SENATE ---
 */

class Game 
{
    /*
     * Some default values and validators
     */
    public static $VALID_PHASES = array('Setup','Mortality','Revenue','Forum','Population','Senate','Combat','Revolution');
    public static $VALID_ACTIONS = array('Bid','RollEvent','Persuasion','Knights','ChangeLeader','SponsorGames','curia');
    public static $DEFAULT_PARTY_NAMES = array ('Imperials' , 'Plutocrats' , 'Conservatives' , 'Populists' , 'Romulians' , 'Remians');
    public static $VALID_SCENARIOS = array('EarlyRepublic','MiddleRepublic','LateRepublic');

    private $id ;
    public $name ;
    public $turn , $phase , $subPhase , $initiative ;
    public $scenario , $unrest , $treasury , $nbPlayers ;
    public $currentBidder , $persuasionTarget ;
    public $party ;
    public $earlyRepublic , $middleRepublic , $lateRepublic , $discard , $unplayedProvinces , $inactiveWars , $activeWars , $imminentWars , $unprosecutedWars , $forum , $curia ;
    public $landBill ,$events , $eventPool , $eventTable , $legion , $fleet ;
    
    public function get_id() {
            return $this->id;
    }

     /************************************************************
     * General functions
     ************************************************************/

    /**
     * create
     * 
     * @param ($name) Game name
     * @param ($scenario) Scenario name
     * @param ($partyNames) array of 'user_id'=>'party name' the order is the standard order of play
     * @param ($userNames) array of 'user_id'=>'user name'
     * @return mixed FALSE (failure) OR $messages array (success)
     */
    public function create($name , $scenario , $partyNames , $userNames) {
        $messages = array () ;
        $this->id = substr(md5(uniqid(rand())),0,8) ;
        $this->name = $name;
        $this->turn = 1 ;
        $this->phase = 'Setup' ;
        $this->subPhase = 'PickLeaders' ;
        $this->initiative = 0 ;
        if (in_array($scenario, self::$VALID_SCENARIOS)) {
            $this->scenario = $scenario ;
        } else {return FALSE;}
        $this->unrest = 0 ;
        $this->treasury = 100 ;
        $this->nbPlayers = count($partyNames);
        if ( ($this->nbPlayers < 3) || ($this->nbPlayers > 6) ) {
            return FALSE;
        }
        $this->currentBidder = NULL;
        $this->persuasionTarget = NULL;
        $this->earlyRepublic = new Deck ;
        $this->earlyRepublic->createFromFile ($scenario) ;
        $this->middleRepublic = new Deck ;
        $this->lateRepublic = new Deck ;
        $this->discard = new Deck ;
        $this->unplayedProvinces = new Deck ;
        $this->unplayedProvinces->createFromFile ('Provinces') ;
        $this->inactiveWars = new Deck ;
        $this->activeWars = new Deck ;
        $this->imminentWars = new Deck ;
        $this->unprosecutedWars = new Deck ;
        $this->forum = new Deck ;
        $this->curia = new Deck ;
        $this->landBill = array() ;
        $this->landBill[1] = 0 ;
        $this->landBill[2] = 0 ;
        $this->landBill[3] = 0 ;
        // Create eventPool
        $this->events = array() ;
        $this->eventPool = array() ;
        $this->eventTable = array() ;
        $this->createEventPool();
        $this->legion = array () ;
        $this->fleet = array () ;
        // Creating parties
        $this->firstParty = null ;
        $this->party = array () ;
        foreach ($partyNames as $key=>$value) {
            $this->party[$key] = new Party ;
            $this->party[$key]->create($value , $key , $userNames[$key]);
        }
        /*
         * handle special cards : The First Punic war & Era ends
         * Then create 4 legions in Rome, the rest of the legions and all the fleets are non-existent (Legions and Fleet objects should never be created during a game)
         */
        $this->inactiveWars->putOnTop($this->earlyRepublic->drawCardWithValue('name', '1ST PUNIC WAR'));
        array_push($messages , array('SETUP PHASE' , 'alert') ) ;
        array_push($messages , array('The First Punic war is now an inactive war') ) ;
        $this->discard->putOnTop($this->earlyRepublic->drawCardWithValue('name', 'ERA ENDS')) ;
        for ($i=1 ; $i<=25 ; $i++) {
            $this->legion[$i] = new Legion () ;
            $this->legion[$i]->create($i);
            $this->fleet[$i] = new Fleet () ;
            $this->fleet[$i]->create($i) ;
	}
        for ($i=1 ; $i<=4 ; $i++) {
            $this->legion[$i]->location = 'Rome';
        }
        array_push($messages , array('Rome starts with 4 Legions') ) ;
        /* 
         * Give initial senators to parties
         * - Create a temporary deck with all 20 families (not statemen)
         * - Shuffle the temp deck
         * - Go through all parties, give each of them 3 senators from the temp deck
         * - Put the temp deck back into the Early Republic deck
         */
        $tempDeck = new Deck;
        while ($card = $this->earlyRepublic->drawCardWithValue('type', 'Family')) {
            $tempDeck->putOnTop($card);
        }
        $tempDeck->shuffle();
        foreach ($this->party as $party) {
            for ($j=0 ; $j<3 ; $j++)
            {
                $party->senators->putOnTop($tempDeck->drawTopCard());
                array_push($messages , array($party->fullName().' receives Senator '.$party->senators->cards[0]->name) ) ;
            }
        }
        while (count($tempDeck->cards)>0) {
            $this->earlyRepublic->putOnTop($tempDeck->drawTopCard());
        }
        /*
         * Give 3 cards to each players, only keeping Faction and Stateman cards
         */
        foreach ($this->party as $key=>$party) {
            $cardsLeftToDraw = 3 ;
            while ($cardsLeftToDraw>0) {
                $this->earlyRepublic->shuffle();
                $card = $this->earlyRepublic->drawTopCard() ;
                switch ($card->type) {
                    case 'Faction' :
                    case 'Stateman' :
                    case 'Concession' :
                        $party->hand->putOnTop($card);
                        $cardsLeftToDraw--;
                        break ;
                    default :
                        $this->earlyRepublic->putOnTop($card);
                }
            }
            array_push($messages , array($party->fullName().' receives 3 cards.')) ;
        }
        /*
         * Give temporary Rome Consul office to random Senator in Rome
         */
        $senatorsInPlay = array() ;
        foreach ($this->party as $party) {
            foreach ($party->senators->cards as $senator) {
                array_push($senatorsInPlay,$senator) ;
            }
        }
        $temporaryRomeConsul = $senatorsInPlay[array_rand($senatorsInPlay)];
        $temporaryRomeConsul->office = 'Rome Consul';
        $temporaryRomeConsul->priorConsul = true ;
        $temporaryRomeConsul->INF += 5 ;
        $party = $this->getPartyOfSenator($temporaryRomeConsul);
        array_push($messages , array($temporaryRomeConsul->name.' '.$party->fullName().' becomes temporary Rome consul.','alert'));
        return $messages ;
    }
    
    /*
     * Convenience function (could be inside CreateGame)
     * The event file should have 4 columns :
     * Event number (should be VG card number) ; event name ; increased event name ; maximum level of the event of 0 if none
     * The event table file should have 3 columns :
     * event number for Early Republic ; Middle Republic ; Late Republic 
     */
    public function createEventPool() {
        $filePointer = fopen(dirname(__FILE__).'/../../data/events.csv', 'r');
        if (!$filePointer) {
            throw new Exception("Could not open the events file");
        }
        while (($data = fgetcsv($filePointer, 0, ";")) !== FALSE) {
            $this->eventPool[$data[0]] = array($data[1] , $data[2] , $data[3]);
        }
        fclose($filePointer);
        $filePointer = fopen(dirname(__FILE__).'/../../data/eventTable.csv', 'r');
        if (!$filePointer) {
            throw new Exception("Could not open the event table file");
        }
        $i=3;
        while (($data = fgetcsv($filePointer, 0, ";")) !== FALSE) {
            $this->eventTable[$i]['EarlyRepublic'] = $data[0] ;
            $this->eventTable[$i]['MiddleRepublic'] = $data[1] ;
            $this->eventTable[$i]['LateRepublic'] = $data[2] ;
            $i++;
        }
        fclose($filePointer);
    }
    
    /**
     * Number of legions (location is NOT nonexistent)
     * @return int
     */
    public function nbOfLegions() {
        $result = 0 ;
        foreach($this->legion as $legion) {
            if ($legion->location <> 'nonexistent' ) {
                $result++;
            }
        }
        return $result ;
    }
    
    /**
     * Number of fleets (location is NOT nonexistent)
     * @return int
     */
    public function nbOfFleets() {
        $result = 0 ;
        foreach($this->fleet as $fleet) {
            if ($fleet->location <> 'nonexistent' ) {
                $result++;
            }
        }
        return $result ;
    }
    
    /**
     * 
     * @param type $senator
     * @return boolean
     */
    public function getPartyOfSenator ($senator) {
        foreach ($this->party as $party) {
            foreach ($party->senators->cards as $senator2) {
                if ($senator2->senatorID==$senator->senatorID) {
                    return $party ;
                }
            }
        }
        foreach ($this->forum->cards as $card) {
            if ($card->type=='Family') {
                if ($card->senatorID == $senator->senatorID) {
                    return 'forum';
                }
            }
        }
        return FALSE ;
    }
    
    /**
     * 
     * @param type $senatorID
     * @return boolean
     */
    public function getPartyOfSenatorWithID ($senatorID) {
        foreach ($this->party as $party) {
            foreach ($party->senators->cards as $senator) {
                if ($senator->senatorID==$senatorID) {
                    return $party ;
                }
            }
        }
        foreach ($this->forum->cards as $card) {
            if ($card->type=='Family') {
                if ($card->senatorID == $senatorID) {
                    return 'forum';
                }
            }
        }
        return FALSE ;
    }
    
    /**
     * 
     * @param type $senatorID
     * @return boolean
     */
    public function getSenatorWithID ($senatorID) {
        foreach ($this->party as $party) {
            foreach ($party->senators->cards as $senator) {
                if ($senator->senatorID==$senatorID) {
                    return $senator ;
                }
            }
        }
        foreach ($this->forum->cards as $card) {
            if ($card->type=='Family') {
                if ($card->senatorID == $senatorID) {
                    return $card;
                }
            }
        }
        return FALSE ;
    }
    
    /**
     * Return the senator's loyalty, checked against LOY special
     * This includes +7 for party affiliation
     * @param type $senatorID
     * @return type
     */
    public function getSenatorActualLoyalty ($senator) {
        $result = $senator->LOY;
        $party = $this->getPartyOfSenator($senator) ;
        // +7 for party affiliation
        $result += ($party=='forum' ? 0 : 7);
        if ($senator->type=='Stateman') {
            // This Stateman has some personal enemies/friends
            if ($senator->specialLOY != NULL) {
                $list = explode(',', $senator->specialLOY) ;
                foreach ($list as $friendOrFoe) {
                    // $effect is + or -
                    $effect = substr($friendOrFoe, 0, 1) ;
                    $friendOrFoeID = substr($friendOrFoe, 1) ;
                    $friendOrFoeParty = $this->getPartyOfSenatorWithID($friendOrFoeID) ;
                    if ($effect=='-' && $friendOrFoeParty==$party) {
                        $result-=$senator->LOY;
                        break;
                    }
                    if ($effect=='+' && $friendOrFoeParty!=$party) {
                        $result-=$senator->LOY;
                        break;
                    }
                }
            }
        }
        return $result;
    }
    
    /**
     * Returns the Senator, Party, and user_id of the HRAO
     * @return array 'senator' , 'party' , 'user_id'
     */
    public function HRAO() {
        $allSenators = array ();
        foreach ($this->party as $user_id=>$party) {
            foreach ($party->senators->cards as $senator) {
                if ($senator->inRome) {
                    switch ($senator->office) {
                        case ('Dictator') :
                            return array ('senator' => $senator , 'party' => $party , 'user_id'=>$user_id) ;
                        case ('Rome Consul') :
                            return array ('senator' => $senator , 'party' => $party , 'user_id'=>$user_id) ;
                        case ('Field Consul') :
                            return array ('senator' => $senator , 'party' => $party , 'user_id'=>$user_id) ;
                        case ('Censor') :
                            return array ('senator' => $senator , 'party' => $party , 'user_id'=>$user_id) ;
                        case ('Master of Horse') :
                            return array ('senator' => $senator , 'party' => $party , 'user_id'=>$user_id) ;
                        case ('Pontifex Maximus') :
                            return array ('senator' => $senator , 'party' => $party , 'user_id'=>$user_id) ;
                        default :
                    }
                }
                /*
                 * In case the HRAO couldn't be determined through offices because no official is in Rome
                 * we will need senators ordered by INF, so we use this loop to prepare that list
                 */
                array_push($allSenators , $senator);
            }
        }
        /* If we reach this part, the HRAO couldn't be determined because there is no Official present in Rome
         * So we check highest INF, break ties with Oratory then lowest ID
         * I'm very proud of this function ! ;-)
         */
        usort ($allSenators, function($a, $b)
        {
            if (($a->INF) != ($b->INF)) {
                return (($a->INF) < ($b->INF)) ;
            } elseif (($a->ORA) != ($b->ORA)) {
                return (($a->ORA) < ($b->ORA)) ;
            } else {
                return strcmp($a->senatorID , $b->senatorID);
            }
        });
        $senator = $allSenators[0] ;
        $party = $this->getPartyOfSenator($senator) ;
        $user_id = $party->user_id ;
        return array ('senator' => $senator , 'party' => $party , 'user_id'=>$user_id) ;
    }
    
    /**
     * 
     * @param user_id $user_id
     * @param \ROR\Senator $stateman
     * @return array 'flag' = TRUE|FALSE , 'message'
     */
    public function statemanPlayable ($user_id , Senator $stateman) {
        if ($stateman->type != 'Stateman') {
            return array('flag' => FALSE, 'message' => '***ERROR***');
        }
        foreach ($this->party as $otherUser_id=>$party) {
            foreach ($party->senators->cards as $senator) {
                // Check if the family is already in play
                if ( ($senator->type == 'Family') && ($senator->senatorID == $stateman->statemanFamily()) ) {
                    if ($otherUser_id != $user_id) {
                        return array('flag' => FALSE , 'message' => 'The Family is already in party '.$party->name);
                    } else {
                        return array('flag' => TRUE , 'message' => 'You have the family');
                    }
                }
                // Check if a related Stateman is already in play
                if ( ($senator->type == 'Stateman') && ($senator->statemanFamily() == $stateman->statemanFamily()) ) {
                    if ( ($stateman->statemanFamily()!=25) && ($stateman->statemanFamily()!=29) ) {
                        return array('flag' => FALSE , 'message' => 'The related stateman '.$senator->name.' is already in play.');
                    } else {
                        // The other brother is in play : this is valid
                        return array('flag' => TRUE , 'message' => $stateman->name.' playable, but the other brother '.$senator->name.' is in play.');
                    }
                }
            }
        }
        foreach ($this->forum->cards as $card) {
            if ($card->type=='Senator' && ($card->senatorID == $stateman->statemanFamily()) ) {
                return array('flag' => TRUE , 'message' => 'The corresponding family card is in the forum');
            }
        }
        return array('flag' => TRUE , 'message' => 'The corresponding family card is not in play');
    }
    
    /**
     * returns array of user_id from HRAO, clockwise in the same order as the array $this->party (order of joining game)
     * @return array 
     */
    public function orderOfPlay() {
        $result = array_keys ($this->party) ;
        $user_idHRAO = $this->HRAO()['user_id'];
        while ($result[0]!=$user_idHRAO) {
            array_push($result , array_shift($result) );
        }
        return $result ;
    }
    
    /**
     * Returns the user_id of the player playing after the player whose user_id has been passed as a parameter
     * Say that three times.
     */
    public function whoIsAfter($user_id) {
        $orderOfPlay = $this->orderOfPlay() ;
        while ($orderOfPlay[0]!=$user_id) {
            array_push($orderOfPlay , array_shift($orderOfPlay) );
        }
        array_push($orderOfPlay , array_shift($orderOfPlay) );
        return $orderOfPlay[0];
    }
    
    /**
     * Returns the $user_id of the first player (in order of play) who hasn't finished his phase, or FALSE if all players are done for this phase
     * @return string|boolean
     */
    public function whoseTurn() {
        $currentOrder = $this->orderOfPlay() ;
        foreach ($currentOrder as $user_id) {
            if ( $this->party[$user_id]->phase_done === FALSE) {
                return $user_id ;
            }    
        }
        return FALSE ;
    }
    
    /**
     * Resets party->phaseDone to FALSE for all parties 
     */
    public function resetPhaseDone () {
        foreach ($this->party as $party) {
            $party->phase_done = FALSE ;
        }        
    }
    
    /**
     * Whether or not the Land Commissioner concession is playable (a Land bill is in play)
     * @return bool
     */
    public function landCommissionerPlaybale () {
        return ( (array_sum($this->landBill) > 0) ? TRUE : FALSE ) ;
    }
    
    /**
     * 
     * @param integer $nb = Number of dice to roll (1 to 3)
     * @param type $evilOmensEffect = Whether evil omens affect the roll by -1 , +1 or 0
     * @return 
     */
    public function rollDice($nb , $evilOmensEffect) {
        $nb = (int)$nb;
        if ($nb<1 || $nb>3) {
            return FALSE ;
        }
        $evilOmensEffect = (int)$evilOmensEffect ;
        if ( ($evilOmensEffect!=-1) && ($evilOmensEffect!=0) && ($evilOmensEffect!=1) ) {
            return FALSE ;
        }
        $result = array() ;
        $result['total'] = 0 ;
        for ($i=0 ; $i<$nb ; $i++) {
            $result[$i]=mt_rand(1,6);
            $result['total']+=$result[$i];
        }
        // Add evil omens effects to the roll
        $result['total'] += $evilOmensEffect * $this->getEventLevel('Evil Omens');
        return $result ;
    }
    
    /**
     * Convenience function to get a straight 1 die roll
     * @param type $evilOmensEffect
     * @return type
     */
    public function rollOneDie($evilOmensEffect) {
        $result = $this->rollDice(1 , $evilOmensEffect) ;
        if ($result!==FALSE) {
            return $result['total'];
        } else {
            return FALSE ;
        }
    }
    
    /**
     * Get a list of user_ids separated by ";" with all user_ids but one
     * @param type $not_this_user_id
     * @return string
     */
    public function getAllButOneUserID ($not_this_user_id) {
        $result='';
        foreach ($this->party as $user_id=>$party) {
            if ($user_id!=$not_this_user_id) {
                $result.=$user_id.';';
            }
        }
        return $result ;
    }
    
    /**
     * Returns the current level of the event if it's in play or 0
     * @param type $eventName
     * @return int
     */
    public function getEventLevel ($eventName) {
        if (array_key_exists($eventName, $this->events)) {
            return $this->events[$eventName] ;
        } else {
            return 0 ;
        }
    }
       
    /************************************************************
     * Functions for SETUP phase
     ************************************************************/
    
    /**
     * Set party leader of $user_id to senator with $senatorID
     * phase_done is set to TRUE for user_id
     * if all players have phase_done==true, move to next subPhase 'PlayCards'
     * @param type $user_id
     * @param type $senatorID
     * @return type
     */
    public function setup_setPartyLeader( $user_id , $senatorID ) {
        if ($this->subPhase!='PickLeaders') {
            return array(array('Wrong phase','error'));
        }
        if ($this->party[$user_id]->leader !== NULL) {
            return array(array('The leader is already set','error'));
        }
        foreach ($this->party[$user_id]->senators->cards as $senator) {
            if ( ($senator->senatorID == $senatorID) && is_null($this->party[$user_id]->leader) ) {
                $this->party[$user_id]->leader = $senator ;
                $card = $this->party[$user_id]->senators->drawCardWithValue('senatorID' , $senatorID);
                $this->party[$user_id]->senators->putOnTop($card);
                $this->party[$user_id]->phase_done = TRUE ;
                if ($this->whoseTurn() === FALSE ) {
                    $this->subPhase = 'PlayCards' ;
                    $this->resetPhaseDone() ;
                }
                return array(array($senator->name.' is the new leader of party '.$this->party[$user_id]->name , 'alert'));
            }
        }
        return array(array('Undocumented error - party leader not set.','error'));
    }
    
    /**
     * Returns a list of possible actions during the special setup machination phase :
     * - Statemen
     * - Concessions
     * @param type $user_id
     * @return array
     */
    public function setup_possibleActions($user_id) {
        $result = array() ;
        foreach ($this->party[$user_id]->hand->cards as $card) {
            // TO DO : Check what happens of statemanPlayable['message'], it seems unused
            if ( (($card->type == 'Stateman') && $this->statemanPlayable($user_id, $card)['flag']) ) {
                array_push($result, array('action' => 'Stateman' , 'card_id' => $card->id , 'message' => $card->name)) ;
            } elseif ($card->type == 'Concession') {
                // The Land Commissioner is not playable without Land bills in play
                if ($card->name!='LAND COMMISSIONER' || $this->landCommissionerPlaybale()) {
                    array_push($result, array('action' => 'Concession' , 'card_id' => $card->id , 'message' => $card->name)) ;                
                }
            }
        }
        array_push($result , array ('action' => 'Done' , 'card_id' => '', 'message' => 'Done playing cards') );
        return $result;
    }
    
    /**
     * Setup is finished for this player, if all players are finished, move to subPhase mortality
     * @param type $user_id
     * @return type
     */
    public function setup_Finished($user_id) {
        $this->party[$user_id]->phase_done = TRUE ;
        if ($this->whoseTurn()===FALSE) {
            return $this->mortality();
        }
        return array();
    }

    /************************************************************
     * Functions for MORTALITY phase
     ************************************************************/

    /**
     * Handles :
     * - Imminent wars
     * - Mortality.
     * Mortality uses the killSenator function
     * Once finished, moves to Revenue phase
     * @return array
     */
    public function mortality() {
        if ( ($this->whoseTurn() === FALSE) && ($this->phase=='Setup') )  {
            $messages = array() ;
            array_push($messages , array('Setup phase is finished. Starting Mortality phase.'));
            $this->phase = 'Mortality';
            array_push($messages , array('MORTALITY PHASE','alert'));
            
            // Activate imminent wars
            if (count($this->imminentWars->cards)==0) {
               array_push($messages , array('There is no imminent conflict to activate.')); 
            } else {
                // More 'usort' magic
                // This orders the imminent wars deck by conflict id
                usort ($this->imminentWars->cards , function ($a,$b)
                {
                    return ($a->id < $b->id) ;
                });
                // Pick the first conflict, activate it,  put all matched imminent conflicts in a temporary deck, repeat until imminent war deck is empty
                $temp = new Deck() ;
                while ( count($this->imminentWars->cards) > 0 ) {
                    $conflict = $this->imminentWars->drawTopCard() ;
                    $matchingName = $conflict->matches ;
                    $this->activeWars->putOnTop($conflict) ;
                    array_push($messages , array('Imminent conflict '.$conflict->name.' has been activated.','alert'));
                    foreach ($this->imminentWars->cards as $matchingConflict) {
                        if ($matchingConflict->matches == $matchingName) {
                            $temp->putOnTop($matchingConflict) ;
                        }
                    }
                }
                // Put the temp deck back
                while ( count($temp->cards) > 0 ) {
                    $this->imminentWars->putOnTop($temp->drawTopCard());
                }
            }
            
            // Draw mortality chits
            $chits = $this->mortality_chits(1) ;
            foreach ($chits as $chit) {
                if ($chit!='NONE' && $chit!='DRAW 2') {
                    $returnedMessage= $this->killSenator((string)$chit) ;
                    array_push($messages , array('Chit drawn : '.$chit.'. '.$returnedMessage[0] , (isset($returnedMessage[1]) ? $returnedMessage[1] : NULL) ));
                } else {
                    array_push($messages , array('Chit drawn : '.$chit));
                }
            }
            array_push($messages , array('Mortality phase is finished. Starting revenue phase.'));
            $this->phase = 'Revenue';
            array_push($messages , array('REVENUE PHASE','alert'));
            $this->revenue_init();
            return $messages ;
        }
    }
    
    /**
     * Draw $qty mortality chits, returns an array
     * @param type $qty The number of chits to draw
     * @return array An array of chits number + "DRAW 2" and "NONE", for log's sakes
     */
    public function mortality_chits( $qty ) {
        $qty = (int)$qty ;
        $result = array() ;
        $chits = array() ;
        for ($i=1 ; $i<=30 ; $i++) { $chits[$i] = $i ; }
        for ($i=31 ; $i<=34 ; $i++) { $chits[$i] = 0 ; }
        $chits [35] = -1 ; $chits [36] = -1 ;
        for ($i=$qty ; $i>0 ; $i--) {
            $pick = array_rand($chits) ;
            if ($chits[$pick]==-1) {
                $i+=2;
                array_push($result , "DRAW 2");
            } else {
                if (($key = array_search($chits[$pick], $chits)) !== false) {
                    if ($chits[$pick]!=0) {
                        array_push($result , $chits[$pick]);
                    } else {
                        array_push($result , "NONE");
                    }
                    unset($chits[$key]);
                }
            }
            if (count($chits)==2) {
                break;
            }
        }
        return $result;
    }
    
    /**
     * Kills the senator with $senatorID. This function handles :
     * - Brothers
     * - Statemen
     * - Party leader
     * - Where senator and controlled cards go (forum, curia, discard)
     * @param type $senatorID
     * @return type
     */
    public function killSenator($senatorID) {
        $message = '' ;
        // $deadSenator needs to be an array, as 2 brothers could be in play
        $deadSenators = array() ;
        foreach($this->party as $party) {
            foreach ($party->senators->cards as $senator) {
                if ( ($senator->type == 'Stateman') && ($senator->statemanFamily() == $senatorID ) ) {
                    array_push($deadSenators , $senator) ;
                } elseif ( ($senator->type == 'Family') && ($senator->senatorID == $senatorID) ) {
                    array_push($deadSenators , $senator) ;
                }
            }
        }
        // Returns either no dead (Senator not in play), 1 dead (found just 1 senator matching the chit), or pick 1 of two brothers if they are both legally in play
        if (count($deadSenators)==0) {
            return array('This senator is not in Play, nobody dies.') ;
        } elseif (count($deadSenators)>1) {
            // Pick one of two brothers
            $deadSenator = array_rand($deadSenators) ;
            $senatorID=$deadSenator->senatorID ;
            $message.=' The two brothers are in play. ' ;
        } else {
            $deadSenator = $deadSenators[0];
        }
        $party = $this->getPartyOfSenator($deadSenator) ;
        if ($party === FALSE) {
            return array('ERROR retrieving the party of the dead Senator','error');
        }
        if ($deadSenator->type == 'Stateman') {
            // Death of a Statesman
            $deadStateman = $party->senators->drawCardWithValue('senatorID',$deadSenator->senatorID) ;
            $deadStateman->resetSenator();
            $this->discard->putOnTop($deadStateman);
            $message.=$deadStateman->name.' ('.$party->fullName().') dies. The card is discarded. ' ;
        } else {
            // Death of a normal Senator
            $deadSenator->resetSenator() ;
            if ($party->leader->senatorID == $senatorID) {
                $message.=$deadSenator->name.' ('.$party->fullName().') dies. This senator was party leader, the family stays in the party. ' ;
            } else {
                $deadSenator = $party->senators->drawCardWithValue('senatorID',$senatorID) ;
                $this->curia->putOnTop($deadSenator);
                $message.=$deadSenator->name.' ('.$party->fullName().') dies. The family goes to the curia. ' ;
            }
        }
        // Handle dead senators' controlled cards, including Families
        while (count($deadSenator->controls->cards)>0) {
            $card = $deadSenator->controls->drawTopCard() ;
            if ($card->type=='Concession') {
                $this->curia->putOnTop($card);
                $message.=$card->name.' goes to the curia. ';
            } elseif ($card->type=='Province') {
                $this->forum->putOnTop($card);
                $message.=$card->name.' goes to the forum. ';
            } elseif ($card->type=='Family') {
                if ($party->leader->senatorID == $deadStateman->senatorID) {
                    $party->senators->putOnTop($card);
                    $message.=$card->name.' stays in the party. ';
                } else {
                    $this->curia->putOnTop($card);
                    $message.=$card->name.' goes to the curia. ';
                }
            } else {
                return array('A card controlled by the dead Senator was neither a Family nor a Concession.','error');
            }
        }
        return array($message) ;
    }

    /************************************************************
     * Functions for REVENUE phase
     ************************************************************/

    /**
     * Initialises revenue phase :
     * - subPhase is 'Base'
     * - For every Senator with a Province, set Province->doneThisTurn to FALSE
     */
    public function revenue_init() {
        $this->resetPhaseDone() ;
        $this->subPhase='Base';
        foreach ($this->party as $party) {
            foreach ($party->senators->cards as $senator) {
                foreach ($senator->controls->cards as $card) {
                    if ($card->type=='Province') {
                        $card->doneThisTurn = FALSE ;
                    }
                }
            }
            
        }
    }
    
    /**
     * 
     * @param type $user_id
     * @return array ['total'] , ['senators'] , ['leader'] , ['knights'] , array ['concessions'] , array ['province_name'] , array ['province_senatorID']
     */
    public function revenue_base($user_id) {
        $result = array() ;
        $result['total'] = 0 ;
        $result['senators'] = 0 ;
        $result['leader'] = '' ;
        $result['knights'] = 0 ;
        $result['concessions'] = array() ;
        $result['provinces'] = array() ;
        foreach ($this->party[$user_id]->senators->cards as $senator) {
            if ($this->party[$user_id]->leader->senatorID == $senator->senatorID) {
                $result['total']+=3 ;
                $result['leader']=$senator->name ;
            } else {
                $result['total']+=1 ;
                $result['senators']+=1 ;
            }
            $result['total']+=$senator->knights ;
            $result['knights']+=$senator->knights ;
            foreach ($senator->controls->cards as $card) {
                if ( $card->type == 'Concession' ) {
                    $card->corrupt = TRUE ;
                    $result['total']+=$card->income ;
                    array_push($result['concessions'] , array( 'name' => $card->name , 'income' => $card->income , 'senator_name' => $senator->name ) );
                } elseif ( $card->type == 'Province' ) {
                    array_push($result['provinces'] , array('province' => $card , 'senator' => $senator ) );
                }
            }
        }
        return $result ;
    }
    
    
    public function revenue_ProvincialSpoils ($user_id , $request ) {
        if ( ($this->phase=='Revenue') && ($this->subPhase=='Base') && ($this->party[$user_id]->phase_done==FALSE) ) {
            $messages = array() ;
            $base = $this->revenue_base($user_id);
            $this->party[$user_id]->treasury+=$base['total'] ;
            array_push ($messages , array($this->party[$user_id]->fullName().' gains '.$base['total'].' T : '.($base['leader']!=NULL ? 3 : 0).'T from leader, '.$base['senators'].'T from senators, '.$base['knights'].'T from knights and '.($base['total']-($base['leader']!=NULL ? 3 : 0)-$base['senators']-$base['knights']).'T from Concessions.')) ;
            foreach ($base['provinces'] as $data) {
                $province = $data['province'];
                $senator = $data['senator'];
                if (is_null($request[$province->id])) {
                    return array('Undefined province.','error');
                }
                $revenue = $province->rollRevenues(-$this->getEventLevel('Evil Omens'));
                $message = $province->name.' : ';
                // Spoils
                if ($request[$province->id] == 'YES') {
                    $senator->corrupt = TRUE ;
                    $message .= $senator->name.' takes provincial spoils for '.$revenue['senator'].'T .';
                    if ($revenue['senator']>0) {
                        $senator->treasury+=$revenue['senator'];
                    } else {
                        if ($request[$province->id.'_LET_ROME_PAY'] == 'YES') {
                            // The Senator decided to let Rome pay for it
                            $message .= ' He decides to let the negative amount be paid by Rome. ' ;
                            $this->treasury+=$revenue['senator'];
                        } else {
                            // The Senator decided to pay for it
                            $message .= ' He decides to pay the negative amount. ' ;
                            $senator->treasury+=$revenue['senator'];
                        }
                    }
                    $message .= ' He is now corrupt.';
                } else {
                // No spoils
                    $message.=$senator->name.' doesn\'t take Provincial spoils.';
                }
                // Develop province
                if ( !($province->developed)) {
                    $roll = $this->rollOneDie(-1) ;
                    $modifier = ( ($senator->corrupt) ? 0 : 1) ;
                    if ( ($roll+$modifier) >= 6 ) {
                        $message.=' A '.$roll.' is rolled'.($modifier==1 ? ' (modified by +1 since senator is not corrupt)' : '').', the province is developed. '.$senator->name.' gains 3 INFLUENCE.';
                        $province->developed = TRUE ;
                        $senator->INF+=3;
                    } else {
                        $message.=' A '.$roll.' is rolled'.($modifier==1 ? ' (modified by +1 since senator is not corrupt)' : '').', the province is not developed.';
                    }
                }
                array_push ($messages , array($message)) ;
            }
            // Phase done for this player. If all players are done, 
            $this->party[$user_id]->phase_done = TRUE ;
            if ($this->whoseTurn() === FALSE ) {
                $this->resetPhaseDone() ;
                $this->subPhase='Redistribution' ;
                array_push ($messages , array('All revenues collected, parties can now redistribute money.')) ;
            }
            return $messages ;
        }
    }

    /**
    * Lists all the possible "From" and "To" for redistribution of wealth
    * @param type $user_id
    * @return array
    */
    public function revenue_ListRedistribute ($user_id) {
        $result=array() ;
        if ( ($this->phase=='Revenue') && ($this->subPhase=='Redistribution') && ($this->party[$user_id]->phase_done==FALSE) ) {
            foreach($this->party[$user_id]->senators->cards as $senator) {
                if ($senator->treasury > 0 ) {
                    array_push($result , array('list' => 'from' , 'type' => 'senator' , 'id' => $senator->senatorID , 'name' => $senator->name , 'treasury' => $senator->treasury ));
                }
                array_push($result , array('list' => 'to' , 'type' => 'senator' , 'id' => $senator->senatorID , 'name' => $senator->name ));
            }
            array_push($result , array('list' => 'from' , 'type' => 'party' , 'id' => $user_id , 'name' => $this->party[$user_id]->name , 'treasury' => $this->party[$user_id]->treasury ));
            foreach($this->party as $key=>$value) {
                array_push($result , array('list' => 'to' , 'type' => 'party' , 'id' => $key , 'name' => $this->party[$key]->name ));
            }
        }
        return $result ;
    }

    /**
     * 
     * $fromTI and $toTI are arrays in the form [0] =>'senator'|'party' , [1] => 'id'
     * @param type $user_id
     * @param type $from
     * @param type $to
     * @return string
     */
    public function revenue_Redistribution ($user_id , $fromRaw , $toRaw , $amount) {
        if ( ($this->phase=='Revenue') && ($this->subPhase=='Redistribution') && ($this->party[$user_id]->phase_done==FALSE) ) {
            $fromTI = explode('|' , $fromRaw);
            $toTI = explode('|' , $toRaw);
            $from = ( $fromTI[0]=='senator' ? $this->getSenatorWithID($fromTI[1]) : $this->party[$fromTI[1]] );
            if ($toTI[0]=='senator') {
                $to = $this->getSenatorWithID($toTI[1]) ;
            } else {
                $to = $this->party[$toTI[1]] ;
            }
            if ($amount<=0) { return array(array('You have no talent. ','error',$user_id)); }
            if ($from===FALSE) { return array(array('Giving from wrong Senator','error',$user_id)); }
            if ($to===FALSE) { return array(array('Giving to wrong Senator','error',$user_id)); }
            if (!isset($from)) { return array(array('Giving from wrong Party','error',$user_id)); }
            if (!isset($to)) { return array(array('Giving to wrong Party','error',$user_id)); }
            if ($from->treasury < $amount) { return array(array('Not enough money','error',$user_id)); }
            if ($toTI[0]== 'senator' && $fromTI[0]=='senator' && $toTI[1]==$fromTI[1] ) { return array(array('Stop drinking','error',$user_id)); }
            $from->treasury-=$amount ;
            $to->treasury+=$amount ;

            if ($toTI[0]== 'senator') {
                // This is a different message for public and private use
                return array(
                    array(($fromTI[0]=='senator' ? ($from->name) : 'The party ' ).' gives '.$amount.'T to '.(($toTI[0]=='party' && $toTI[1]==$user_id) ? 'Party treasury. ' : $to->name.'.')  , 'message' , $user_id ) ,
                    array($this->party[$user_id]->fullName().' moves some money around'  , 'message' , $this->getAllButOneUserID($user_id) )
                    ) ;
            } else {
                return array(array($from->name.' gives '.$amount.'T to '.(($toTI[0]=='party' && $toTI[1]==$user_id) ? 'Party treasury. ' : $to->name.'.')  , 'message' , $user_id ));
            }
        }
        return array(array('Undocumented Redistribution error','error',$user_id));
    }

    /**
     * Finish the redistribution of wealth for $user_id
     * If everyone is done, do State revenue :
     * > 100 T
     * > Provinces
     * Then move to Contributions subphase
     * @param type $user_id
     * @return array
     */
    public function revenue_RedistributionFinished ($user_id) {
        $messages = array () ;
        if ( ($this->phase=='Revenue') && ($this->subPhase=='Redistribution') && ($this->party[$user_id]->phase_done==FALSE) ) {
            $this->party[$user_id]->phase_done=TRUE ;
            array_push($messages , array($this->party[$user_id]->fullName().' has finished redistributing wealth.')) ;
            if ($this->whoseTurn()===FALSE) {
                array_push($messages , array('The redistribution sub phase is finished.')) ;
                array_push($messages , array('State revenues.')) ;
                // Rome gets 100T.
                $this->treasury+=100 ;
                array_push($messages , array('Rome collects 100 T.'));
                foreach ($this->party as $party) {
                    foreach ($party->senators->cards as $senator) {
                        foreach ($senator->controls->cards as $province) {
                            if ($province->type=='Province') {
                                $revenue = $province->rollRevenues(-$this->getEventLevel('Evil Omens'));
                                array_push($messages , array($province->name.' : Rome\'s revenue is '.$revenue['rome'].'T . ') );
                                $this->treasury+=$revenue['rome'];
                            }
                        }
                    }
                }
                array_push($messages , array('The state revenue sub phase is finished.')) ;
                array_push($messages , array('Contributions.')) ;
                $this->subPhase='Contributions';
                $this->resetPhaseDone();
            }
        }
        return $messages ;       
    }
   
    public function revenue_listContributions($user_id) {
        $result = array() ;
        if ( ($this->phase=='Revenue') && ($this->subPhase=='Contributions') && ($this->party[$user_id]->phase_done==FALSE) ) {
            foreach($this->party[$user_id]->senators->cards as $senator) {
                if ($senator->treasury > 0 ) {
                    array_push( $result , array('senatorID' => $senator->senatorID , 'name' => $senator->name , 'treasury' => $senator->treasury));
                }
            }
        }
        return $result ;
    }
    
    public function revenue_Contributions($user_id , $rawSenator , $amount) {
        $amount=(int)$amount;
        $lessRaw = explode('|' , $rawSenator);
        $senatorID = $lessRaw[0];
        foreach ($this->party[$user_id]->senators->cards as $senator) {
            if ($senator->senatorID==$senatorID) {
                if ($senator->treasury < $amount) {
                    return array(array('This senator doesn\'t have enough money','error',$user_id));
                } elseif ($amount<1) {
                    return array(array('Wrong amount','error',$user_id));
                } else {
                    if ($amount>=50) { $INFgain = 7 ; } elseif ($amount>=25) { $INFgain = 3 ; } elseif ($amount>=10) { $INFgain = 1 ; } else { $INFgain = 0 ; }
                    $senator->INF+=$INFgain ;
                    $senator->treasury-=$amount ;
                    $this->treasury+=$amount ;
                    return array(array($senator->name.' gives '.$amount.'T to Rome.'.( ($INFgain!=0) ? ' He gains '.$INFgain.' Influence.' : '') ));
                }
            }
        }
        return array('Error retrieving Senator','error',$user_id);
    }
    
    public function revenue_Finished ($user_id) {
        $messages = array () ;
        if ( ($this->phase=='Revenue') && ($this->subPhase=='Contributions') && ($this->party[$user_id]->phase_done==FALSE) ) {
            $this->party[$user_id]->phase_done=TRUE ;
            array_push($messages , array($this->party[$user_id]->fullName().' has finished contributions to Rome.')) ;
            if ($this->whoseTurn()===FALSE) {
                // Finish revenue Phase
                // Pay for active (including unprosecuted) wars
                $textWars = '' ;
                $nbWars = 0 ;
                foreach ($this->activeWars->cards as $activeWar) {
                    $textWars.=$activeWar->name.' , ';
                    $nbWars++ ;
                }
                foreach ($this->unprosecutedWars->cards as $unprosecutedWar) {
                    $textWars.=$unprosecutedWar->name.' , ';
                    $nbWars++ ;
                }
                if ($nbWars>0) {
                    $this->treasury-=$nbWars*20 ;
                    $textWars = substr($textWars, 0 , -3) ;
                    array_push($messages , array('Rome pays '.($nbWars*20).'T for '.$nbWars.' active Conflicts : '.$textWars.'.'));
                }
                // Land bills
                $totalLandBills =  $this->landBill[1]*10 + $this->landBill[2]*5 + $this->landBill[3]*10 ;
                if ($totalLandBills>0) {
                    $this->treasury-=$totalLandBills;
                    array_push($messages , array('Rome pays '.$totalLandBills.' talents for land bills (I , II & III): '.($this->landBill[1]*10).'T for '.$this->landBill[1].' (I) which are then discarded, '.($this->landBill[2]*5).'T for (II) and '.($this->landBill[3]*10).'T for (III).'));
                }
                // Forces maintenance
                $nbLegions = $this->nbOfLegions();
                $nbFleets = $this->nbOfFleets();
                $totalCostForces=2*($nbLegions + $nbFleets) ;
                if ($totalCostForces>0) {
                    $this->treasury-=$totalCostForces ;
                    array_push($messages , array('Rome pays '.$totalCostForces.'T for the maintenance of '.$nbLegions.' legions and '.$nbFleets.' fleets. '));
                }
                // Return of provinces governors
                foreach($this->party as $party) {
                    foreach ($party->senators->cards as $senator) {
                        foreach ($senator->controls->cards as $card) {
                            if ($card->type=='province') {
                                $card->mandate++;
                                if ($card->mandate == 3) {
                                    array_push($messages , array($senator->name.' returns from '.$card->name.' which is placed in the Forum.'));
                                    $senator->inRome=TRUE;
                                    $card->mandate=0;
                                    $this->forum->putOnTop($senator->controls->drawCardWithValue('id',$card->id));
                                } else {
                                    array_push($messages , array($senator->name.' spends '.( ($card->mandate==1) ? 'First' : 'Second' ).' game turn in '.$card->name.'.'));
                                }
                            }
                        }
                    }
                }
                // Done, move to Forum phase.
                array_push($messages , array('Revenue phase is finished. Rome now has '.$this->treasury.'T. Starting Forum phase.'));
                $this->resetPhaseDone();
                $this->phase='Forum';
                array_push($messages , array('FORUM PHASE','alert'));
                // TO DO : remove events that expire at the beginning of the forum phase
                $this->subPhase='RollEvent';
                $this->initiative=1;
                array_push($messages , array('Initiative #1','alert'));
            }
        }
        return $messages ;
    }

     /************************************************************
     * Functions for FORUM phase
     ************************************************************/

    /**
     * A message saying who is currently the highest bidder
     */
    public function forum_highestBidder () {
        $result['bid']=0 ;
        $result['message']='' ;
        foreach ($this->party as $party) {
            if ($party->bid>$result['bid']) {
                $result['bid']=$party->bid ;
                $result['user_id']=$party->user_id;
                $result['message']=$party->fullName().' with a bid of '.$result['bid'].'T.' ;
            }
        }
        if ($result['bid']==0) {
            $HRAO = $this->HRAO() ;
            $result['message']='The HRAO '.$HRAO['party']->fullName().' as all bets are 0.';
            $result['user_id']=$HRAO['user_id'];
        }
        return $result ;
    }

    /**
     * Returns the $user_id of the user currently having the initiative or FALSE if bidding is still underway
     * @return boolean|array
     */
    public function forum_whoseInitiative() {
        // If the current initiative is <= nbPlayers, we don't need to bid. Initiative number X belongs to player number X in the order of play
        if ($this->initiative<=$this->nbPlayers) {
            $currentOrder = $this->orderOfPlay() ;
            return $currentOrder[$this->initiative-1] ;
        } else {
        // This initiative was up for bidding, the winner has the initiative. The winner is the only one left with bidDone==FALSE
        // This is to allow multiple rounds of initiative bidding as an option
            $candidates=array() ;
            foreach ($this->party as $user_id=>$party) {
                if ($party->bidDone===FALSE) {
                    array_push($candidates , $user_id);
                }
            }
            if (count($candidates)==1) {
                return $candidates[0] ;
            } else {
                return FALSE;
            }
        }
        
    }
    
    /**
     * Re-initialises all parties bids for initiative.
     */
    public function forum_initInitiativeBids() {
        foreach ($this->party as $party) {
            $party->bidDone=FALSE;
            $party->bid=0;
            $party->bidWith=NULL;
        }
        $HRAO = $this->HRAO();
        $this->currentBidder = $HRAO['user_id'];
    }
    
    public function forum_bid ($user_id , $senatorRaw , $amount) {
        $messages = array() ;
        if ($this->forum_whoseInitiative()===FALSE) {
            // There was no bid
            if ($senatorRaw=='NONE') {
                array_push($messages , array($this->party[$user_id]->fullName().' cannot or will not bid for this initiative.'));
            // There was a bid    
            } else {
                $senatorData = explode('|' , $senatorRaw) ;
                $senatorID = $senatorData[0] ;
                $senator = $this->getSenatorWithID($senatorID) ;
                if ($this->getPartyOfSenator($senator)->user_id == $user_id) {
                    if ($senator->treasury>=$amount) {
                        $senator->treasury-=$amount ;
                        $this->party[$user_id]->bid = $amount ;
                        array_push($messages , array($this->party[$user_id]->fullName().' bids '.$amount.'T for this initiative.'));
                    } else {
                        array_push($messages , array('Not enough money' , 'error' , $user_id));
                    }
                } else {
                    array_push($messages , array('Wrong party' , 'error' , $user_id));
                }
            }
            // There was a bid or pass, we need to move on to the next bidder and check if the bids are over
            $this->currentBidder = $this->whoIsAfter($user_id);
            $HRAO = $this->HRAO();
            // We went around the table once : bids are finished
            if ($this->currentBidder == $HRAO['user_id']) {
                $highestBidder = $this->forum_highestBidder() ;
                foreach($this->party as $party) {
                    if ($party->user_id!=$highestBidder['user_id']) {
                        $party->bidDone=TRUE;
                    }
                }
                array_push($messages , array($this->party[$highestBidder['user_id']]->fullName().' wins this initiative.'));
            }
        } else {
            array_push($messages , array('Cannot bid as this initiative already belongs to another player' , 'error' , $user_id));
        }
        return $messages ;
    }
    
    public function forum_rollEvent($user_id) {
        $messages = array() ;
        if ( ($this->phase=='Forum') && ($this->subPhase=='RollEvent') && ($this->forum_whoseInitiative()==$user_id) ) {
            array_push($messages , array('Event roll Sub Phase'));
            $roll = $this->rollDice(2, 0) ;
            if ($roll['total']==7) {
                // Event
                $eventRoll = $this->rollDice(3,0) ;
                array_push($messages , array($this->party[$user_id]->fullName().' rolls a 7, then rolls a '.$eventRoll['total'].' on the events table.'));
                // $newEvent is an array (0 => name , 1 => increased name , 2=> max level)
                $newEvent = $this->eventPool[$this->eventTable[$eventRoll['total']][$this->scenario]];
                if (in_array($newEvent[0] , $this->events)) {
                    // The event is already in play, check its level and check if level can be increased
                } else {
                    // The event was not in play
                    $this->events[$newEvent[0]]=1;
                    array_push($messages, array($newEvent[0].' is now in play.'));
                }
            } else {
                // Card
                array_push($messages , array($this->party[$user_id]->fullName().' rolls a '.$roll['total'].' and draws a card.'));
                $card = $this->earlyRepublic->drawTopCard() ;
                if ($card !== NULL) {
                    if ($card->type == 'Stateman' || $card->type == 'Faction' || $card->type == 'Concession') {
                        // Keep the card
                        $this->party[$user_id]->hand->putOnTop($card);
                        array_push($messages , array($this->party[$user_id]->fullName().' draws a faction card and keeps it.','message',$this->getAllButOneUserID($user_id)));
                        array_push($messages , array('You draw '.$card->name.' and put it in your hand.','message',$user_id));
                    } else {
                        // Card goes to forum
                        $this->forum->putOnTop($card) ;
                        array_push($messages , array($this->party[$user_id]->fullName().' draws '.$card->name.' that goes to the forum.'));
                    }
                } else {
                    array_push($messages , array('There is no more cards in the deck.','alert'));
                }
            }
            // Persuasion initialisation
            $this->subPhase = 'Persuasion';
            array_push($messages , array ('Persuasion Sub Phase') );
            $this->forum_resetPersuasion();
            $this->currentBidder = $user_id;
        }
        return $messages ;
    }
    
    /**
     * Resets all variables used in persuasion :
     * game : currentBidder , persuasionTarget
     * parties : bid , bidWith
     */
    public function forum_resetPersuasion() {
        foreach ($this->party as $party) {
            $party->bid=FALSE ;
            $party->bidWith=NULL ;
        }
        $this->currentBidder = NULL ;
        $this->persuasionTarget = NULL ;
    }

    /**
     * Lists the possible targets for persuasion by player user_id
     * format : array ('senatorID','name','party','LOY','treasury')
     * 'party' can be 'forum'
     * Warning : 'LOY' is modified by party affiliation and enemy/friends
     * @param type $user_id
     * @return boolean|array
     */
    public function forum_listPersuasionTargets($user_id) {
        $result = array();
        if ( ($this->phase=='Forum') && ($this->subPhase=='Persuasion') && ($this->forum_whoseInitiative()==$user_id) ) {
            foreach ($this->party as $party) {
                foreach ($party->senators->cards as $senator) {
                    if ($senator->inRome && $senator->senatorID != $party->leader->senatorID && $party->user_id!=$user_id) {
                        array_push($result, array('senatorID' => $senator->senatorID , 'name' => $senator->name , 'party' => $party->user_id , 'LOY' => $this->getSenatorActualLoyalty($senator) , 'treasury' => $senator->treasury)) ;
                    }
                }
            }
            foreach ($this->forum->cards as $card) {
                if ($card->type=='Family') {
                    array_push($result, array('senatorID' => $card->senatorID , 'name' => $card->name , 'party' => 'forum' , 'LOY' => $this->getSenatorActualLoyalty($card) , 'treasury' => $card->treasury)) ;
                }
            }
        } else {
            return FALSE ;
        }
        return $result;
    }
    
    /**
     * List the possible persuading senators for player user_id
     * format : array ('senatorID','name','ORA','INF','treasury')
     * @param type $user_id
     * @return boolean|array
     */
    public function forum_listPersuaders($user_id) {
        $result = array();
        if ( ($this->phase=='Forum') && ($this->subPhase=='Persuasion') && ($this->forum_whoseInitiative()==$user_id) ) {
            foreach ($this->party[$user_id]->senators->cards as $senator) {
                if ($senator->inRome) {
                    array_push( $result , array('senatorID' => $senator->senatorID , 'name' => $senator->name , 'ORA' => $senator->ORA , 'INF' => $senator->INF , 'treasury' => $senator->treasury )) ;
                }
            }
        } else {
            return FALSE ;
        }
        return $result;
    }
    
    /**
     * List the cards in the hand of user_id that can be used for persuasion
     * @param type $user_id
     * @return array
     */
    public function forum_listPersuasionCards($user_id) {
        $result = array();
        foreach ($this->party[$user_id]->hand->cards as $card) {
            if (($card->name=='SEDUCTION') || ($card->name=='BLACKMAIL')) {
                array_push($result , $card);
            }
        }
        return $result ;
    }
    
    /**
     * When $user_id doesn't do any persuasion for this initiative, move on to the next subPhase (Knights)
     * @param type $user_id
     * @return array
     */
    public function forum_noPersuasion($user_id) {
        $messages = array() ;
        if ( ($this->phase=='Forum') && ($this->subPhase=='Persuasion') && ($this->forum_whoseInitiative()==$user_id) ) {
            $this->subPhase = 'Knights';
            array_push($messages , array($this->party[$user_id]->fullName().' doesn\'t try to persuade any senator during this initiative.'));
        } else {
            array_push($messages , array('Wrong phase, subphase or player','error',$user_id));
        }
        return $messages ;
    }
    
    /**
     * Returns an array with a complete list of information about the current persuasion attempt
     * @return type
     */
    public function forum_persuasionListCurrent() {
        $rollOdds = array(2 => 1/36 , 3 => 2/36 , 4 => 6/36 , 5 => 10/36 , 6 => 15/36 , 7 => 21/36 , 8 => 26/36 , 9 => 30/36 );
        $result = array();
        $result['target']['senatorID'] = $this->persuasionTarget->senatorID ;
        $result['target']['party'] = $this->getPartyOfSenator($this->persuasionTarget) ;
        $result['target']['treasury'] = $this->persuasionTarget->treasury ;
        $result['target']['LOY'] = $this->getSenatorActualLoyalty($this->persuasionTarget) ;
        $result['target']['name'] = $this->persuasionTarget->name ;
        $persuader = $this->party[$this->forum_whoseInitiative()]->bidWith ;
        $result['persuader']['senatorID'] = $persuader->senatorID ;
        $result['persuader']['treasury'] = $persuader->treasury ;
        $result['persuader']['INF'] = $persuader->INF ;
        $result['persuader']['ORA'] = $persuader->ORA ;
        $result['persuader']['name'] = $persuader->name ;
        $result['odds']['for'] = $result['persuader']['INF'] + $result['persuader']['ORA'] ;
        $result['odds']['against'] = $result['target']['LOY'] + $result['target']['treasury'] ;
        foreach($this->party as $party) {
            $result['bid'][$party->user_id] = (int)$party -> bid ;
            if ($party->user_id == $this->forum_whoseInitiative()) {
                $result['odds']['for'] += $result['bid'][$party->user_id] ;
            } else {
                $result['odds']['against'] += $result['bid'][$party->user_id] ;
            }
        }
        $result['odds']['total'] = $result['odds']['for'] - $result['odds']['against'] ;
        if ($result['odds']['total'] < 2) {
            $result['odds']['percentage'] = 0 ;
        } else if ($result['odds']['total'] > 9) {
            $result['odds']['percentage'] = number_format($rollOdds[9]*100 , 2)  ;
        } else {
            $result['odds']['percentage'] = number_format($rollOdds[$result['odds']['total']]*100 , 2) ;
        }
        return $result ;
    }
    
    /**
     * The main persuasion function :
     * - Pick up a target, a persuader, an amount to spend, cards to play
     * - If target already picked, put more money (all players) OR roll with current odds (player with initiative)
     * @param type $user_id
     * @return array
     */
    public function forum_persuasion($user_id , $persuaderRaw , $targetRaw , $amount , $card) {
        $messages = array() ;
        $amount = (int)$amount ;
        if ( ($this->phase=='Forum') && ($this->subPhase=='Persuasion') ) {
            /*
             *  We don't know who is the target yet.
             */
            if ($this->persuasionTarget===NULL) {
                // target [0] = senatorID , [1] = treasury , [2] = party , [3] = actual loyalty
                $target = explode('|' , $targetRaw);
                $partyTarget = $this->getPartyOfSenatorWithID($target[0]) ;
                if ($partyTarget==$target[2]) {
                    // Everything is fine so far, proceed to check persuader
                    // persuader [0] = senatorID , [1] = treasury , [2] = INF , [3] = ORA
                    $persuader = explode('|' , $persuaderRaw);
                    $partyPersuader = $this->getPartyOfSenatorWithID($persuader[0]) ;
                    if ($partyPersuader->user_id==$user_id) {
                        // Still OK
                        $targetSenator = $this->getSenatorWithID($target[0]);
                        $persuadingSenator = $this->getSenatorWithID($persuader[0]);
                        if ($amount<=$persuadingSenator->treasury) {
                            // Amount looks good
                            // No persuasion-specific card was played
                            if ($card=='NONE') {
                                $this->party[$user_id]->bidWith = $persuadingSenator ;
                                $this->party[$user_id]->bidWith->treasury-=$amount ;
                                $this->party[$user_id]->bid = $amount ;
                                $this->persuasionTarget = $targetSenator ;
                                $this->currentBidder = $this->whoIsAfter($user_id);
                                array_push($messages , array ($persuadingSenator->name.' ('.$partyPersuader->fullName().') attempts to persuade '.$targetSenator->name.' ('.($partyTarget=='forum' ? 'forum' : $partyTarget->fullName()).')')) ;
                            
                            // A persuasion-specific card was played
                            } else {
                                // TO DO
                            }
                        } else {
                            return array(array('Amount error','error',$user_id));
                        }
                    } else {
                        return array(array('Error - party mismatch','error',$user_id));
                    }
                } else {
                    return array(array('Error - party mismatch','error',$user_id));
                }
            /* 
             * We know the target, this is a bribe     
             */
            } else {
                
                // This user is indeed the current bidder
                if ($user_id==$this->currentBidder) {
                    
                    // This user has the initiative, this might be a bribe ($amount>0), or a roll
                    if ($this->forum_whoseInitiative()==$user_id) {
                        
                        // This is the final roll
                        if ($amount==0) {
                            $currentPersuasion = $this->forum_persuasionListCurrent();
                            $roll = $this->rollDice(2, 1);
                            // Failure on 10+
                            if ($roll['total']>=10) {
                                array_push ($messages , array('FAILURE - '.$this->party[$user_id]->fullName().' rolls an unmodified '.$roll['total'].', which is greater than 9 and an automatic failure.'));
                            // Failure if roll > target number    
                            } elseif ($roll['total']>$currentPersuasion['odds']['total']) {
                                array_push ($messages , array('FAILURE - '.$this->party[$user_id]->fullName().' rolls '.$roll['total'].', which is greater than the target number of '.$currentPersuasion['odds']['total'].'.'));
                            // Success
                            } else {
                                array_push ($messages , array('SUCCESS - '.$this->party[$user_id]->fullName().' rolls '.$roll['total'].', which is lower than the target number of '.$currentPersuasion['odds']['total'].'.'));
                                if ($currentPersuasion['target']['party'] == 'forum') {
                                    $senator = $this->forum->drawCardWithValue('senatorID' , $currentPersuasion['target']['senatorID']);
                                    $this->party[$user_id]->senators->putOnTop($senator) ;
                                    array_push ($messages , array($senator->name.' leaves the forum and joins '.$this->party[$user_id]->fullName()));
                                } else {
                                    $senator = $this->party[$currentPersuasion['target']['party']]->drawCardWithValue('senatorID' , $currentPersuasion['target']['senatorID']);
                                    $this->party[$user_id]->senators->putOnTop($senator) ;
                                    array_push ($messages , array($senator->name.' leaves '.$this->party[$currentPersuasion['target']['party']].fullName().' and joins '.$this->party[$user_id]->fullName()));
                                }
                            }
                            $totalBids = 0 ;
                            foreach ($this->party as $party) {
                                $totalBids += $party->bid ;
                            }
                            if ($totalBids>0) {
                                array_push ($messages , array($currentPersuasion['target']['name'].' takes a total of '.$totalBids.' T from bribes and counter-bribes.'));
                            }
                            $this->forum_resetPersuasion() ;
                            $this->subPhase = 'Knights';
                            array_push($messages , array ('Knights Sub Phase') );
                            
                        // More bribe : go for another round of counter bribes
                        } else {
                            if ($this->party[$user_id]->bidWith->treasury>=$amount) {
                                $this->party[$user_id]->bidWith->treasury-=$amount;
                                $this->party[$user_id]->bid+=$amount;
                                $this->currentBidder = $this->whoIsAfter($user_id) ;
                                array_push ($messages , array($this->party[$user_id]->fullName().' bribes more.'));
                            } else {
                                array_push ($messages , array('The senator is too poor' , 'error' , $user_id));
                            }
                        }
                        
                    // This user doesn't have the initiative, this is a counter-bribe
                    } else {
                        if ($amount==0) {
                            array_push ($messages , array($this->party[$user_id]->fullName().' doesn\'t spend money to counter-bribe.'));
                            $this->currentBidder = $this->whoIsAfter($user_id) ;
                        } elseif ($this->party[$user_id]->treasury >= $amount) {
                            $this->party[$user_id]->treasury -= $amount ;
                            $this->party[$user_id]->bid += $amount ;
                            $this->currentBidder = $this->whoIsAfter($user_id) ;
                            array_push ($messages , array($this->party[$user_id]->fullName().' spends '.$amount.' T from the party treasury to counter-bribe.'));
                        } else {
                            return array(array('Error - not enough money in the party\'s treasury','error',$user_id));
                        }
                    }
                // This is user is NOT the current bidder, something is wrong
                } else {
                    return array(array('Error - this is not your turn to play','error',$user_id));
                }
            }
        }
        return $messages ;
    }
    
    /**
     * Returns a list of senatorID, name , knights, treasury and inRome by senator
     * Useful for attracking and pressuring knights
     * @param type $user_id
     * @return array
     */
    public function forum_listKnights($user_id) {
        $result = array () ;
        foreach ($this->party[$user_id]->senators->cards as $senator) {
            array_push($result , array ( 'senatorID' => $senator->senatorID , 'name' => $senator->name , 'knights' => $senator->knights , 'treasury' => $senator->treasury , 'inRome' => $senator->inRome) );
        }
        return $result ;
    }
    
    /**
     * Knight persuasion
     * @param type $user_id
     * @return array
     */
    public function forum_knights($user_id , $senatorRaw , $amount) {
        $messages = array();
        $senatorData = explode('|' , $senatorRaw);
        $senatorID = $senatorData[0] ;
        $senator = $this->getSenatorWithID($senatorID) ;
        // Check that we have a senator, that he belongs to the right party, and that his treasury is equal or greater to/than the amount
        if ($senator!==FALSE) {
            if ($this->getPartyOfSenator($senator)->user_id==$user_id) {
                if ($amount<=$senator->treasury) {
                    //1d6+bribe >=6
                    $senator->treasury-=$amount ;
                    $roll = $this->rollOneDie(-1);
                    if (($roll+$amount) >= 6) {
                        $senator->knights++;
                        array_push($messages , array('SUCCESS - '.$senator->name.' ('.$this->party[$user_id]->fullName().') spends '.$amount.' T and rolls '.$roll.'. The total is >= 6.')) ;
                    } else {
                        array_push($messages , array('FAILURE - '.$senator->name.' ('.$this->party[$user_id]->fullName().') spends '.$amount.' T and rolls '.$roll.'. The total is < 6.')) ;
                    }
                    $this->subPhase = 'SponsorGames';
                    array_push ($messages , array('Sponsor Games Sub Phase'));
                    // Be nice : skip sponsor games sub phase if no senator can do them.
                    $listSponsorGames = $this->forum_listSponsorGames($user_id) ;
                    if (count($listSponsorGames)==0) {
                        array_push ($messages , array($this->party[$user_id]->fullName().' has no senator who can sponsor games.'));
                        $this->subPhase = 'ChangeLeader';
                        array_push ($messages , array('Change Leader Sub Phase'));
                    }
                } else {
                    array_push($messages , array('Amount error' , 'error' , $user_id)) ;
                }
            } else {
                array_push($messages , array('Wrong party' , 'error' , $user_id)) ;
            }
        } else {
            array_push($messages , array('Error retrieving senator data' , 'error' , $user_id)) ;
        }
        return $messages ;
    }
    
    /**
     * Pressure knights
     * The POST data from the form is retrieved in $request, which is a list of senatorIDs=>nbOfKnights
     * @param type $user_id
     * @param type $request
     * @return array
     */
    public function forum_pressureKnights($user_id , $request) {
        $messages = array();
        $error = FALSE ;
        foreach($request as $senatorID=>$pressuredKnights) {
            $pressuredKnights = (int)$pressuredKnights ;
            if ($pressuredKnights>0) {
                $senator = $this->getSenatorWithID($senatorID);
                if ($this->getPartyOfSenator($senator)->user_id==$user_id) {
                    if ($senator->knights <= $pressuredKnights) {
                        $message = $senator->name.' pressures '.$pressuredKnights.' knight'.($pressuredKnights>1 ? 's' : '').'. Rolls : ';
                        $total = 0 ;
                        for ($i=1 ; $i<$pressuredKnights ; $i++) {
                            $roll = min($this->rollOneDie(-1),0);
                            $message.=$roll.', ';
                            $total+=$roll;
                        }
                        $message = substr($message, 0 , -2) ;
                        $message.= '. Earns a total of '.$total.'T.';
                        array_push($messages , array($message));
                    } else {
                        $error = TRUE ;
                        array_push($messages , array('Not enough knights for '.$senator->name.' : ignored' , 'error' , $user_id));
                    }
                } else {
                    $error = TRUE ;
                    array_push($messages , array('Wrong party for '.$senator->name.' : ignored' , 'error' , $user_id));
                }
            }
        }
        // If there is no error, move to next sub phase (SponsorGames)
        // Be nice : skip sponsor games sub phase if no senator can do them.
        if (!$error) {
            $this->subPhase = 'SponsorGames';
            array_push ($messages , array('Sponsor Games Sub Phase'));
            $listSponsorGames = $this->forum_listSponsorGames($user_id) ;
            if (count($listSponsorGames)==0) {
                array_push ($messages , array($this->party[$user_id]->fullName().' has no senator who can sponsor games.'));
                $this->subPhase = 'ChangeLeader';
                array_push ($messages , array('Change Leader Sub Phase'));
            }
        }
        return $messages ;
    }
    
    /**
     * Lists all the senators who are able to sponsor games in party user_id 
     * @param type $user_id
     * @return array
     */
    public function forum_listSponsorGames ($user_id) {
        $result = array() ;
        if ( ($this->phase=='Forum') && ($this->subPhase=='SponsorGames') ) {
            foreach ($this->party[$user_id]->senators->cards as $senator) {
                if ($senator->inRome && $senator->treasury >=7) {
                    array_push($result , array('senatorID' => $senator->senatorID , 'name' => $senator->name , 'treasury' => $senator->treasury));
                }
            }
        }
        return $result ;
    }
    
    /**
     * Sponsor games
     * @param type $user_id
     * @param type $senatorRaw
     * @param type $type
     * @return array
     */
    public function forum_sponsorGames ($user_id , $senatorRaw , $type) {
        $type=(int)$type;
        $messages = array();
        $gamesEffects = array() ; $gamesEffects[7]= 1 ; $gamesEffects[13]= 2 ; $gamesEffects[18]= 3 ;
        $gamesName = array() ; $gamesName[7] = 'Slice & Dice' ; $gamesName[13] = 'Blood Fest' ; $gamesName[18] = 'Gladiator Gala' ; 
        if ( ($this->phase=='Forum') && ($this->subPhase=='SponsorGames') && ($this->forum_whoseInitiative()==$user_id) && ($type==7 || $type==13 || $type==18) ) {
            $senatorData = explode('|' , $senatorRaw);
            $senatorID = $senatorData[0] ;
            $senator= $this->getSenatorWithID($senatorID);
            if ($this->getPartyOfSenator($senator)->user_id == $user_id) {
                if ($senator->treasury>=$type) {
                    $senator->treasury-=$type ;
                    $this->unrest-=$gamesEffects[$type];
                    $senator->changePOP($gamesEffects[$type]);
                    array_push($messages , array($senator->name.' organises '.$gamesName[$type].', reducing the unrest by '.$gamesEffects[$type].' and gaining '.$gamesEffects[$type].' popularity.'));
                    $this->subPhase = 'ChangeLeader';
                    array_push ($messages , array('Change Leader Sub Phase'));
                } else {
                    array_push($messages , array($senator->name.' doesn\'t have enough money to sponsor these games.' , 'error' , $user_id));
                }
            } else {
                array_push($messages , array('Error - Wrong party' , 'error' , $user_id));
            }
        }
        return $messages ;
    }
    
    /**
     * Change leader and/or move to next initiative. If this is the last initiative, move to curia subPhase (a.k.a. "Putting Rome in order")
     * @param type $user_id
     * @param type $senator
     * @return array
     */
    public function forum_changeLeader($user_id , $senatorID) {
        $messages = array();
        $error = FALSE ;
        if ( ($this->phase=='Forum') && ($this->subPhase=='ChangeLeader') && ($this->forum_whoseInitiative()==$user_id) ) {
            if ($senatorID!='NO') {
                $senator = $this->getSenatorWithID($senatorID);
                if ($this->getPartyOfSenator($senator)->user_id==$user_id) {
                    if ($this->party[$user_id]->leader->senatorID != $senatorID) {
                        $this->party[$user_id]->leader = $senator ;
                        array_push($messages , array($senator->name.' is now the leader of '.$this->party[$user_id]->fullName()));
                    } else {
                        $error = TRUE ;
                        array_push($messages , array('Error - This senator is already the leader' , 'error' , $user_id));
                    }
                } else {
                    $error = TRUE ;
                    array_push($messages , array('Error - Wrong party' , 'error' , $user_id));
                }
            }
            if (!$error) {
                $this->initiative++ ;
                if ($this->initiative<=6) {
                    $this->subPhase = 'RollEvent';
                    $this->forum_initInitiativeBids();
                    array_push($messages , array('Initiative #'.$this->initiative , 'alert'));
                } else {
                    $this->subPhase = 'curia';
                    array_push($messages , array('All initiatives have been played, putting Rome in order.'));
                }
            }
        }
        return $messages ;
    }
    
    
    /************************************************************
     * Functions for REVOLUTION phase
     ************************************************************/

    /**
     * 
     * @param type $user_id
     * @param type $card_id
     * @return array
     */
    public function playStateman( $user_id , $card_id ) {
        $messages = array() ;
        $stateman = $this->party[$user_id]->hand->drawCardWithValue('id', $card_id);
        if ($stateman === FALSE ) {
            array_push($messages , array('The card is not in '.$this->party[$user_id]->fullName().'\'s hand','error'));
            return $messages ;   
        } else {
                if ($stateman->type!='Stateman') {
                    // This is not a stateman, put the card back and report an error
                    $this->party[$user_id]->hand->putOnTop($stateman);
                    array_push($messages , array('"'.$stateman->name.'" is not a stateman','error'));
                    return $messages ;
                } else {
                    // This is a Stateman, proceed
                    // First, get the stateman's family number
                    $family = $stateman->statemanFamily() ;
                    if ($family === FALSE) {
                        // This family is weird. Put the card back and report the error
                        $this->party[$user_id]->hand->putOnTop($stateman);
                        array_push($messages , array('Weird family.','error'));
                        return $messages ;
                    }
                    // Check if family is already in player's party
                    foreach ($this->party[$user_id]->senators->cards as $senator) {
                        if ($senator->senatorID == $family) {
                            // Family found in the player's party
                            $matchedFamily = $this->party[$user_id]->senators->drawCardWithValue('senatorID' , $family);
                            if ($matchedFamily === FALSE) {
                                $this->party[$user_id]->hand->putOnTop($stateman);
                                $this->party[$user_id]->senators->putOnTop($matchedFamily);
                                array_push($messages , array('Weird family.','error'));
                                return $messages ;
                            } else {
                                // SUCCESS : Family is in player's party - put it under the Stateman
                                $this->party[$user_id]->senators->putOnTop($stateman) ;
                                $stateman->controls->putOnTop($matchedFamily);
                                // Adjust Stateman's value that are below the Family's
                                if ($matchedFamily->priorConsul) {$stateman->priorConsul ;}
                                if ($matchedFamily->INF > $stateman->INF) {$stateman->INF = $matchedFamily->INF ;}
                                if ($matchedFamily->POP > $stateman->POP) {$stateman->POP = $matchedFamily->POP ;}
                                $stateman->treasury = $matchedFamily->treasury ;
                                $stateman->knights = $matchedFamily->knights ;
                                $stateman->office = $matchedFamily->office ;
                                $matchedFamily->resetSenator() ;
                                // The family was the party's leader
                                if ($this->party[$user_id]->leader->senatorID == $matchedFamily->senatorID) {
                                    $this->party[$user_id]->leader=$stateman;
                                }
                                array_push($messages , array($this->party[$user_id]->fullName().' plays Stateman '.$stateman->name.' on top of senator '.$matchedFamily->name));
                                return $messages ;
                            }
                        }
                    }
                    // Check if the family is unaligned in the forum
                    foreach ($this->forum->cards as $card) {
                        if ( ($card->type == 'Family') && ($card->senatorID == $family) ) {
                            $matchedFamily = $this->forum->drawCardWithValue('senatorID' , $family);
                            // SUCCESS : Family is unaligned in the forum - put it under the Stateman
                            $this->party[$user_id]->senators->putOnTop($stateman) ;
                            $stateman->controls->putOnTop($matchedFamily);
                            array_push($messages , array($this->party[$user_id]->fullName().' plays Stateman '.$stateman->name.' and gets the matching unaligned family from the Forum.'));
                            return $messages ;
                        }
                        
                    }
                    // SUCCESS : There was no matched family in the player's party or the Forum
                    $this->party[$user_id]->senators->putOnTop($stateman) ;
                    array_push($messages , array($this->party[$user_id]->fullName().' plays Stateman '.$stateman->name));
                    return $messages ;
                }
        }
    }
    
    /**
     * 
     * @param type $user_id
     * @param type $card_id
     * @param type $senator_id
     * @return array
     */
    public function playConcession( $user_id , $card_id , $senator_id) {
        $messages = array() ;
        $partyOfTargetSenator = $this->getPartyOfSenatorWithID($senator_id) ;
        if (!$partyOfTargetSenator || $partyOfTargetSenator=='forum') {
            array_push($messages , array('This senator is not in play','alert')) ;
            return $messages;
        }
        $senator = $this->party[$user_id]->senators->drawCardWithValue('senatorID', $senator_id);
        if ($senator === FALSE ) {
            array_push($messages , array('The senator is not in '.$this->party[$user_id]->fullName().'\'s party' , 'error'));
            return $messages ;   
        }
        $concession = $this->party[$user_id]->hand->drawCardWithValue('id', $card_id);
        if ($concession === FALSE ) {
            $this->party[$user_id]->senators->putOnTop($senator);
            array_push($messages , array('The card is not in '.$this->party[$user_id]->fullName().'\'s hand' , 'error'));
            return $messages ;   
        } else {
            if ($concession->type!='Concession') {
               $this->party[$user_id]->senators->putOnTop($senator);
               $this->party[$user_id]->hand->putOnTop($concession);
               array_push($messages , array($concession->name.'" is not a concession' , 'error'));
               return $messages ;
            } elseif($concession->name=='LAND COMMISSIONER' && !$this->landCommissionerPlaybale()) {
               $this->party[$user_id]->senators->putOnTop($senator);
               $this->party[$user_id]->hand->putOnTop($concession);
               array_push($messages , array('The Land commissioner can only be played while Land bills are enacted.','error'));
               return $messages ;
            } else {
                $senator->controls->putOnTop($concession);
                $this->party[$user_id]->senators->putOnTop($senator);
                array_push($messages , array($this->party[$user_id]->fullName().' plays Concession '.$concession->name.' on Senator '.$senator->name));
                return $messages ;
            }
        }
    }
}