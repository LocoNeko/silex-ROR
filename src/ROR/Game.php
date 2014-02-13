<?php
namespace ROR;

/** 
 * int $id : game id<br>
 * string $name : game name
 * $turn (int)
 * $phase (string) : the current turn phase, can have any value defined in $VALID_PHASES
 * $subPhase (string) : the current sub phase of the current phase
 * $initiative (int) : Current initiative from 1 to 6
 * $scenario (string), can have any value defined in $VALID_SCENARIOS
 * $variants (array) : an array of values that must be in $VALID_SCENARIOS
 * $unrest (int) : current unrest level
 * $treasury (int) : current treasury
 * $nbPlayers (int) : number of human players from 1 to 6
 * --- FORUM PHASE ---
 * $currentBidder (user_id) : The user_id of the party which turn it currently is to bid
 * $persuasionTarget (Senator) : The senator being persuaded
 * --- THE PARTIES ---
 * $party array (Party) /!\ key : user_id ; value : party_name
 * --- THE DECKS ---
 * $drawDeck
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
 * --- LAND BILLS, EVENTS, POPULATION TABLE , LEGIONS & FLEETS ---
 * $landBill array (int)=>(int) : landBill[X]=Y means there is Y land bills of type X
 * $events array (int [event number] => array(name , increased_name , max_level , level)) : array of events
 * $landBills array (int [land bill level] => array ('cost' , 'duration' , 'sponsor' , 'cosponsor' , 'against' , 'unrest' , 'repeal sponsor' , 'repeal vote' , 'repeal unrest'))
 * $legion array of legion objects
 * $fleet array of fleet objects
 * --- SENATE ---
 * $steppedDown : array of SenatorIDs of Senators who have stepped down during this Senate phase
 * $proposals : array of proposals for the turn, see proposal class
 * $laws : array of laws in play
 */
class Game 
{
    /*
     * Some default values and validators
     */
    public static $VALID_PHASES = array('Setup','Mortality','Revenue','Forum','Population','Senate','Combat','Revolution');
    public static $VALID_ACTIONS = array('Bid','RollEvent','Persuasion','Knights','ChangeLeader','SponsorGames','curia');
    public static $DEFAULT_PARTY_NAMES = array ('Imperials' , 'Plutocrats' , 'Conservatives' , 'Populists' , 'Romulians' , 'Remians');
    public static $VALID_SCENARIOS = array('EarlyRepublic');
    public static $VALID_VARIANTS = array('Pontifex Maximus' , 'Provincial Wars' , 'Rebel governors' , 'Legionary disbandment' , 'Advocates' , 'Passing Laws');
    
    private $id ;
    public $name ;
    public $turn , $phase , $subPhase , $initiative ;
    public $scenario , $variants , $unrest , $treasury , $nbPlayers ;
    public $currentBidder , $persuasionTarget ;
    public $party ;
    public $drawDeck , $earlyRepublic , $middleRepublic , $lateRepublic , $discard , $unplayedProvinces , $inactiveWars , $activeWars , $imminentWars , $unprosecutedWars , $forum , $curia ;
    public $landBill ;
    public $events , $eventTable ;
    public $populationTable , $appealTable , $landBillsTable ;
    public $legion, $fleet ;
    public $steppedDown, $proposals, $laws ;
    
     /************************************************************
     * General functions
     ************************************************************/

    /**
     * Creates the game
     * 
     * @param string $name Game name
     * @param string $scenario Scenario name
     * @param array $partyNames array of 'user_id'=>'party name' the order is the standard order of play
     * @param array $userNames array of 'user_id'=>'user name'
     * @return mixed FALSE (failure) OR $messages array (success)
     */
    public function create($name , $scenario , $partyNames , $userNames , $pickedVariants) {
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
        $this->variants = explode(',' , $pickedVariants) ;
        foreach($this->variants as $key=>$variant) {
            if (!in_array($variant, self::$VALID_VARIANTS)) {
                array_push($messages , array(sprintf(_('The %s variant wasn\'t recognised and has been removed') , $variant) , 'alert') ) ;
                unset($this->variants[$key]) ;
            }
        }
        $this->unrest = 0 ;
        $this->treasury = 100 ;
        $this->nbPlayers = count($partyNames);
        if ( ($this->nbPlayers < 3) || ($this->nbPlayers > 6) ) {
            return FALSE;
        }
        $this->currentBidder = NULL;
        $this->persuasionTarget = NULL;
        $this->drawDeck = new Deck ;
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
        /*
         *  Create events
         */
        $this->events = array() ;
        $this->eventTable = array() ;
        $this->createEvents();
        /*
         *  Create Tables : Population , Appeal, Land bills
         */
        $this->populationTable = array();
        $this->createPopulationTable();
        $this->appealTable = array();
        $this->createAppealTable();
        $this->landBillsTable = array() ;
        $this->createLandBillsTable();
        /*
         *  Legions and Fleets
         */
        $this->legion = array () ;
        $this->fleet = array () ;
        for ($i=1 ; $i<=25 ; $i++) {
            $this->legion[$i] = new Legion () ;
            $this->legion[$i]->create($i);
            $this->fleet[$i] = new Fleet () ;
            $this->fleet[$i]->create($i) ;
	}
        for ($i=1 ; $i<=4 ; $i++) {
            $this->legion[$i]->location = 'Rome';
        }
        array_push($messages , array(_('Rome starts with 4 Legions')) ) ;
        $this->steppedDown = array() ;
        $this->proposals = array() ;
        $this->laws = array() ;
        /*
         *  Creating parties
         */
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
        array_push($messages , array(_('SETUP PHASE') , 'alert') ) ;
        array_push($messages , array(_('The First Punic war is now an inactive war')) ) ;
        // TO DO : handle Era Ends properly
        $this->discard->putOnTop($this->earlyRepublic->drawCardWithValue('name', 'ERA ENDS')) ;
        /* 
         * Give initial senators to parties
         * - Create a temporary deck with all 20 families (not statesmen)
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
                array_push( $messages, array ( sprintf(_('{%s} receives senator %s') , $party->user_id , $party->senators->cards[0]->name) ) ) ;
            }
        }
        while (count($tempDeck->cards)>0) {
            $this->earlyRepublic->putOnTop($tempDeck->drawTopCard());
        }
        /*
         * Give 3 cards to each players, only keeping Faction and Statesman cards
         */
        foreach ($this->party as $key=>$party) {
            $cardsLeftToDraw = 3 ;
            while ($cardsLeftToDraw>0) {
                $this->earlyRepublic->shuffle();
                $card = $this->earlyRepublic->drawTopCard() ;
                switch ($card->type) {
                    case 'Faction' :
                    case 'Statesman' :
                    case 'Concession' :
                        $party->hand->putOnTop($card);
                        $cardsLeftToDraw--;
                        break ;
                    default :
                        $this->earlyRepublic->putOnTop($card);
                }
            }
            array_push($messages , array( sprintf(_('{%s} receives 3 cards') , $party->user_id)) ) ;
        }
        /*
         * Put all remaining cards of the Early Republic in the draw deck
         */
        while (count($this->earlyRepublic->cards)>0) {
            $this->drawDeck->putOnTop($this->earlyRepublic->drawTopCard()) ;
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
        array_push($messages , array(sprintf(_('%s ({%s}) becomes temporary Rome consul') , $temporaryRomeConsul->name , $party->user_id) , 'alert'));
        return $messages ;
    }
    
    /*
     * Convenience function (could be inside CreateGame)
     * The event file should have 4 columns :
     * Event number (should be VG card number) ; event name ; increased event name ; description ; increased event description ; maximum level of the event (0 if none)
     * The event table file should have 3 columns :
     * event number for Early Republic ; Middle Republic ; Late Republic 
     */
    public function createEvents() {
        $eventsFilePointer = fopen(dirname(__FILE__).'/../../data/events.csv', 'r');
        if (!$eventsFilePointer) {
            throw new Exception(_('Could not open the events file'));
        }
        while (($data = fgetcsv($eventsFilePointer, 0, ";")) !== FALSE) {
            $this->events[(int)$data[0]] = array( 'name' => $data[1] , 'increased_name' => $data[2] , 'description' => $data[3] , 'increased_description' => $data[4] , 'max_level' => $data[5] , 'level' => 0);
        }
        fclose($eventsFilePointer);
        $eventTableFilePointer = fopen(dirname(__FILE__).'/../../data/eventTable.csv', 'r');
        if (!$eventTableFilePointer) {
            throw new Exception(_('Could not open the event table file'));
        }
        $i=3;
        while (($data = fgetcsv($eventTableFilePointer, 0, ";")) !== FALSE) {
            $this->eventTable[$i]['EarlyRepublic'] = $data[0] ;
            $this->eventTable[$i]['MiddleRepublic'] = $data[1] ;
            $this->eventTable[$i]['LateRepublic'] = $data[2] ;
            $i++;
        }
        fclose($eventTableFilePointer);
    }
    
    /**
     * Reads the populationTable csv file and creates an array Unrest level => array of effects
     * Effects are : +# increase unrest by # , -# decrease unrest by # , MS manpower shortage , NR no recruitment , Mob
     * @throws Exception
     */
    public function createPopulationTable() {
        $filePointer = fopen(dirname(__FILE__).'/../../data/populationTable.csv', 'r');
        if (!$filePointer) {
            throw new Exception(_('Could not open the Population table file'));
        }
        while (($data = fgetcsv($filePointer, 0, ";")) !== FALSE) {
            $this->populationTable[$data[0]] = array();
            $effects = explode(',', $data[1]);
            foreach($effects as $effect) {
                array_push($this->populationTable[$data[0]] , $effect);
            }
        }
        fclose($filePointer);
    }

    /**
     * Reads the appealTable csv file and creates an array appealTable : keys = roll , values = array ('votes' => +/- votes , 'special' => NULL|'killed'|'freed' )
     * @throws Exception
     */
    public function createAppealTable() {
        $filePointer = fopen(dirname(__FILE__).'/../../data/appealTable.csv', 'r');
        if (!$filePointer) {
            throw new Exception(_('Could not open the Appeal table file'));
        }
        while (($data = fgetcsv($filePointer, 0, ";")) !== FALSE) {
            $this->appealTable[$data[0]] = array();
            array_push($this->appealTable[$data[0]] , array('votes' => $data[1] , 'special' => (isset($data[2]) ? $data[2] : NULL)));
        }
        fclose($filePointer);
    }

    
    public function createLandBillsTable() {
        $filePointer = fopen(dirname(__FILE__).'/../../data/landBills.csv', 'r');
        if (!$filePointer) {
            throw new Exception(_('Could not open the Land Bills table file'));
        }
        while (($data = fgetcsv($filePointer, 0, ";")) !== FALSE) {
            if (substr($data[0],0,1)!='#') {
                $this->landBillsTable[$data[0]] = array();
                array_push($this->landBillsTable[$data[0]] , array(
                        'cost' => $data[1] ,
                        'duration' => $data[2] ,
                        'sponsor' => $data[3] ,
                        'cosponsor' => $data[4] ,
                        'against' => $data[5] ,
                        'unrest' => $data[6] ,
                        'repeal sponsor' => $data[7] ,
                        'repeal vote' => $data[8] ,
                        'repeal unrest' => $data[9]
                    )
                );
            }
        }
        fclose($filePointer);
    }
    
    /**
     * Number of legions (location is NOT NULL)
     * @return int
     */
    public function getNbOfLegions() {
        $result = 0 ;
        foreach($this->legion as $legion) {
            if ($legion->location <> NULL ) {
                $result++;
            }
        }
        return $result ;
    }
    
    /**
     * Number of fleets (location is NOT NULL)
     * @return int
     */
    public function getNbOfFleets() {
        $result = 0 ;
        foreach($this->fleet as $fleet) {
            if ($fleet->location <> NULL ) {
                $result++;
            }
        }
        return $result ;
    }
    
    public function getProvinceGarrisons($province) {
        $result = 0 ;
        if ($province->type == 'Province') {
            $id = $province->id;
            foreach ($this->legion as $legion) {
                if ($legion->location == $id) {
                    $result++;
                }
            }
        } else {
            return FALSE ;
        }
        return $result;
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
     * Returns the name of a senator, with the name of his party (or 'forum')
     * @param type $senatorID The ID of the senator 
     * @return boolean string or FALSE
     */
    public function getSenatorFullName($senatorID) {
        $senator = $this->getSenatorWithID($senatorID) ;
        if ($senator!==FALSE) {
            $party = $this->getPartyOfSenator($senator) ;
            if ($party!==FALSE) {
                $partyName = ($party=='forum' ? '(forum)' : ' ('.$party->fullName().') ');
                return $senator->name.$partyName;
            }
            return FALSE ;
        }
        return FALSE ;
    }
    
    /**
     * 
     * @param string $senatorID The ID of the Senator
     * @return boolean $senator or FALSE
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
        if ($senator->type=='Statesman') {
            // This Statesman has some personal enemies/friends
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
     * Returns a list of all aligned senators
     * @return array
     */
    public function getAllAlignedSenators($inRomeFlag=TRUE) {
        $result=array();
        foreach ($this->party as $party) {
            foreach ($party->senators->cards as $senator) {
                if ($senator->inRome() == $inRomeFlag) {
                    array_push($result , $senator) ;
                }
            }
        }
        return $result ;
    }
    
    /**
     * Returns the Senator, Party, and user_id of the HRAO
     * if $presiding is TRUE, ignores senators who have stepped down
     * @return array 'senator' , 'party' , 'user_id'
     */
    public function getHRAO($presiding=FALSE) {
        /*
         *  Reminder : $VALID_OFFICES = array('Dictator', 'Rome Consul' , 'Field Consul' , 'Censor' , 'Master of Horse' , 'Pontifex Maximus');
         */
        $allSenators = array ();
        $rankedSenators = array() ;
        foreach ($this->party as $user_id=>$party) {
            foreach ($party->senators->cards as $senator) {
                if ( $senator->inRome() && $senator->office!==NULL && (!$presiding || !in_array($senator->senatorID , $this->steppedDown) )) {
                    // This will put an array ('senator','party','user_id') in another array $rankedSenators, ordered by Valid Offices keys (Dictator first, Rome Consul second, etc...
                    $rankedSenators[array_search($senator->office, Senator::$VALID_OFFICES)] = array ('senator' => $senator , 'party' => $party , 'user_id'=>$user_id) ;
                }
                /*
                 * In case the HRAO couldn't be determined through offices because no official is in Rome
                 * we will need senators ordered by INF, so we use this loop to prepare that list
                 */
                array_push($allSenators , $senator);
            }
        }
        // We found at least one ranked Senator
        if (count($rankedSenators)>0) {
            // If we are looking for the presiding magistrate, The Censor must be returned during the Senate phase, Prosecutions subPhase
            if ( $presiding && $this->phase=='Senate' && $this->subPhase=='Prosecutions' && isset($rankedSenators[3]) ) {
                return $rankedSenators[3] ;
            // Otherwise, the HRAO
            } else {
                return array_shift($rankedSenators) ;
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
        // If we are looking for the Presiding Magistrate, we must ignore senators who have stepped down
        // The censor for prosecutions is completely irrelevant, since if we are here, there is no Censor, therefore no prosecutions...
        if ($presiding) {
            while (in_array($allSenators[0]->senatorID , $this->steppedDown)) {
                array_shift($allSenators) ;
            }
        }
        $senator = $allSenators[0] ;
        $party = $this->getPartyOfSenator($senator) ;
        $user_id = $party->user_id ;
        return array ('senator' => $senator , 'party' => $party , 'user_id'=>$user_id) ;
    }
    
    /**
     * 
     * @param user_id $user_id
     * @param \ROR\Senator $statesman
     * @return array 'flag' = TRUE|FALSE , 'message'
     */
    public function statesmanPlayable ($user_id , Senator $statesman) {
        if ($statesman->type != 'Statesman') {
            return array('flag' => FALSE, 'message' => _('ERROR'));
        }
        foreach ($this->party as $otherUser_id=>$party) {
            foreach ($party->senators->cards as $senator) {
                // Check if the family is already in play
                if ( ($senator->type == 'Family') && ($senator->senatorID == $statesman->statesmanFamily()) ) {
                    if ($otherUser_id != $user_id) {
                        return array('flag' => FALSE , 'message' => sprintf(_('The Family is already in party %s') , $party->name) );
                    } else {
                        return array('flag' => TRUE , 'message' => _('You have the family'));
                    }
                }
                // Check if a related Statesman is already in play
                if ( ($senator->type == 'Statesman') && ($senator->statesmanFamily() == $statesman->statesmanFamily()) ) {
                    if ( ($statesman->statesmanFamily()!=25) && ($statesman->statesmanFamily()!=29) ) {
                        return array('flag' => FALSE , 'message' => sprintf(_('The related statesman %s is already in play.' , $senator->name)));
                    } else {
                        // The other brother is in play : this is valid
                        return array('flag' => TRUE , 'message' => sprintf(_('%s playable, but the other brother %s is in play.') , $statesman->name , $senator->name));
                    }
                }
            }
        }
        foreach ($this->forum->cards as $card) {
            if ($card->type=='Senator' && ($card->senatorID == $statesman->statesmanFamily()) ) {
                return array('flag' => TRUE , 'message' => _('The corresponding family card is in the forum'));
            }
        }
        return array('flag' => TRUE , 'message' => _('The corresponding family card is not in play') );
    }
    
    /**
     * returns array of user_id from HRAO, clockwise in the same order as the array $this->party (order of joining game)
     * @return array 
     */
    public function getOrderOfPlay() {
        $result = array_keys ($this->party) ;
        $user_idHRAO = $this->getHRAO()['user_id'];
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
        $result = array() ;
        $result['messages'] = array();
        $orderOfPlay = $this->getOrderOfPlay() ;
        while ($orderOfPlay[0]!=$user_id) {
            array_push($orderOfPlay , array_shift($orderOfPlay) );
        }
        array_push($orderOfPlay , array_shift($orderOfPlay) );
        // We are bidding
        if ($this->subPhase=='RollEvent') {
            // Whatever happens, we must never pass the HRAO, as all bids will have to stop there anyway
            $firstPlayer = $this->getHRAO()['user_id'] ;
            $highestBid = $this->forum_highestBidder() ;
            // Skip all parties whose richest senator does not have more money than the current highest bid
            // Don't do anything if we already have a winner (forum_whoseInitiative()==FALSE)
            while ($orderOfPlay[0] != $firstPlayer && $this->forum_whoseInitiative()==FALSE) {
                $richestSenator = $this->party[$orderOfPlay[0]]->getRichestSenator();
                if ($richestSenator['amount']<=$highestBid['bid']) {
                    array_push($result['messages'] , array( sprintf(_('Skipping {%s} : not enough talents to bid.') , $this->party[$orderOfPlay[0]]->user_id)) );
                    $this->party[$orderOfPlay[0]]->bidDone=TRUE;
                    array_push($orderOfPlay , array_shift($orderOfPlay) );
                } else {
                    break ;
                }
            }
        } elseif ($this->subPhase=='Persuasion') {
            // Skip all parties who have no money in their party treasury
            do {
                if ($this->party[$orderOfPlay[0]]->treasury == 0) {
                    array_push( $result['messages'], array(sprintf(_('Skipping {%s} : no talents in the party treasury to counter-bribe.'),$this->party[$orderOfPlay[0]]->user_id)) );
                    array_push( $orderOfPlay, array_shift($orderOfPlay) );
                } else {
                    break ;
                }
            // Whatever happens, we must never pass the player with the initiative, as all bids will have to stop there anyway
            } while ($orderOfPlay[0] != $this->forum_whoseInitiative()) ;
        }
        $result['user_id'] = $orderOfPlay[0];
        return $result ;
    }
    
    /**
     * Returns the $user_id of the first player (in order of play) who hasn't finished his phase, or FALSE if all players are done for this phase
     * @return string|boolean
     */
    public function whoseTurn() {
        $currentOrder = $this->getOrderOfPlay() ;
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
     * Whether or not the Land Commissioner concession is playable ( which means a Land bill is in play)
     * @return bool
     */
    public function landCommissionerPlayable () {
        return ( (array_sum($this->landBill) > 0) ? TRUE : FALSE ) ;
    }
    
    /**
     * 
     * @param integer $nb = Number of dice to roll (1 to 3)
     * @param type $evilOmensEffect = Whether evil omens affect the roll by -1 , +1 or 0
     * @return array 'total' => the total roll , 'x' => value of die X so we can obtain 1 white die & 2 black dice
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
        $result['total'] += $evilOmensEffect * $this->getEventLevel('name' , 'Evil Omens');
        return $result ;
    }
    
    /**
     * Convenience function to get a straight 1 die roll
     * @param type $evilOmensEffect
     * @return int The result
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
     * Useful to send a different message to non-phasing players
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
     * Returns the current level of the event if it's in play, 0 if it's not, FALSE if the request made no sense
     * @param string $type can be 'name' or 'number'
     * @param mixed $search The name of the event <b>OR</b> its number, based on the value of $type
     * @return mixed The event's level (<b>0</b> if not in play) or FALSE if $type was wrong
     */
    public function getEventLevel ($type , $search) {
        if ($type=='name') {
            foreach ($this->events as $event) {
                if ($event['name'] == $search) {
                    return $event['level'];
                }
            }
            return 0 ;
        } elseif ($type=='number') {
            return $this->events[$search]['level'] ;
        }
        return FALSE ;
    }
    
    /**
     * Returns the current total drought level, both from the event card and wars that cause droughts.
     * @return integer level
     */
    public function getTotalDroughtLevel() {
        $level = $this->getEventLevel('name' , 'Drought') ;
        //TO DO : Check wars that cause drought even when inactive
        foreach ($this->activeWars->cards as $war) {
            if (strstr($war->causes,'drought')!==FALSE) {
                $level++;
            }
        }
        foreach ($this->unprosecutedWars->cards as $war) {
            if (strstr($war->causes,'drought')!==FALSE) {
                $level++;
            }
        }
        return $level ;
    }
    
    /**
     * Goes through all public decks and returns when it finds a card that has a $property equal to $value. 
     * 
     * @param type $property
     * @param type $value
     * @return array 'card' => $card object , 'where' => 'senator|forum|curia|a war deck...' , 'deck' => deck object , and 'senator' & 'party' if 'where' is 'senator'.<br>
     * Warning : The party CAN BE 'forum'<br>
     * returns FALSE if the card was not found
     */
    public function getSpecificCard($property , $value) {
        foreach ($this->party as $party) {
            foreach ($party->senators->cards as $senator) {
                foreach ($senator->controls->cards as $card) {
                    if ($card->$property == $value) {
                        return array ('card' => $card , 'where' => 'senator' , 'deck' => $senator->controls , 'senator' => $senator , 'party' => $senator );
                    }
                }
            }
        }
        foreach ($this->forum->cards as $card) {
            if ($card->$property == $value) {
                return array ('card' => $card , 'where' => 'forum' , 'deck' => $this->forum);
            }
            if ($card->type=='Family' || $card->type=='Statesman') {
                foreach ($card->controls->cards as $card2) {
                    if ($card2->$property == $value) {
                        return array ('card' => $card2 , 'where' => 'senator' , 'deck' => $card->controls , 'senator' => $card , 'party' => 'forum' );
                    }
                }
            }
        }
        foreach ($this->curia->cards as $card) {
            if ($card->$property == $value) {
                return array ('card' => $card , 'where' => 'curia' , 'deck' => $this->curia);
            }
        }
        foreach ($this->inactiveWars->cards as $card) {
            if ($card->$property == $value) {
                return array ('card' => $card , 'where' => 'inactiveWars' , 'deck' => $this->inactiveWars);
            }
        }
        foreach ($this->activeWars->cards as $card) {
            if ($card->$property == $value) {
                return array ('card' => $card , 'where' => 'activeWars' , 'deck' => $this->activeWars);
            }
        }
        foreach ($this->imminentWars->cards as $card) {
            if ($card->$property == $value) {
                return array ('card' => $card , 'where' => 'imminentWars' , 'deck' => $this->imminentWars);
            }
        }
        foreach ($this->unprosecutedWars->cards as $card) {
            if ($card->$property == $value) {
                return array ('card' => $card , 'where' => 'unprosecutedWars' , 'deck' => $this->unprosecutedWars);
            }
        }
        return FALSE ;
    }

    /**
     * This adds the ability to pay ransom of Senators captured during battle or barbarian raids<br>
     * This is a global function, called from the main view interface to allow for payment at any time.<br>
     * TO DO : Killing of captives should be checked when needed (defeat of the war if captured during battle, or Forum phase if captured by Barbarians)
     * @param string $user_id the user id
     * @return array 'captiveOf' , 'senatorID' , 'ransom'
     */
    public function getListOfCaptives($user_id) {
        $result = array () ;
        foreach($this->party[$user_id]->senators->cards as $senator) {
            if($senator->captive!==FALSE) {
                array_push( array('captiveOf' => $senator->captive , 'senatorID' => $senator->senatorID , 'ransom' => max(10 , 2 * $senator->INF)));
            }
        }
        if (count($result)==0) {
            $result = FALSE ;
        }
        return $result ;
    }
    
    /**
     * 
     * @param Conflict $conflict
     * @return int|boolean
     */
    public function getNumberOfMatchedConflicts($conflict) {
        $result = 0 ;
        if ($conflict->type != 'Conflict') {
            return FALSE ;
        }
        foreach ($this->activeWars as $active) {
            if ( $active->matches == $conflict->matches) {
                $result++;
            }
        }
        foreach ($this->unprosecutedWars  as $unprosecuted) {
            if ( $unprosecuted->matches == $conflict->matches ) {
                $result++;
            }
        }
        // No conflict matching this has been found
        if ($result==0) {
            return FALSE ;
        }
        return $result ;
    }
    
    /**
     * 
     * @param Conflict $conflict
     * @return aray|boolean Array("land" , "support" , "fleet") | FALSE
     */
    public function getModifiedConflictStrength($conflict) {
        $result = array () ;
        if ($conflict->type != 'Conflict') {
            return FALSE ;
        }
        $result['land'] = $conflict->land ;
        $result['support'] = $conflict->support ;
        $result['fleet'] = $conflict->fleet ;
        $matchedConflicts = $this->getNumberOfMatchedConflicts($conflict) ;
        $result['land'] *= $matchedConflicts ;
        $result['fleet'] *= $matchedConflicts ;
        foreach ($conflict->leaders->cards as $leader) {
            $result['land']+=$leader->strength ;
            $result['fleet']+=$leader->strength ;
        }
        return $result ;
    }
    
    /************************************************************
     * Functions for SETUP phase
     ************************************************************/
    
    /**
     * Set party leader of $user_id to senator with $senatorID<br>
     * phase_done is set to TRUE for user_id<br>
     * if all players have phase_done==true, move to next subPhase 'PlayCards'
     * @param type $user_id The user_id fo the player setting his party leader
     * @param type $senatorID The senatorID of the senator to be appointed party leader
     * @return type array messages in the form of arrays ('message','type of message','recipients')
     */
    public function setup_setPartyLeader( $user_id , $senatorID ) {
        if ( ($this->phase=='Setup') && ($this->subPhase=='PickLeaders') && ($this->party[$user_id]->leader === NULL) ) {
            foreach ($this->party[$user_id]->senators->cards as $senator) {
                if ($senator->senatorID == $senatorID) {
                    $this->party[$user_id]->leader = $senator ;
                    $card = $this->party[$user_id]->senators->drawCardWithValue('senatorID' , $senatorID);
                    $this->party[$user_id]->senators->putOnTop($card);
                    $this->party[$user_id]->phase_done = TRUE ;
                    if ($this->whoseTurn() === FALSE ) {
                        $this->subPhase = 'PlayCards' ;
                        $this->resetPhaseDone() ;
                    }
                    return array(array(sprintf(_(' %s is the new leader of party %s') , $senator->name , $this->party[$user_id]->name , 'alert')) );
                }
            }
            return array(array(_('Undocumented error - party leader not set.'), 'error', $user_id));
        }
    }
    
    /**
     * Returns a list of possible actions during the special setup machination phase :<br>
     * - Statemen<br>
     * - Concessions
     * @param string $user_id the player's user_id
     * @return array ('action' , 'card_id' , 'message')<br>
     * 'action' can be Statesman|Concession|Done
     */
    public function setup_possibleActions($user_id) {
        $result = array() ;
        foreach ($this->party[$user_id]->hand->cards as $card) {
            if ( (($card->type == 'Statesman') && $this->statesmanPlayable($user_id, $card)['flag']) ) {
                array_push($result, array('action' => 'Statesman' , 'card_id' => $card->id , 'message' => $card->name)) ;
            } elseif ($card->type == 'Concession') {
                // The Land Commissioner is not playable without Land bills in play
                if ($card->name!='LAND COMMISSIONER' || $this->landCommissionerPlayable()) {
                    array_push($result, array('action' => 'Concession' , 'card_id' => $card->id , 'message' => $card->name)) ;                
                }
            }
        }
        return $result;
    }
    
    /**
     * Setup is finished for this player, if all players are finished, executes mortality subPhase
     * @param type $user_id
     * @return type array messages
     */
    public function setup_Finished($user_id) {
        if ( ($this->phase=='Setup') && ($this->subPhase=='PlayCards') ) {
            $this->party[$user_id]->phase_done = TRUE ;
        }
        if ($this->whoseTurn()===FALSE) {
            return $this->mortality_base();
        }
        return array();
    }
    
    /**
     * setup_view returns all the data needed to render setup templates. The function returns an array $output :
     * $output['state'] (Mandatory) : gives the name of the current state to be rendered
     * $output['X'] : an array of values 
     * - X is the name of the component to be rendered
     * - 'values' is the actual content : text for a text, list of options for select, etc...
     * @param string $user_id
     * @return array $output
     */
    public function setup_view($user_id) {
        $output = array() ;
        
        // PickLeaders
        if ( ($this->phase=='Setup') && ($this->subPhase=='PickLeaders') ) {
            
            // Not your turn
            if ( $user_id != $this->whoseTurn()) {
                $output['state'] = 'Pick Leaders - Waiting';
                // Give the name of the player we are waiting for
                $output['Text'] = $this->party[$this->whoseTurn()]->fullName() ;
            
            // Your turn : give a list of Senators to pick from
            } else {
                $output['state'] = 'Pick Leaders - Picking';
                $output['senatorList'] = array () ;
                foreach ($this->party[$user_id]->senators->cards as $senator) {
                    array_push ($output['senatorList'] , array('SenatorID' => $senator->senatorID , 'name' => $senator->name) );
                }
            }
            
        // PlayCards
        } elseif ( ($this->phase=='Setup') && ($this->subPhase=='PlayCards') ) {
            // Not your turn
            if ( $user_id != $this->whoseTurn()) {
                $output['state'] = 'Play Cards - Waiting';
                // Give the name of the player we are waiting for
                $output['Text'] = $this->party[$this->whoseTurn()]->fullName() ;
            
            // Your turn : give a list of options
            } else {
                $output['state'] = 'Play Cards';
                $output['Statemen'] = array() ;
                $output['Concessions'] = array() ;
                foreach ($this->setup_possibleActions($user_id) as $possibleAction) {
                    if ($possibleAction['action']=='Statesman') {
                        array_push($output['Statemen'] , array('card_id' => $possibleAction['card_id'] , 'message' => $possibleAction['message']));
                    }
                    if ($possibleAction['action']=='Concession') {
                        array_push($output['Concessions'] , array('card_id' => $possibleAction['card_id'] , 'message' => $possibleAction['message']));
                    }
                }
                $output['PlayStatemen'] = (count($output['Statemen'])>0) ;
                $output['PlayConcessions'] = (count($output['Concessions'])>0) ;
                $output['Senators'] = $this->view_party($user_id) ;
            }
        } else {
            $output['state'] = 'Error';
        }
        return $output ;
    }

    /************************************************************
     * Functions for MORTALITY phase
     ************************************************************/

    /**
     * Handles :<br>
     * - Imminent wars<br>
     * - Mortality.
     * Mortality uses the mortality_killSenator function<br>
     * Once finished, moves to Revenue phase<br>
     * @return array messages
     */
    public function mortality_base() {
        if ( ($this->whoseTurn() === FALSE) && ($this->phase=='Setup') )  {
            $messages = array() ;
            array_push($messages , array(_('Setup phase is finished. Starting Mortality phase.')));
            $this->phase = 'Mortality';
            array_push($messages , array(_('MORTALITY PHASE'),'alert'));
            
            // Activate imminent wars
            if (count($this->imminentWars->cards)==0) {
               array_push($messages , array(_('There is no imminent conflict to activate.'))); 
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
                    array_push($messages , array(sprintf(_('Imminent conflict %s has been activated.') , $conflict->name),'alert') );
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
                    $returnedMessage= $this->mortality_killSenator((string)$chit) ;
                    array_push($messages , array(sprintf(_('Chit drawn : %s. %s'), $chit , $returnedMessage[0]) , (isset($returnedMessage[1]) ? $returnedMessage[1] : NULL) ));
                } else {
                    array_push($messages , array(sprintf(_('Chit drawn : %s'),$chit)));
                }
            }
            array_push($messages , array(_('Mortality phase is finished. Starting revenue phase.')));
            $this->phase = 'Revenue';
            array_push($messages , array(_('REVENUE PHASE'),'alert'));
            $moreMessages = $this->revenue_init();
            foreach($moreMessages as $message) {
                array_push($messages , $message) ;
            }
            return $messages ;
        }
    }
    
    /**
     * Draw $qty mortality chits, returns an array
     * @param int $qty The number of chits to draw
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
     * Kills the senator with $senatorID. This function handles :<br>
     * - Brothers<br>
     * - Statemen<br>
     * - Party leader<br>
     * - Where senator and controlled cards go (forum, curia, discard)
     * @param string $senatorID The SenatorID of the dead senator
     * @return array messages
     */
    public function mortality_killSenator($senatorID) {
        $message = '' ;
        // $deadSenator needs to be an array, as 2 brothers could be in play
        $deadSenators = array() ;
        foreach($this->party as $party) {
            foreach ($party->senators->cards as $senator) {
                if ( ($senator->type == 'Statesman') && ($senator->statesmanFamily() == $senatorID ) ) {
                    array_push($deadSenators , $senator) ;
                } elseif ( ($senator->type == 'Family') && ($senator->senatorID == $senatorID) ) {
                    array_push($deadSenators , $senator) ;
                }
            }
        }
        // Returns either no dead (Senator not in play), 1 dead (found just 1 senator matching the chit), or pick 1 of two brothers if they are both legally in play
        if (count($deadSenators)==0) {
            return array(_('This senator is not in Play, nobody dies.')) ;
        } elseif (count($deadSenators)>1) {
            // Pick one of two brothers
            $deadSenator = array_rand($deadSenators) ;
            $senatorID=$deadSenator->senatorID ;
            $message.=_(' The two brothers are in play. ') ;
        } else {
            $deadSenator = $deadSenators[0];
        }
        $party = $this->getPartyOfSenator($deadSenator) ;
        if ($party === FALSE) {
            return array(_('ERROR retrieving the party of the dead Senator'),'error');
        }
        if ($deadSenator->type == 'Statesman') {
            // Death of a Statesman
            $deadStatesman = $party->senators->drawCardWithValue('senatorID',$deadSenator->senatorID) ;
            $deadStatesman->resetSenator();
            $this->discard->putOnTop($deadStatesman);
            $message.=sprintf(_('%s of party {%s} dies. The card is discarded. ') , $deadStatesman->name , $party->user_id) ;
        } else {
            // Death of a normal Senator
            $deadSenator->resetSenator() ;
            if ($party->leader->senatorID == $senatorID) {
                $message.=sprintf(_('%s of party {%s} dies. This senator was party leader, the family stays in the party. ') , $deadSenator->name , $party->user_id);
            } else {
                $deadSenator = $party->senators->drawCardWithValue('senatorID',$senatorID) ;
                $this->curia->putOnTop($deadSenator);
                $message.=sprintf(_('%s of party {%s} dies. The family goes to the curia. ') , $deadSenator->name , $party->user_id);
            }
        }
        // Handle dead senators' controlled cards, including Families
        while (count($deadSenator->controls->cards)>0) {
            $card = $deadSenator->controls->drawTopCard() ;
            if ($card->type=='Concession') {
                $this->curia->putOnTop($card);
                $message.=sprintf(_('%s goes to the curia. ') , $card->name);
            } elseif ($card->type=='Province') {
                $this->forum->putOnTop($card);
                $message.=sprintf(_('%s goes to the forum. ') , $card->name);
            } elseif ($card->type=='Family') {
                if ($party->leader->senatorID == $deadStatesman->senatorID) {
                    $party->senators->putOnTop($card);
                    $message.=sprintf(_('%s stays in the party. ') , $card->name);
                } else {
                    $this->curia->putOnTop($card);
                    $message.=sprintf(_('%s goes to the curia. ') , $card->name);
                }
            } else {
                return array(_('Error - A card controlled by the dead Senator was not a Family, a Concession nor a Province.'),'error');
            }
        }
        return array($message) ;
    }

    /************************************************************
     * Functions for REVENUE phase
     ************************************************************/

    /**
     * Initialises revenue phase (called at the end of mortality phase) :
     * - subPhase is 'Base'
     * - For every Senator with a Province, set Province->overrun to FALSE
     */
    public function revenue_init() {
        $messages = array() ;
        $this->resetPhaseDone() ;
        $this->subPhase='Base';
        foreach ($this->party as $party) {
            foreach ($party->senators->cards as $senator) {
                foreach ($senator->controls->cards as $card) {
                    if ($card->type=='Province') {
                        $card->overrun = FALSE ;
                        // Event 163;Barbarian Raids;Barbarian Raids Increase;
                        $barbarianRaids = $this->getEventLevel('number', 163) ;
                        // Only for frontier provinces
                        if ($barbarianRaids>0 && $card->frontier) {
                            $barabarianRaidsMessages = revenue_barbarianRaids($barbarianRaids , $card , $senator) ;
                            foreach($barabarianRaidsMessages as $message) {
                                array_push ($messages , $message ) ;
                            }
                        }
                        // Event 169;Internal Disorder;Increased Internal Disorder;
                        $internalDisorder = $this->getEventLevel('number', 169) ;
                        // Only for undeveloped provinces
                        if ($internalDisorder>0 && !$card->developed) {
                            $internalDisorderMessages = revenue_internalDisorder($internalDisorder , $card , $senator) ;
                            foreach($internalDisorderMessages as $message) {
                                array_push ($messages , $message ) ;
                            }
                        }
                    }
                }
            }
        }
        return $messages ;
    }
    
    /**
     * Function that handles the Barabarian raid event first step (the rolls, and the immediate effects of overrunning)
     * Later, the overrun flag will be used to prevent income and development
     * @param integer $barbarianRaids the raids level : 1|2
     * @param Province $province The provincial Province being governed by the governing Governor
     * @param Senator $senator The Governor governing the provincial Province being governed
     * @return array messages
     */
    private function revenue_barbarianRaids ($barbarianRaids , $province , $senator) {
        $messages = array() ;
        $provinceName = $province->name ;
        $writtenForce = $province->land() ;
        $garrisons = $this->getProvinceGarrisons($province) ;
        $governorName = $senator->name ;
        $governorMIL = $senator->MIL ;
        $roll = $this->rollDice(2, -1) ;
        $total = $writtenForce + 2 * $garrisons + $governorMIL + $roll['total'];
        $message = sprintf(_('Province %s is attacked by %s Barabarian raids. Military force is %d (written force) + %d (for %d legions) + %d (%s\'s MIL), a %d (white die %d, black die %d) is rolled for a total of %d ') ,
                $provinceName , ($barbarianRaids==2 ? 'increased ' : '') , $writtenForce , 2*$garrisons , $garrisons , $governorMIL , $governorName , $roll['total'] , $roll[0] , $roll[1] , $total
                );
        if ($total>( $barbarianRaids==1 ? 15 : 17)) {
            $message.= sprintf(_(' which is greater than %d, the province is safe.') , ($barbarianRaids==1 ? 15 : 17)) ;
            array_push($messages , array($message));
        } else {
            $province->overrun = TRUE ;
            $message.= sprintf(_(' which is not greater than %d, the province is overrun.') , ($barbarianRaids==1 ? 15 : 17)) ;
            array_push($messages , array($message,'alert'));
            if ($province->developed) {
                $province->developed = FALSE ;
                array_push($messages , array(_('The Province reverts to undeveloped status'),'alert'));
            }
            $mortalityChits = $this->mortality_chits($roll[1]) ;
            $message = sprintf(_('The black die was a %d, so %d mortality chits are drawn : ') , $roll[1]);
            $outcome = 'safe' ;
            $i=1 ;
            foreach($mortalityChits as $chit) {
                $message.=$chit.', ';
                if (    ($senator->type=='Family' && $senator->senatorID==$chit)
                    ||  ($senator->type=='Statesman' && $senator->statesmanFamily()==$chit)
                    ) {
                    // The outcome is based on whether or not the chit drawn was the last (which means capture)
                    $outcome = ($i++==$roll[1] ? _('captured') : _('killed')) ;
                }
            }
            $message=substr($message, 0, -2);
            array_push($messages , $message);
            switch($outcome) {
                case 'killed' :
                    $this->mortality_killSenator($senator->senatorID);
                    array_push($messages , array(sprintf(_('%s is killed by the barbaric barbarians.') , $senator->name) , 'alert'));
                    break ;
                case 'captured' :
                    $senator->captive='barbarians';
                    array_push($messages , array(sprintf(_('%s is captured by the barbaric barbarians. Ransom must be paid before next Forum phase or he\'s BBQ.') , $senator->name) , 'alert'));
                    break ; 
                default :
                    array_push($messages , array(sprintf(_('%s is safe.') , $senator->name) ));
            }
        }
        return $messages ;
    }
    
    /**
     * Function that handles the internal disorder events first step (order rolls and immediate effects of failure)
     * @param integer $internalDisorder
     * @param Province $province The Province
     * @param Senator $senator The Governor
     * @return array $messages
     */
    private function revenue_internalDisorder($internalDisorder , $province , $senator) {
        $messages = array() ;
        $garrisons = $this->getProvinceGarrisons($province) ;
        $roll = $this->rollOneDie(-1);
        $message = sprintf(_('Province %s faces internal disorder, %s rolls a %d + %d garrisons for a total of %d' , $province->name , $senator->name , $roll , $garrisons , ($roll+$garrisons) ));
        if (($roll+$garrisons) > ($internalDisorder == 1 ? 4 : 5)) {
            $message.sprintf(_(' which is greater than %d. The province will not generate revenue and cannot be improved this turn.') , ($internalDisorder == 1 ? '4' : '5'));
            // Using the overrun property both for Barbarian raids & Internal Disorder
            $province->overrun = TRUE ;
        } else {
            // Revolt : Kill Senator, garrisons, and move Province to the Active War deck
            array_push($messages , array($message.sprintf(_(' which is not greater than %d') , ($internalDisorder == 1 ? '4' : '5')) , 'alert'));
            $this->mortality_killSenator($senator->senatorID);
            // Note : The war is now in the forum, because of the mortality_killSenator function, so $revoltedProvince['deck'] should be $this->forum
            $revoltedProvince = $this->getSpecificCard('id', $province->id);
            $this->activeWars->putOnTop($this->$revoltedProvince['deck']->drawCardWithValue('id', $province->id));
            array_push($message , array(sprintf(_('%s is killed %s and %s becomes an active war' , $senator->name , ($garrisons>0 ? _(' with all ').$garrisons._(' garrisons, ') : '') , $province->name )) , 'alert'));
        }
        return $messages ;
    }
    
    /**
     * Returns a list of the various components of base revenue : senators, leader, knights, concessions, provinces
     * @param string $user_id
     * @return array ['total'] , ['senators'] , ['leader'] , ['knights'] ,
     * array ['concessions'] => ('id' , 'name' , 'income' , 'special' , 'senator_name' , 'senatorID') ,
     * array ['provinces'] => ('province' , 'senator') ,
     * array['rebels'] => ('senatorID' , 'name' , 'nbLegions' , 'loyal' , 'notLoyal' , 'list')
     */
    public function revenue_base($user_id) {
        $result = array() ;
        $result['total'] = 0 ;
        $result['senators'] = 0 ;
        $result['leader'] = '' ;
        $result['knights'] = 0 ;
        $result['concessions'] = array() ;
        $result['provinces'] = array() ;
        $result['rebels'] = array() ;
        foreach ($this->party[$user_id]->senators->cards as $senator) {
            if (!$senator->rebel && !$senator->captive) {
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
                    if ( $card->type == 'Concession' && $card->income > 0) {
                        $card->corrupt = TRUE ;
                        $result['total']+=$card->income ;
                        array_push($result['concessions'] , array( 'id' => $card->id , 'name' => $card->name , 'income' => $card->income , 'special' => $card->special , 'senator_name' => $senator->name , 'senatorID' => $senator->senatorID ) );
                    } elseif ( $card->type == 'Province' ) {
                        array_push($result['provinces'] , array('province' => $card , 'senator' => $senator ) );
                    }
                }
            } elseif ($senator->rebel) {
                // Rebel senator's legions
                $nbLegions = 0 ;
                $nbVeteransLoyal = 0 ;
                $nbVeteransNotLoyal = 0 ;
                $legionList = array() ;
                foreach($this->legion as $legion) {
                    if ($legion->location == $senator->senatorID) {
                        array_push($legionList , $legion) ;
                        $nbLegions++ ;
                        if ($legion->veteran) {
                            if ($legion->loyalty == $senator->senatorID) {
                                $nbVeteransLoyal ++ ;
                            } else {
                                $nbVeteransNotLoyal ++ ;
                            }
                        }
                    }
                }
                //  Rebels CAN collect provincial spoils
                foreach ($senator->controls->cards as $card) {
                    if ( $card->type == 'Province' ) {
                        array_push($result['provinces'] , array('province' => $card , 'senator' => $senator ) );
                    }
                }
                array_push($result['rebels'] , array('senatorID' => $senator->senatorID , 'name' => $senator->name , 'nbLegions' => $nbLegions , 'loyal' => $nbVeteransLoyal , 'notLoyal' => $nbVeteransNotLoyal , 'list' => $legionList) ) ;
            }
        }
        return $result ;
    }
    
    
    /**
     * Takes the POST vars from $request, and generates revenue for player with $user_id<br>
     * This includes :
     * - Taking extra income and lose POP for drought-affected concessions
     * - Take provincial spoils or not
     * - Handle Rome's treasury loss if the Senator lets Rome pay
     * - Develop provinces
     * - Pay for rebel legions maintenance
     * - Move to Redistribution subphase if the player was the last in order of play
     * @param string $user_id the player's user_id
     * @param request $request the POST variables
     * @return array
     */
    public function revenue_ProvincialSpoils ($user_id , $request ) {
        if ( ($this->phase=='Revenue') && ($this->subPhase=='Base') && ($this->party[$user_id]->phase_done==FALSE) ) {
            $messages = array() ;
            $base = $this->revenue_base($user_id);
            /*
             * Handle increased revenue & POP loss from 'drought' concessions (the two 'grains' concessions)
             * This comes in the request with a YES or NO flag on $request[id] where id is the concession card's id
             */
            $droughtLevel = $this->getTotalDroughtLevel() ;
            $earnedFromDrought = 0 ;
            $droughtSpecificMessage = array() ;
            if ( $droughtLevel > 0 ) {
                foreach ($base['concessions'] as $concession) {
                    if ($concession['special']=='drought') {
                        if ($request[$concession['id']] == 'YES') {
                            $earnedFromDrought+=$droughtLevel*$concession['income'] ;
                            $senator = $this->getSenatorWithID($concession['senatorID']) ;
                            $senator->changePOP(-1-$droughtLevel) ;
                            array_push ( $droughtSpecificMessage , sprintf(_('This includes an extra %dT from %s, earned by %s during the drought, causing him a loss a %d POP.' , $droughtLevel*$concession['income'] , $concession['name'] , $senator->name , (-1-$droughtLevel))) ) ;
                        } else {
                            array_push ( $droughtSpecificMessage , sprintf(_(' %s decided not to earn more from %s during the drought.') , $senator->name , $concession['name']) ) ;
                        }
                    }
                }
            }
            $this->party[$user_id]->treasury+=$base['total'] + $earnedFromDrought ;
            $concessionsTotal = $base['total']-($base['leader']!=NULL ? 3 : 0)-$base['senators']-$base['knights'] ;
            array_push ($messages , array(sprintf(_('{%s} gains %dT : %dT from leader, %dT from senators, %dT from knights and %dT from Concessions.') , $user_id , $base['total'] , ($base['leader']!=NULL ? 3 : 0) , $base['senators'] , $base['knights'] , $concessionsTotal))) ;
            foreach ($droughtSpecificMessage as $droughtMessage) {
                array_push ($messages , $droughtMessage);
            }
            /*
             *  Provincial spoils
             */
            foreach ($base['provinces'] as $data) {
                $province = $data['province'];
                $senator = $data['senator'];
                if (is_null($request[$province->id])) {
                    return array(_('Undefined province.'),'error');
                }
                // Check if province was overrun by barbarians / internal disorder
                if (!$province->overrun) {
                    $revenue = $province->rollRevenues('senator' , -$this->getEventLevel('name' , 'Evil Omens'));
                    $message = $province->name.' : ';
                    // Spoils
                    if ($request[$province->id] == 'YES') {
                        $senator->corrupt = TRUE ;
                        $message .= sprintf(_('%s takes provincial spoils for %dT.') , $senator->name , $revenue );
                        if ($revenue>0) {
                            $senator->treasury+=$revenue;
                        } else {
                            if ($request[$province->id.'_LET_ROME_PAY'] == 'YES') {
                                // The Senator decided to let Rome pay for it
                                $message .= _(' He decides to let the negative amount be paid by Rome. ') ;
                                $this->treasury+=$revenue;
                            } else {
                                if ($senator->treasury<$revenue) {
                                    // The senator is forced to let Rome pay because of his treasury
                                    $message .= _(' He has to let the negative amount be paid by Rome. ') ;
                                    $this->treasury+=$revenue;
                                } else {
                                    // The Senator decided to pay for it
                                    $message .= _(' He decides to pay the negative amount. ') ;
                                    $senator->treasury+=$revenue;
                                }
                            }
                        }
                        $message .= _(' He is now corrupt.');
                    } else {
                    // No spoils
                        $message.= sprintf(_('%s doesn\'t take Provincial spoils.') , $senator->name);
                    }
                    // Develop province
                    if ( !($province->developed)) {
                        $roll = $this->rollOneDie(-1) ;
                        $modifier = ( ($senator->corrupt) ? 0 : 1) ;
                        if ( ($roll+$modifier) >= 6 ) {
                            $message.= sprintf(_(' A %d is rolled%s, the province is developed. %s gains 3 INFLUENCE.') , $roll , ($modifier==1 ? _(' (modified by +1 since the governor is not corrupt)') : '') , $senator->name);
                            $province->developed = TRUE ;
                            $senator->INF+=3;
                        } else {
                            $message.=sprintf(_(' A %d is rolled%s, the province is not developed.') , $roll , ($modifier==1 ? _(' (modified by +1 since senator is not corrupt)') : ''));
                        }
                    }
                } else {
                    $message = sprintf(_('%s was overrun by Barbarians and/or internal disorder. No revenue nor development this turn.') , $province->name);
                }
                array_push ($messages , array($message)) ;
            }
            if ($request['rebel']=='YES') {
                // Pick up each LEGION_XXX_YYY (XXX is the legion's name, YYY is the rebel senator's senatorID), check which option was picked : A,B,C,D,E
                // TO DO : Pay maintenance or disband
                // TO DO : If legions are released, If the HRAO does not wish to pay the maintenance costs of these troops or if the senate cannot afford them, they are immediately disbanded.
                // This specific HRAO decision should be moved to the HRAO's redistribution function.
                foreach($request as $key=>$value) {
                    if (substr($key,0,6)=='LEGION') {
                        $itemised = explode('_' , $key) ;
                        $legionName = $itemised[1] ;
                        $rebelID = $itemised[2] ;
                        switch($value) {
                            case 'A' :
                            case 'B' :
                            case 'C' :
                            case 'D' :
                            case 'E' :
                        }
                    }
                }
            }
            // Phase done for this player. If all players are done, move to redistribution subPhase
            $this->party[$user_id]->phase_done = TRUE ;
            if ($this->whoseTurn() === FALSE ) {
                $this->resetPhaseDone() ;
                $this->subPhase='Redistribution' ;
                array_push ($messages , array(_('All revenues collected, parties can now redistribute money.'))) ;
            }
            return $messages ;
        }
    }

    /**
    * Lists all the possible "from" and "to" (Senators and Parties) for redistribution of wealth
    * @param string $user_id the player's user_id
    * @return array A list of 'from' & 'to' <br>
    * 'list' => 'from'|'to' ,<br> 'type' => 'senator'|'party' ,<br> 'id' => senatorID|user_id ,<br> 'name' => senator or party name ,<br> 'treasury' => senator or party treasury (only for 'from')
    */
    public function revenue_ListRedistribute ($user_id) {
        $result=array() ;
        if ( ($this->phase=='Revenue') && ($this->subPhase=='Redistribution') && ($this->party[$user_id]->phase_done==FALSE) ) {
            foreach($this->party[$user_id]->senators->cards as $senator) {
                if ($senator->treasury > 0 && !$senator->captive && !$senator->rebel) {
                    array_push($result , array('list' => 'from' , 'type' => 'senator' , 'id' => $senator->senatorID , 'name' => $senator->name , 'treasury' => $senator->treasury ));
                }
                if (!$senator->captive && !$senator->rebel) {
                    array_push($result , array('list' => 'to' , 'type' => 'senator' , 'id' => $senator->senatorID , 'name' => $senator->name ));
                }
            }
            array_push($result , array('list' => 'from' , 'type' => 'party' , 'id' => $user_id , 'name' => $this->party[$user_id]->name , 'treasury' => $this->party[$user_id]->treasury ));
            foreach($this->party as $key=>$value) {
                array_push($result , array('list' => 'to' , 'type' => 'party' , 'id' => $key , 'name' => $this->party[$key]->name ));
            }
        }
        return $result ;
    }

    /**
     * Executes one transfer of talents from Senator or Party to Senator or Party<br>
     * $fromRaw and $toRaw are arrays in the form [0] =>'senator'|'party' , [1] => 'id'
     * @param type $user_id The player's user_id
     * @param type $fromRaw A raw POST string for the origin of the redistribution
     * @param type $toRaw A raw POST string for the destination of the redistribution
     * @return array messages
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
            if ($amount<=0) { return array(array(_('You have no talent. '),'error',$user_id)); }
            if ($from===FALSE) { return array(array(_('Giving from wrong Senator'),'error',$user_id)); }
            if ($to===FALSE) { return array(array(_('Giving to wrong Senator'),'error',$user_id)); }
            if (!isset($from)) { return array(array(_('Giving from wrong Party'),'error',$user_id)); }
            if (!isset($to)) { return array(array(_('Giving to wrong Party'),'error',$user_id)); }
            if ($from->treasury < $amount) { return array(array(_('Not enough money'),'error',$user_id)); }
            if ($toTI[0]== 'senator' && $fromTI[0]=='senator' && $toTI[1]==$fromTI[1] ) { return array(array(_('Stop drinking'),'error',$user_id)); }
            $from->treasury-=$amount ;
            $to->treasury+=$amount ;

            if ($toTI[0]== 'senator') {
                // There is a different message for public and private use
                return array(
                    array( sprintf(_('%s gives %dT to %s.') , ($fromTI[0]=='senator' ? ($from->name) : _('The party ') ) , $amount , ( ($toTI[0]=='party' && $toTI[1]==$user_id) ? _('Party treasury. ') : $to->name) )  , 'message' , $user_id ) ,
                    array( sprintf(_('{%s} moves some money around') , $user_id) , 'message' , $this->getAllButOneUserID($user_id) ) ,
                    ) ;
            } else {
                return array(array(sprintf(_('%s give %dT to %s') , $from->name , $amount , (($toTI[0]=='party' && $toTI[1]==$user_id) ? 'Party treasury. ' : $to->name.'.')) , 'message' , $user_id ));
            }
        }
        return array(array(_('Undocumented Redistribution error'), 'error' , $user_id));
    }

    /**
     * Finish the redistribution of wealth for $user_id<br>
     * If everyone is done, do State revenue :<br>
     * - 100 T<br>
     * - Provinces<br>
     * Then move to Contributions subphase<br>
     * @param string $user_id The player's user_id
     * @return array
     */
    public function revenue_RedistributionFinished ($user_id) {
        $messages = array () ;
        if ( ($this->phase=='Revenue') && ($this->subPhase=='Redistribution') && ($this->party[$user_id]->phase_done==FALSE) ) {
            $this->party[$user_id]->phase_done=TRUE ;
            array_push($messages , array(sprintf(_('{%s} has finished redistributing wealth.') , $user_id))) ;
            if ($this->whoseTurn()===FALSE) {
                array_push($messages , array(_('The redistribution sub phase is finished.'))) ;
                array_push($messages , array(_('State revenues.'))) ;
                // Rome gets 100T.
                $this->treasury+=100 ;
                array_push($messages , array(_('Rome collects 100 T.')));
                // Event '162;Allied Enthusiasm;Extreme Allied Enthusiasm'
                $alliedEnthusiasm = $this->getEventLevel('number',162) ;
                if ($alliedEnthusiasm>0) {
                    $name = ($alliedEnthusiasm==1 ? 'name' : 'increased_name') ;
                    $description = ($alliedEnthusiasm==1 ? 'description' : 'increased_description') ;
                    array_push($messages , $this->events[162][$name].' : '.$this->events[162][$description]);
                    $this->treasury+=($alliedEnthusiasm==1 ? 50 : 75);
                    $this->events[162]['level'] = 0 ;
                }
                // Provinces revenues for aligned Senators
                foreach ($this->party as $party) {
                    foreach ($party->senators->cards as $senator) {
                        foreach ($senator->controls->cards as $province) {
                            if ($province->type=='Province') {
                                $revenue = $province->rollRevenues('rome' , -$this->getEventLevel('name' , 'Evil Omens'));
                                array_push($messages , array(sprintf(_('%s : Rome\'s revenue is %dT . ') , $province->name , $revenue)) );
                                $this->treasury+=$revenue;
                            }
                        }
                    }
                }
                // Provinces revenues for unaligned Senators
                foreach ($this->forum->cards as $senator) {
                    if ($senator->type=='Family') {
                        foreach ($senator->controls->cards as $province) {
                            if ($province->type=='Province') {
                                $revenue = $province->rollRevenues('rome' , -$this->getEventLevel('name' , 'Evil Omens'));
                                array_push($messages , array(sprintf(_('%s : Rome\'s revenue is %dT . ') , $province->name , $revenue)) );
                                $this->treasury+=$revenue;
                            }
                        }
                    }
                }
                array_push($messages , array(_('The state revenue sub phase is finished.')) ) ;
                array_push($messages , array(_('Contributions.')) ) ;
                $this->subPhase='Contributions';
                $this->resetPhaseDone();
            }
        }
        return $messages ;       
    }
   
    /**
     * Provides an array of Senators ('senatorID' , 'name' , 'treasury') who can give to Rome
     * @param type $user_id The player's user_id
     * @return array
     */
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
    
    /**
     * Handles the contribution of a Senator, including INF gains.
     * @param string $user_id The player's user_id
     * @param string $rawSenator The POST vars related to the Senator's contribution
     * @param int $amount
     * @return array
     */
    public function revenue_Contributions($user_id , $rawSenator , $amount) {
        if ( ($this->phase=='Revenue') && ($this->subPhase=='Contributions') && ($this->party[$user_id]->phase_done==FALSE) ) {
            $amount=(int)$amount;
            $medium = explode('|' , $rawSenator);
            $senatorID = $medium[0];
            $senator = $this->getSenatorWithID($senatorID) ;
            if ($senator!==FALSE) {
                if ($senator->treasury < $amount) {
                    return array(array(_('This senator doesn\'t have enough money'),'error',$user_id));
                } elseif ($amount<1) {
                    return array(array(_('Wrong amount'),'error',$user_id));
                } else {
                    if ($amount>=50) { $INFgain = 7 ; } elseif ($amount>=25) { $INFgain = 3 ; } elseif ($amount>=10) { $INFgain = 1 ; } else { $INFgain = 0 ; }
                    $senator->INF+=$INFgain ;
                    $senator->treasury-=$amount ;
                    $this->treasury+=$amount ;
                    return array(array(sprintf(_('%s gives %dT to Rome') , $senator->name , $amount).( ($INFgain!=0) ? sprintf(_(' He gains %d influence.') , $INFgain) : '') ));
                }
            }
            return array(_('Error retrieving Senator'),'error',$user_id);
        }
    }
    
    /**
     * Sets the phase_done to TRUE for this player<br>
     * If he was the last player, handles Rome expenses :<br>
     * - Active & Unprosecuted Wars
     * - Land bills
     * - Forces maintenance
     * - Returning governors
     * - Moving to Forum phase
     * @param string $user_id The player's user_id
     * @return array
     */
    public function revenue_Finished ($user_id) {
        $messages = array () ;
        if ( ($this->phase=='Revenue') && ($this->subPhase=='Contributions') && ($this->party[$user_id]->phase_done==FALSE) ) {
            $this->party[$user_id]->phase_done=TRUE ;
            array_push($messages , array(sprintf(_('{%s} has finished contributions to Rome.') , $user_id))) ;
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
                    array_push($messages , array(sprintf(_('Rome pays %dT for %d active Conflicts : ') , ($nbWars*20) , $nbWars) . $textWars.'.'));
                }
                // Land bills
                $totalLandBills =  $this->landBill[1]*10 + $this->landBill[2]*5 + $this->landBill[3]*10 ;
                if ($totalLandBills>0) {
                    $this->treasury-=$totalLandBills;
                    array_push($messages , array(sprintf(_('Rome pays %dT for land bills (I , II & III): %dT for %d (I) which are then discarded, %dT for (II) and %dT for (III).') , $totalLandBills , ($this->landBill[1]*10) , $this->landBill[1] , ($this->landBill[2]*5) , ($this->landBill[3]*10))));
                    // Remove level I land bills
                    $this->landBill[1] = 0 ;
                }
                // Forces maintenance
                $nbLegions = $this->getNbOfLegions();
                $nbFleets = $this->getNbOfFleets();
                $totalCostForces=2*($nbLegions + $nbFleets) ;
                if ($totalCostForces>0) {
                    $this->treasury-=$totalCostForces ;
                    array_push($messages , array(sprintf(_('Rome pays %dT for the maintenance of %d legions and %d fleets. ') , $totalCostForces , $nbLegions , $nbFleets)));
                }
                // Return of provinces governors
                foreach($this->party as $party) {
                    foreach ($party->senators->cards as $senator) {
                        foreach ($senator->controls->cards as $card) {
                            if ($card->type=='province') {
                                $card->mandate++;
                                if ($card->mandate == 3) {
                                    array_push($messages , array(sprintf(_('%s returns from %s which is placed in the Forum.') , $senator->name , $card->name)));
                                    $card->mandate=0;
                                    $this->forum->putOnTop($senator->controls->drawCardWithValue('id',$card->id));
                                } else {
                                    array_push( $messages , array(sprintf(_('%s spends %s game turn in %s') , $senator->name , ( ($card->mandate==1) ? _('First') : _('Second') ) , $card->name)) );
                                }
                            }
                        }
                    }
                }
                // Handle unaligned senators who are governors
                foreach($this->forum->cards as $senator) {
                    if ($senator->type=='Family') {
                        foreach ($senator->controls->cards as $card) {
                            if ($card->type=='Province') {
                                $card->mandate++;
                                if ($card->mandate == 3) {
                                    array_push( $messages , array(sprintf(_('%s (unaligned) returns from %s which is placed in the Forum.') , $senator->name , $card->name)) );
                                    $card->mandate=0;
                                    $this->forum->putOnTop($senator->controls->drawCardWithValue('id',$card->id));
                                } else {
                                    array_push( $messages , array(sprintf(_('%s (unaligned) spends %s game turn in %s.') , $senator->name , ( ($card->mandate==1) ? _('First') : _('Second') ) , $card->name)) );
                                }
                            }
                        }
                    }
                }
                // Done, move to Forum phase.
                array_push($messages , array(sprintf(_('Revenue phase is finished. Rome now has %dT. Starting Forum phase.') , $this->treasury)));
                $this->resetPhaseDone();
                $this->phase='Forum';
                array_push($messages , array(_('FORUM PHASE'),'alert'));
                // TO DO : remove events that expire at the beginning of the forum phase
                $this->subPhase='RollEvent';
                $this->initiative=1;
                array_push($messages , array(_('Initiative #1'),'alert'));
            }
        }
        return $messages ;
    }

    /**
     * revenue_view returns all the data needed to render revenue templates. The function returns an array $output :
     * $output['state'] (Mandatory) : gives the name of the current state to be rendered
     * $output['X'] : an array of values 
     * - X is the name of the component to be rendered
     * - 'values' is the actual content : text for a text, list of options for select, etc...
     * @param string $user_id
     * @return array $output
     */
    public function revenue_view($user_id) {
        $output = array() ;

        // Base revenue sub phase
        if ( ($this->phase=='Revenue') && ($this->subPhase=='Base') ) {
            // Waiting for other players
            if ($this->party[$user_id]->phase_done) {
                $output['state'] = 'Base - Waiting' ;
                $waitingFor = '' ;
                foreach($this->party as $party) {
                    if(!$party->phase_done) {
                        $waitingFor.=$party->fullName().', ';
                    }
                }
                $waitingForBis = substr($waitingFor, 0, -2);
                $output['text'] = $waitingForBis ;
            // Playing base revenue phase : Getting more from concessions during drought & Provincial spoils
            } else {
                $output['state'] = 'Base - Playing' ;
                $revenueBase = $this->revenue_base($user_id) ;
                $output['text']['senators'] = ($revenueBase['senators']>0 ? sprintf(_('Revenue collected from %d senators : %dT.') , $revenueBase['senators'] , $revenueBase['senators']) : _('Currently no Senators in the party : no revenue collected from senators.') );
                $output['text']['leader'] = ($revenueBase['leader']!='' ? sprintf(_('Revenue collected from Leader %s : 3T.') , $revenueBase['leader']) : _('Currently no leader : no revenue collected from leader.'));
                $output['text']['knights'] = ($revenueBase['knights']>0 ? sprintf(_('Revenue collected from %d knights : %dT.') , $revenueBase['knights'] , $revenueBase['knights']) : _('Currently no knights : no revenue collected from knights.'));
                // rebel legions
                $output['rebels'] = $revenueBase['rebels'] ;
                $output['text']['rebels'] = _('There is a rebel in the faction');
                // Concessions
                $output['concessions'] = array() ;
                $output['concession_drought'] = array();
                if (count($revenueBase['concessions'])>0) {
                    $output['text']['concessions'] = _('Revenue collected from concessions : ');
                    $droughtLevel = $this->getTotalDroughtLevel() ;
                    foreach ($revenueBase['concessions'] as $concession) {
                        array_push($output['concessions'] , sprintf(_('%dT from %s (%s)') , $concession['income'] , $concession['name'] , $concession['senator_name']) );
                        // Populates the $output['concession_drought'] array to show the interface allowing senators to profit from drought-affected concessions
                        if ($concession['special'] == 'drought' && $droughtLevel>0) {
                            array_push($output['concession_drought'] , array('id' => $concession['id'], 'text' => sprintf(_('Do you want %s to be a sick bastard and earn more money from %s because of the drought') , $concession['senator_name'] , $concession['name']) ));
                        }
                    }
                } else {
                    $output['text']['concessions'] = _('Currently no concessions : no revenue collected from concessions.') ;
                }
                $output['text']['total'] = sprintf(_('Total base revenue : %d') , $revenueBase['total']);
                // Provinces
                $output['provinces'] = array () ;
                if (count($revenueBase['provinces'])>0) {
                    $output['text']['provinces'] = _('Revenue from Provincial spoils :');
                    foreach ($revenueBase['provinces'] as $province) {
                        array_push  ($output['provinces'] , array (
                                'province_name' => $province['province']->name
                              , 'governor_name' => $province['senator']->name
                              , 'overrun' => $province['province']->overrun
                              , 'province_id' => $province['province']->id
                            )
                        );
                    }
                } else {
                    $output['text']['provinces'] = _('Currently no provinces : no revenue collected from provinces.') ;
                }
            }

        // Redistribution sub phase
        } elseif ( ($this->phase=='Revenue') && ($this->subPhase=='Redistribution') ) {
            // Waiting for other players
            if ($this->party[$user_id]->phase_done) {
                $output['state'] = 'Redistribution - Waiting' ;
                $waitingFor = '' ;
                foreach($this->party as $party) {
                    if(!$party->phase_done) {
                        $waitingFor.=$party->fullName().', ';
                    }
                }
                $waitingForBis = substr($waitingFor, 0, -2);
                $output['text'] = $waitingForBis ;
            } else {
                $output['state'] = 'Redistribution - Playing' ;
                $output['redistribution'] = $this->revenue_ListRedistribute($user_id) ;
            }
            
        // Contributions sub phase
        } elseif ( ($this->phase=='Revenue') && ($this->subPhase=='Contributions') ) {
            // Waiting for other players
            if ($this->party[$user_id]->phase_done) {
                $output['state'] = 'Contributions - Waiting' ;
                $waitingFor = '' ;
                foreach($this->party as $party) {
                    if(!$party->phase_done) {
                        $waitingFor.=$party->fullName().', ';
                    }
                }
                $waitingForBis = substr($waitingFor, 0, -2);
                $output['text'] = $waitingForBis ;
            } else {
                $output['state'] = 'Contributions - Playing' ;
                $output['contributions'] = $this->revenue_listContributions($user_id) ;
            }
        }
        
        return $output ;
    }
    
     /************************************************************
     * Functions for FORUM phase
     ************************************************************/

    /**
     * A message saying who is currently the highest bidder<br>
     * The message can also indicate that the HRAO currently would have the initiative if nobody is betting
     * @return array 'bid','message','user_id'
     */
    public function forum_highestBidder () {
        $result['bid']=0 ;
        $result['message']='' ;
        foreach ($this->party as $party) {
            if ($party->bid>$result['bid']) {
                $result['bid']=$party->bid ;
                $result['user_id'] = $party->user_id;
                $result['message'] = sprintf(_(' %s with a bid of %dT.') , $party->fullName() , $result['bid']) ;
            }
        }
        if ($result['bid']==0) {
            $HRAO = $this->getHRAO() ;
            $result['message'] = sprintf(_('The HRAO (%s) as all bets are 0.') , $HRAO['party']->fullName());
            $result['user_id'] = $HRAO['user_id'];
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
            $currentOrder = $this->getOrderOfPlay() ;
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
        $messages = array() ;
        foreach ($this->party as $party) {
            $party->bidDone=FALSE;
            $party->bid=0;
            $party->bidWith=NULL;
        }
        $HRAO = $this->getHRAO();
        $this->currentBidder = $HRAO['user_id'];
        $richestSenator = $HRAO['party']->getRichestSenator() ;
        if ($richestSenator['amount']==0) {
            array_push($messages , array( sprintf(_('Skipping the HRAO ({%s}): not enough talents to bid.') , $HRAO['party']->user_id) )) ;
            $nextPlayer = $this->whoIsAfter($HRAO['user_id']);
            $this->currentBidder = $nextPlayer['user_id'];
            foreach ($nextPlayer['messages'] as $message) {
                array_push($messages , $message);
            }
        }
        return $messages ;
    }
    
    /**
     * Handles the bid from a Senator, as passed in the POST vars
     * @param string $user_id
     * @param string $senatorRaw
     * @param int $amount
     * @return array
     */
    public function forum_bid ($user_id , $senatorRaw , $amount) {
        $amount = (int)$amount ;
        $messages = array() ;
        if ($this->phase=='Forum') {
            if ($this->forum_whoseInitiative()===FALSE) {
                // There was no bid
                if ($senatorRaw=='NONE' || $amount<=0 ) {
                    array_push($messages , array( sprintf(_('{%s} cannot or will not bid for this initiative.') , $user_id) ));
                // There was a bid
                } else {
                    $senatorData = explode('|' , $senatorRaw) ;
                    $senatorID = $senatorData[0] ;
                    $senator = $this->getSenatorWithID($senatorID) ;
                    if ($this->getPartyOfSenator($senator)->user_id == $user_id) {
                        if ($senator->treasury>=$amount) {
                            $this->party[$user_id]->bidWith = $senator ;
                            $this->party[$user_id]->bid = $amount ;
                            array_push($messages , array( sprintf(_('{%s} bids %dT with %s for this initiative.') , $user_id , $amount , $senator->name) ));
                        } else {
                            array_push($messages , array(_('Not enough money') , 'error' , $user_id));
                        }
                    } else {
                        array_push($messages , array(_('Wrong party') , 'error' , $user_id));
                    }
                }
                // There was a bid or pass, we need to move on to the next bidder and check if the bids are over
                $nextPlayer = $this->whoIsAfter($user_id);
                $this->currentBidder = $nextPlayer['user_id'];
                foreach ($nextPlayer['messages'] as $message) {
                    array_push($messages , $message);
                }
                $HRAO = $this->getHRAO();
                // We went around the table once : bids are finished
                if ($this->currentBidder == $HRAO['user_id']) {
                    $highestBidder = $this->forum_highestBidder() ;
                    if ($highestBidder['bid']>0) {
                        $this->party[$highestBidder['user_id']]->bidWith->treasury-=$highestBidder['bid'];
                    }
                    // This is not straight-forward, but it allows for multiple rounds of bidding as a possible variant
                    foreach($this->party as $party) {
                        if ($party->user_id!=$highestBidder['user_id']) {
                            $party->bidDone=TRUE;
                            $party->bidWith=NULL;
                        }
                    }
                    if ($highestBidder['bid']>0) {
                        array_push($messages , array( sprintf(_(' {%s} wins this initiative. %s spends %dT from his personal treasury.') , $highestBidder['user_id'] , $this->party[$highestBidder['user_id']]->bidWith->name , $highestBidder['bid']) ));
                    } else {
                        array_push($messages , array( sprintf(_(' {%s} wins this initiative since he is the HRAO and no one bid.') , $highestBidder['user_id']) ));
                    }
                }
            } elseif ($user_id!=$this->forum_whoseInitiative()) {
                array_push($messages , array(_('Cannot bid as this initiative already belongs to another player') , 'error' , $user_id));
            }
        }
        return $messages ;
    }
    
    /**
     * Roll for events, and either :
     * - If the total was 7 : Draw the event card and handle immediate event effects, amd/or event level increase if applicable
     * - Otherwise, draw a card and put Faction cards in the user's hand, or other cards where they belong (forum / wars)
     * @param type $user_id
     * @return array
     */
    public function forum_rollEvent($user_id) {
        $messages = array() ;
        if ( ($this->phase=='Forum') && ($this->subPhase=='RollEvent') && ($this->forum_whoseInitiative()==$user_id) ) {
            array_push($messages , array('Event roll Sub Phase'));
            $roll = $this->rollDice(2, 0) ;
            if ($roll['total']==7) {
                // Event
                $eventRoll = $this->rollDice(3,0) ;
                array_push($messages , array($this->party[$user_id]->fullName().' rolls a 7, then rolls a '.$eventRoll['total'].' on the events table.'));
                $eventNumber = $this->eventTable[(int)$eventRoll['total']][$this->scenario] ;
                $message = $this->forum_putEventInPlay('number' , $eventNumber) ;
                array_push($messages, array($message , 'alert'));
            } else {
                // Card
                array_push($messages , array($this->party[$user_id]->fullName().' rolls a '.$roll['total'].' and draws a card.'));
                $card = $this->drawDeck->drawTopCard() ;
                if ($card !== NULL) {
                    if ($card->type == 'Statesman' || $card->type == 'Faction' || $card->type == 'Concession') {
                        // Keep the card
                        $this->party[$user_id]->hand->putOnTop($card);
                        array_push($messages , array($this->party[$user_id]->fullName().' draws a faction card and keeps it.','message',$this->getAllButOneUserID($user_id)));
                        array_push($messages , array('You draw '.$card->name.' and put it in your hand.','message',$user_id));
                    } else {
                        // If a Family has been drawn check if a corresponding Statesman is in play
                        if ($card->type=='Family') {
                            $possibleStatemen = array() ;
                            foreach ($this->party as $party) {
                                foreach ($party->senators->cards as $senator) {
                                    if ($senator->type=='Statesman' && $senator->statesmanFamily() == $card->senatorID) {
                                        array_push($possibleStatemen , array('senator' => $senator , 'party' => $party)) ;
                                    }
                                }
                            }
                            // No corresponding statesman : Family goes to the Forum
                            if (count($possibleStatemen)==0) {
                                $this->forum->putOnTop($card) ;
                                array_push($messages , array($this->party[$user_id]->fullName().' draws '.$card->name.' that goes to the forum.'));
                            // Found one or more (in case of brothers) corresponding Statesmen : put the Family under them
                            // Case 1 : only one Statesman
                            } elseif (count($possibleStatemen)==1) {
                                $possibleStatemen[0]['senator']->controls->putOnTop($card) ;
                                array_push($messages , array($possibleStatemen[0]['party']->fullName().' has '.$possibleStatemen[0]['senator']->name.' so the family joins him.'));
                            // Case 2 : brothers are in play
                            } else {
                                // Sorts the possibleStatemen in SenatorID order, so 'xxA' is before 'xxB'
                                // This is only relevant to brothers
                                usort ($possibleStatemen, function($a, $b) {
                                    return strcmp($a['senator']->senatorID , $b['senator']->senatorID);
                                });
                                $possibleStatemen[0]['senator']->controls->putOnTop($card) ;
                                array_push($messages , array($possibleStatemen[0]['party']->fullName().' has '.$possibleStatemen[0]['senator']->name.'  (who has the letter "A" and takes precedence over his brother) so the family joins him.'));
                            }
                        } else {
                            // Card goes to forum
                            // TO DO : Wars and Leaders don't go to forum
                            $this->forum->putOnTop($card) ;
                            array_push($messages , array($this->party[$user_id]->fullName().' draws '.$card->name.' that goes to the forum.'));
                        }
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
     * Puts an event in play
     * @param string $type The type of parameter : 'name'|'number'
     * @param type $parameter The name or number of the event to play
     * @return string A message describing if the event was new, or a level increase, or already at max level
     */
    public function forum_putEventInPlay($type , $parameter) {
        // events is an array => (array => ('name' , 'increased_name' , 'max_level' , 'level'))
        $message = '' ;
        $eventNumber = NULL ;
        if ($type == 'number') {
            $eventNumber = (int)$parameter ;
        } elseif ($type == 'name') {
            foreach ($this->events as $key=>$eventArray) {
                if ($eventArray['name'] == $parameter) {
                    $eventNumber = $key ;
                }
            }
        }
        if ($eventNumber!==NULL) {
            // The event is not currently in play
            if ($this->events[$eventNumber]['level'] == 0) {
                $this->events[$eventNumber]['level']++ ;
                $message='Event '.$this->events[$eventNumber]['name'].' is now in play.';
            // The event is currently in play at maximum level & CANNOT increase
            } elseif ($this->events[$eventNumber]['level'] == $this->events[$eventNumber]['max_level']) {
                $nameToUse = ($this->events[$eventNumber]['level']> 1 ? $this->events[$eventNumber]['increased_name'] : $this->events[$eventNumber]['name'] ) ;
                $message='Event '.$nameToUse.' is already in play at its maximum level ('.$this->events[$eventNumber]['max_level'].').';
            // The event is currently in play and not yet at maximum level : it can increase
            } else {
                $this->events[$eventNumber]['level']++ ;
                $message='Event '.$this->events[$eventNumber]['name'].' has its level increased to '.$this->events[$eventNumber]['increased_name'].' (level '.$this->events[$eventNumber]['level'].').';
            }
        } else {
            $message = 'Error retrieving event.' ;
        }
        return $message ;
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
                    if ($senator->inRome() && $senator->senatorID != $party->leader->senatorID && $party->user_id!=$user_id) {
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
     * Current player user_id
     * @return boolean|array
     */
    public function forum_listPersuaders($user_id) {
        $result = array();
        if ( ($this->phase=='Forum') && ($this->subPhase=='Persuasion') && ($this->forum_whoseInitiative()==$user_id) ) {
            foreach ($this->party[$user_id]->senators->cards as $senator) {
                if ($senator->inRome()) {
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
        $result['target']['card'] = FALSE ;
        foreach ($this->persuasionTarget->controls->cards as $card) {
            if ($card->name=="SEDUCTION" || $card->name=="BLACKMAIL") {
                $result['target']['card'] = $card->id ;
            }
        }
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
    public function forum_persuasion($user_id , $persuaderRaw , $targetRaw , $amount , $cardID) {
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
                            if ($cardID=='NONE') {
                                $this->party[$user_id]->bidWith = $persuadingSenator ;
                                $this->party[$user_id]->bidWith->treasury-=$amount ;
                                $this->party[$user_id]->bid = $amount ;
                                $this->persuasionTarget = $targetSenator ;
                                array_push($messages , array ($persuadingSenator->name.' ('.$partyPersuader->fullName().') attempts to persuade '.$targetSenator->name.' ('.($partyTarget=='forum' ? 'forum' : $partyTarget->fullName()).')')) ;
                                $nextPlayer = $this->whoIsAfter($user_id);
                                $this->currentBidder = $nextPlayer['user_id'];
                                foreach ($nextPlayer['messages'] as $message) {
                                    array_push($messages , $message);
                                }
                            // A persuasion-specific card was played
                            } else {
                                $card = $this->party[$user_id]->hand->drawCardWithValue('id',$cardID) ;
                                if ($card!==FALSE) {
                                    if ($card->name=='SEDUCTION' || $card->name=='BLACKMAIL') {
                                        $this->party[$user_id]->bidWith = $persuadingSenator ;
                                        $this->party[$user_id]->bidWith->treasury-=$amount ;
                                        $this->party[$user_id]->bid = $amount ;
                                        $this->persuasionTarget = $targetSenator ;
                                        // Important : The persuasion card is played ON the target Senator, to make it very easy to check later
                                        $targetSenator->controls->putOnTop($card);
                                        // The player stays the current bidder, as other players cannot counter-bribe on a seduction card
                                        $this->currentBidder = $user_id;
                                        array_push($messages , array ($persuadingSenator->name.' ('.$partyPersuader->fullName().') attempts to persuade '.$targetSenator->name.' ('.($partyTarget=='forum' ? 'forum' : $partyTarget->fullName()).') using a '.$card->name.' card.')) ;
                                    }
                                } else {
                                    return array(array('You do not have that card in hand.','error',$user_id));
                                }
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
                        
                        // This is the final roll, either because there was no more bribe, or because a persuasion card was played
                        $currentPersuasion = $this->forum_persuasionListCurrent();
                        if ( ($amount==0) || ($currentPersuasion['target']['card']!==FALSE) ) {
                            $roll = $this->rollDice(2, 1);
                            // Failure on 10+
                            if ($roll['total']>=10) {
                                if (($currentPersuasion['target']['card']!==FALSE)) {
                                    array_push ($messages , $this->forum_removePersuasionCard($user_id , $currentPersuasion['target']['senatorID'] , $currentPersuasion['target']['card'] , 'FAILURE') );
                                }
                                array_push ($messages , array('FAILURE - '.$this->party[$user_id]->fullName().' rolls an unmodified '.$roll['total'].', which is greater than 9 and an automatic failure.'));
                            // Failure if roll > target number    
                            } elseif ($roll['total']>$currentPersuasion['odds']['total']) {
                                if (($currentPersuasion['target']['card']!==FALSE)) {
                                    array_push ($messages , $this->forum_removePersuasionCard($user_id , $currentPersuasion['target']['senatorID'] , $currentPersuasion['target']['card'] , 'FAILURE') );
                                }
                                array_push ($messages , array('FAILURE - '.$this->party[$user_id]->fullName().' rolls '.$roll['total'].', which is greater than the target number of '.$currentPersuasion['odds']['total'].'.'));
                            // Success
                            } else {
                                if (($currentPersuasion['target']['card']!==FALSE)) {
                                    array_push ($messages , $this->forum_removePersuasionCard($user_id , $currentPersuasion['target']['senatorID'] , $currentPersuasion['target']['card'] , 'SUCCESS') );
                                }
                                array_push ($messages , array('SUCCESS - '.$this->party[$user_id]->fullName().' rolls '.$roll['total'].', which is not greater than the target number of '.$currentPersuasion['odds']['total'].'.'));
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
                                // Whatever the outcome, put total bribe and counter-bribe money on the target.
                                $senator = $this->getSenatorWithID($currentPersuasion['target']['senatorID']) ;
                                $senator->treasury+=$totalBids;
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
                                array_push ($messages , array($this->party[$user_id]->fullName().' bribes more.'));
                                $nextPlayer = $this->whoIsAfter($user_id);
                                $this->currentBidder = $nextPlayer['user_id'];
                                foreach ($nextPlayer['messages'] as $message) {
                                    array_push($messages , $message);
                                }
                            } else {
                                array_push ($messages , array('The senator is too poor' , 'error' , $user_id));
                            }
                        }
                        
                    // This user doesn't have the initiative, this is a counter-bribe
                    } else {
                        if ($amount==0) {
                            array_push ($messages , array($this->party[$user_id]->fullName().' doesn\'t spend money to counter-bribe.'));
                            $nextPlayer = $this->whoIsAfter($user_id);
                            $this->currentBidder = $nextPlayer['user_id'];
                            foreach ($nextPlayer['messages'] as $message) {
                                array_push($messages , $message);
                            }
                        } elseif ($this->party[$user_id]->treasury >= $amount) {
                            $this->party[$user_id]->treasury -= $amount ;
                            $this->party[$user_id]->bid += $amount ;
                            array_push ($messages , array($this->party[$user_id]->fullName().' spends '.$amount.' T from the party treasury to counter-bribe.'));
                            $nextPlayer = $this->whoIsAfter($user_id);
                            $this->currentBidder = $nextPlayer['user_id'];
                            foreach ($nextPlayer['messages'] as $message) {
                                array_push($messages , $message);
                            }
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
     * Applies the effects and removes the persuasion card with $id from senator with $senatorID after an $outcome of 'FAILURE' or 'SUCCESS'
     * @param type $senatorID
     * @param type $id
     * @param type $outcome
     * @return type
     */
    public function forum_removePersuasionCard($user_id , $senatorID , $id , $outcome) {
        $senator = $this->getSenatorWithID($senatorID);
        if ($senator!==FALSE) {
            $card = $senator->controls->drawCardWithValue($id) ;
            if ($card!==FALSE) {
                $completeMessage='The '.$card->name.' card is discarded.';
                if ($card->name=="BLACKMAIL") {
                    if ($outcome=='FAILURE') {
                        $rollINF = min(0,$this->rollDice(2, -1)) ;
                        $rollPOP = min(0,$this->rollDice(2, -1)) ;
                        $senator->changeINF(-$rollINF);
                        $senator->changePOP(-$rollPOP);
                        $completeMessage.=' The failure of the persuasion causes a loss of '.$rollINF.' INF and '.$rollPOP.' POP to '.$senator->name;
                    }
                }
                $message = array($completeMessage);
            } else {
                $message = array('Cannot find this Card' , 'error' , $user_id );
            }
        } else {
            $message = array('Cannot find this Senator' , 'error' , $user_id );
        }
        return $message ;
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
            array_push($result , array ( 'senatorID' => $senator->senatorID , 'name' => $senator->name , 'knights' => $senator->knights , 'treasury' => $senator->treasury , 'inRome' => $senator->inRome()) );
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
        if (($this->phase=='Forum') && ($this->subPhase=='Knights') && ($this->forum_whoseInitiative()==$user_id) ) {
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
                            array_push($messages , array('Attracting Knight : SUCCESS - '.$senator->name.' ('.$this->party[$user_id]->fullName().') spends '.$amount.' T and rolls '.$roll.'. The total is >= 6.')) ;
                        } else {
                            array_push($messages , array('Attracting Knight : FAILURE - '.$senator->name.' ('.$this->party[$user_id]->fullName().') spends '.$amount.' T and rolls '.$roll.'. The total is < 6.')) ;
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
        if (($this->phase=='Forum') && ($this->subPhase=='Knights') && ($this->forum_whoseInitiative()==$user_id) ) {
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
                if ($senator->inRome() && $senator->treasury >=7) {
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
        if ( ($this->phase=='Forum') && ($this->subPhase=='SponsorGames') && ($this->forum_whoseInitiative()==$user_id) && ($type==7 || $type==13 || $type==18) ) {
            $gamesEffects = array() ; $gamesEffects[7]= 1 ; $gamesEffects[13]= 2 ; $gamesEffects[18]= 3 ;
            $gamesName = array() ; $gamesName[7] = 'Slice & Dice' ; $gamesName[13] = 'Blood Fest' ; $gamesName[18] = 'Gladiator Gala' ; 
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
     * @return array messages
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
                    array_push($messages , array('Initiative #'.$this->initiative , 'alert'));
                    // forum_initInitiativeBids returns messages to indicate players who might have been skipped because they can't bid
                    $initMessages = $this->forum_initInitiativeBids();
                    foreach ($initMessages as $message) {
                        array_push($messages , $message) ;
                    }
                } else {
                    $this->initiative=6;
                    $this->subPhase = 'curia';
                    array_push($messages , array('All initiatives have been played, putting Rome in order.'));
                    $curia_messages = $this->forum_curia();
                    foreach ($curia_messages as $message) {
                        array_push($messages , $message) ;
                    }
                    array_push($messages , array('POPULATION PHASE','alert'));
                    $this->phase = 'Population';
                    $this->subPhase = 'Speech';
                    $this->resetPhaseDone();
                }
            }
        }
        return $messages ;
    }
    
    /**
     * This function is called from within forum_changeLeader on the last initiative
     * It handles :
     * - Major corruption markers
     * - Ruining Concessions because of some conflicts
     * - Reviving Concessions and Senators
     * - Discarding Enemy leaders
     * @return array messages
     */
    public function forum_curia() {
        $messages = array();
        /*
         *  Major corruption markers
         */
        foreach($this->party as $party) {
            foreach ($party->senators->cards as $senator) {
                if ($senator->office !=NULL) {
                    array_push($messages , array($senator->name.' ('.$party->fullName().') held a major office and gets a major corruption marker.'));
                    $senator->major = TRUE ;
                } else {
                    $senator->major = FALSE ;
                }
            }
        }
        
        /*
         *  Ruin tax farmers if 2nd Punic War or slave revolts are in place
         */
        $ruinMessages = $this->forum_ruinTaxFarmers();
        foreach($ruinMessages as $message) {
            array_push($messages , $message);
        }
        
        /*
         *  Curia death/revival
         */
        if (count($this->curia->cards)>0) {
            array_push($messages , array('Rolling for cards in the curia'));
            $cardsToMoveToForum = array() ;
            $cardsToDiscard = array() ;
            foreach ($this->curia->cards as $card) {
                if ($card->type=='Concession' || $card->type=='Family') {
                    $roll = $this->rollOneDie(-1) ;
                    if ($roll>=5) {
                        array_push($cardsToMoveToForum , $card->id);
                        array_push($messages , array('A '.$roll.' is rolled and '.$card->name.' comes back to the forum.'));
                    } else {
                        array_push($messages , array('A '.$roll.' is rolled and '.$card->name.' stays in the curia.'));
                    }
                } elseif ($card->type=='Leader') {
                    $roll = $this->rollOneDie(-1) ;
                    if ($roll>=5) {
                        array_push($cardsToDiscard , $card->id);
                        array_push($messages , array('A '.$roll.' is rolled and '.$card->name.' is discarded.'));
                    } else {
                        array_push($messages , array('A '.$roll.' is rolled and '.$card->name.' stays in the curia.'));
                    }
                }
            }
            // Doing this later in order not to disturb the main loop above (unsure how foreach would behave with a potentially shrinking array...)
            foreach ($cardsToMoveToForum as $cardID) {
                $card = $this->curia->drawCardWithValue('id' , $cardID) ;
                if ($card!==FALSE) {
                    $this->forum->putOnTop($card);
                } else {
                    array_push($messages , array('Error retrieving card from the curia','error')) ;
                }
            }
            foreach ($cardsToDiscard as $cardID) {
                $card = $this->curia->drawCardWithValue('id' , $cardID) ;
                if ($card!==FALSE) {
                    $this->discard->putOnTop($card);
                } else {
                    array_push($messages , array('Error retrieving card from the curia','error')) ;
                }
            }
        }
        return $messages ;
    }

    /**
     * Function taken out of forum_curia for readibility's sake
     * Ruins tax farmers if a conflict that causes their ruin is in place (2nd Punic War or slave revolt)
     * @return array messages
     */
    private function forum_ruinTaxFarmers() {
        $messages = array() ;
        // Ruined by active war
        foreach($this->activeWars->cards as $war) {
            if (strstr($war->causes , 'tax farmer')) {
                $roll=$this->rollOneDie(0);
                $name='TAX FARMER '.(string)$roll;
                $toRuin = $this->getSpecificCard('name', $name) ;
                array_push($messages , array($this->forum_ruinSpecificTaxFarmer($toRuin , $war->name , $roll),'alert'));
            }
        }
        // Ruined by unprosecuted war
        foreach($this->unprosecutedWars->cards as $war) {
            if (strstr($war->causes , 'tax farmer')) {
                $roll=$this->rollOneDie(0);
                $name='TAX FARMER '.(string)$roll;
                $toRuin = $this->getSpecificCard('name', $name) ;
                array_push($messages , array($this->forum_ruinSpecificTaxFarmer($toRuin , $war->name , $roll),'alert'));
            }
        }
        return $messages ;
    }
    
    /**
     * This function is part of forum_ruinTaxFarmers and was also split for readibility's sake
     * @param type $toRuin
     * @param type $becauseOf
     * @return type
     */
    private function forum_ruinSpecificTaxFarmer($toRuin , $becauseOf , $roll) {
        // RUIN tax farmer
        if ($toRuin!==FALSE) {
            if ($toRuin['where']=='curia') {
                return $becauseOf.' causes the ruin of a random tax farmer. A '.$roll.' is rolled : no effect as this tax farmer is already in the curia.';
            } elseif ($toRuin['where']=='senator') {
                $this->curia->putOnTop($toRuin['deck']->drawCardWithValue('id',$toRuin['card']->id));
                return $becauseOf.' causes the ruin of a random tax farmer. A '.$roll.' is rolled : the ruined tax farmer is removed from '.$toRuin['senator']->name.'\'s control and placed in the curia.';
            } elseif ($toRuin['where']=='forum') {
                $this->curia->putOnTop($toRuin['deck']->drawCardWithValue('id',$toRuin['card']->id));
                return $becauseOf.' causes the ruin of a random tax farmer. A '.$roll.' is rolled : the ruined tax farmer is removed from the forum and placed in the curia.';
            } else {
                return $becauseOf.' causes the ruin of a random tax farmer. A '.$roll.' is rolled : ERROR - the ruined tax farmer was found in the wrong deck ('.$toRuin['where'].')';
            }
        } else {
            return $becauseOf.' causes the ruin of a random tax farmer. A '.$roll.' is rolled : no effect as this tax farmer is not in play.';
        }
    }
    
    /************************************************************
     * Functions for POPULATION phase
     ************************************************************/

    /**
     * This function returns an array 'total' => unrest change this turn , 'message' => description of the reason for the change
     * @return array
     */
    public function population_unrest() {
        $result = array() ;
        $result['total']=0;
        $result['message']='No change in unrest from wars and droughts this turn.';
        foreach ($this->unprosecutedWars->cards as $conflict) {
            $result['total']++;
            if ($result['total']==1) {
                $result['message']='Unrest increases because of unprosecuted wars : ';
            }
            $result['message'].=$conflict.name.', ';
        }
        if ($result['total']>0) {
            $result['message']=substr($result['message'],0,-2);
            $result['message'].=' : + '.$result['total'].' unrest.';
        }
        $droughtLevel = $this->getTotalDroughtLevel();
        if ($droughtLevel > 0 ) {
            if ($result['total']>0) {
                $result['message'].= ' And ';
            }
            $result['message'].=$droughtLevel.' droughts cause + '.$droughtLevel.' unrest.';
            $result['total']+=$droughtLevel;
        }
        return $result;
    }

    /**
     * - The unrest is increased by the value calculated in population_unrest
     * - The HRAO makes the speech and effects are applied (including possible game over)
     * @param type $user_id
     * @return array
     */
    public function population_speech($user_id) {
        $messages = array() ;
        if ( ($this->phase=='Population') && ($this->whoseTurn()==$user_id) ) {
            $thisTurnUnrest = $this->population_unrest() ;
            array_push($messages , array($thisTurnUnrest['message']));
            $this->unrest+=$thisTurnUnrest['total'];
            $roll=$this->rollDice(3, -1) ;
            $HRAO = $this->getHRAO();
            // $total is minimum -1 and maximum 18
            $total = max (-1 , min (18 , $roll['total'] + $this->unrest - $HRAO['senator']->POP )) ;
            array_push($messages , array($HRAO['senator']->name.' rolls '.$roll['total'].' + current unrest ('.$this->unrest.') - HRAO\'s Popularity ('.$HRAO['senator']->POP.')  for a total of '.$total.'.'));
            if ($total!=-1) {
                $effects = $this->populationTable[$total];
                foreach ($effects as $effect) {
                    switch($effect) {
                        case 'MS' :
                            array_push($messages , array($this->forum_putEventInPlay('name' , 'Manpower Shortage'),'alert'));
                            break ;
                        case 'NR' :
                            array_push($messages , array($this->forum_putEventInPlay('name' , 'No Recruitment'),'alert'));
                            break ;
                        case 'Mob' :
                            $mobMessages = $this->population_mob() ;
                            foreach ($mobMessages as $message) {
                                array_push($messages , $message);
                            }
                            break ;
                        case 0 :
                            array_push($messages , array('No effect.')) ;
                            break ;
                        default :
                            $effect=(int)$effect;
                            $this->unrest+=$effect;
                            // Unrest cannot be negative
                            $this->unrest=max(0 , $this->unrest) ;
                            array_push($messages , array('The unrest is changed by '.($effect>0 ? '+' :'').$effect.', now at '.$this->unrest,'alert')) ;
                    }
                }
            } else {
                array_push($messages , array(_('People revolt - Game over.') , 'error'));
            }
            array_push($messages , array('SENATE PHASE','alert'));
            $init_messages = $this->senate_init();
            foreach($init_messages as $init_message) {
                array_push($messages , $init_message);
            }
        }
        return $messages ;
    }
    
    /**
     * Sub function of population_speech, handling angry mob
     * @return array message
     */
    public function population_mob() {
        $messages = array() ;
        array_push($messages , array(_('The Senate is attacked by an angry mob !'),'alert')) ;
        $chits = $this->mortality_chits(6);
        foreach ($chits as $chit) {
            if ($chit!='NONE' && $chit!='DRAW 2') {
                $returnedMessage= $this->mortality_killSenator((string)$chit) ;
                array_push( $messages , array(sprintf(_('Chit drawn : %s. ') , $chit).$returnedMessage[0] , (isset($returnedMessage[1]) ? $returnedMessage[1] : NULL) ));
            } else {
                array_push( $messages , array(sprintf(_('Chit drawn : %s') , $chit)) );
            }
        }
        return $messages ;
    }
    
    /************************************************************
     * Functions for SENATE phase
     ************************************************************/
    
    /**
     * Initialises various Senate-related variables
     */
    public function senate_init() {
        $messages = array() ;
        $this->phase = 'Senate';
        $this->subPhase = 'Consuls';
        unset($this->steppedDown); $this->steppedDown = array() ;
        unset($this->proposals); $this->proposals = array() ;
        // Free tribunes per party, based on Senators inRome & specialAbility
        foreach ($this->party as $party) {
            unset($party->freeTribunes) ; $party->freeTribunes = array() ;
            foreach ($party->senators->cards as $senator) {
                if ($senator->inRome() && $senator->specialAbility!==NULL) {
                    $abilities = explode(',' , $senator->specialAbility) ;
                    if (in_array('Tribune' , $abilities)) {
                        $party->freeTribunes[] = array('senatorID' => $senator->senatorID , 'name' => $senator->name ) ;
                    }
                }
            }
        }
        // Check if there is only one possible pair of consuls, and appoint them if it's the case.
        $possiblePairs = $this->senate_consulsPairs() ;
        if (count($possiblePairs)==1) {
            $senator1 = $this->getSenatorWithID($possiblePairs[0][0]) ;
            $senator2 = $this->getSenatorWithID($possiblePairs[0][1]) ;
            $proposal = new Proposal ;
            $proposal->init('Consuls' , NULL , NULL , $this->party , array($possiblePairs[0][0] , $possiblePairs[0][1]) , NULL) ;
            $proposal->outcome = TRUE ;
            array_push($messages , array(sprintf(_('%s and %s are nominated consuls as the only available pair.') , $senator1->name , $senator2->name)) );
        }
        return $messages ;
    }
    
    /**
     * Return an array with the Senator & party holding the office $office or FALSE if not found
     * @param string $office
     * @return array|bool array('senator' , 'user_id') or FALSE
     */
    public function senate_findOfficial($office) {
        foreach ($this->party as $party) {
            foreach ($party->senators->cards as $senator) {
                if ($senator->office==$office) {
                    return array('senator'=>$senator , 'user_id'=>$party->user_id);
                }
            }
        }
        return FALSE ;
    }

    /**
     * @param type $user_id
     * Put a proposal forward
     * @param string $type A valid proposal type, as in Proposal::$VALID_PROPOSAL_TYPES
     * @param string $description A proposal's description
     * @param type $proposalHow
     * @param type $parameters an array of parameters
     * @param type $votingOrder
     * @return array messages
     */
    public function senate_proposal($user_id , $type , $description , $proposalHow , $parameters , $votingOrder) {
        $messages = array() ;
        /*
         * Basic checks
         */
        $check = $this->senate_validateProposalBasic($user_id , $type , $proposalHow , $votingOrder) ;
        if ($check !==TRUE) {
            return array(array($check , 'error' , $user_id)) ;
        }
        /*
         * Parameters validation
         */
        $rules = Proposal::validationRules($type) ;
        // For Prosecutions, parameters 0 & 1 are 'compressed' into parameter 0 at the time of form submission
        if ($type=='Prosecutions') {
            $compressedParameter = explode(',' , $parameters[0]);
            $parameters[0] = $compressedParameter[0] ;
            $parameters[1] = $compressedParameter[1] ;
        }
        foreach ($rules as $rule) {
            $validation = $this->senate_validateParameter($rule[0] , $parameters , $rule[1]) ;
            if ($validation !== TRUE) {
                return array(array($validation , 'error' , $user_id)) ;
            }
        }
        /*
         * Create the proposal, return error if initialisation fails although this is unlikely
         * (can only be caused by description error, freak type error, etc...)
         */
        $proposal = new Proposal ;
        $result = $proposal->init($type , $user_id , $description , $this->party , $parameters , $votingOrder) ;
        if ($result !== TRUE) {
            return array(array($result)) ;
        }

        // Consuls
        if ($type=='Consuls') {
            $senator1 = $this->getSenatorWithID($parameters[0]) ;
            $senator2 = $this->getSenatorWithID($parameters[1]) ;
            $using = $this->senate_useProposalHow($user_id , $proposalHow) ;
            array_push($messages , array(sprintf(_('%s, {%s} proposes %s and %s as consuls.') , $using , $user_id , $senator1->name , $senator2->name)) );
            array_push($this->proposals , $proposal) ;
            
        //Dictator
        } elseif ($type=='Dictator') {
            // TO DO : Dictator Proposal
             
        // Censor
        } elseif ($type=='Censor') {
            // TO DO : Censor Proposal
        
        // Prosecutions
        } elseif ($type=='Prosecutions') {
            $reasonsList = $this->senate_getListPossibleProsecutions() ;
            $reasonText = '';
            foreach ($reasonsList as $reason) {
                if ($reason['reason']==$parameters[1]) {
                    $reasonText = $reason['text'] ;
                }
            }
            array_push($messages , array(sprintf(_('The Censor {%s} accuses %s, appointing %s as prosecutor. Reason : %s') , $user_id , $this->getSenatorFullName($parameters[0]) , $this->getSenatorFullName($parameters[2]) , $reasonText )) );
            array_push($this->proposals , $proposal) ;
        
        // TO DO : 15 other types of proposals... 
        } else {
            return array(array(_('Error with proposal type.') , 'error' , $user_id)) ;
        }
        return $messages;
    }
    
    /**
     * Function that validates proposals, checking only for basic errors :
     * Wrong phase, another proposal under way, wrong type, wrong way of making proposal, wrong voting order
     * @param string $user_id
     * @param string $type
     * @param mixed $proposalHow string or senatorID
     * @param array $votingOrder
     * @return TRUE or array of messages
     */
    private function senate_validateProposalBasic($user_id , $type , $proposalHow , $votingOrder) {
        /*
         * Basic checks
         */
        if ($this->phase!='Senate') {
            return array(array(_('Wrong phase.') , 'error' , $user_id)) ;
        }
        
        $latestProposal = $this->senate_getLatestProposal() ;
        
        // Check that no voting is underway already
        // TO DO : A tribune can interrupt the current proposal
        if ($latestProposal!==FALSE && $latestProposal->outcome===NULL) {
            return array(array(_('Error - Another proposal is underway.') , 'error' , $user_id)) ;
        }
        
        // Check $type
        $typeKey = array_search($type, Proposal::$VALID_PROPOSAL_TYPES) ;
        if ($typeKey===FALSE) {
            return array(array(_('Error with proposal type.') , 'error' , $user_id)) ;
        }

        // Check if the returned $proposalHow value is valid for this user_id
        $canMakeProposalList = $this->senate_canMakeProposal($user_id) ;
        $canMakeProposal = FALSE ;
        foreach($canMakeProposalList as $how) {
            if (is_array($how)) {
                if ($how['senatorID']==$proposalHow) {
                    $canMakeProposal = TRUE ;
                }
            } else {
                if ($how==$proposalHow) {
                    $canMakeProposal = TRUE ;
                }
            }
        }
        if (!$canMakeProposal) {
            return array(array(sprintf(_('Cannot make proposals using %s') , $proposalHow) , 'error')) ;
        }
        // Voting Order : Goes through all the elements of the $votingOrder array and check if they are all exactly the same as the keys in $this->party
        if (count($votingOrder)!=count($this->party)) {
            return array(array(_('Invalid voting order : not enough parties') , 'error')) ;
        }
        while (count($votingOrder)>0) {

            $currentElement = array_shift($votingOrder) ;
            if (!array_key_exists($currentElement , $this->party)) {
                return array(array(_('Invalid voting order : unknown party') , 'error')) ;
            }
        }
        return TRUE ;
    }
    
    /**
     * Validates a proposal's parameters against a specific rule
     * @param type $rule 'Pair'|'inRome'|'office'|'censorRejected'
     * @param array $parameters the array of parameters of the proposal
     * @param integer $index The index of the parameter to be validated, if needed (for some rules, it's obvious, like 'pair')
     * @return string
     */
    private function senate_validateParameter($rule , $parameters , $index=0) {
        switch ($rule) {
            case 'pair' :
                usort ($parameters, function($a, $b) {
                    return strcmp($a, $b);
                });
                if ($parameters[0] == $parameters[1]) {
                    return 'This is a pair of one. Please stop drinking.';
                }
                foreach ($this->proposals as $proposal) {
                    if ($proposal->type=='Consuls' && $proposal->outcome==FALSE && $proposal->parameters[0]==$parameters[0] && $proposal->parameters[1]==$parameters[1]) {
                        return 'This pair has already been rejected';
                    }
                }
                return TRUE ;
            case 'inRome' :
                return ( $this->getSenatorWithID($parameters[$index])->inRome() ? TRUE : 'Senator is not in Rome.' ) ;
            case 'office' :
                $senator = $this->getSenatorWithID($parameters[$index]) ;
                if (!in_array('Tradition Erodes' , $this->laws)) {
                    if ( ($senator->office == 'Dictator') || ($senator->office == 'Rome Consul') || ($senator->office == 'Field Consul') ) {
                        return 'Before the \'Tradition Erodes\' law is in place, Senators cannot be proposed if they are already Dictator or Consul.';
                    }
                }
                return ( ($senator->office == 'Pontifex Maximus') ? 'The Pontifex Maximus cannot be proposed.' : TRUE ) ;
            case 'censorRejected' :
                foreach ($this->proposals as $proposal) {
                    if ($proposal->type=='Censor' && $proposal->outcome==FALSE && $proposal->parameters[0]==$parameters[0]) {
                        return 'Proposing this Senator as Censor has already been rejected';
                    }
                }
                return TRUE ;
            case 'cantProsecuteSelf' :
                return ($parameters[0]==$parameters[2] ? 'A Senator cannot prosecute himself' : TRUE) ;
            case 'censorCantBeProsecutor' :
                $senator = $this->getSenatorWithID($parameters[2]) ;
                return ($senator->office=='Censor' ? 'The Censor cannot be prosecutor' : TRUE) ;
            case 'prosecutionRejected' :
                foreach ($this->proposals as $proposal) {
                    if ($proposal->type=='Prosecutions' && $proposal->outcome==FALSE && $proposal->parameters[0]==$parameters[0] && $proposal->parameters[1]==$parameters[1]) {
                        return 'This prosecution has already been rejected';
                    }
                }
                return TRUE ;
        }
        return 'Wrong rule';
    }
    
    /**
     * Cast vote on current proposal
     * @param string $user_id
     * @param array $request
     */
    public function senate_vote($user_id , $request) {
        // TO DO : Veto
        $messages = array() ;
        $ballotMessage = array();
        $ballotMessage[-1] = _('votes AGAINST the proposal');
        $ballotMessage[0] = _('ABSTAINS');
        $ballotMessage[1] = _('votes FOR the proposal');
        if ($this->phase!='Senate') {
            return array(array(_('Wrong phase.') , 'error' , $user_id));
        }
        $latestProposal = $this->senate_getLatestProposal() ;
        if ($latestProposal->votingOrder[0]!=$user_id) {
            return array(array(_('This is not your turn to vote.') , 'error' , $user_id));
        }
        if ($latestProposal->outcome!==NULL) {
            return array(array(_('The vote on this proposal is already over.') , 'error' , $user_id));
        }
        $votingMessage = '';
        foreach ($latestProposal->voting as $key => $voting) {
            if ($voting['user_id']==$user_id) {
                // Whole party vote : set all the senators of this party to vote the same way
                if (isset($request['wholeParty'])) {
                    $latestProposal->voting[$key]['ballot'] = (int)$request['wholeParty'] ;
                    $votingMessage = sprintf(_('{%s} %s') , $user_id , $ballotMessage[$request['wholeParty']]);
                // Per senator vote : set the senator's ballot to the value given through POST & handle talents spent
                } else {
                    // Ignore senators with 0 votes (not in Rome)
                    if (isset($request[$voting['senatorID']]) && $latestProposal->voting[$key]['votes']>0) {
                        $latestProposal->voting[$key]['ballot'] = (int)$request[$voting['senatorID']] ;
                        // The Senator has spent talents to increase his votes
                        if (isset($request[$voting['senatorID'].'_talents'])) {
                            $amount = (int)$request[$voting['senatorID'].'_talents'];
                            $senator = $this->getSenatorWithID($voting['senatorID']) ;
                            if ($senator->treasury < $amount) {
                                return array(array(sprintf(_('%s tried to spent talents he doesn\'t have.') , $voting['name']) , 'error' , $user_id));
                            } else {
                                $senator->treasury -= $amount ;
                                $latestProposal->voting[$key]['talents'] = $amount ;
                            }
                        }
                        $votingMessage = sprintf(_('%s of party {%s} %s') , $voting['name'] , $user_id , $ballotMessage[$request[$voting['senatorID']]]) ;
                        $votingMessage.= ($latestProposal->voting[$key]['talents']==0 ? '' : sprintf(_(' and spends %dT to increase his votes') , $latestProposal->voting[$key]['talents'])) ;
                    } else {
                        return array(array(sprintf(_('On a per senator vote, %s\'s vote was not set.') , $voting['name']) , 'error' , $user_id));
                    }
                }
            }
        }
        array_push($messages , array($votingMessage)) ;
        // Remove the voting party from the voting order, as they have now voted.
        array_shift($latestProposal->votingOrder);
        
        // Vote is finished
        if (count($latestProposal->votingOrder)==0) {
            $total = 0 ; $for = 0 ; $against = 0 ; $abstention = 0 ;
            $unanimous = TRUE ;
            $HRAO = $this->getHRAO(TRUE) ;
            foreach ($latestProposal->voting as $voting) {
                // The $unanimous flag is set to FALSE as soon as we find ONE Senator not from the Presiding magistrate party, with non-0 votes, who voted For or Abstained
                if ($voting['user_id']!=$HRAO['user_id'] && $voting['votes']>0 && $voting['ballot']!=-1) {
                    $unanimous = FALSE ;
                }
                $total += $voting['ballot'] * ($voting['votes'] + $voting['talents']) ;
                switch($voting['ballot']) {
                    case -1 :
                        $against+=($voting['votes'] + $voting['talents']) ;
                        break ;
                    case 0 :
                        $abstention+=($voting['votes'] + $voting['talents']) ;
                        break ;
                    case 1 :
                        $for+=($voting['votes'] + $voting['talents']) ;
                        break ;
                }
            }
            $latestProposal->outcome = ( $total > 0 ) ;
            $votingMessage = ($latestProposal->outcome ? _('The proposal is adopted') : _('The proposal is rejected')) ;
            $votingMessage .= sprintf(_(' by %d votes for, %d against, and %d abstentions.') , $for , $against , $abstention);
            array_push($messages , array($votingMessage)) ;
            if ($unanimous && $latestProposal->proposedBy==$HRAO['user_id']) {
                array_push($messages , array(sprintf(_('The presiding magistrate %s has been unanimously defeated.') , $HRAO['senator']->name))) ;
                $this->subPhase = 'Unanimous defeat' ;
            }
            /* 
             * Deal with automatic nominations if the outcome was FALSE
             * - Consuls : Only one pair of senators left
             * - Censor : Only one prior consul left
             */
            if ($latestProposal->outcome == FALSE) {
                if ($latestProposal->type=='Consuls') {
                    $possiblePairs = $this->senate_consulsPairs() ;
                    if (count($possiblePairs)==1) {
                        $senator1 = $this->getSenatorWithID($possiblePairs[0][0]) ;
                        $senator2 = $this->getSenatorWithID($possiblePairs[0][1]) ;
                        $proposal = new Proposal ;
                        $proposal->init('Consuls' , NULL , NULL , $this->party , array($possiblePairs[0][0] , $possiblePairs[0][1]) , NULL) ;
                        $proposal->outcome = TRUE ;
                        array_push($messages , array(sprintf(_('%s and %s are nominated consuls as the only available pair.') , $senator1->name , $senator2->name)) );
                    }
                } elseif ($latestProposal->type=='Censor') {
                    $candidates = $this->senate_possibleCensors() ;
                    // Only one eligible candidate : appoint him and move to prosecution phase
                    if (count($candidates)==1) {
                        array_push($messages , array(sprintf(_('Only %s is eligible for Censorship, so he is automatically elected.') , $candidates[0]->name)) );
                        $this->senate_appointOfficial('Censor', $candidates[0]->senatorID) ;
                        $this->subPhase='Prosecutions';
                    }
                }
            }
        }
        return $messages ;
    }

    /**
     * Roll on the popular appeal table when a player appeals to the people, and applies the immediate effects if any
     * @param string $user_id The player appealling to the people
     * @return array messages
     */
    public function senate_appeal($user_id) {
        $messages = array() ;
        if ($this->phase == 'Senate' && $this->subPhase == 'Prosecutions') {
            $latestProposal = $this->senate_getLatestProposal() ;
            $accused = $this ->getSenatorWithID($latestProposal->parameters[0]) ;
            if ($latestProposal->type == 'Prosecutions' && ($this ->getPartyOfSenator($accused) -> user_id == $user_id) && $latestProposal->parameters[4]===NULL ) {
                $roll = $this->rollDice(2, -1) ;
                array_push($messages , sprintf(_('Popular Appeal : %s rolls %d.') , $accused->name , $roll['total'])) ;
                $modifiedResult = $roll['total'] + $accused->POP ;
                $appealEffects = $this->appealTable[max(2 , min(12 , $modifiedResult))] ;
                if ($appealEffects['special']=='freed') {
                    // The accused is freed, and mortality chits are drawn to potentially kill the prosecutor or censor
                    $chitsToDraw = 12 - $modifiedResult ;
                    array_push($messages , sprintf(_('The righteous populace frees him. Since a %d was rolled, %d mortality chits are drawned to see if they kill the Prosecutor or Censor for this obvious frame-up.') , $modifiedResult , $chitsToDraw)) ;
                    $prosecutor = $this ->getSenatorWithID($latestProposal->parameters[2]) ;
                    $censor = $this->getHRAO(TRUE) ;
                    $chits = $this->mortality_chits($chitsToDraw) ;
                    foreach ($chits as $chit) {
                        if ($chit!='NONE' && $chit!='DRAW 2') {
                            if ($prosecutor->senatorID == (string)$chit || $censor->senatorID == (string)$chit || $prosecutor->statesmanFamily()==(string)$chit || $censor->statesmanFamily()==(string)$chit) {
                                $returnedMessage= $this->mortality_killSenator((string)$chit) ;
                                array_push($messages , array(sprintf(_('Chit drawn : %s. %s'), $chit , $returnedMessage[0]) , (isset($returnedMessage[1]) ? $returnedMessage[1] : NULL) ));
                            }
                        } else {
                            array_push($messages , array(sprintf(_('Chit drawn : %s'),$chit)));
                        }
                    }
                    $this->proposals[count($this->proposals)-1]->parameters[4] = 'freed' ;
                    $this->proposals[count($this->proposals)-1]->outcome = FALSE ;
                } elseif ($appealEffects['special']=='killed') {
                    array_push($messages , _('The disgusted populace kills him themselves')) ;
                    // TO DO : Check what happens when the ID is alphanumerical
                    array_push($messages , $this->mortality_killSenator($accused->senatorID)) ;
                    $this->proposals[count($this->proposals)-1]->parameters[4] = 'killed' ;
                    $this->proposals[count($this->proposals)-1]->outcome = TRUE ;
                } else {
                    $this->proposals[count($this->proposals)-1]->parameters[4] = $appealEffects['votes'] ;
                    array_push($messages , sprintf(_('%s %s %d votes from his popular appeal.' , $accused->name , ($appealEffects['votes']>0 ? _('gains') : _('loses')) , $appealEffects['votes'] ))) ;
                }
            }
        }
        return $messages ;
    }

    /**
     * Proceed with the effects of a successful prosecutions : gain/loss of POP,INF,Prior Consul,Concessions, Life
     * @return array messages
     */
    public function senate_prosecutionSuccessful() {
        $messages = array() ;
        $latestProposal = $this->senate_getLatestProposal() ;
        if ($latestProposal->type=='Prosecutions' && $latestProposal->outcome==TRUE) {
            $accused = $this->getSenatorWithID($latestProposal->parameters[0]) ;
            $prosecutor = $this->getSenatorWithID($latestProposal->parameters[2]) ;
            // This was a major prosecution
            // TO DO 
            if ($latestProposal->parameters[1]=='major') {
            // This was a minor prosecution
            } else {
                $INFloss = min(5 , $accused->INF) ;
                $priorConsulMarker = $accused->priorConsul ;
                $accused->changePOP(-5) ;
                $accused->changeINF(-5) ;
                $accused->priorConsul = FALSE ;
                $message = sprintf(_('Minor prosecution successful : %s loses %d INF, 5 POP%s. ') , $accused->name , $INFloss , ($priorConsulMarker ? _(' and his prior consul marker') : '') );
                $concessionLossMessage = NULL;
                foreach($accused->controls->cards as $card) {
                    if ($card->type=='Concession') {
                        if ($concessionLossMessage===NULL) {
                            $concessionLossMessage = _('As well as the following concessions : ');
                        }
                        $concession = $accused->controls->drawCardWithValue('id' , $card->id) ;
                        $concessionLossMessage .= $concession->name.', ';
                        $this->forum->putOnTop($concession) ;
                    }
                }
                if ($concessionLossMessage !== NULL) {
                    $concessionLossMessage = substr($concessionLossMessage, 0 , -2) ;
                    $message.=$concessionLossMessage ;
                }
                array_push($messages , array($message)) ;
                $prosecutor->changeINF($INFloss) ;
                $message2 = sprintf(_('The prosecutor %s gains %d INF.') , $prosecutor->name , $INFloss);
                if ($priorConsulMarker) {
                    $prosecutor->priorConsul = TRUE ;
                    $message2.=' As well as the accused\'s prior consul marker.';
                }
                array_push($messages , array($message2)) ;
            }
        }
        return $messages ;
    }
    
    /**
     * Handles the Presiding Magistrate decision to stepdown or not following an unanimous defeat, then sets the subPhase back to the latest proposal type
     * @param string $user_id
     * @param int $stepDown 0|1
     * @return array
     */
    public function senate_stepDown($user_id , $stepDown) {
        $messages = array() ;
        $HRAO = $this->getHRAO(TRUE) ;
        if ($HRAO['user_id']==$user_id) {
            if ($stepDown==0 && ($HRAO['senator']->INF > 0) ) {
                $HRAO['senator']->INF -= 1 ;
                array_push($messages , array(sprintf(_('%s stays presiding magistrate after his unanimous defeat, losing 1 Influence.') , $HRAO['senator']->name )) );
            } else {
                array_push ($this->steppedDown ,  $HRAO['senator']->senatorID ) ;
                array_push($messages , array(sprintf(_('%s steps down as presiding magistrate after his unanimous defeat.') , $HRAO['senator']->name )) );
            }
            $latestProposal = $this->senate_getLatestProposal() ;
            $this->subPhase = $latestProposal->type ;
            // If the Censor steps down, move to Governors election.
            if ($stepDown==1 && $this->subPhase == 'Prosecutions') {
                array_push($messages , array(_('With the Censor stepping down, the Prosecution phase ends.')) );
                $this->subPhase = 'Governors' ;
            }
        } else {
            return array(array(_('You are not presiding magistrate, hence cannot step down.') , 'error' , $user_id));
        }
        return $messages ;
    }
    
     /*
     * setup_view returns all the data needed to render setup templates. The function returns an array $output :
     * $output['state'] (Mandatory) gives the name of the current state to be rendered :
     * - Vote : A vote on a proposal is underway
     * - Proposal : Make a proposal
     * - Proposal impossible : Unable to make a proposal
     * - Agree : Need to agree on a voted proposal (e.g. Consuls decide who does what, Accusator agrees to prosecute, etc)
     * - Error : A problem occured
     * $output[{other}] : values or array of values based on context
     * @param string $user_id
     * @return array $output
     */
    public function senate_view($user_id) {
        $output = array() ;
        /* TO DO : Check if the following 2 actions are available (they almost always are) :
         * - Assassination
         * - Tribune
         */
        $latestProposal = $this->senate_getLatestProposal() ;
        /*
         * Short-circuit the normal view if the sate is 'Unanimous defeat'
         */
        if (($this->phase=='Senate') && ($this->subPhase=='Unanimous defeat') ) {
            $output['state'] = 'Unanimous defeat' ;
            $HRAO = $this->getHRAO(TRUE) ;
            if ($HRAO['user_id']==$user_id) {
                $output['presiding'] = TRUE ;
                $output['name'] = $HRAO['senator']->name ;
                // INF at 0 : no choice but to resign
                if ($HRAO['senator']->INF == 0) {
                // INF greater than 0 : choice to resign
                    $output['choice'] = FALSE ;
                } else {
                    $output['choice'] = TRUE ;
                }
            } else {
                $output['presiding'] = FALSE ;
            }
            return $output ;
        }
        /*
         *  There is a proposal underway : Either a decision has to be made (before or after a vote), or a vote is underway
         */
        $output['type'] = $latestProposal->type ;
        // The proposal's long description
        $output['longDescription'] = $this->senate_viewProposalDescription($latestProposal) ;
        /*
         * Decisions
         */
        // Consuls : The outcome is TRUE (the proposal was voted), but Consuls have yet to decide who will be Consul of Rome / Field Consul
        if ($this->phase=='Senate' && $this->subPhase=='Consuls' && $latestProposal->outcome===TRUE && ($latestProposal->parameters[2]===NULL || $latestProposal->parameters[3]===NULL) ) {
            $output['state'] = 'Decision' ;
            $output['type'] = 'Consuls' ;
            $senator = array() ; $party = array() ;
            for ($i=0 ; $i<2 ; $i++) {
                $senator[$i] = $this->getSenatorWithID($latestProposal->parameters[$i]) ;
                $party[$i] = $this->getPartyOfSenator($senator[$i]);
            }
            if ($party[0]->user_id!=$user_id && $party[1]->user_id!=$user_id) {
                $output['canDecide'] = FALSE ;
            } else {
                $output['canDecide'] = TRUE ;
                for ($i=0 ; $i<2 ; $i++) {
                    if ($party[$i]->user_id == $user_id) {
                        if ( ($latestProposal->parameters[2]===NULL || $latestProposal->parameters[2]!=$senator[$i]->senatorID) && ($latestProposal->parameters[3]===NULL || $latestProposal->parameters[3]!=$senator[$i]->senatorID) ) {
                            $output['senator'][$i] = $senator[$i] ;
                        } else {
                            $output['senator'][$i] = 'alreadyDecided' ;
                        }

                    } else {
                        $output['senator'][$i] = 'notYou' ;
                    }
                }
            }
        // Prosecutions : The prosecutor must agree to his appointment
        } elseif ($this->phase=='Senate' && $this->subPhase=='Prosecutions' && $latestProposal->outcome===NULL && $latestProposal->parameters[3]===NULL ) {
            $output['state'] = 'Decision' ;
            $output['type'] = 'Prosecutions' ;
            $prosecutor = $this->getSenatorWithID($latestProposal->parameters[2]) ;
            $output['prosecutorName'] = $this->getSenatorFullName($latestProposal->parameters[2]) ;
            $output['prosecutorID'] = $prosecutor -> senatorID ;
            $output['prosecutorParty'] = $this->getPartyOfSenator($prosecutor)->user_id ;
            $output['canDecide'] = ( $output['prosecutorParty'] == $user_id ) ;
        /*
         *  TO DO - other decisions :
         * - Dictator appoints Master of horse
         * - Accused calls Popular Appeal
         * etc
         */
        /*
         * Vote (The proposal has no outcome but no decision is required : make vote possible)
         */
        } elseif ($latestProposal!==FALSE && $latestProposal->outcome===NULL) {
            $output['state'] = 'Vote' ;
            $output['type'] = $latestProposal->type ;
            // The proposal's long description
            $output['longDescription'] = $this->senate_viewProposalDescription($latestProposal) ;
            $output['voting'] = $latestProposal->voting ;
            $output['treasury'] = array() ;
            foreach($this->party[$user_id]->senators->cards as $senator) {
                $output['treasury'][$senator->senatorID] = $senator->treasury ;
            }
            $output['votingOrder'] = $latestProposal->votingOrder ;
            $output['votingOrderNames'] = array() ;
            foreach ($output['votingOrder'] as $key=>$value) {
                $output['votingOrderNames'][$key] = $this->party[$value]->fullName();
            }
            // Popular appeal possible or not
            if ( ($latestProposal->type=='Prosecutions') && ($this ->getPartyOfSenatorWithID($latestProposal->parameters[0]) -> user_id == $user_id) && $latestProposal->parameters[4]===NULL ) {
                $output['appeal'] = TRUE ;
                $output['popularity'] = $this->getSenatorWithID($latestProposal->parameters[0])->POP ;
            } else {
                $output['appeal'] = FALSE ;
            }
            // This is not your turn to vote
            if ($output['votingOrder'][0]!=$user_id) {
                $output['canVote'] = FALSE ;
            // This is your turn to vote
            } else {
                $output['canVote'] = TRUE ;
            }

        /*
         * There is no proposal underway, give the possibility to make proposals
         */
        } else {
            // This 'votingOrder' parameter is simply a list of user_ids, it's provided to be re-ordered by the player making the proposal.
            $output['votingOrder']=array();
            foreach($this->party as $party) {
                array_push($output['votingOrder'],array('user_id' => $party->user_id , 'name' => $party->fullname()));
            }
            // How to make a proposal
            $output['proposalHow'] = $this->senate_canMakeProposal($user_id);
            
            // Cannot make a proposal
            if (count($output['proposalHow'])==0) {
                $output['state'] = 'Proposal impossible';
                
            //Can make a proposal
            } else {
                /*
                 * Consuls
                 */
                if ( ($this->phase=='Senate') && ($this->subPhase=='Consuls') ) {
                    $output['state'] = 'Proposal';
                    $output['type'] = 'Consuls';
                    $output['pairs'] = array() ;
                    $possiblePairs = $this->senate_consulsPairs() ;
                    foreach($possiblePairs as $pair) {
                        $senator1 = $this->getSenatorWithID($pair[0]) ;
                        $party1 = $this->getPartyOfSenator($senator1) ;
                        $senator2 = $this->getSenatorWithID($pair[1]) ;
                        $party2 = $this->getPartyOfSenator($senator2) ;
                        array_push($output['pairs'] , array($senator1->name.' ('.$party1->fullName().')' , $senator2->name.' ('.$party2->fullName().')'));
                    }
                    $output['senators'] = array() ;
                    $alignedSenators = $this->getAllAlignedSenators(TRUE) ;
                    foreach ($alignedSenators as $senator) {
                        array_push($output['senators'] , array('senatorID' => $senator->senatorID , 'name' => $senator->name , 'partyName' => $this->getPartyOfSenator($senator)->fullName())) ;
                    }
                /*
                 * Pontifex Maximus
                 */
                /*
                 * Dictator
                 */
                /*
                 * Censor
                 */
                } elseif ( ($this->phase=='Senate') && ($this->subPhase=='Censor') ) {
                    $candidates = $this->senate_possibleCensors() ;
                    $output['state'] = 'Proposal';
                    $output['type'] = 'Censor';
                    $output['senators'] = array () ;
                    foreach ($candidates as $candidate) {
                        array_push($output['senators'] , array('senatorID' => $candidate->senatorID , 'name' => $candidate->name , 'partyName' => $this->getPartyOfSenator($candidate)->fullName())) ;
                    }
                /*
                 * Prosecutions
                 */
                } elseif ( ($this->phase=='Senate') && ($this->subPhase=='Prosecutions') ) {
                    $output['state'] = 'Proposal';
                    $output['type'] = 'Prosecutions';
                    $output['list'] = $this->senate_getListPossibleProsecutions() ;
                    $output['possibleProsecutors'] = $this->senate_getListPossibleProsecutors() ;
                
                // TO DO : All the rest...
                } else {
                    $output['state'] = 'Error';
                }
            }
        }
        return $output ;
    }

    /**
     * Returns a description of the current proposal
     * @return type
     */
    private function senate_viewProposalDescription($latestProposal) {
        if ($latestProposal->proposedBy == NULL) {
            $description = _('Automatic proposal for : ');
        } else {
            $description = sprintf(_('%s is proposing : ') , $this->party[$latestProposal->proposedBy]->fullName());
        }
        switch ($latestProposal->type) {
            case 'Consuls' :
                $senator1 = $this->getSenatorWithID($latestProposal->parameters[0]) ;
                $senator2 = $this->getSenatorWithID($latestProposal->parameters[1]) ;
                $description.= sprintf(_('%s and %s as consuls.') , $senator1->name , $senator2->name);
                break ;
            case 'Censor' :
                $censor = $this->getSenatorWithID($latestProposal->parameters[0]) ;
                $description.= sprintf(_('%s as Censor.') , $censor->name);
                break ;
            case 'Prosecutions' :
                $reasonsList = $this->senate_getListPossibleProsecutions() ;
                $reasonText = '';
                foreach ($reasonsList as $reason) {
                    if ($reason['reason']==$latestProposal->parameters[1]) {
                        $reasonText = $reason['text'] ;
                    }
                }
                $description.= sprintf(_('%s. Prosecutor : %s') , $reasonText , $this->getSenatorFullName($latestProposal->parameters[2]) );
                break ;
            // TO DO : other types of proposals
        }
        return $description ;
    }
    
    /**
     * A decision (not a vote) has been made on a proposal that was voted (outcome is TRUE) :<br>
     * - Consuls deciding who will do what<br>
     * - Dictator appointing MoH<br>
     * - Prosecutor accepting/refusing nomination<br>
     *   etc...
     * @param string $user_id user_id
     * @param array $request the POST data
     * @return array message array
     */
    public function senate_decision($user_id , $request) {
        $messages = array() ;
        $latestProposal = $this->senate_getLatestProposal() ;
        /*
         * Consuls decision
         */
        if ($this->phase=='Senate' && $this->subPhase=='Consuls' && $latestProposal->outcome===TRUE && $request['type']=='consuls') {
            for ($i=0 ; $i<2 ; $i++) {
                $senator[$i] = $this->getSenatorWithID($latestProposal->parameters[$i]) ;
                $party[$i] = $this->getPartyOfSenator($senator[$i])->user_id;
                $choice[$i] = (isset($request[$latestProposal->parameters[$i]]) ? $request[$latestProposal->parameters[$i]] : FALSE) ;
            }
            if ($party[0]!=$user_id && $party[1]!=$user_id) {
                return array(array(_('You cannot take such a decision at this moment.')  , 'error' , $user_id));
            }
            $disagreement = FALSE ;
            for ($i=0 ; $i<2 ; $i++) {
                // The player has made a choice for this senator
                if ($party[$i]==$user_id && $choice[$i]!==FALSE) {
                    if ( ($choice[$i]=='ROME' && $latestProposal->parameters[2]!==NULL) || ($choice[$i]=='FIELD' && $latestProposal->parameters[3]!==NULL) ) {
                        $disagreement = TRUE ;
                    } elseif ($choice[$i]=='ROME') {
                        array_push($messages , array(sprintf(_('You picked %s to be Rome Consul.') , $senator[$i]->name) , 'message' , $user_id )) ;
                        $this->proposals[count($this->proposals)-1]->parameters[2] = $senator[$i]->senatorID ;
                    } elseif ($choice[$i]=='FIELD') {
                        array_push($messages , array(sprintf(_('You picked %s to be Field Consul.') , $senator[$i]->name) , 'message' , $user_id )) ;
                        $this->proposals[count($this->proposals)-1]->parameters[3] = $senator[$i]->senatorID ;
                    } 
                }
            }
            // disagreement between players : set both consuls randomely
            if ($disagreement) {
                $roll = $this->rollOneDie(0) ;
                $latestProposal->parameters[($roll<=3 ? 2 : 3)] = $latestProposal->parameters[0];
                $latestProposal->parameters[($roll<=3 ? 3 : 2)] = $latestProposal->parameters[1];
                array_push($messages , array(_('As the newly elected pair couldn\'t agree, the senators are randomely appointed.')));
                $romeConsul = $this->senate_appointOfficial('Rome Consul' , $latestProposal->parameters[2]);
                $fieldConsul = $this->senate_appointOfficial('Field Consul' , $latestProposal->parameters[3]);
                array_push($messages , array(sprintf(_('%s becomes Rome Consul, and %s becomes Field Consul') , $romeConsul->name , $fieldConsul->name)));
            // Both consuls picked
            } elseif ($latestProposal->parameters[2]!==NULL && $latestProposal->parameters[3]!==NULL) {
                $romeConsul = $this->senate_appointOfficial('Rome Consul' , $latestProposal->parameters[2]);
                $fieldConsul = $this->senate_appointOfficial('Field Consul' , $latestProposal->parameters[3]);
                array_push($messages , array(sprintf(_('%s becomes Rome Consul, and %s becomes Field Consul') , $romeConsul->name , $fieldConsul->name)));
            // One consul hasn't been picked yet
            } else {
                array_push($messages , array(_('Now waiting for the other elected consul to pick a position.') , 'message' , $user_id ));
            }
            // Consuls have been elected : check possibility of a dictator
            if ($latestProposal->parameters[2]!==NULL && $latestProposal->parameters[3]!==NULL) {
                $dictatorFlag = $this->senate_getDictatorFlag();
                if (count($dictatorFlag)==0) {
                    array_push($messages , array(_('A dictator cannot be appointed or elected. Moving on to Censor election.')) );
                    $this->subPhase='Censor';
                    $candidates = $this->senate_possibleCensors() ;
                    // Only one eligible candidate : appoint him and move to prosecution phase
                    if (count($candidates)==1) {
                        array_push($messages , array(sprintf(_('Only %s is eligible for Censorship, so he is automatically elected.') , $candidates[0]->name)) );
                        $this->senate_appointOfficial('Censor', $candidates[0]->senatorID) ;
                        $this->subPhase='Prosecutions';
                    }
                } else {
                    // A dictator can be appointed/elected. Set each party's "bidDone" to FALSE to give them all a chance to do so.
                    foreach ($dictatorFlag as $flag) {
                        array_push($messages , array($flag) );
                    }
                    array_push($messages , array(_('A dictator can be appointed or elected.')) );
                    foreach($this->party as $party) {
                        $party->bidDone = FALSE ;
                    }
                    $this->subPhase='Dictator';
                }
            }
            
        /*
         * Prosecutions decision (prosecutor accepting/refusing appointment)
         */
        } elseif ($this->phase=='Senate' && $this->subPhase=='Prosecutions' && $latestProposal->outcome===NULL && $request['type']=='prosecutions') {
            $prosecutor = $this->getSenatorWithID($latestProposal->parameters[2]) ;
            $prosecutorParty = $this->getPartyOfSenator($prosecutor)->user_id ;
            if ($prosecutorParty == $user_id) {
                if ($request['accept']=='YES') {
                    $this->proposals[count($this->proposals)-1]->parameters[3] = TRUE ;
                    array_push($messages , sprintf(_('%s accepts to be prosecutor.') , $prosecutor->name)) ;
                } else {
                    // Since this proposal was never actually put forward, simply destroy it
                    unset ($this->proposals[count($this->proposals)-1]) ;
                    return array(array(sprintf(_('The appointed prosecutor %s chickens out.') , $prosecutor->name)));
                }
            } else {
                return array(array(_('You cannot take such a decision at this moment.')  , 'error' , $user_id));
            }
        /*
         * TO DO : Other decision types
         */
        } else {
            
        }
        return $messages ;
    }

    /**
     * Convenience function to appoint officials
     * @param string $type 'Dictator' | 'Rome Consul' | 'Field Consul' | 'Censor' | 'Master of Horse' | 'Pontifex Maximus'
     * @param string $senatorID The senatorID of the senator to appoint
     * @return mixed senator object if successful, FALSE if $type was wrong or Senator already holds an office
     */
    private function senate_appointOfficial($type , $senatorID) {
        if (in_array($type , Senator::$VALID_OFFICES)) {
            $currentOfficial = $this->senate_findOfficial($type);
            $official = $this->getSenatorWithID($senatorID) ;
            // Error : Senator already holds an office other than Censor (who can be re-elected)
            if ($official->office!=NULL && $type!='Censor') {
                return FALSE ;
            }
            if ($currentOfficial!==FALSE) {
                $currentOfficial['senator']->office = NULL ;
            }
            $official->office = $type ;
            switch ($type) {
                case 'Dictator' :
                    $INFincrease = 7 ;
                    break ;
                case 'Master of Horse' :
                    $INFincrease = 3 ;
                    break ;
                default :
                    $INFincrease = 5 ;
            }
            $official->changeINF($INFincrease) ;
            return $official ;
        } else {
            return FALSE ;
        }
    }
    
    /**
     * Returns the reason why a dictator can be appointed. If impossible, the array will be empty.
     * @return array
     */
    private function senate_getDictatorFlag() {
        $result = array() ;
        if ( ($this->unprosecutedWars->nbCards() + $this->activeWars->nbCards()) >= 3 ) {
            array_push($result , _('There is 3 or more active conflicts') ) ;
        }
        foreach ($this->activeWars->cards as $active) {
            if ( ($this->getModifiedConflictStrength($active)) >= 20) {
                array_push($result , sprintf(_('%s has a combined strength equal or greater than 20') , $active->name) ) ;
            }
        }
        foreach ($this->unprosecutedWars->cards  as $unprosecuted) {
            if ( ($this->getModifiedConflictStrength($unprosecuted)) >= 20) {
                array_push($result , sprintf(_('%s has a combined strength equal or greater than 20') , $unprosecuted->name) ) ;
            }
        }
        return $result ;
    }
    
    /**
     * This function returns an array indicating all the ways user_id can currently make a proposal ('president' , 'tribune card' , 'free tribune')
     * or empty array if he can't
     * @param type $user_id
     * @return array with a list of ways to make a proposal
     */
    public function senate_canMakeProposal($user_id) {
        $result=array() ;
        // Short-circuit the function : prosecutions limit the possibilities of proposal to the Censor (not even tribune cards)
        if ($this->subPhase=='Prosecutions') {
            $censor = $this->senate_findOfficial('Censor');
            if ($censor['user_id']==$user_id) {
                return array('Censor');
            } else {
                return array();
            }
        }
        $president = $this->getHRAO(TRUE);
        if ($president['user_id']==$user_id) {
            $result[] = 'President' ;
        }
        foreach ($this->party[$user_id]->freeTribunes as $freeTribune) {
            $result[] = $freeTribune;
        }
        foreach ($this->party[$user_id]->hand->cards as $card) {
            if ($card->name == 'TRIBUNE') {
                $result[] = 'Tribune card';
            }
        }
        return $result ;
    }
    
    /**
     * Reports on how a proposal was made : President, tribune card, or free tribune
     * Uses up the tribune card or the free tribune ability if appropriate
     * @param 'President','Tribune card', or an array ('SenatorID','name') $proposalHow
     * @return message
     */
    private function senate_useProposalHow($user_id , $proposalHow) {
        if (strcmp($proposalHow ,'President')==0) {
            return _('Using the Presiding magistrate\'s ability');
        } elseif (strcmp($proposalHow ,'Tribune card')==0) {
            $this->discard->putOnTop($this->party[$user_id]->hand->drawCardWithValue('name' , 'TRIBUNE'));
            return _('Using a Tribune card');
        } elseif (is_array ($proposalHow)) {
            foreach ($this->party[$user_id]->freeTribunes as $key=>$value) {
                if ($value['senatorID'] == $proposalHow['senatorID']) {
                    unset ($this->party[$user_id]->freeTribunes[$key]) ;
                    return sprintf(_('Using the Tribune ability of %s'),$proposalHow['name']);
                }
            }
            // If we reach this, it means the free tribune couldn't be found, this is an error
            return _('Using an ERROR in the program') ;
        } else {
            return _('Using an ERROR in the program') ;
        }
    }
    
    
    /**
     * Returns a list of all possible consul pairs :
     * - Both in Rome
     * - Both without an incompatible office before the "Tradition Erodes" law is in place
     * - Not yet rejected as a pair
     * @return array of senatorIDs
     */
    public function senate_consulsPairs() {
        $listOfSenators=array();
        foreach($this->party as $party) {
            foreach($party->senators->cards as $senator) {
                if ($senator->inRome()) {
                    if ( in_array('Tradition Erodes' , $this->laws) || (($senator->office != 'Dictator') && ($senator->office != 'Rome Consul') && ($senator->office != 'Field Consul') && ($senator->office != 'Pontifex Maximus')) ) {
                        array_push($listOfSenators , $senator->senatorID);
                    }
                }
            }
        }
        usort ($listOfSenators, function($a, $b) {
                return strcmp($a , $b);
        });
        $result=array();
        foreach ($listOfSenators as $senator1) {
            foreach ($listOfSenators as $senator2) {
                if (strcmp($senator1 , $senator2)<0) {
                    $rejected = FALSE ;
                    foreach ($this->proposals as $proposal) {
                        if ($proposal->type=='Consuls') {
                            if ($proposal->outcome==FALSE) {
                                if ($proposal->parameters[0]==$senator1 && $proposal->parameters[1]==$senator2) {
                                    $rejected=TRUE;
                                }
                            }
                        }
                    }
                    if (!$rejected) {
                        array_push($result , array($senator1 , $senator2));
                    }
                }
            }
        }
        return $result;
    }
    
    /**
     * Returns an array of senators that can be elected Censor
     * @return array
     */
    public function senate_possibleCensors() {
        $result=array();
        $alreadyRejected=array();
        foreach ($this->proposals as $proposal) {
            if ( ($proposal->type=='Censor') && ($proposal->outcome==FALSE) ) {
                array_push($alreadyRejected , $proposal->parameters[0]) ;
            }
        }
        foreach($this->party as $party) {
            foreach($party->senators->cards as $senator) {
                if ($senator->priorConsul && $senator->inRome() && $senator->office != 'Dictator' && $senator->office != 'Rome Consul' && $senator->office != 'Field Consul' && !in_array($senator->senatorID , $alreadyRejected) ) {
                    array_push($result , $senator);
                }    
            }
        }
        // If there is no possible candidate, the Censor can exceptionnaly be a Senator without prior consul marker
        if (count($result)==0) {
            foreach($this->party as $party) {
                foreach($party->senators->cards as $senator) {
                    if ($senator->inRome() && $senator->office != 'Dictator' && $senator->office != 'Rome Consul' && $senator->office != 'Field Consul' && !in_array($senator->senatorID , $alreadyRejected)) {
                        array_push($result , $senator);
                    }    
                }
            }
        }
        return $result ;
    }
    
    /**
     * Returns the latest proposal or FALSE if there is none
     * @return boolean
     */
    public function senate_getLatestProposal() {
        if ( count($this->proposals) > 0) {
            return $this->proposals[count($this->proposals)-1] ;
        } else {
            return FALSE ;
        }
    }
    
    /**
     * Returns the number of each type of prosecutions that have already been voted upon
     * @return array ['minor'] , ['major'] , ['minorList']
     */
    public function senate_getFinishedProsecutions() {
        $result = array() ;
        $result['minor'] = 0 ;
        $result['major'] = 0 ;
        foreach ($this->proposals as $proposal) {
            if ($proposal->type=='Prosecutions' && $proposal->parameters[1]=='major' && $proposal->outcome!==NULL) {
                $result['major']++;
            } elseif ($proposal->type=='Prosecutions' && $proposal->parameters[1]!='major' && $proposal->outcome!==NULL) {
                $result['minor']++;
            }
        }
        return $result ;
    }
    
    /**
     * Returns a list of all the possible reasons for prosecutions
     * @return array 'senator' , 'reason'
     */
    public function senate_getListPossibleProsecutions() {
        $result = array();
        $nbProsecutions = $this->senate_getFinishedProsecutions() ;
        foreach ($this->party as $party) {
            foreach ($party->senators->cards as $senator) {
                    if ($senator->major) {
                        // Cannot have a major prosecution if there already was a minor prosecution
                        if ($nbProsecutions['minor']==0) {
                            $result[] = array('senator' => $senator , 'reason' => 'major' , 'text' => sprintf(_('MAJOR prosecution - %s (%s) for holding an office') , $senator->name , $party->fullname())) ;
                        }
                        $result[] = array('senator' => $senator , 'reason' => 'minor' , 'text' => sprintf(_('Minor prosecution - %s (%s) for holding an office') , $senator->name , $party->fullname())) ;
                    }
                if ($senator->corrupt) {
                    $alreadyprosecuted = FALSE ;
                    foreach ($this->proposals as $proposal) {
                        if ($proposal->type=='Prosecutions' && $proposal->outcome!==NULL && $proposal->parameter[0]==$senator->senatorID && $proposal->parameter[1]=='province' ) {
                            $alreadyprosecuted = TRUE ;
                        }
                    }
                    if (!$alreadyprosecuted) {
                        $result[] = array('senator' => $senator , 'reason' => 'province' , 'text' => sprintf(_('Minor prosecution - %s (%s) for governing a province') , $senator->name , $party->fullname())) ;
                    }
                }
                foreach ($senator->controls->cards as $card) {
                    if ($card->type=='Concession' && $card->corrupt) {
                        $alreadyprosecuted = FALSE ;
                        foreach ($this->proposals as $proposal) {
                            if ($proposal->type=='Prosecutions' && $proposal->outcome!==NULL && $proposal->parameter[0]==$senator->senatorID && $proposal->parameter[1]==$card->id ) {
                                $alreadyprosecuted = TRUE ;
                            }
                        }
                        if (!$alreadyprosecuted) {
                            $result[] = array('senator' => $senator , 'reason' => $card->id , 'text' => sprintf(_('Minor prosecution - %s (%s) for profiting from concession %s') , $senator->name , $party->fullname() , $card->name)) ;
                        }
                    }
                }
            }
        }
        return $result ;
    }
    
    /**
     * Returns an array of senators in Rome who are not the Censor
     * @return type
     */
    public function senate_getListPossibleProsecutors() {
        $result = array() ;
        foreach ($this->party as $party) {
            foreach ($party->senators->cards as $senator) {
                if ($senator->inRome() && $senator->office!='Censor') {
                    $result[] = array('senatorID' => $senator->senatorID , 'name' => $senator->name.' ('.$party->fullName().')') ;
                }
            }
        }
        return $result ;
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
    public function revolution_playStatesman( $user_id , $card_id ) {
        $messages = array() ;
        if ( (($this->phase=='Setup') && ($this->subPhase=='PlayCards')) || (($this->phase=='Revolution') && ($this->subPhase=='PlayCards')) ) {
            $statesman = $this->party[$user_id]->hand->drawCardWithValue('id', $card_id);
            if ($statesman === FALSE ) {
                array_push($messages , array('The card is not in '.$this->party[$user_id]->fullName().'\'s hand','error'));
                return $messages ;   
            } else {
                if ($statesman->type!='Statesman') {
                    // This is not a statesman, put the card back and report an error
                    $this->party[$user_id]->hand->putOnTop($statesman);
                    array_push($messages , array('"'.$statesman->name.'" is not a statesman','error'));
                    return $messages ;
                } else {
                    // This is a Statesman, proceed
                    // First, get the statesman's family number
                    $family = $statesman->statesmanFamily() ;
                    if ($family === FALSE) {
                        // This family is weird. Put the card back and report the error
                        $this->party[$user_id]->hand->putOnTop($statesman);
                        array_push($messages , array('Weird family.','error'));
                        return $messages ;
                    }
                    // Check if family is already in player's party
                    foreach ($this->party[$user_id]->senators->cards as $senator) {
                        if ($senator->senatorID == $family) {
                            // Family found in the player's party
                            $matchedFamily = $this->party[$user_id]->senators->drawCardWithValue('senatorID' , $family);
                            if ($matchedFamily === FALSE) {
                                $this->party[$user_id]->hand->putOnTop($statesman);
                                $this->party[$user_id]->senators->putOnTop($matchedFamily);
                                array_push($messages , array('Weird family.','error'));
                                return $messages ;
                            } else {
                                // SUCCESS : Family is in player's party - put it under the Statesman
                                $this->party[$user_id]->senators->putOnTop($statesman) ;
                                $statesman->controls->putOnTop($matchedFamily);
                                // Adjust Statesman's value that are below the Family's
                                if ($matchedFamily->priorConsul) {$statesman->priorConsul ;}
                                if ($matchedFamily->INF > $statesman->INF) {$statesman->INF = $matchedFamily->INF ;}
                                if ($matchedFamily->POP > $statesman->POP) {$statesman->POP = $matchedFamily->POP ;}
                                $statesman->treasury = $matchedFamily->treasury ;
                                $statesman->knights = $matchedFamily->knights ;
                                $statesman->office = $matchedFamily->office ;
                                $matchedFamily->resetSenator() ;
                                // The family was the party's leader
                                if ($this->party[$user_id]->leader->senatorID == $matchedFamily->senatorID) {
                                    $this->party[$user_id]->leader=$statesman;
                                }
                                array_push($messages , array($this->party[$user_id]->fullName().' plays Statesman '.$statesman->name.' on top of senator '.$matchedFamily->name));
                                return $messages ;
                            }
                        }
                    }
                    // Check if the family is unaligned in the forum
                    foreach ($this->forum->cards as $card) {
                        if ( ($card->type == 'Family') && ($card->senatorID == $family) ) {
                            $matchedFamily = $this->forum->drawCardWithValue('senatorID' , $family);
                            // SUCCESS : Family is unaligned in the forum - put it under the Statesman
                            $this->party[$user_id]->senators->putOnTop($statesman) ;
                            $statesman->controls->putOnTop($matchedFamily);
                            array_push($messages , array($this->party[$user_id]->fullName().' plays Statesman '.$statesman->name.' and gets the matching unaligned family from the Forum.'));
                            return $messages ;
                        }

                    }
                    // SUCCESS : There was no matched family in the player's party or the Forum
                    $this->party[$user_id]->senators->putOnTop($statesman) ;
                    array_push($messages , array($this->party[$user_id]->fullName().' plays Statesman '.$statesman->name));
                    return $messages ;
                }
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
    public function revolution_playConcession( $user_id , $card_id , $senator_id) {
        if ( (($this->phase=='Setup') && ($this->subPhase=='PlayCards')) || (($this->phase=='Revolution') && ($this->subPhase=='PlayCards')) ) {
            $messages = array() ;
            $partyOfTargetSenator = $this->getPartyOfSenatorWithID($senator_id) ;
            if (!$partyOfTargetSenator || $partyOfTargetSenator=='forum') {
                array_push($messages , array('This senator is not in play','alert',$user_id)) ;
                return $messages;
            }
            $senator = $this->party[$user_id]->senators->drawCardWithValue('senatorID', $senator_id);
            if ($senator === FALSE ) {
                array_push($messages , array('The senator is not in '.$this->party[$user_id]->fullName().'\'s party' , 'error',$user_id));
                return $messages ;   
            }
            $concession = $this->party[$user_id]->hand->drawCardWithValue('id', $card_id);
            if ($concession === FALSE ) {
                $this->party[$user_id]->senators->putOnTop($senator);
                array_push($messages , array('The card is not in '.$this->party[$user_id]->fullName().'\'s hand' , 'error',$user_id));
                return $messages ;   
            } else {
                if ($concession->type!='Concession') {
                   $this->party[$user_id]->senators->putOnTop($senator);
                   $this->party[$user_id]->hand->putOnTop($concession);
                   array_push($messages , array($concession->name.'" is not a concession' , 'error',$user_id));
                   return $messages ;
                } elseif($concession->special=='land bill' && !$this->landCommissionerPlaybale()) {
                   $this->party[$user_id]->senators->putOnTop($senator);
                   $this->party[$user_id]->hand->putOnTop($concession);
                   array_push($messages , array('The Land commissioner can only be played while Land bills are enacted.','error',$user_id));
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
    
     /************************************************************
     * Functions used by Views
     ************************************************************/

    public function view_party($user_id) {
        $result = array() ;
        foreach ($this->party[$user_id]->senators->cards as $senator) {
            $result[] = $senator ;
        }
        return $result ;
    }
    
}