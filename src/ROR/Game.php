<?php
namespace ROR;

/** 
 * int $id : game id<br>
 * string $name : game name
 * $turn (int)
 * $phase (string) : the current turn phase, can have any value defined in $VALID_PHASES
 * $subPhase (string) : the current sub phase of the current phase
 * $initiative (int) : Current initiative from 1 to 6
 * $censorIsDone (bool) : Whether or not the Censor has finished prosecutions
 * $senateAdjourned (bool) : Whether or not the president has adjourned the Senate
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
 * $assassination : array of parameters for an assassination (assassin ID , target ID , cards played)
 */
class Game
{
    /*
     * Some default values and validators
     */
    public static $VALID_PHASES = array('Setup','Mortality','Revenue','Forum','Population','Senate','Combat','Revolution','Rome falls');
    public static $VALID_ACTIONS = array('Bid','RollEvent','Persuasion','Knights','ChangeLeader','SponsorGames','curia');
    public static $DEFAULT_PARTY_NAMES = array ('Imperials' , 'Plutocrats' , 'Conservatives' , 'Populists' , 'Romulians' , 'Remians');
    public static $VALID_SCENARIOS = array('EarlyRepublic');
    public static $VALID_VARIANTS = array('Pontifex Maximus' , 'Provincial Wars' , 'Rebel governors' , 'Legionary disbandment' , 'Advocates' , 'Passing Laws');
    
    private $id ;
    public $name ;
    public $turn , $phase , $subPhase , $initiative , $censorIsDone , $senateAdjourned ;
    public $scenario , $variants , $unrest , $treasury , $nbPlayers ;
    public $currentBidder , $persuasionTarget ;
    public $party ;
    public $drawDeck , $earlyRepublic , $middleRepublic , $lateRepublic , $discard , $unplayedProvinces , $inactiveWars , $activeWars , $imminentWars , $unprosecutedWars , $forum , $curia ;
    public $landBill ;
    public $events , $eventTable ;
    public $populationTable , $appealTable , $landBillsTable ;
    public $legion, $fleet ;
    public $steppedDown, $proposals, $laws , $assassination ;
    
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
        $this->senateAdjourned = FALSE ;
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
        $this->drawDeck->name = 'Draw deck' ;
        $this->earlyRepublic = new Deck ;
        $this->earlyRepublic->createFromFile ($scenario) ;
        $this->earlyRepublic->name = 'Early Republic deck' ;
        $this->middleRepublic = new Deck ;
        $this->middleRepublic->name = 'Middle Republic deck' ;
        $this->lateRepublic = new Deck ;
        $this->lateRepublic->name = 'Late Republic deck' ;
        $this->discard = new Deck ;
        $this->discard->name = 'Discard deck' ;
        $this->unplayedProvinces = new Deck ;
        $this->unplayedProvinces->createFromFile ('Provinces') ;
        $this->unplayedProvinces->name = 'Unplayed Provinces deck' ;
        $this->inactiveWars = new Deck ;
        $this->inactiveWars->name = 'Inactive Wars deck' ;
        $this->activeWars = new Deck ;
        $this->activeWars->name = 'Active Wars deck' ;
        $this->imminentWars = new Deck ;
        $this->imminentWars->name = 'Imminent Wars deck' ;
        $this->unprosecutedWars = new Deck ;
        $this->unprosecutedWars->name = 'Unprosecuted Wars deck' ;
        $this->forum = new Deck ;
        $this->forum->name = 'The Forum' ;
        $this->curia = new Deck ;
        $this->curia->name = 'The Curia' ;
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
        $this->assassination = array() ;
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
            $this->appealTable[$data[0]] = array('votes' => $data[1] , 'special' => (isset($data[2]) ? $data[2] : NULL));
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
        $result = array('Rome'=>0 , 'Total'=>0) ;
        foreach($this->fleet as $fleet) {
            if ($fleet->location == 'Rome' ) {
                $result['Rome']++;
            }
            if ($fleet->location <> NULL ) {
                $result['Total']++;
            }
        }
        return $result ;
    }
    
    /**
     * Returns an array with detailed information on legions :<br>
     * If the key is a number, the other dimensions are :<br>
     * 'location' => ''|'error'|'Rome'|'released'|'with XXX' where XX is a Senator's name<br>
     * 'veteran' => ''|'error'|'YES'|'NO'|'Senator Name'<br>
     * 'action' => ''|'RECRUIT'|'DISBAND'<br>
     * If the key is 'totals', we get total numbers of regular and veteran legions in Rome, released, away (loyal), away (rebel), in garrison, recruitable, and veterans in Rome loyal to a Senator<br>
     * @return array
     */
    public function getLegionDetails() {
        $result = array () ;
        $result['totals']['Rome']['regular'] = 0 ;
        $result['totals']['Rome']['veteran'] = 0 ;
        $result['totals']['released']['regular'] = 0 ;
        $result['totals']['released']['veteran'] = 0 ;
        $result['totals']['awayLoyal']['regular'] = 0 ;
        $result['totals']['awayLoyal']['veteran'] = 0 ;
        $result['totals']['awayRebel']['regular'] = 0 ;
        $result['totals']['awayRebel']['veteran'] = 0 ;
        $result['totals']['garrison']['regular'] = 0 ;
        $result['totals']['garrison']['veteran'] = 0 ;
        $result['totals']['recruitable'] = 0 ;
        $result['totals']['Rome']['loyal_veteran'] = 0 ;
        foreach ($this->legion as $key=>$legion) {
            $result[$key]['name'] = $legion->name ;
            switch($legion->location) {
                case NULL :
                    $result[$key]['location'] = '' ;
                    $result[$key]['action'] = 'RECRUIT' ;
                    $result['totals']['recruitable']++;
                    break ;
                case 'Rome' :
                case 'released' :
                    $result[$key]['location'] = $legion->location ;
                    $result[$key]['action'] = 'DISBAND' ;
                    $result['totals'][$legion->location][($legion->veteran ? 'veteran' : 'regular')]++ ;
                    if ($legion->veteran && $legion->loyalty!==NULL) {
                        $result['totals']['Rome']['loyal_veteran']++ ;
                    }
                    break ;
                default :
                    $senator = $this->getSenatorWithID($legion->location) ;
                    if ($senator==FALSE) {
                        $province = $this->getSpecificCard('id', $legion->location) ;
                        if ($province==FALSE) {
                            $result[$key]['location'] = 'error' ;
                        } else {
                            $result[$key]['location'] = 'garrison in '.$province->name;
                            $result['totals']['garrison'][($legion->veteran ? 'veteran' : 'regular')]++ ;
                        }
                    } else {
                        $result[$key]['location'] = 'with '.$senator->name;
                        $result['totals'][($senator->rebel ? 'awayRebel' : 'awayLoyal')][($legion->veteran ? 'veteran' : 'regular')]++ ;
                    }
                    $result[$key]['action'] = '' ;
            }
            $result[$key]['veteran'] = ($result[$key]['location']== '' ? '' : ($legion->veteran ? 'YES' : 'NO')) ;
            if ($legion->loyalty!=NULL) {
                $senator = $this->getSenatorWithID($legion->loyalty) ;
                if ($senator==FALSE) {
                    $result[$key]['veteran'] = 'error' ;
                } else {
                    $result[$key]['veteran'] = $senator->name;
                }
            }
        }
        return $result ;
    }
    
    /**
     * Returns an array with detailed information on fleets :<br>
     * 'location' => ''|'error'|'Rome'|'with XXX' where XX is a Senator's name<br>
     * 'action' => ''|'RECRUIT'|'DISBAND'<br>
     * If the key is 'totals', we get total numbers of fleets in Rome<br>
     * @return array
     */
    public function getFleetDetails() {
        $result = array () ;
        $result['total'] = 0 ;
        foreach ($this->fleet as $key=>$fleet) {
            $result[$key]['name'] = $fleet->name ;
            switch($fleet->location) {
                case NULL :
                    $result[$key]['location'] = '' ;
                    $result[$key]['action'] = 'RECRUIT' ;
                    break ;
                case 'Rome' :
                    $result[$key]['location'] = $fleet->location ;
                    $result[$key]['action'] = 'DISBAND' ;
                    $result['total']++;
                    break ;
                default :
                    $senator = $this->getSenatorWithID($fleet->location) ;
                    if ($senator==FALSE) {
                        $result[$key]['location'] = 'error' ;
                    } else {
                        $result[$key]['location'] = 'with '.$senator->name;
                    }
                    $result[$key]['action'] = '' ;
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
     * @param Conflict $conflict
     * @return string The description of the conflict
     */
    public function getConflictDescription($conflict) {
        if ($conflict->type=='Conflict') {
            $result=array() ;
            $result[0]=$conflict->description;
            $commanderGlobal=$this->getSpecificCard('conflict', $conflict->id) ;
            $commander=$commanderGlobal['card'] ;
            if ($commander!=FALSE) {
                $nbRegulars = 0 ;
                $nbVeterans = 0 ;
                $nbFleets = 0 ;
                for ($i=1 ; $i<=25 ; $i++) {
                    if ($this->legion[$i]->location===$commander->senatorID) {
                        if ($this->legion[$i]->veteran===FALSE) {
                            $nbRegulars++ ;
                        } else {
                            $nbVeterans++ ;
                        }
                    }
                    if ($this->fleet[$i]->location===$commander->senatorID) {
                        $nbFleets++;
                    }
                }
                $result[1]=sprintf(_('Currently attacked by %s with %d/%d/%d') , $commander->name , $nbRegulars , $nbVeterans , $nbFleets);
            } else {
                $result[1]='';
            }
            return $result ;
        } else {
            return array(0 => 'Card error' , 1 => 'Wrong conflict') ;
        }
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
     * @return mixed party | 'forum' | FALSE
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
     * @return array 'senator' , 'user_id'
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
                    // This will put an array ('senator','user_id') in another array $rankedSenators, ordered by Valid Offices keys (Dictator first, Rome Consul second, etc...
                    $rankedSenators[array_search($senator->office, Senator::$VALID_OFFICES)] = array ('senator' => $senator , 'party_name' => $this->party[$user_id]->fullName() , 'user_id' => $user_id) ;
                }
                /*
                 * In case the HRAO couldn't be determined through offices because no official is in Rome
                 * we will need senators ordered by INF, so we use this loop to prepare that list
                 */
                array_push($allSenators , $senator);
            }
        }
        ksort($rankedSenators) ;
        // We found at least one ranked Senator
        if (count($rankedSenators)>0) {
            // If we are looking for the presiding magistrate, The Censor must be returned during the Senate phase if the latest proposal was a prosecution
            // TO DO : what if the all thing was interupted by a Special Assassin Prosecution ?
            if ( $presiding && $this->phase=='Senate' && count($this->proposals)>0 && end($this->proposals)->type=='Prosecutions' && isset($rankedSenators[3]) ) {
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
        return array ('senator' => $senator , 'party_name' => $party->fullName() , 'user_id'=>$user_id) ;
    }
    
    /**
     * 
     * @param user_id $user_id
     * @param \ROR\Senator $statesman
     * @return array 'flag' = TRUE|FALSE , 'message'
     */
    public function statesmanPlayable ($user_id , $statesman) {
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
     */
    /**
     * Returns the user_id of the player playing after the player whose user_id has been passed as a parameter
     * Say that three times.
     * @param string $user_id
     * @return array Messages
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
            // Skip all parties who have no money in their party treasury. But whatever happens, we must never pass the player with the initiative, as all bids will have to stop there anyway
            while ($orderOfPlay[0] != $this->forum_whoseInitiative()) {
                if ($this->party[$orderOfPlay[0]]->treasury == 0) {
                    array_push( $result['messages'], array(sprintf(_('Skipping {%s} : no talents in the party treasury to counter-bribe.'),$this->party[$orderOfPlay[0]]->user_id)) );
                    array_push( $orderOfPlay, array_shift($orderOfPlay) );
                } else {
                    break ;
                }
            }
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
     * Returns a description of the effects of evil omens on a die/dice roll. Empty if current evil omens level is 0
     * @param type $effect -1|+1
     * @return string description
     */
    public function getEvilOmensMessage($effect) {
        $evilOmensLevel = $this->getEventLevel('name' , 'Evil Omens') ;
        if ($evilOmensLevel==0) {
            return '';
        } else {
            return sprintf(_(' (including %d from evil Omens)') , $effect*$evilOmensLevel);
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
     * @return array 'card' => $card object , 'where' => 'senator|party|forum|curia|a war deck...' , 'deck' => deck object ,<br>
     * 'senator' & 'party' if 'where' is 'senator' , 'party' if 'where' is 'party'<br>
     * Warning : The party CAN BE 'forum'<br>
     * returns FALSE if the card was not found
     */
    public function getSpecificCard($property , $value) {
        foreach ($this->party as $party) {
            foreach ($party->senators->cards as $senator) {
                if (isset($senator->$property) && $senator->$property == $value) {
                    return array ('card' => $senator , 'where' => 'party' , 'party' => $party );
                }
                foreach ($senator->controls->cards as $card) {
                    if (isset($card->$property) && $card->$property == $value) {
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
                    if (isset($card2->$property) &&  $card2->$property == $value) {
                        return array ('card' => $card2 , 'where' => 'senator' , 'deck' => $card->controls , 'senator' => $card , 'party' => 'forum' );
                    }
                }
            }
        }
        foreach ($this->curia->cards as $card) {
            if (isset($card->$property) && $card->$property == $value) {
                return array ('card' => $card , 'where' => 'curia' , 'deck' => $this->curia);
            }
        }
        foreach ($this->inactiveWars->cards as $card) {
            if (isset($card->$property) && $card->$property == $value) {
                return array ('card' => $card , 'where' => 'inactiveWars' , 'deck' => $this->inactiveWars);
            }
        }
        foreach ($this->activeWars->cards as $card) {
            if (isset($card->$property) && $card->$property == $value) {
                return array ('card' => $card , 'where' => 'activeWars' , 'deck' => $this->activeWars);
            }
        }
        foreach ($this->imminentWars->cards as $card) {
            if (isset($card->$property) && $card->$property == $value) {
                return array ('card' => $card , 'where' => 'imminentWars' , 'deck' => $this->imminentWars);
            }
        }
        foreach ($this->unprosecutedWars->cards as $card) {
            if (isset($card->$property) && $card->$property == $value) {
                return array ('card' => $card , 'where' => 'unprosecutedWars' , 'deck' => $this->unprosecutedWars);
            }
        }
        return FALSE ;
    }

    /**
     * This adds the ability to pay ransom of Senators captured during battle or barbarian raids<br>
     * This is a global function, called from the main view interface to allow for payment at any time.<br>
     * TO DO : Killing of captives should be checked when a war is defeated (captured during battle).<br>
     * DONE : Killing by barbarians has been implemented already (beginning of forum phase).
     * @param string $user_id the user id
     * @return array 'captiveOf' , 'senatorID' , 'treasury' , 'ransom'
     */
    public function getListOfCaptives($user_id) {
        $result = array () ;
        foreach($this->party[$user_id]->senators->cards as $senator) {
            if($senator->captive!==FALSE) {
                array_push( $result , array('captiveOf' => $senator->captive , 'senatorID' => $senator->senatorID , 'treasury' => $senator->treasury , 'ransom' => max(10 , 2 * $senator->INF)));
            }
        }
        if (count($result)==0) {
            $result = FALSE ;
        }
        return $result ;
    }
    
    /**
     * Returns the number of matched active Conflicts :<br>
     * Either in the active wars or unprosecuted wars deck
     * @param Conflict $conflict
     * @return int|boolean
     */
    public function getNumberOfMatchedConflicts($conflict) {
        $result = 0 ;
        if ($conflict->type != 'Conflict') {
            return FALSE ;
        }
        foreach ($this->activeWars->cards as $active) {
            if ( $active->matches == $conflict->matches) {
                $result++;
            }
        }
        foreach ($this->unprosecutedWars->cards  as $unprosecuted) {
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
        $result['land'] *= 1+$matchedConflicts ;
        $result['fleet'] *= 1+$matchedConflicts ;
        foreach ($conflict->leaders->cards as $leader) {
            $result['land']+=$leader->strength ;
            $result['fleet']+=$leader->strength ;
        }
        return $result ;
    }
    
    public function getListOfConflicts() {
        $result = array() ;
        foreach ($this->activeWars->cards as $card) {
            $result[] = array('id' => $card->id , 'name' => $card->name , 'land' => $this->getModifiedConflictStrength($card)['land'] , 'support' => $this->getModifiedConflictStrength($card)['support'] , 'fleet' => $this->getModifiedConflictStrength($card)['fleet']);
        }
        foreach ($this->unprosecutedWars->cards  as $card) {
            $result[] = array('id' => $card->id , 'name' => $card->name , 'land' => $this->getModifiedConflictStrength($card)['land'] , 'support' => $this->getModifiedConflictStrength($card)['support'] , 'fleet' => $this->getModifiedConflictStrength($card)['fleet']);
        }
        foreach ($this->imminentWars->cards  as $card) {
            $result[] = array('id' => $card->id , 'name' => $card->name , 'land' => $this->getModifiedConflictStrength($card)['land'] , 'support' => $this->getModifiedConflictStrength($card)['support'] , 'fleet' => $this->getModifiedConflictStrength($card)['fleet']);
        }
        foreach ($this->inactiveWars->cards as $card) {
            $result[] = array('id' => $card->id , 'name' => $card->name , 'land' => $this->getModifiedConflictStrength($card)['land'] , 'support' => $this->getModifiedConflictStrength($card)['support'] , 'fleet' => $this->getModifiedConflictStrength($card)['fleet']);
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
        if ( ($this->phase=='Setup') && ($this->subPhase=='PickLeaders') && ($this->party[$user_id]->leaderID === NULL) ) {
            foreach ($this->party[$user_id]->senators->cards as $senator) {
                if ($senator->senatorID == $senatorID) {
                    $this->party[$user_id]->leaderID = $senator->senatorID ;
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
     * - 'values' is the actual content : variables, text, array of options for select, etc...
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
     * - Where senator and controlled cards go (forum, curia, discard)<br>
     * @param string $senatorID The SenatorID of the dead senator
     * @param specificID TRUE if the Senator with this specific ID should be killed,<br>FALSE if the ID is a family, and Statesmen must be tested (default)
     * @param specificParty FALSE or equal to the $user_id of the party to which the dead Senator must belong<br>
     * @param POPThreshold FALSE or equal to the level of POP at which a Senator is safe
     * @param epidemic FALSE or equal to either 'domestic' or 'foreign'
     * senators from other parties will not be killed.<br>
     * FALSE (default)
     * @return array Just a one message-array, not an array of messages
     */
    public function mortality_killSenator($senatorID , $specificID=FALSE , $specificParty=FALSE , $POPThreshold=FALSE , $epidemic=FALSE) {
        $message = '' ;
        
        // Case of a random mortality chit
        if (!$specificID) {
            // Creates an array of potentially dead senators, to handle both Statesmen & Families
            $deadSenators = array() ;
            foreach($this->party as $party) {
                // If no party is targetted put any senator in the array, otherwise only put senators belonging to that party
                if ($specificParty===FALSE || ($specificParty!=FALSE && $specificParty==$party->user_id)) {
                    foreach ($party->senators->cards as $senator) {
                        // On top of that, if the $specificParty flag is set, we only consider senators in Rome
                        // And if the POPThreshold is set, only kill senators with POP below that
                        if (
                                ($specificParty===FALSE || ($specificParty && $senator->inRome())) &&
                                ($POPThreshold===FALSE || ($senator->POP<$POPThreshold && $senator->inRome())) &&
                                ($epidemic===FALSE || ( ($epidemic='domestic' && $senator->inRome()) || ($epidemic='foreign' && !$senator->inRome()) ) )
                        ) {
                            if ( ($senator->type == 'Statesman') && ($senator->statesmanFamily() == $senatorID ) ) {
                                array_push($deadSenators , $senator) ;
                            } elseif ( ($senator->type == 'Family') && ($senator->senatorID == $senatorID) ) {
                                array_push($deadSenators , $senator) ;
                            }
                        } 
                    }
                }
            }
            // Returns either no dead (Senator not in play), 1 dead (found just 1 senator matching the chit), or pick 1 of two brothers if they are both legally in play
            if (count($deadSenators)==0 && $specificID===FALSE && $specificParty===FALSE) {
                return array(_('This senator is not in Play, nobody dies.')) ;
            } elseif (count($deadSenators)>1) {
                // Pick one of two brothers
                $deadSenator = array_rand($deadSenators) ;
                $senatorID=$deadSenator->senatorID ;
                $message.=_(' The two brothers are in play. ') ;
            } else {
                $deadSenator = $deadSenators[0];
            }
            
        // Case of a specific Senator being targeted
        } else {
            $deadSenator = $this->getSenatorWithID($senatorID) ;
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
            if ($party->leaderID == $senatorID) {
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
                if ($party->leaderID == $deadStatesman->senatorID) {
                    // Now that the Satesman is dead, the family is the party leader
                    $party->leaderID = $card->senatorID ;
                    $party->senators->putOnTop($card);
                    $message.=sprintf(_('%s stays in the party and is now leader. ') , $card->name);
                } else {
                    $this->curia->putOnTop($card);
                    $message.=sprintf(_('%s goes to the curia. ') , $card->name);
                }
            } else {
                return array(_('Error - A card controlled by the dead Senator was neither a Family, a Concession nor a Province.') , 'error');
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
        $message = sprintf(_('Province %s is attacked by %s Barabarian raids. Military force is %d (written force) + %d (for %d legions) + %d (%s\'s MIL), a %d (white die %d, black die %d) is rolled for a total of %d%s ') ,
                $provinceName , ($barbarianRaids==2 ? 'increased ' : '') , $writtenForce , 2*$garrisons , $garrisons , $governorMIL , $governorName , $roll['total'] , $roll[0] , $roll[1] , $total , $this->getEvilOmensMessage(-1)
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
            array_push($messages , array($message));
            switch($outcome) {
                case 'killed' :
                    $this->mortality_killSenator($senator->senatorID , TRUE);
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
        $message = sprintf(_('Province %s faces internal disorder, %s rolls a %d%s + %d garrisons for a total of %d' , $province->name , $senator->name , $roll , $this->getEvilOmensMessage(-1)  , $garrisons , ($roll+$garrisons) ));
        if (($roll+$garrisons) > ($internalDisorder == 1 ? 4 : 5)) {
            $message.sprintf(_(' which is greater than %d. The province will not generate revenue and cannot be improved this turn.') , ($internalDisorder == 1 ? '4' : '5'));
            // Using the overrun property both for Barbarian raids & Internal Disorder
            $province->overrun = TRUE ;
        } else {
            // Revolt : Kill Senator, garrisons, and move Province to the Active War deck
            array_push($messages , array($message.sprintf(_(' which is not greater than %d') , ($internalDisorder == 1 ? '4' : '5')) , 'alert'));
            $this->mortality_killSenator($senator->senatorID , TRUE);
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
            if (!$senator->rebel && $senator->captive===FALSE) {
                if ($this->party[$user_id]->leaderID == $senator->senatorID) {
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
                foreach($this->legion as $key=>$legion) {
                    if ($legion->location == $senator->senatorID) {
                        array_push($legionList , array('number' => $key , 'name' => $legion->name , 'veteran' => $legion->veteran , 'loyalty' => $legion->loyalty)) ;
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
                            $message.= sprintf(_(' A %d is rolled%s%s, the province is developed. %s gains 3 INFLUENCE.') , $roll , ($modifier==1 ? _(' (modified by +1 since the governor is not corrupt)') : '') , $this->getEvilOmensMessage(-1)  , $senator->name);
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
            // Rebel legions maintenance
            if ($request['rebel']=='YES') {
                $rebelLegionsMaintenanceMessages = $this->revenue_rebelLegionsMaintenance ($request) ;
                foreach ($rebelLegionsMaintenanceMessages as $message) {
                    array_push($messages , $rebelLegionsMaintenanceMessages) ;
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
     * Pick up each LEGION_XXX_YYY (XXX is the legion's name, YYY is the rebel senator's senatorID)<br>
     * check which option was picked : PARTY, PERSONAL, DISBAND, FREE<br>
     * Then pay maintenance or disband the legion. If disbanded, its location is set to 'released'<br>
     * If legions are released and the HRAO does not wish to pay the maintenance costs of these troops or if the senate cannot afford them, they are immediately disbanded.<br>
     * This specific HRAO decision is in the HRAO's redistribution function.<br>
     * In the POST data, the $key is in the form LEGION_XXX_YYY : XXX is the legion's name, YYY is the rebel senator's senatorID<br>     * 
     * @param type $request the POST data
     * @return array messages
     */
    private function revenue_rebelLegionsMaintenance ($request) {
        $messages = array() ;
        foreach($request as $key=>$value) {
            if (substr($key,0,6)=='LEGION') {
                $itemised = explode('_' , $key) ;
                $legionNumber = $itemised[1] ;
                $rebelID = $itemised[2] ;
                $rebel = $this->getSenatorWithID($rebelID) ;
                // TO DO : maybe I should include user_id in the function, and check that it's equal to rebelParty
                $rebelParty = $this->getPartyOfSenatorWithID($rebelID) ;
                if ($this->legion[$legionNumber]->location != $rebelID) {
                    array_push($messages , array( sprintf(_('Rebel legion maintenance error - Legion %s is not commanded by %s') , $this->legion[$legionNumber]->name , $rebel->name) ));
                } else {
                    // $value can be PARTY, PERSONAL, DISBAND, FREE
                    switch($value) {
                        case 'PARTY' :
                            if ($rebelParty->treasury>=2) {
                                $rebelParty->treasury-=2 ;
                                array_push($messages , array( sprintf(_('The party pays 2T for the maintenance of %s\' rebel legion %s.') , $rebel->name , $this->legion[$legionNumber]->name ) ));
                            } else {
                                array_push($messages , array( sprintf(_('The party cannot pay for the maintenance of %s\' rebel legion %s, it is released.') , $rebel->name , $this->legion[$legionNumber]->name ) ));
                                $this->legion[$legionNumber]->location = 'released';
                            }
                            break ;
                        case 'PERSONAL' :
                            if ($rebel->treasury>=2) {
                                $rebel->treasury-=2 ;
                                array_push($messages , array( sprintf(_('%s pays 2T for the maintenance of rebel legion %s.') , $rebel->name , $this->legion[$legionNumber]->name ) ));
                            } else {
                                array_push($messages , array( sprintf(_('%s cannot pay for the maintenance of his rebel legion %s, it is released.') , $rebel->name , $this->legion[$legionNumber]->name ) ));
                                $this->legion[$legionNumber]->location = 'released';
                            }
                            break ;
                        case 'FREE' :
                            array_push($messages , array( sprintf(_('%s is loyal to %s, no maintenance required.') , $this->legion[$legionNumber]->name , $rebel->name ) ));
                            break ;
                        case 'DISBAND' :
                        default :
                            array_push($messages , array( sprintf(_('Legion %s is released.') , $this->legion[$legionNumber]->name) ));
                            break ;
                    }
                }
            }
        }
        return $messages ;
    }
    
    /**
    * Lists all the possible "from" and "to" (Senators and Parties) for redistribution of wealth<br>
    * Also, if legions were released by a rebel, this function gives the option to the HRAO not to pay the maintenance costs of these troops<br>
    * or if the senate cannot afford them, they are immediately disbanded.<br>
    * @param string $user_id the player's user_id<br>
    * @return array A list of 'from' & 'to' <br>
    * 'list' => 'from'|'to' ,<br> 'type' => 'senator'|'party' ,<br> 'id' => senatorID|user_id ,<br> 'name' => senator or party name ,<br> 'treasury' => senator or party treasury (only for 'from')
    */
    public function revenue_ListRedistribute ($user_id) {
        $result=array() ;
        if ( ($this->phase=='Revenue') && ($this->subPhase=='Redistribution') && ($this->party[$user_id]->phase_done==FALSE) ) {
            foreach($this->party[$user_id]->senators->cards as $senator) {
                if ($senator->treasury > 0 && $senator->captive===FALSE && !$senator->rebel) {
                    array_push($result , array('list' => 'from' , 'type' => 'senator' , 'id' => $senator->senatorID , 'name' => $senator->name , 'treasury' => $senator->treasury ));
                }
                if ($senator->captive===FALSE && !$senator->rebel) {
                    array_push($result , array('list' => 'to' , 'type' => 'senator' , 'id' => $senator->senatorID , 'name' => $senator->name ));
                }
            }
            array_push($result , array('list' => 'from' , 'type' => 'party' , 'id' => $user_id , 'name' => $this->party[$user_id]->name , 'treasury' => $this->party[$user_id]->treasury ));
            foreach($this->party as $key=>$value) {
                array_push($result , array('list' => 'to' , 'type' => 'party' , 'id' => $key , 'name' => $this->party[$key]->name ));
            }
            // For the HRAO only, give a list of released legions to let him chose if they are maintained or disbanded.
            if ($this->getHRAO()['user_id']==$user_id) {
                foreach($this->legion as $key => $legion) {
                    if ($legion->location =='released') {
                        array_push($result , array('list' => 'releasedLegions' , 'number' => $key , 'name' => $legion->name)) ;
                    }
                }
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
     * @param array $request The POST request used only for the HRAO's decisions on released legions.
     * @return array
     */
    public function revenue_RedistributionFinished ($user_id , $request) {
        $messages = array () ;
        if ( ($this->phase=='Revenue') && ($this->subPhase=='Redistribution') && ($this->party[$user_id]->phase_done==FALSE) ) {
            $this->party[$user_id]->phase_done=TRUE ;
            array_push($messages , array(sprintf(_('{%s} has finished redistributing wealth.') , $user_id))) ;
            // For the HRAO only, there might be released legions maintenance POST data.
            $flag = FALSE ;
            if ($this->getHRAO()['user_id'] == $user_id) {
                $disbandedLegions = 0;
                $maintainedLegions = 0;
                foreach($this->legion as $key=>$legion) {
                    if ($legion->location == 'released') {
                        $flag = TRUE ;
                        if ( ($request['LEGION_'.$key] == 'MAINTAINED') && ($this->treasury>=2) ) {
                            $maintainedLegions++;
                            $this->treasury-- ;
                            $this->legion[$key]->location = 'Rome' ;
                        } else {
                            $disbandedLegions++;
                            $this->legion[$key]->location = NULL ;
                        }
                    }
                }
                if ($flag) {
                    array_push($messages , array(sprintf(_('The HRAO disbanded %d and maintained %d (for a cost of %dT) of the legions released by rebels.') , $disbandedLegions , $maintainedLegions , 2*$maintainedLegions))) ;
                }
            }
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
                    array_push($messages , array($this->events[162][$name].' : '.$this->events[162][$description]) );
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
     * - Barbarians kill senators whose ransom was not paid
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
                // TO DO : Correct the below, use 'totals' from $this->getLegionDetails()
                $nbLegions = $this->getNbOfLegions();
                $nbFleets = $this->getNbOfFleets()['Total'];
                $totalCostForces=2*($nbLegions + $nbFleets) ;
                if ($totalCostForces>0) {
                    $this->treasury-=$totalCostForces ;
                    array_push($messages , array(sprintf(_('Rome pays %dT for the maintenance of %d legions and %d fleets. ') , $totalCostForces , $nbLegions , $nbFleets)));
                }
                // Return of provinces governors
                foreach($this->party as $party) {
                    foreach ($party->senators->cards as $senator) {
                        // returningGovernor is used during the Senate phase : returning governors cannot be appointed governor again on the turn of their return without their approval
                        $senator->returningGovernor = FALSE ;
                        foreach ($senator->controls->cards as $card) {
                            if ($card->type=='province') {
                                $card->mandate++;
                                if ($card->mandate == 3) {
                                    array_push($messages , array(sprintf(_('%s returns from %s which is placed in the Forum.') , $senator->name , $card->name)));
                                    $card->mandate=0;
                                    $this->forum->putOnTop($senator->controls->drawCardWithValue('id',$card->id));
                                    $senator->returningGovernor = TRUE ;
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
                        $senator->returningGovernor = FALSE ;
                        foreach ($senator->controls->cards as $card) {
                            if ($card->type=='Province') {
                                $card->mandate++;
                                if ($card->mandate == 3) {
                                    array_push( $messages , array(sprintf(_('%s (unaligned) returns from %s which is placed in the Forum.') , $senator->name , $card->name)) );
                                    $card->mandate=0;
                                    $this->forum->putOnTop($senator->controls->drawCardWithValue('id',$card->id));
                                    $senator->returningGovernor = TRUE ;
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
                // Remove events that expire at the beginning of the forum phase
                foreach ($this->events as $number => $event) {
                    if ($event['level']>0) {
                        if ( ($number != 174) && ($number != 175) && ($number != 176) ) {
                            $this->events[$number]['level'] = 0 ;
                            array_push($messages , array(sprintf(_('Event %s is removed.') , $event['name'])));
                        }
                    }
                }
                // Barbarians kill captives
                foreach ($this->party as $key=>$party) {
                    $captiveList = $this->getListOfCaptives($key) ;
                    if ($captiveList!==FALSE) {
                        foreach ($captiveList as $captive) {
                            if ($captive['captiveOf'] == 'barbarians') {
                                array_push($messages , array( sprintf(_('The barbarians slaughter %s, whose ransom was not paid by {%s}') , $this->getSenatorWithID($captive['senatorID'])->name , $key ) ,'alert'));
                                array_push($messages , $this->mortality_killSenator($captive['senatorID'], TRUE) );
                            }
                        }
                    }
                }
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
     * - 'values' is the actual content : variables, text, array of options for select, etc...
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
            $result['message'] = sprintf(_('The HRAO (%s) as all bets are 0.') , $this->party[$HRAO['user_id']]->fullName());
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
        $richestSenator = $this->party[$HRAO['user_id']]->getRichestSenator() ;
        if ($richestSenator['amount']==0) {
            array_push($messages , array( sprintf(_('Skipping the HRAO ({%s}): not enough talents to bid.') , $HRAO['user_id']) )) ;
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
            array_push($messages , array(_('Event roll Sub Phase')));
            $roll = $this->rollDice(2, 0) ;
            if ($roll['total']==7) {
                // Event
                $eventRoll = $this->rollDice(3,0) ;
                array_push($messages , array( sprintf(_('{%s} rolls a 7, then rolls a %d on the events table.') , $user_id , $eventRoll['total']) ));
                $eventNumber = $this->eventTable[(int)$eventRoll['total']][$this->scenario] ;
                $eventMessage = $this->forum_putEventInPlay('number' , $eventNumber) ;
                foreach ($eventMessage as $message) {
                    $messages[] = $message;
                }
            } else {
                // Card
                array_push($messages , array( sprintf(_('{%s} rolls a %d and draws a card.') , $user_id , $roll['total']) ));
                $card = $this->drawDeck->drawTopCard() ;
                if ($card !== NULL) {
                    if ($card->type == 'Statesman' || $card->type == 'Faction' || $card->type == 'Concession') {
                        // Keep the card
                        $this->party[$user_id]->hand->putOnTop($card);
                        array_push($messages , array(sprintf(_('{%s} draws a faction card and keeps it.') , $user_id),'message',$this->getAllButOneUserID($user_id)));
                        array_push($messages , array(sprintf(_('You draw %s and put it in your hand.') , $card->name),'message',$user_id));
                    // If a Family has been drawn check if a corresponding Statesman is in play
                    } elseif ($card->type=='Family') {
                        $possibleStatemen = array() ;
                        foreach ($this->party as $party) {
                            foreach ($party->senators->cards as $senator) {
                                if ($senator->type=='Statesman' && $senator->statesmanFamily() == $card->senatorID) {
                                    array_push($possibleStatemen , array('senator' => $senator , 'party' => $party)) ;
                                }
                            }
                        }
                        array_push($messages , array( sprintf(_('{%s} draws %s.') , $user_id , $card->name) ));
                        // No corresponding statesman : Family goes to the Forum
                        if (count($possibleStatemen)==0) {
                            $this->forum->putOnTop($card) ;
                            array_push($messages , array( _('he goes to the forum.')));
                        // Found one or more (in case of brothers) corresponding Statesmen : put the Family under them
                        // Case 1 : only one Statesman
                        } elseif (count($possibleStatemen)==1) {
                            $possibleStatemen[0]['senator']->controls->putOnTop($card) ;
                            array_push($messages , array( sprintf(_('{%s} has %s so the family joins him.') , $possibleStatemen[0]['party']->user_id , $possibleStatemen[0]['senator']->name) ));
                        // Case 2 : brothers are in play
                        } else {
                            // Sorts the possibleStatemen in SenatorID order, so 'xxA' is before 'xxB'
                            // This is only relevant to brothers
                            usort ($possibleStatemen, function($a, $b) {
                                return strcmp($a['senator']->senatorID , $b['senator']->senatorID);
                            });
                            $possibleStatemen[0]['senator']->controls->putOnTop($card) ;
                            array_push($messages , array( sprintf(_('{%s} has %s (who has the letter "A" and takes precedence over his brother) so the family joins him.') , $possibleStatemen[0]['party']->user_id , $possibleStatemen[0]['senator']->name) ));
                        }
                    // Card goes to forum
                    /*
                     * A War card (card A) is drawn :
                     * - If a matching war (card B) is already active, card A is placed in the imminent wars
                     * - If a matching war (card B) is inactive, card A is placed in the imminent wars and card B becomes active.
                     * - If no matching war is in place, the active/inactive icon on card A determines if it is active or inactive.
                     * - If there is a leader (card C) in the curia who matches card A, card C is placed with card A which is now active if it wasn't. However, if card A was imminent, it stays imminent.
                     * 
                     * A Leader (card D) is drawn :
                     * - If a matching war (card E) is already in play, active or inactive, card D is placed with card E which is now active if it wasn't.
                     * - If no matching war is in play, card D is placed in the curia.
                     */
                    /*
                     * A Conflict is drawn
                     */
                    } elseif ($card->type == 'Conflict') {
                        $matchedConflicts = $this->getNumberOfMatchedConflicts($card) ;
                        /*
                         *  There is a matched active conflict : War goes to imminent deck
                         */
                        $activateInCaseOfLeader = FALSE ;
                        if ($matchedConflicts !== FALSE) {
                            $this->imminentWars->putOnTop($card) ;
                            array_push($messages , array(sprintf(_('{%s} draws %s, there is %d matched conflicts, the card goes to the imminent deck.') , $user_id , $card->name , $matchedConflicts)));
                        } else {
                            $matchedInactiveWar = $this->getSpecificCard('matches', $card->matches) ;
                            /*
                             *  There is a matched inactive war.
                             */
                            if ($matchedInactiveWar!==FALSE && $matchedInactiveWar['where'] == 'inactiveWars') {
                                $cardPicked = $this->inactiveWars->drawCardWithValue('id' , $matchedInactiveWar['card']->id);
                                $this->activeWars->putOnTop($cardPicked) ;
                                $this->imminentWars->putOnTop($card) ;
                                array_push($messages , array(sprintf(_('{%s} draws %s, the card goes to the imminent deck and the inactive card %s is now active.') , $user_id , $card->name , $cardPicked->name)));
                            /*
                             *  The active/inactive icon determines where the card goes
                             */
                            } elseif ($card->active) {
                                $this->activeWars->putOnTop($card) ;
                                array_push($messages , array(sprintf(_('{%s} draws %s, there are no matched conflicts, so based on the card\'s icon, the war is now active.') , $user_id , $card->name)));
                            } else {
                                $this->inactiveWars->putOnTop($card) ;
                                array_push($messages , array(sprintf(_('{%s} draws %s, there are no matched conflicts, so based on the card\'s icon, the war is inactive.') , $user_id , $card->name)));
                                $activateInCaseOfLeader = TRUE ;
                            }
                        }
                        // Move any matched leaders from the curia to the Conflict card
                        foreach ($this->curia->cards as $curiaCard) {
                            if ($curiaCard->type=='Leader' && $curiaCard->matches==$card->matches) {
                                $pickedLeader = $this->curia->drawCardWithValue('id' , $curiaCard->id) ;
                                $card->leaders->putOnTop($pickedLeader) ;
                                $completeMessage = sprintf(_('The leader %s is matched with %s, so moves from the Curia to the card.') , $pickedLeader->name , $card->name) ;
                                // A leader activates an inactive conflict
                                if ($activateInCaseOfLeader) {
                                    $this->activeWars->putOnTop($this->inactiveWars->drawCardWithValue('id' , $card->id)) ;
                                    $completeMessage.=_(' This activates the conflict.');
                                }
                                array_push($messages , array($completeMessage)) ;
                            }
                        }
                    /*
                    * A Leader is drawn
                    */
                    } elseif ($card->type == 'Leader') {
                        $matchedWar = $this->getSpecificCard('matches', $card->matches) ;
                        // There is no matching conflict, the leader goes to the Curia
                        if ($matchedWar===FALSE) {
                            $this->curia->putOnTop($card) ;
                            array_push($messages , array(sprintf(_('{%s} draws %s, without a matched conflicts, the card goes to the Curia.') , $user_id , $card->name)));
                        // There is a matching conflict.
                        } else {
                            $matchedWar['card']->leaders->putOnTop($card) ;
                            $completeMessage = sprintf(_('{%s} draws %s, which is placed on the matched conflict %s.') , $user_id , $card->name , $matchedWar['card']->name) ;
                            // Activate the war if it was not
                            if ($matchedWar['where'] == 'inactiveWars') {
                                $this->activeWars->putOnTop($this->inactiveWars->drawCardWithValue('id' , $matchedWar['card']->id)) ;
                                $completeMessage.=_(' This activates the conflict.');
                            }
                            array_push($messages , array($completeMessage));
                        }
                    /*
                    * Another type of card is drawn
                    */
                    } else {
                        $this->forum->putOnTop($card) ;
                        array_push($messages , array(sprintf(_('{%s} draws %s that goes to the forum.') , $user_id , $card->name)));
                    }
                } else {
                    array_push($messages , array(_('There is no more cards in the deck.'),'alert'));
                }
            }
            // Persuasion initialisation
            $this->subPhase = 'Persuasion';
            array_push($messages , array (_('Persuasion Sub Phase')) );
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
        $messages = array() ;
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
        $hasAnEffect = FALSE ;
        if ($eventNumber!==NULL) {
            // The event is not currently in play
            if ($this->events[$eventNumber]['level'] == 0) {
                $this->events[$eventNumber]['level']++ ;
                $messages[] = array(sprintf(_('Event %s is now in play.') , $this->events[$eventNumber]['name']) , 'alert') ;
                $hasAnEffect = TRUE ;
            // The event is currently in play at maximum level & CANNOT increase
            } elseif ($this->events[$eventNumber]['level'] == $this->events[$eventNumber]['max_level']) {
                $nameToUse = ($this->events[$eventNumber]['level']> 1 ? $this->events[$eventNumber]['increased_name'] : $this->events[$eventNumber]['name'] ) ;
                $messages[] = array(sprintf(_('Event %s is already in play at its maximum level (%d).') , $nameToUse , $this->events[$eventNumber]['max_level']) , 'alert');
            // The event is currently in play and not yet at maximum level : it can increase
            } else {
                $this->events[$eventNumber]['level']++ ;
                $messages[] = array(sprintf(_('Event %s has its level increased to %s (level %d).') , $this->events[$eventNumber]['name'] , $this->events[$eventNumber]['increased_name'] , $this->events[$eventNumber]['level']) , 'alert');
                $hasAnEffect = TRUE ;
            }
        } else {
            return _('Error retrieving event.') ;
        }
        // TO DO : All events that have an immediate effect 
        if ($eventNumber!==NULL && $hasAnEffect) {
            $level = $this->events[$eventNumber]['level'] ;
            switch ($eventNumber) {
                // Epidemic
                case 167 :
                    $nbOfMortalityChits = $this->rollOneDie(1) ;
                    foreach ($this->mortality_chits($nbOfMortalityChits) as $chit) {
                        if ($chit!='NONE' && $chit!='DRAW 2') {
                            $returnedMessage= $this->mortality_killSenator((string)$chit,FALSE,FALSE,FALSE,($level==1 ? 'domestic' : 'foreign')) ;
                            $messages[] = array(sprintf(_('Chit drawn : %s. ') , $chit).$returnedMessage[0] , (isset($returnedMessage[1]) ? $returnedMessage[1] : NULL) );
                        } else {
                            $messages[] = array(sprintf(_('Chit drawn : %s') , $chit)) ;
                        }
                    }
                    break ;
                // Mob Violence
                case 171 :
                    $roll = $this->rollOneDie(1) ;
                    $nbOfMortalityChits = $this->unrest + ($level==1 ? 0 : $roll );
                    $POPThreshold = $this->unrest + ($level==1 ? 0 : 1 );
                    $messages[] = array(
                        sprintf(_('The unrest level is %d %s%s, so %d mortality chit%s are drawn. Senators in Rome with a POP below %d will be killed.') , 
                            $this->unrest ,
                            ($level>1 ? ' +'.$roll : '') ,
                            ($level>1 ? $this->getEvilOmensMessage(1) : '') ,
                            $nbOfMortalityChits ,
                            ($nbOfMortalityChits == 1 ? '' : 's'),
                            $POPThreshold
                        ) ,
                        'alert');
                    foreach ($this->mortality_chits($nbOfMortalityChits) as $chit) {
                        if ($chit!='NONE' && $chit!='DRAW 2') {
                            $returnedMessage= $this->mortality_killSenator((string)$chit,FALSE,FALSE,$POPThreshold) ;
                            $messages[] = array(sprintf(_('Chit drawn : %s. ') , $chit).$returnedMessage[0] , (isset($returnedMessage[1]) ? $returnedMessage[1] : NULL) );
                        } else {
                            $messages[] = array(sprintf(_('Chit drawn : %s') , $chit)) ;
                        }
                    }
                    break ;
                // Natural Disaster
                case 172 :
                    // First : Pay 50T the first time the vent is played
                    if ($level==1) {
                        $this->treasury-=50 ;
                        $messages[] = array (_('Rome must pay 50T.') , 'alert');
                        if ($this->treasury<0) {
                            $messages[] = array (_('ROME IS BANKRUPT - GAME OVER') , 'alert');
                            $this->phase = 'Rome falls' ;
                            return $messages ;
                        }
                    }
                    // Then : Ruin some stuff
                    $roll = $this->rollOneDie(0) ;
                    $ruin = '' ;
                    switch($roll) {
                        case 1:
                        case 2:
                            $ruin = 'MINING' ;
                            break ;
                        case 3:
                        case 4:
                            $ruin = 'HARBOR FEES' ;
                            break ;
                        case 5 :
                            $ruin = 'ARMAMENTS' ;
                            break ;
                        case 6:
                            $ruin = 'SHIP BUILDING' ;
                            break ;
                    }
                    $ruinresult = $this->getSpecificCard('name', $ruin) ;
                    if ($ruinresult['where']=='forum') {
                        $messages[] = array(sprintf(_('The %s concession was in the forum. It is destroyed and moved to the curia') , $ruinresult['card']->name) , 'alert') ;
                        $this->curia->putOnTop($ruinresult['deck']->drawCardWithValue('name' , $ruin));
                    }
                    if ($ruinresult['where']=='senator' ) {
                        $messages[] = array(sprintf(_('The %s concession was controlled by %s. It is destroyed and moved to the curia') , $ruinresult['card']->name , $ruinresult['senator']->name) , 'alert') ;
                        $this->curia->putOnTop($ruinresult['deck']->drawCardWithValue('name' , $ruin));
                    }
            }
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
                    if ($senator->inRome() && $senator->senatorID != $party->leaderID && $party->user_id!=$user_id) {
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
            array_push($messages , array( sprintf(_('{%s} doesn\'t try to persuade any senator during this initiative.') , $user_id) ));
        } else {
            array_push($messages , array(_('Wrong phase, subphase or player'),'error',$user_id));
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
        $targetParty = $this->getPartyOfSenator($this->persuasionTarget) ;
        $result['target']['user_id'] = ($targetParty == 'forum' ? 'forum' : $targetParty->user_id) ;
        $result['target']['party_name'] = ($targetParty == 'forum' ? 'forum' : $targetParty->fullName()) ;
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
                if (($partyTarget=='forum' && $target[2]=='forum') || $partyTarget->user_id==$target[2]) {
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
                                array_push($messages , array ( sprintf(_('%s ({%s}) attempts to persuade %s (%s)') , $persuadingSenator->name , $partyPersuader->user_id , $targetSenator->name , ($partyTarget=='forum' ? _('forum') : '{'.$partyTarget->user_id.'}') ) )) ;
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
                                        array_push($messages , array ( sprintf(_('%s ({%s}) attempts to persuade %s (%s) using a %s card.') , $persuadingSenator->name , $partyPersuader->user_id , $targetSenator->name , ($partyTarget=='forum' ? _('forum') : '{'.$partyTarget->user_id.'}') , $card->name ) )) ;
                                    }
                                } else {
                                    return array(array(_('You do not have that card in hand.'),'error',$user_id));
                                }
                            }
                        } else {
                            return array(array(_('Amount error'),'error',$user_id));
                        }
                    } else {
                        return array(array(_('Error - persuading Senator party mismatch'),'error',$user_id));
                    }
                } else {
                    return array(array(_('Error - target Senator party mismatch'),'error',$user_id));
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
                                array_push ($messages , array( sprintf(_('FAILURE - {%s} rolls an unmodified %d, which is greater than 9 and an automatic failure.') , $user_id , $roll['total']) ));
                            // Failure if roll > target number    
                            } elseif ($roll['total']>$currentPersuasion['odds']['total']) {
                                if (($currentPersuasion['target']['card']!==FALSE)) {
                                    array_push ($messages , $this->forum_removePersuasionCard($user_id , $currentPersuasion['target']['senatorID'] , $currentPersuasion['target']['card'] , 'FAILURE') );
                                }
                                
                                array_push ($messages , array( sprintf(_('FAILURE - {%s} rolls %d%s, which is greater than the target number of %d.') , $user_id , $roll['total'] , $this->getEvilOmensMessage(1) , $currentPersuasion['odds']['total']) ));
                            // Success
                            } else {
                                if (($currentPersuasion['target']['card']!==FALSE)) {
                                    array_push ($messages , $this->forum_removePersuasionCard($user_id , $currentPersuasion['target']['senatorID'] , $currentPersuasion['target']['card'] , 'SUCCESS') );
                                }
                                array_push ($messages , array( sprintf(_('SUCCESS - {%s} rolls %d%s, which is not greater than the target number of %d.') , $user_id , $roll['total'] , $this->getEvilOmensMessage(1) , $currentPersuasion['odds']['total']) ));
                                if ($currentPersuasion['target']['party_name'] == 'forum') {
                                    $senator = $this->forum->drawCardWithValue('senatorID' , $currentPersuasion['target']['senatorID']);
                                    $this->party[$user_id]->senators->putOnTop($senator) ;
                                    array_push ($messages , array( sprintf(_('%s leaves the forum and joins {%s}.') , $senator->name , $user_id) ));
                                } else {
                                    $senator = $this->party[$currentPersuasion['target']['user_id']]->senators->drawCardWithValue('senatorID' , $currentPersuasion['target']['senatorID']);
                                    $this->party[$user_id]->senators->putOnTop($senator) ;
                                    array_push ($messages , array( sprintf(_('%s leaves {%s} and joins {%s}.') , $senator->name , $currentPersuasion['target']['user_id'] , $user_id) ));
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
                                array_push ($messages , array( sprintf(_('%s takes a total of %dT from bribes and counter-bribes.') , $currentPersuasion['target']['name'] , $totalBids) ));
                            }
                            $this->forum_resetPersuasion() ;
                            $this->subPhase = 'Knights';
                            array_push($messages , array (_('Knights Sub Phase')) );
                            
                        // More bribe : go for another round of counter bribes
                        } else {
                            if ($this->party[$user_id]->bidWith->treasury>=$amount) {
                                $this->party[$user_id]->bidWith->treasury-=$amount;
                                $this->party[$user_id]->bid+=$amount;
                                array_push ($messages , array( sprintf(_('%s bribes more.') , $user_id) ));
                                $nextPlayer = $this->whoIsAfter($user_id);
                                $this->currentBidder = $nextPlayer['user_id'];
                                foreach ($nextPlayer['messages'] as $message) {
                                    array_push($messages , $message);
                                }
                            } else {
                                array_push ($messages , array(_('The senator is too poor') , 'error' , $user_id));
                            }
                        }
                        
                    // This user doesn't have the initiative, this is a counter-bribe
                    } else {
                        if ($amount==0) {
                            array_push ($messages , array( sprintf(_('{%s} doesn\'t spend money to counter-bribe.') , $user_id) ));
                            $nextPlayer = $this->whoIsAfter($user_id);
                            $this->currentBidder = $nextPlayer['user_id'];
                            foreach ($nextPlayer['messages'] as $message) {
                                array_push($messages , $message);
                            }
                        } elseif ($this->party[$user_id]->treasury >= $amount) {
                            $this->party[$user_id]->treasury -= $amount ;
                            $this->party[$user_id]->bid += $amount ;
                            array_push ($messages , array( sprintf(_('{%s} spends %dT from the party treasury to counter-bribe.') , $user_id , $amount) ));
                            $nextPlayer = $this->whoIsAfter($user_id);
                            $this->currentBidder = $nextPlayer['user_id'];
                            foreach ($nextPlayer['messages'] as $message) {
                                array_push($messages , $message);
                            }
                        } else {
                            return array(array(_('Error - not enough money in the party\'s treasury'),'error',$user_id));
                        }
                    }
                // This is user is NOT the current bidder, something is wrong
                } else {
                    return array(array(_('Error - this is not your turn to play'),'error',$user_id));
                }
            }
        }
        return $messages ;
    }
    
    /**
     * Applies the effects and removes the persuasion card with $id from senator with $senatorID after an $outcome of 'FAILURE' or 'SUCCESS'
     * @param string $senatorID ID of the Senator
     * @param integer $id Card id
     * @param string $outcome
     * @return array A message array (not an array of messages)
     */
    public function forum_removePersuasionCard($user_id , $senatorID , $id , $outcome) {
        $senator = $this->getSenatorWithID($senatorID);
        if ($senator!==FALSE) {
            $card = $senator->controls->drawCardWithValue('id',$id) ;
            if ($card!==FALSE) {
                $completeMessage=sprintf(_('The %s card is discarded.') , $card->name);
                if ($card->name=="BLACKMAIL") {
                    if ($outcome=='FAILURE') {
                        $rollINF = max(0,$this->rollDice(2, -1)['total']) ;
                        $rollPOP = max(0,$this->rollDice(2, -1)['total']) ;
                        $senator->changeINF(-$rollINF);
                        $senator->changePOP(-$rollPOP);
                        $completeMessage.=sprintf(_(' The failure of the persuasion causes a loss of %d INF and %d POP%s to %s') , $rollINF , $rollPOP , $this->getEvilOmensMessage(-1) , $senator->name);
                    }
                }
                $message = array($completeMessage);
            } else {
                $message = array(_('Cannot find this Card') , 'error' , $user_id );
            }
        } else {
            $message = array(_('Cannot find this Senator') , 'error' , $user_id );
        }
        return $message ;
    }
    
    
    /**
     * Returns a list of senatorID, name , knights, treasury and inRome by senator
     * Useful for attracking and pressuring knights
     * @param string $user_id The user_id of the current player
     * @return array ( 'senatorID' , 'name' , 'knights' , 'treasury' , 'inRome' )
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
                            array_push($messages , array( sprintf(_('Attracting Knight : SUCCESS - %s ({%s}) spends %dT and rolls %d%s. The total is >= 6.') , $senator->name , $user_id , $amount , $roll , $this->getEvilOmensMessage(-1)) )) ;
                        } else {
                            array_push($messages , array( sprintf(_('Attracting Knight : FAILURE - %s ({%s}) spends %dT and rolls %d%s. The total is < 6.') , $senator->name , $user_id , $amount , $roll , $this->getEvilOmensMessage(-1)) )) ;
                        }
                        $this->subPhase = 'SponsorGames';
                        array_push ($messages , array(_('Sponsor Games Sub Phase')));
                        // Be nice : skip sponsor games sub phase if no senator can do them.
                        $listSponsorGames = $this->forum_listSponsorGames($user_id) ;
                        if (count($listSponsorGames)==0) {
                            array_push ($messages , array( sprintf(_('{%s} has no senator who can sponsor games.') , $user_id) ))  ;
                            $this->subPhase = 'ChangeLeader';
                            array_push ($messages , array(_('Change Leader Sub Phase')));
                        }
                    } else {
                        array_push($messages , array(_('Amount error') , 'error' , $user_id)) ;
                    }
                } else {
                    array_push($messages , array(_('Wrong party') , 'error' , $user_id)) ;
                }
            } else {
                array_push($messages , array(_('Error retrieving senator data') , 'error' , $user_id)) ;
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
                            $message = sprintf(_('%s pressures %d knight%s. Rolls : ') , $senator->name , $pressuredKnights , ($pressuredKnights>1 ? 's' : '') ) ;
                            $total = 0 ;
                            for ($i=1 ; $i<$pressuredKnights ; $i++) {
                                $roll = min($this->rollOneDie(-1),0);
                                $message.=$roll.', ';
                                $total+=$roll;
                            }
                            $message = substr($message, 0 , -2) ;
                            $message.= sprintf(_('%s. Earns a total of %dT.') , $this->getEvilOmensMessage(-1) , $total);
                            array_push($messages , array($message));
                        } else {
                            $error = TRUE ;
                            array_push($messages , array(sprintf(_('Not enough knights for %s : ignored') , $senator->name) , 'error' , $user_id));
                        }
                    } else {
                        $error = TRUE ;
                        array_push($messages , array(sprintf(_('Wrong party for %s : ignored') , $senator->name) , 'error' , $user_id));
                    }
                }
            }
            // If there is no error, move to next sub phase (SponsorGames)
            // Be nice : skip sponsor games sub phase if no senator can do them.
            if (!$error) {
                $this->subPhase = 'SponsorGames';
                array_push ($messages , array( _('Sponsor Games Sub Phase') ));
                $listSponsorGames = $this->forum_listSponsorGames($user_id) ;
                if (count($listSponsorGames)==0) {
                    array_push ($messages , array( sprintf(_('{%s} has no senator who can sponsor games.') , $user_id) ));
                    $this->subPhase = 'ChangeLeader';
                    array_push ($messages , array( _('Change Leader Sub Phase') ));
                }
            }
        }
        return $messages ;
    }
    
    /**
     * Lists all the senators who are able to sponsor games in party user_id 
     * @param string $user_id The user_id of the current player
     * @return array ('senatorID' , 'name' , 'treasury')
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
            $gamesName = array() ; $gamesName[7] = _('Slice & Dice') ; $gamesName[13] = _('Blood Fest') ; $gamesName[18] = _('Gladiator Gala') ; 
            $senatorData = explode('|' , $senatorRaw);
            $senatorID = $senatorData[0] ;
            $senator= $this->getSenatorWithID($senatorID);
            if ($this->getPartyOfSenator($senator)->user_id == $user_id) {
                if ($senator->treasury>=$type) {
                    $senator->treasury-=$type ;
                    $this->unrest-=$gamesEffects[$type];
                    $senator->changePOP($gamesEffects[$type]);
                    array_push($messages , array( sprintf(_('%s organises %s, reducing the unrest by %d and gaining %d popularity.') , $senator->name , $gamesName[$type] , $gamesEffects[$type] , $gamesEffects[$type]) ));
                    $this->subPhase = 'ChangeLeader';
                    array_push ($messages , array( _('Change Leader Sub Phase') ));
                } else {
                    array_push($messages , array(sprintf(_('%s doesn\'t have enough money to sponsor these games.') , $senator->name) , 'error' , $user_id ));
                }
            } else {
                array_push($messages , array(_('Error - Wrong party') , 'error' , $user_id));
            }
        } elseif ( ($this->phase=='Forum') && ($this->subPhase=='SponsorGames') && ($this->forum_whoseInitiative()==$user_id) && $type==0) {
            array_push($messages , array(sprintf(_('{%s} doesn\'t sponsor games during this initiative.') , $user_id) ));
            $this->subPhase = 'ChangeLeader';
            array_push ($messages , array( _('Change Leader Sub Phase') ));
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
                    if ($this->party[$user_id]->leaderID != $senatorID) {
                        $this->party[$user_id]->leaderID = $senator->senatorID ;
                        array_push($messages , array(sprintf(_('%s is now the leader of {%s}') , $senator->name , $user_id) ));
                    } else {
                        $error = TRUE ;
                        array_push($messages , array(_('Error - This senator is already the leader') , 'error' , $user_id));
                    }
                } else {
                    $error = TRUE ;
                    array_push($messages , array(_('Error - Wrong party') , 'error' , $user_id));
                }
            }
            if (!$error) {
                $this->initiative++ ;
                if ($this->initiative<=6) {
                    $this->subPhase = 'RollEvent';
                    array_push($messages , array(sprintf(_('Initiative #%d') , $this->initiative) , 'alert'));
                    // forum_initInitiativeBids returns messages to indicate players who might have been skipped because they can't bid
                    $initMessages = $this->forum_initInitiativeBids();
                    foreach ($initMessages as $message) {
                        array_push($messages , $message) ;
                    }
                } else {
                    $this->initiative=6;
                    $this->subPhase = 'curia';
                    array_push($messages , array(_('All initiatives have been played, putting Rome in order.') ));
                    $curia_messages = $this->forum_curia();
                    foreach ($curia_messages as $message) {
                        array_push($messages , $message) ;
                    }
                    array_push($messages , array(_('POPULATION PHASE'),'alert'));
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
                    array_push($messages , array(sprintf(_('%s ({%s}) held a major office and gets a major corruption marker.') , $senator->name , $party->user_id)));
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
            array_push($messages , array(_('Rolling for cards in the curia')));
            $cardsToMoveToForum = array() ;
            $cardsToDiscard = array() ;
            foreach ($this->curia->cards as $card) {
                if ($card->type=='Concession' || $card->type=='Family') {
                    $roll = $this->rollOneDie(-1) ;
                    if ($roll>=5) {
                        array_push($cardsToMoveToForum , $card->id);
                        array_push($messages , array( sprintf(_('A %d%s is rolled and %s comes back to the forum.') , $roll , $this->getEvilOmensMessage(-1) , $card->name) ));
                    } else {
                        array_push($messages , array( sprintf(_('A %d%s is rolled and %s stays in the curia.') , $roll , $this->getEvilOmensMessage(-1) , $card->name) ));
                    }
                } elseif ($card->type=='Leader') {
                    $roll = $this->rollOneDie(-1) ;
                    if ($roll>=5) {
                        array_push($cardsToDiscard , $card->id);
                        array_push($messages , array( sprintf(_('A %d%s is rolled and %s is discarded.') , $roll , $this->getEvilOmensMessage(-1) , $card->name) ));
                    } else {
                        array_push($messages , array( sprintf(_('A %d%s is rolled and %s stays in the curia.') , $roll , $this->getEvilOmensMessage(-1) , $card->name) ));
                    }
                }
            }
            // Doing this later in order not to disturb the main loop above (unsure how foreach would behave with a potentially shrinking array...)
            foreach ($cardsToMoveToForum as $cardID) {
                $card = $this->curia->drawCardWithValue('id' , $cardID) ;
                if ($card!==FALSE) {
                    $this->forum->putOnTop($card);
                } else {
                    array_push($messages , array(_('Error retrieving card from the curia') , 'error')) ;
                }
            }
            foreach ($cardsToDiscard as $cardID) {
                $card = $this->curia->drawCardWithValue('id' , $cardID) ;
                if ($card!==FALSE) {
                    $this->discard->putOnTop($card);
                } else {
                    array_push($messages , array( _('Error retrieving card from the curia') , 'error')) ;
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
                $name=sprintf(_('TAX FARMER %s') , (string)$roll);
                $toRuin = $this->getSpecificCard('name', $name) ;
                array_push($messages , array($this->forum_ruinSpecificTaxFarmer($toRuin , $war->name , $roll),'alert'));
            }
        }
        // Ruined by unprosecuted war
        foreach($this->unprosecutedWars->cards as $war) {
            if (strstr($war->causes , 'tax farmer')) {
                $roll=$this->rollOneDie(0);
                $name=sprintf(_('TAX FARMER %s') , (string)$roll);
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
                return sprintf(_('%s causes the ruin of a random tax farmer. A %d is rolled : no effect as this tax farmer is already in the curia.') , $becauseOf , $roll);
            } elseif ($toRuin['where']=='senator') {
                $this->curia->putOnTop($toRuin['deck']->drawCardWithValue('id',$toRuin['card']->id));
                return sprintf(_('%s causes the ruin of a random tax farmer. A %d is rolled : the ruined tax farmer is removed from %s\'s control and placed in the curia.') , $becauseOf , $roll , $toRuin['senator']->name);
            } elseif ($toRuin['where']=='forum') {
                $this->curia->putOnTop($toRuin['deck']->drawCardWithValue('id',$toRuin['card']->id));
                return sprintf(_('%s causes the ruin of a random tax farmer. A %d is rolled : the ruined tax farmer is removed from the forum and placed in the curia.') , $becauseOf , $roll);
                } else {
                return sprintf(_('%s causes the ruin of a random tax farmer. A %d is rolled : ERROR - the ruined tax farmer was found in the wrong deck (%s)') , $becauseOf , $roll , $toRuin['where']);
            }
        } else {
            return sprintf(_('%s causes the ruin of a random tax farmer. A %d is rolled : no effect as this tax farmer is not in play.') , $becauseOf , $roll);
        }
    }
    
    /**
     * forum_view returns all the data needed to render forum templates. The function returns an array $output :
     * $output['state'] (Mandatory) : gives the name of the current state to be rendered
     * $output['X'] : an array of values 
     * - X is the name of the component to be rendered
     * - 'values' is the actual content : variables, text, array of options for select, etc...
     * @param string $user_id
     * @return array $output
     */
    public function forum_view($user_id) {
        $output = array () ;
        $output['initiative'] = $this->initiative ;
        // This is only necessary for subPhase == 'Knights', but needed for JavaScript functions in every state/subPhase
        // TO DO : Check if eveil omens are taken into account for persuasion
        $output['evilOmens'] = $this->getEventLevel('name','Evil Omens') ;
        // We don't know who has the initiative : we are bidding
        if ($this->forum_whoseInitiative() === FALSE) {
            if ($this->currentBidder == $user_id) {
                $output['state'] = 'bidding' ;
                
                $output['highestBidder'] = $this->forum_highestBidder();
                $output['senatorList'] = array() ;
                $output['showAmount'] = FALSE ;
                foreach ($this->party[$user_id]->senators->cards as $senator) {
                    if ( $senator->inRome() && $senator->treasury>$output['highestBidder']['bid'] ) {
                        array_push( $output['senatorList'] , array('senatorID' => $senator->senatorID , 'name' => $senator->name , 'treasury' => $senator->treasury) );
                        $output['showAmount'] = TRUE ;
                    }
                }
            } else {
                $output['state'] = 'Waiting for bidding' ;
                $output['currentBidderName'] = $this->party[$this->currentBidder]->fullName() ;
            }
            
        // We know who has the initiative
        } else {
            $output['state'] = 'notBidding' ;
            $output['Initiative description'] = sprintf(_('Initiative %d (%s)') , $this->initiative , $this->party[$this->forum_whoseInitiative()]->fullName());
            $output['subPhase'] = $this->subPhase ;
            // This user has the initiative
            if ($this->forum_whoseInitiative() == $user_id) {
                $output['initiativeIsYours'] = TRUE ;
                
                // Persuasion
                if ($output['subPhase'] == 'Persuasion' ) {
                    // We don't know the target
                    if ($this->persuasionTarget === NULL) {
                        $output['targetKnown'] = FALSE ;
                        $output['listPersuaders'] = $this->forum_listPersuaders($user_id) ;
                        $output['listTargets'] = $this->forum_listPersuasionTargets($user_id) ;
                        $output['listCards'] = $this->forum_listPersuasionCards($user_id) ;
                    // We know the target
                    } else {
                        $output['targetKnown'] = TRUE;
                        // this user has the initiative, therefore he must choose to roll or bid more
                        if ($this->currentBidder == $user_id) {
                            $output['briber'] = TRUE;
                            $output['persuasionList'] = $this->forum_persuasionListCurrent() ;
                        } else {
                            $output['briber'] = FALSE;
                            $output['briberFullName'] = $this->party[$this->currentBidder]->fullName() ;
                        }
                    }
                } elseif ($output['subPhase'] == 'Knights' ) {
                    $output['listKnights'] = $this->forum_listKnights($user_id) ;
                    $output['canPressure'] = FALSE ;
                    foreach ($output['listKnights'] as $item) {
                        if ($item['knights']> 0) {
                            $output['canPressure'] = TRUE ;
                        }
                    }
                } elseif ($output['subPhase'] == 'SponsorGames' ) {
                    $output['listGames'] = $this->forum_listSponsorGames($user_id) ;
                }  elseif ($output['subPhase'] == 'ChangeLeader' ) {
                    $partyLeader = $this->getSenatorWithID($this->party[$user_id]->leaderID);
                    $output['leaderName'] = $partyLeader->name ;
                    $output['leaderSenatorID'] = $this->party[$user_id]->leaderID ;
                    $output['listSenators'] = $this->party[$user_id]->senators->cards ;
                }
            // This user does not have the initiative
            } else {
                $output['initiativeIsYours'] = FALSE ;
                // Persuasion counter-bribe by players without the initiative
                if ($output['subPhase'] == 'Persuasion' ) {
                    // He is the current counter-briber
                    if ($this->currentBidder == $user_id) {
                        $output['counterBribe'] = TRUE ;
                        $output['treasury'] = $this->party[$user_id]->treasury ;
                    // He is not the current counter-briber
                    } else {
                        $output['counterBribe'] = FALSE ;
                        $output['currentBidderName'] = $this->party[$this->currentBidder]->fullName();
                        $output['waitingFor'] = ( ($this->currentBidder == $this->forum_whoseInitiative() ) ? 'persuasion' : 'counterBribes') ;
                    }
                }
            }
            
        }
        return $output ;
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
        $result['message']=_('No change in unrest from wars and droughts this turn.');
        foreach ($this->unprosecutedWars->cards as $conflict) {
            $result['total']++;
            if ($result['total']==1) {
                $result['message']=_('Unrest increases because of unprosecuted wars : ');
            }
            $result['message'].=$conflict.name.', ';
        }
        if ($result['total']>0) {
            $result['message']=substr($result['message'],0,-2);
            $result['message'].=sprintf(_(' : + %d unrest.') , $result['total']);
        }
        $droughtLevel = $this->getTotalDroughtLevel();
        if ($droughtLevel > 0 ) {
            if ($result['total']>0) {
                $result['message'].= _(' And ');
            }
            $result['message'].=sprintf(_('%d droughts cause +%d unrest.') , $droughtLevel , $droughtLevel);
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
            array_push($messages , array( sprintf(_('%s rolls %d%s + current unrest (%d) - HRAO\'s Popularity (%d)  for a total of %d.') , $HRAO['senator']->name , $roll['total'] , $this->getEvilOmensMessage(-1) , $this->unrest , $HRAO['senator']->POP , $total) ));
            if ($total!=-1) {
                $effects = $this->populationTable[$total];
                foreach ($effects as $effect) {
                    switch($effect) {
                        case 'MS' :
                            $eventMessage = $this->forum_putEventInPlay('name' , 'Manpower Shortage') ;
                            foreach ($eventMessage as $message) {
                                $messages[] = $message;
                            }
                            break ;
                        case 'NR' :
                            $eventMessage = $this->forum_putEventInPlay('name' , 'No Recruitment') ;
                            foreach ($eventMessage as $message) {
                                $messages[] = $message;
                            }
                            break ;
                        case 'Mob' :
                            $mobMessages = $this->population_mob() ;
                            foreach ($mobMessages as $message) {
                                $messages[] = $message;
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
                            array_push($messages , array( sprintf(_('The unrest is changed by '.($effect>0 ? '+' :'').'%d, now at %d') , $effect , $this->unrest) ,'alert')) ;
                    }
                }
            } else {
                array_push($messages , array(_('PEOPLE REVOLT - GAME OVER.') , 'error'));
                $this->phase = 'Rome falls' ;
                return $messages ;
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
        $this->senateAdjourned = FALSE;
        $this->censorIsDone = FALSE;
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
                // Re-initialise corrupt state of all concessions before Senate starts
                foreach ($senator->controls->cards as $card) {
                    if ($card->type=='Concession') {
                        $card->corrupt=FALSE ;
                    }
                }
            }
            // Reset assasination attemps and targets
            $party->assassinationAttempt = FALSE ;
            $party->assassinationTarget = FALSE ;
        }
        // Check if there is only one possible pair of consuls, and appoint them if it's the case.
        $possiblePairs = $this->senate_consulsPairs() ;
        if (count($possiblePairs)==1) {
            $senator1 = $this->getSenatorWithID($possiblePairs[0][0]) ;
            $senator2 = $this->getSenatorWithID($possiblePairs[0][1]) ;
            // Automatically put this proposal in the array of proposals
            $proposal = new Proposal ;
            $proposal->init('Consuls' , NULL , NULL , $this->party , array($possiblePairs[0][0] , $possiblePairs[0][1]) , NULL) ;
            $proposal->outcome = TRUE ;
            $this->proposals[] = $proposal ;
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
        /* Short-circuit the process entirely if this is an exception case to normal proposals :
         * - Giving the opportunity to a player to give up on Dictator's proposal for the rest of the turn
         */
        if ($type=='Dictator' && $parameters[0]=='DONE') {
            $this->party[$user_id]->bidDone = TRUE ;
            $finished = TRUE ;
            foreach($this->party as $party) {
                if (!$party->bidDone) {
                    $finished = FALSE ;
                }
            }
            if ($finished) {
                return $this->senate_nextSubPhase() ;
            } else {
                return array(array(_('You do not wish to propose a Dictator this turn.') , 'message' , $user_id)) ;
            }
                
        }
        // Consuls proposal = we might need to swap parameters to ensure lexicographical order 
        if ($type=='Consuls' && strcmp($parameters[0],$parameters[1])>0) {
            $tmpparameter = $parameters[0] ;
            $parameters[0] = $parameters[1] ;
            $parameters[1] = $tmpparameter ;
        }
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

        foreach ($rules as $rule) {
            $validation = $this->senate_validateParameter($rule[0] , $parameters , $rule[1]) ;
            if ($validation !== TRUE) {
                return array(array($validation , 'error' , $user_id)) ;
            }
        }
        // For Governors, the 'SenatorAccepts' parameters are set to 'PENDING' if the proposed governor is a returning governor, and to 'NA' otherwise
        if ($type=='Governors') {
            foreach ($parameters as $key => $parameter) {
                // This is the 'SenatorAccepts' parameter
                if ($key % 3 == 1) {
                    $parameters[$key] = ( $this->getSenatorWithID($parameters[$key-1])->returningGovernor ? 'PENDING' : 'NA' ) ;
                }
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
            $this->proposals[] = $proposal ;
            
        //Dictator
        } elseif ($type=='Dictator') {
            // 'Appointnment' proposal
            if ($proposalHow==='Dictator appointed by Consuls') {
                $proposal->parameters[1] = TRUE ;
                // Check if Senator is equal to 'FALSE', which means a Consul didn't want to appoint, immediately ending the Dictator appointment phase
                if ($parameters[0]=='FALSE') {
                    $proposal->outcome = FALSE ;
                    array_push($messages , array(_('Consuls decide not to appoint a Dictator this turn.')) );
                } else {
                    array_push($messages , array(sprintf(_('You propose to appoint %s {%s} as a Dictator.') , $this->getSenatorWithID($parameters[0])->name , $this->getPartyOfSenatorWithID($parameters[0])->user_id ) , $user_id) );
                }
                $this->proposals[] = $proposal ;
                // The proposal can be accetped immediately if there is only one Consul
                if ($this->senate_findOfficial('Rome Consul')===FALSE || $this->senate_findOfficial('Field Consul')===FALSE) {
                    $proposal->outcome = TRUE ;
                    $this->senate_appointOfficial('Dictator', $parameters[0]) ;
                    array_push($messages , array(sprintf(_('The only Consul alive appoints %s {%s} as a Dictator.') , $this->getSenatorWithID($parameters[0])->name , $this->getPartyOfSenatorWithID($parameters[0])->user_id )) );
                }
            // Normal Dictator Proposal
            } else {
                $proposal->parameters[1] = FALSE ;
                $using = $this->senate_useProposalHow($user_id , $proposalHow) ;
                array_push($messages , array(sprintf(_('%s, {%s} proposes %s as dictator.') , $using , $user_id , $this->getSenatorWithID($parameters[0])->name)) );
                $this->proposals[] = $proposal ;
            }
            // The 'bidDone' parameter is used here to make sure that every party has a chance to propose a dictator
             
        // Censor
        } elseif ($type=='Censor') {
            $using = $this->senate_useProposalHow($user_id , $proposalHow) ;
            $senator = $this->getSenatorWithID($parameters[0]) ;
            array_push($messages , array(sprintf(_('%s, {%s} proposes %s as Censor.') , $using , $user_id , $senator->name)) );
            $this->proposals[] = $proposal ;
        
        // Prosecutions
        } elseif ($type=='Prosecutions') {
            $reasonsList = $this->senate_getListPossibleProsecutions() ;
            $reasonText = '';
            foreach ($reasonsList as $reason) {
                if ($reason['reason']==$parameters[1]) {
                    $reasonText = $reason['text'] ;
                }
            }
            // If prosecutor and Censor are in the same party, prosecutor automagically accepts
            $autoAccept = '';
            if ($this->getPartyOfSenatorWithID($parameters[2])->user_id == $user_id) {
                $proposal->parameters[3] = TRUE ;
                $autoAccept = _(' (being in the same party as the Censor, he accepts immediately)') ;
            }
            array_push($messages , array(sprintf(_('The Censor %s of {%s} accuses %s, appointing %s as prosecutor%s. Reason : %s') , $this->senate_findOfficial('Censor')['senator']->name , $user_id , $this->getSenatorFullName($parameters[0]) , $this->getSenatorFullName($parameters[2]) , $autoAccept , $reasonText )) );
            $this->proposals[] = $proposal ;
        
        // Governorships
        } elseif ($type=='Governors') {
            // TO DO
        } elseif ($type=='Concessions') {
            $using = $this->senate_useProposalHow($user_id , $proposalHow) ;
            array_push($messages, array( sprintf(_('%s {%s} proposes to assign concessions as follows : ') , $using , $user_id).$this->senate_getConcessionProposalDetails($parameters) )) ;
            $this->proposals[] = $proposal ;
        } elseif ($type=='Land Bills') {
            $using = $this->senate_useProposalHow($user_id , $proposalHow) ;
            // TO DO
        } elseif ($type=='Forces') {
            $using = $this->senate_useProposalHow($user_id , $proposalHow) ;
            array_push($messages, array( sprintf(_('%s {%s} proposes : ') , $using , $user_id).$this->senate_getForcesProposalDetails($parameters) )) ;
            $this->proposals[] = $proposal ;
        } elseif ($type=='Garrison') {
            // TO DO
        } elseif ($type=='Deploy') {
            $using = $this->senate_useProposalHow($user_id , $proposalHow) ;
            array_push($messages, array( sprintf(_('%s {%s} proposes : ') , $using , $user_id).$this->senate_getDeployProposalDetails($parameters) )) ;
            $this->proposals[] = $proposal ;
        } elseif ($type=='Recall') {
            // TO DO
        } elseif ($type=='Reinforce') {
            // TO DO
        } elseif ($type=='Recall Pontifex') {
            // TO DO
        } elseif ($type=='Priests') {
            // TO DO
        } elseif ($type=='Consul for life') {
            // TO DO
        } elseif ($type=='Minor') {
            // TO DO
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
        
        // Check that no voting is underway already
        // TO DO : A tribune can interrupt the current proposal
        if (end($this->proposals)!==FALSE && end($this->proposals)->outcome===NULL) {
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
        // This does not apply to Dictator appointment by Consuls
        if ($proposalHow!='Dictator appointed by Consuls') {
            if (count($votingOrder)!=count($this->party)) {
                return array(array(_('Invalid voting order : not enough parties') , 'error')) ;
            }
            while (count($votingOrder)>0) {

                $currentElement = array_shift($votingOrder) ;
                if (!array_key_exists($currentElement , $this->party)) {
                    return array(array(_('Invalid voting order : unknown party') , 'error')) ;
                }
            }
        }
        return TRUE ;
    }
    
    /**
     * Validates a proposal's parameters against a specific rule
     * @param type $rule 'Pair'|'inRome'|'NO_or_inRome'|'boolean'|'office'|'censorRejected'|'cantProsecuteSelf'|'censorCantBeProsecutor'|'prosecutionRejected'<br>
     * |'possibleGovernors'|'possibleProvinces'|'concessionOddParameters'|'concessionEvenParameters'|'Forces'|'Deploy'
     * @param array $parameters the array of parameters of the proposal
     * @param integer $index The index of the parameter to be validated, if needed (for some rules, it's obvious, like 'pair')
     * @return mixed TRUE if validated | message explaining why it didn't validate
     */
    private function senate_validateParameter($rule , $parameters , $index=0) {
        switch ($rule) {
            case 'pair' :
                usort ($parameters, function($a, $b) {
                    return strcmp($a, $b);
                });
                if ($parameters[0] == $parameters[1]) {
                    return _('This is a pair of one. Please stop drinking.');
                }
                foreach ($this->proposals as $proposal) {
                    if ($proposal->type=='Consuls' && $proposal->outcome==FALSE && $proposal->parameters[0]==$parameters[0] && $proposal->parameters[1]==$parameters[1]) {
                        return _('This pair has already been rejected');
                    }
                }
                return TRUE ;
            case 'inRome' :
                return ( $this->getSenatorWithID($parameters[$index])->inRome() ? TRUE : _('Senator is not in Rome.') ) ;
            case 'NO_or_inRome' :
                return ( ($parameters[$index]=='FALSE' || $this->getSenatorWithID($parameters[$index])->inRome()) ? TRUE : _('Senator is not in Rome.') ) ;
            case 'boolean' :
                return ( ( $parameters[$index]==TRUE || $parameters[$index]==FALSE) ? TRUE : sprintf(_('Value at index %d should be True or False.') , $index ) ) ;
            case 'office' :
                $senator = $this->getSenatorWithID($parameters[$index]) ;
                if (!in_array('Tradition Erodes' , $this->laws)) {
                    if ( ($senator->office == 'Dictator') || ($senator->office == 'Rome Consul') || ($senator->office == 'Field Consul') ) {
                        return _('Before the \'Tradition Erodes\' law is in place, Senators cannot be proposed if they are already Dictator or Consul.');
                    }
                }
                return ( ($senator->office == 'Pontifex Maximus') ? _('The Pontifex Maximus cannot be proposed.') : TRUE ) ;
            case 'censorRejected' :
                foreach ($this->proposals as $proposal) {
                    if ($proposal->type=='Censor' && $proposal->outcome==FALSE && $proposal->parameters[0]==$parameters[0]) {
                        return _('Proposing this Senator as Censor has already been rejected');
                    }
                }
                return TRUE ;
            case 'cantProsecuteSelf' :
                return ($parameters[0]==$parameters[2] ? _('A Senator cannot prosecute himself') : TRUE) ;
            case 'censorCantBeProsecutor' :
                $senator = $this->getSenatorWithID($parameters[2]) ;
                return ($senator->office=='Censor' ? _('The Censor cannot be prosecutor') : TRUE) ;
            case 'prosecutionRejected' :
                foreach ($this->proposals as $proposal) {
                    if ($proposal->type=='Prosecutions' && $proposal->outcome==FALSE && $proposal->parameters[0]==$parameters[0] && $proposal->parameters[1]==$parameters[1]) {
                        return _('This prosecution has already been rejected');
                    }
                }
                return TRUE ;
            case 'possibleGovernors' :
                // Make a list of all possible governors
                $possibleGovernorsRaw = $this->senate_getListAvailableGovernors() ;
                $possibleGovernorsList = array() ;
                foreach($possibleGovernorsRaw as $item) {
                    array_push($possibleGovernorsList , $item['senatorID']) ;
                }
                // Check if parameters 0 modulo 3 are in the list
                foreach ($parameters as $key=>$parameter) {
                    if ( ($key % 3) == 0 ) {
                        if (!in_array($parameter , $possibleGovernorsList)) {
                            return sprintf(_('%s cannot be a governor.') , $this->getSenatorWithID($parameter)->name ) ;
                        }
                    }
                }
                return TRUE ;
            case 'possibleProvinces' :
                // Make a list of all possible provinces
                $possibleProvincesRaw = $this->senate_getListAvailableProvinces() ;
                $possibleProvincesList = array() ;
                foreach ($possibleProvincesRaw as $item) {
                    array_push($possibleProvincesList , $item['province_id']);
                }
                // Check if parameters 2 modulo 3 are in the list
                foreach ($parameters as $key=>$parameter) {
                    if ( ($key % 3) == 2 ) {
                        if (!in_array($parameter , $possibleProvincesList)) {
                            return _('Unknown province.') ;
                        }
                    }
                }
                return TRUE ;
            case 'concessionOddParameters' :
                $listAvailableConcessions = $this->senate_getListAvailableConcessions() ;
                foreach ($parameters as $key=>$parameter) {
                    $ok = FALSE ;
                    if ( ($key % 2) == 1 ) {
                        foreach($listAvailableConcessions as $availableConcession) {
                            if ($parameter == $availableConcession['id']) {
                                $ok = TRUE;
                                break ;
                            }
                        }
                        if (!$ok) {
                            return _('Unknown concession.') ;
                        }
                    }
                }
                return TRUE ;
            case 'concessionEvenParameters' :
                $atLeastOneSenator = FALSE ;
                foreach ($parameters as $key=>$parameter) {
                    if ( ($key % 2) == 0 ) {
                        if ($parameter!='NOBODY') {
                            $atLeastOneSenator = TRUE ;
                            if ($this->getSenatorWithID($parameter)!=FALSE && !$this->getSenatorWithID($parameter)->inRome()) {
                                return _('Senator is not in Rome.') ;
                            } elseif($this->getSenatorWithID($parameter)==FALSE) {
                                return _('Senator ID not recognised.') ;
                            }
                        } else {
                            // This has been removed after basic checks and should not happen, but what the hell
                            return _('Senator is a nobody.') ;
                        }
                    }
                }
                return ( $atLeastOneSenator ? TRUE : _('You must name at least one senator.') ) ;
            case 'Forces':
                $legionDetails = $this->getLegionDetails();
                $fleetDetails = $this->getFleetDetails() ;
                if ($parameters[0]>$legionDetails['totals']['recruitable']) {
                    return sprintf(_('Error - Tried to recruit %d legions, only %d available.') , $parameters[0] , $legionDetails['totals']['recruitable']);
                }
                if ($parameters[1]>(25-$fleetDetails['total'])) {
                    return sprintf(_('Error - Tried to recruit %d fleets, only %d available.') , $parameters[1] , (25-$fleetDetails['total']));
                }
                $nbUnits = $parameters[0] + $parameters[1];
                if ($parameters[2]>$legionDetails['totals']['Rome']['regular']) {
                    return sprintf(_('Error - Tried to disband %d regular legions, only %d available.') , $parameters[2] , $legionDetails['totals']['Rome']['regular']);
                }
                if ($parameters[3]>$legionDetails['totals']['Rome']['veteran']) {
                    return sprintf(_('Error - Tried to disband %d veteran legions, only %d available.') , $parameters[3] , $legionDetails['totals']['Rome']['veteran']);
                }
                if ($parameters[4]>$fleetDetails['total']) {
                    return sprintf(_('Error - Tried to disband %d fleets, only %d available.') , $parameters[4] , $fleetDetails['total']);
                }
                if ( ($this->getEventLevel('name' , 'No Recruitment')>0) && $nbUnits>0) {
                    return _('Invalid proposal. The \'No Recruitment\' event is in place.');
                }
                $specificLegions = explode(',', $parameters[5]) ;
                foreach ($specificLegions as $legionNb) {
                    if (is_numeric($legionNb)) {
                        $legion = $this->legion[$legionNb] ;
                        if ($legion->location != 'Rome' || $legion->veteran != TRUE || $legion->loyalty===null) {
                            return sprintf(_('Error with veteran legion %s.') , $legion->name);
                        }
                    }
                }
                $cost = $nbUnits * 10 * (1 + $this->getEventLevel('name', 'Manpower Shortage'));
                return ($this->treasury>=$cost ? TRUE : sprintf(_('Invalid proposal. The cost of raising those forces would be %d. There is only %d in Rome\'s treasury.') , $cost , $this->treasury ));
            case 'Deploy' :
                // Parameters : 0 = Commander SenatorID , 1 = conflict card id , 2 = (# of regular legions , # of veteran legions , list of specific legions) , 3 = # of fleets
                if (count($parameters)==0) {
                    return _('You need to pass at least one parameter');
                }
                $groupedProposals = (int) (count($parameters)/5) ;
                $commanders = array() ;
                $conflicts = array() ;
                $regulars = 0 ;
                $veterans = 0 ;
                $fleets = 0 ;
                $specificVeterans = array() ;
                $legionDetails = $this->getLegionDetails() ;
                // Various checks : conflict exists, commander exists, commander is in Rome, commander can command forces ,
                //                  number of veterans is equal or greater than number of specific veterans , no specific veteran is picked twice , enough support fleets
                for ($i=0; $i<$groupedProposals; $i++) {
                    $commanders[$i] = $this->getSenatorWithID($parameters[5*$i]) ;
                    $conflicts[$i] = $this->getSpecificCard('id' , $parameters[1+5*$i]) ;
                    if ($conflicts[$i]===FALSE) { return _('Error - Conflict doesn\'t exist.'); }
                    if ($commanders[$i]===FALSE) { return _('Error - Commander doesn\'t exist.'); }
                    if (!$commanders[$i]->inRome()) { return _('Error - Commander is not in Rome.'); }
                    if (!in_array($commanders[$i]->office , array('Dictator', 'Rome Consul' , 'Field Consul'))) { return _('Error - This Senator cannot be a Commander.'); }
                    $landForces = explode(',' , $parameters[2+5*$i]) ;
                    $regulars += $landForces[0] ;
                    $veterans += $landForces[1] ;
                    if ((count($landForces)-2)>$landForces[1]) {
                        return _('Error - more specific veteran legions picked than actual veteran legions in the proposal.') ;
                    }
                    
                    for ($j=2 ; $j<count($landForces) ; $j++) {
                        if (in_array($landForces[$j] , $specificVeterans)) {
                            return _('Error - same veteran legion picked twice.');
                        }
                        if ( ($legionDetails[$landForces[$j]]['veteran']=='NO') || ($legionDetails[$landForces[$j]]['location']!='Rome') ) {
                            return sprintf(_('Error - Veteran legion %d not found or not in Rome.') , $landForces[$j]);
                        }
                        $specificVeterans[] = $landForces[$j] ;
                    }
                    $fleets += $parameters[3+5*$i] ;
                    if ($fleets<$conflicts[$i]['card']->support) { return _('Error - Not enough support fleets.'); }
                    if ( (($regulars+$veterans) == 0) && ($fleets == 0)) { return _('Error - The commander cannot go alone.'); }
                }
                // More checks : enough regulars, enough veterans, enough fleets
                $legionsDetails = $this->getLegionDetails() ;
                $fleetsDetails = $this->getFleetDetails() ;
                if ($legionsDetails['totals']['Rome']['regular']<$regulars) { return _('Error - Not enough regular legions in Rome.'); }
                if ($legionsDetails['totals']['Rome']['veteran']<$veterans) { return _('Error - Not enough veteran legions in Rome.'); }
                if ($fleetsDetails['total']<$fleets) { return _('Error - Not enough fleets in Rome.'); }
                // TO DO : Check for minimum forces. If not applicable, set parameter[4] to TRUE, otherwise to NULL
                return TRUE ;
        }
        return 'Wrong rule';
    }
    
    /**
     * Function used by the Censor to end prosecutions
     * @param string $user_id The user_id (supposed to be the Censor)
     * @return array messages
     */
    public function senate_endProscutions($user_id) {
        $messages = array() ;
        if ($this->phase == 'Senate' && $this->subPhase == 'Prosecutions') {
            $censor = $this->senate_findOfficial('Censor') ;
            if ($censor['user_id']!=$user_id) {
                return array(array(_('ERROR - Only the Censor can end prosecutions') , 'error' , $user_id));
            } else {
                $this->censorIsDone = TRUE ;
                array_push($messages , array( sprintf(_('The Censor %s returns the floor to the Presiding Magistrate') , $censor['senator']->name) ));
                // Sub phase will turn either to 'Governors' or 'Other business'
                $messages = array_merge($messages , $this->senate_nextSubPhase()) ;
            }
        }
        return $messages ;
    }
    
    /**
     * Cast vote on current proposal
     * @param string $user_id
     * @param array $request
     */
    public function senate_vote($user_id , $request) {
        $messages = array() ;
        $ballotMessage = array();
        $ballotMessage[-1] = _('votes AGAINST the proposal');
        $ballotMessage[0] = _('ABSTAINS');
        $ballotMessage[1] = _('votes FOR the proposal');
        if ($this->phase!='Senate') {
            return array(array(_('Wrong phase.') , 'error' , $user_id));
        }
        if (end($this->proposals)->votingOrder[0]!=$user_id) {
            return array(array(_('This is not your turn to vote.') , 'error' , $user_id));
        }
        if (end($this->proposals)->outcome!==NULL) {
            return array(array(_('The vote on this proposal is already over.') , 'error' , $user_id));
        }
        $votingMessage = '';
        
        /*
         * Veto - set the outcome to FALSE and don't go through all Senators
         */
        if(isset($request['useVeto']) && $request['useVeto'] === 'YES') {
            $vetoResult = $this->senate_useVeto($user_id , $request['veto']) ;
            if ($vetoResult!==FALSE) {
                array_push($messages , array(sprintf(_('{%s} vetoes the proposal %s') , $user_id , $vetoResult)) );
                end($this->proposals)->outcome=FALSE ;
            } else {
                array_push($messages , array(_('Error when trying to veto the proposal.') , 'error') );
            }
            
        /*
         * No Veto - normal process
         */
        } else {
            foreach (end($this->proposals)->voting as $key => $voting) {
                if ($voting['user_id']==$user_id) {
                    // Mandatory popular appeal in case of Special Major Prosecution for assassination
                    if ($this->subPhase=='Assassin prosecution' && $this->assassination['assassinParty']==$user_id) {
                        array_push($messages , array(sprintf(_('This is a Special Major Prosecution for assassination : {%s} must use popular appeal, modified by the victim\'s POP : %+d') , $user_id , $this->assassination['victimPOP'])) ) ;
                        $mandatoryAppealMessages = $this->senate_appeal($user_id) ;
                        foreach ($mandatoryAppealMessages as $message) {
                            array_push($messages , $message) ;
                        }
                    }
                    // Whole party vote : set all the senators of this party to vote the same way
                    if (isset($request['wholeParty'])) {
                        end($this->proposals)->voting[$key]['ballot'] = (int)$request['wholeParty'] ;
                        $votingMessage = sprintf(_('{%s} %s') , $user_id , $ballotMessage[$request['wholeParty']]);
                    // Per senator vote : set the senator's ballot to the value given through POST & handle talents spent
                    } else {
                        // Ignore senators with 0 votes (not in Rome)
                        if (isset($request[$voting['senatorID']]) && end($this->proposals)->voting[$key]['votes']>0) {
                            end($this->proposals)->voting[$key]['ballot'] = (int)$request[$voting['senatorID']] ;
                            // The Senator has spent talents to increase his votes
                            if (isset($request[$voting['senatorID'].'_talents'])) {
                                $amount = (int)$request[$voting['senatorID'].'_talents'];
                                $senator = $this->getSenatorWithID($voting['senatorID']) ;
                                if ($senator->treasury < $amount) {
                                    return array(array(sprintf(_('%s tried to spend talents he doesn\'t have.') , $voting['name']) , 'error' , $user_id));
                                } else {
                                    $senator->treasury -= $amount ;
                                    end($this->proposals)->voting[$key]['talents'] = $amount ;
                                }
                            }
                            $votingMessage.= sprintf(_('%s of party {%s} %s') , $voting['name'] , $user_id , $ballotMessage[$request[$voting['senatorID']]]) ;
                            $votingMessage.= (end($this->proposals)->voting[$key]['talents']==0 ? '' : sprintf(_(' and spends %dT to increase his votes') , end($this->proposals)->voting[$key]['talents'])) ;
                            $votingMessage.='. ';
                        } else {
                            return array(array(sprintf(_('On a per senator vote, %s\'s vote was not set.') , $voting['name']) , 'error' , $user_id));
                        }
                    }
                }
            }
            array_push($messages , array($votingMessage)) ;
            array_shift(end($this->proposals)->votingOrder);
        }
        // Remove the voting party from the voting order, as they have now voted.
        // Vote is finished : no one left to vote, or Veto has been used (so outcome is not NULL anymore)
        if (count(end($this->proposals)->votingOrder)==0 || end($this->proposals)->outcome!==NULL) {
            // Handle normal voting process if Veto has not been used.
            if (end($this->proposals)->outcome===NULL) {
                $total = 0 ; $for = 0 ; $against = 0 ; $abstention = 0 ;
                $unanimous = TRUE ;
                $HRAO = $this->getHRAO(TRUE) ;
                foreach (end($this->proposals)->voting as $voting) {
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
                // Add/Substract popular appeal votes. Those votes are in parameters[4] for a Prosecutions and in assassination['appealResult'] for an Assassin prosecution
                if ( (end($this->proposals)->type=='Prosecutions' && end($this->proposals)->parameters[4]!=NULL && end($this->proposals)->parameters[4]!='freed' && end($this->proposals)->parameters[4]!='killed') ||
                     (end($this->proposals)->type=='Assassin prosecution' && $this->assassination['appealResult']!='freed' && $this->assassination['appealResult']!='killed')
                   ) {
                    $extraVotes = (end($this->proposals)->type=='Prosecutions' ? end($this->proposals)->parameters[4] : $this->assassination['appealResult'] ) ;
                    $total-=$extraVotes ;
                    if ($extraVotes<0) {
                        $against+= $extraVotes ;
                    } else {
                        $for+= -$extraVotes ;
                    }
                    array_push($messages , array(sprintf(_('Popular appeal %s %d votes.') , ($extraVotes<0 ? _('subtracted') : _('added')) , abs($extraVotes)))) ;
                }
                end($this->proposals)->outcome = ( $total > 0 ) ;
                $votingMessage = (end($this->proposals)->outcome ? _('The proposal is adopted') : _('The proposal is rejected')) ;
                $votingMessage .= sprintf(_(' by %d votes for, %d against, and %d abstentions.') , $for , $against , $abstention);
                array_push($messages , array($votingMessage)) ;
                if ($unanimous && end($this->proposals)->proposedBy==$HRAO['user_id'] && end($this->proposals)->outcome===FALSE) {
                    array_push($messages , array(sprintf(_('The presiding magistrate %s has been unanimously defeated.') , $HRAO['senator']->name))) ;
                    $this->subPhase = 'Unanimous defeat' ;
                }
            }
            /* 
             * Results of an unsuccesful vote (outcome is FALSE)
             * - Automatic nominations :
             * > Consuls : Only one pair of senators left
             * > Censor : Only one prior consul left
             */
            if (end($this->proposals)->outcome === FALSE) {
                if (end($this->proposals)->type=='Consuls') {
                    $possiblePairs = $this->senate_consulsPairs() ;
                    if (count($possiblePairs)==1) {
                        $senator1 = $this->getSenatorWithID($possiblePairs[0][0]) ;
                        $senator2 = $this->getSenatorWithID($possiblePairs[0][1]) ;
                        $proposal = new Proposal ;
                        $proposal->init('Consuls' , NULL , NULL , $this->party , array($possiblePairs[0][0] , $possiblePairs[0][1]) , NULL) ;
                        $proposal->outcome = TRUE ;
                        array_push($messages , array(sprintf(_('%s and %s are nominated consuls as the only available pair.') , $senator1->name , $senator2->name)) );
                    }
                } elseif (end($this->proposals)->type=='Censor') {
                    $messages = array_merge($messages , $this->senate_nextSubPhase()) ;
                } elseif (end($this->proposals)->type=='Concessions') {
                    $flippedConcessionsMessage='';
                    foreach (end($this->proposals)->parameters as $key=>$parameter) {
                        if ($key %2 == 1) {
                            foreach($this->forum->cards as $card) {
                                if ($card->type=='Concession' && $card->id == $parameter) {
                                    $card->flipped = TRUE ;
                                    $flippedConcessionsMessage .= $card->name.(($key==count(end($this->proposals)->parameters)-1 ? '.' : ', ' ) );
                                }
                            }
                        }
                    }
                    array_push ($messages , array
                        (
                            sprintf
                                (_('The following Concessions%s flipped and cannot be assigned again during this Senate phase : %s') ,
                                    (count(end($this->proposals)->parameters)==2 ? ' is' : 's are') ,
                                    $flippedConcessionsMessage
                                )
                        )
                    );
                }
            /*
             * Results of succesful votes that don't require a decision after voting
             */
            } elseif (end($this->proposals)->outcome === TRUE) {
                if (end($this->proposals)->type=='Censor') {
                    $this->senate_appointOfficial('Censor', end($this->proposals)->parameters[0]) ;
                    array_push($messages , array(sprintf(_('%s is elected Censor.') , $this->getSenatorWithID(end($this->proposals)->parameters[0])->name )) );
                    $messages = array_merge($messages , $this->senate_nextSubPhase() );
                } elseif (end($this->proposals)->type=='Prosecutions' || end($this->proposals)->type=='Assassin prosecution') {
                    $prosecutionSuccessfulMessages = $this->senate_prosecutionSuccessful($user_id) ;
                    foreach($prosecutionSuccessfulMessages as $message) {
                        array_push($messages, $message) ;
                    }
                } elseif (end($this->proposals)->type=='Concessions') {
                    foreach (end($this->proposals)->parameters as $key=>$parameter) {
                        if ($key %2 == 1) {
                            $assignedTo = $this->getSenatorWithID(end($this->proposals)->parameters[$key-1]);
                            if ($assignedTo===FALSE) {
                                return array(array(_('Assigning Concession : Error on Senator ID.') , 'error' , $user_id));
                            }
                            $concessionAssigned = $this->forum->drawCardWithValue('id' , $parameter);
                            if ($concessionAssigned===FALSE) {
                                return array(array(_('Assigning Concession : Error on Concession.') , 'error' , $user_id));
                            }
                            $assignedTo->controls->putOnTop($concessionAssigned);
                            array_push($messages , array(sprintf(_('%s is assigned to %s.') , $concessionAssigned->name, $assignedTo->name )));
                        }
                    }
                } elseif (end($this->proposals)->type=='Forces') {
                    $parameters = end($this->proposals)->parameters ;
                    $legionsToRecruit = $parameters[0] ;
                    $fleetsToRecruit = $parameters[1] ;
                    $regularsToDisband = $parameters[2] ;
                    $veteransToDisband = $parameters[3] ;
                    $fleetsToDisband = $parameters[4] ;
                    $specificLegionsToDisband = explode(',' , $parameters[5]) ;
                    // Legions
                    $i=1 ;
                    while ($i<=25) {
                        if ($legionsToRecruit>0 && $this->legion[$i]->canBeRecruited()) {
                            $this->legion[$i]->recruit() ;
                            $legionsToRecruit--;
                        }
                        if ($regularsToDisband>0 && $this->legion[$i]->canBeDisbanded() && !$this->legion[$i]->veteran) {
                            $this->legion[$i]->disband() ;
                            $regularsToDisband--;
                        }
                        if ($veteransToDisband>0 && $this->legion[$i]->canBeDisbanded() && $this->legion[$i]->veteran) {
                            $this->legion[$i]->disband() ;
                            $veteransToDisband--;
                        }
                        if (in_array($i, $specificLegionsToDisband)) {
                            $this->legion[$i]->disband() ;
                        }
                        $i++ ;
                    }
                    // Fleets
                    $i=1 ;
                    while ($i<=25) {
                        if ($fleetsToRecruit>0 && $this->fleet[$i]->canBeRecruited()) {
                            $this->fleet[$i]->recruit() ;
                            $fleetsToRecruit--;
                        }
                        if ($fleetsToDisband>0 && $this->fleet[$i]->canBeDisbanded()) {
                            $this->fleet[$i]->disband() ;
                            $fleetsToDisband--;
                        }
                        $i++ ;
                    }
                    $cost = ($parameters[0] + $parameters[1]) * 10 * (1 + $this->getEventLevel('name', 'Manpower Shortage'));
                    $this->treasury -= $cost;
                    $messages[] = array(sprintf(_('The forces are recruited & disbanded as proposed for a total cost of %d.') , $cost));
                    $armaments = $this->getSpecificCard('name', 'ARMAMENTS') ;
                    $shipBuilding = $this->getSpecificCard('name', 'SHIP BUILDING') ;
                    if ($armaments['where']=='senator') {
                        $armaments['card']->corrupt = TRUE ;
                        $armaments['senator']->treasury += 2*$legionsToRecruit ;
                        $messages[] = array(sprintf(_('%s controls the Armaments concession and gains %d from this recruitment.') , $armaments['senator']->name , 2*$legionsToRecruit));
                    }
                    if ($shipBuilding['where']=='senator') {
                        $shipBuilding['card']->corrupt = TRUE ;
                        $shipBuilding['senator']->treasury += 3*$fleetsToRecruit ;
                        $messages[] = array(sprintf(_('%s controls the Ship Building concession and gains %d from this recruitment.') , $armaments['senator']->name , 3*$fleetsToRecruit));
                    }
                } elseif (end($this->proposals)->type=='Deploy') {
                    // TO DO - here now
                    // Senator->conflict is the card ID of the conflict for which the senator is a commander (not MoH, who is automatically considered to accompany the Dictator)
                    // fleet->location or legion->location is the Senator ID of the commander
                    $parameters = end($this->proposals)->parameters ;
                    // First, check that the proposals is indeed correct
                    $validation = $this->senate_validateParameter('Deploy', $parameters) ;
                    if ($validation!==TRUE) {
                        return array(array($validation , 'error' , $user_id));
                    } else {
                        // As several deploy can be grouped, and each deploy has 5 parameters, we need to go through parameters in multiples of 5 
                        for ($i = 0 ; $i<count($parameters)/5 ; $i++) {
                            $commander = $this->getSenatorWithID($parameters[5*$i]) ;
                            $conflictAll = $this->getSpecificCard('id' , $parameters[5*$i+1]) ;
                            $listOfLegions = explode(',',$parameters[5*$i + 2]) ;
                            $specificVeteran = array() ;
                            foreach($listOfLegions as $key=>$value) {
                                switch($key) {
                                    case 0 :
                                        $nbRegulars = $value ;
                                        break ;
                                    case 1 :
                                        $nbVeterans = $value ;
                                        break ;
                                    default :
                                        $specificVeteran[] = $value ;
                                        $this->legion[$value]->location = $commander->senatorID ;
                                }
                            }
                            $nbFleets = $parameters[5*$i + 3];
                            $conflict = $conflictAll['card'] ;
                            $commander->conflict = $conflict->id ;
                            // Send the regulars
                            for ($i=1 ; $i<=25 ; $i++) {
                                if ($this->legion[$i]->location=='Rome' && $this->legion[$i]->veteran==FALSE && $nbRegulars>0) {
                                    $this->legion[$i]->location = $commander->senatorID ;
                                    $nbRegulars-- ;
                                }
                            }
                            // Send the veterans among the non-specific ones
                            for ($i=1 ; $i<=25 ; $i++) {
                                if ($this->legion[$i]->location=='Rome' && $this->legion[$i]->veteran!==FALSE && !in_array($i , $specificVeteran) && $nbVeterans>0) {
                                    $this->legion[$i]->location = $commander->senatorID ;
                                    $nbVeterans-- ;
                                }
                            }
                            // Send the fleets
                            for ($i=1 ; $i<=25 ; $i++) {
                                if ($this->fleet[$i]->location=='Rome' && $nbFleets>0) {
                                    $this->fleet[$i]->location = $commander->senatorID ;
                                    $nbFleets-- ;
                                }
                            }
                        }
                        return array(array(_('The Senate agrees on : ').$this->senate_getDeployProposalDetails($parameters)));
                    }
                }
                // TO DO : all other types
            } else {
                return array(array(_('Error on vote outcome.') , 'error' , $user_id));
            }
            // This will either stay in 'Prosecutions' or move to the next phase after 1 major or 2 minor prosecutions
            if (end($this->proposals)->type=='Prosecutions' && $this->subPhase != 'Unanimous defeat') {
                $messages = array_merge($messages , $this->senate_nextSubPhase()) ;
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
            $accused = $this ->getSenatorWithID(end($this->proposals)->parameters[0]) ;
            if (end($this->proposals)->type == 'Prosecutions' && ($this ->getPartyOfSenator($accused) -> user_id == $user_id) && end($this->proposals)->parameters[4]===NULL ) {
                $roll = $this->rollDice(2, -1) ;
                array_push($messages , array(sprintf(_('Popular Appeal : %s rolls %d%s%s.'),
                    $accused->name,
                    $roll['total'],
                    ($accused->POP!=0 ? sprintf(_(' modified by his POP (%d)') , $accused->POP) : ''),
                    $this->getEvilOmensMessage(-1) 
                ))) ;
                $modifiedResult = $roll['total'] + $accused->POP ;
                $appealEffects = $this->appealTable[max(2 , min(12 , $modifiedResult))] ;
                if ($appealEffects['special']=='freed') {
                    // The accused is freed, and mortality chits are drawn to potentially kill the prosecutor or censor
                    $chitsToDraw = 12 - $modifiedResult ;
                    array_push($messages , array ( sprintf(_('The righteous populace frees him. Since a %d was rolled, %d mortality chits are drawned to see if they kill the Prosecutor or Censor for this obvious frame-up.') , $modifiedResult , $chitsToDraw))) ;
                    $prosecutor = $this ->getSenatorWithID(end($this->proposals)->parameters[2]) ;
                    $censor = $this->getHRAO(TRUE) ;
                    $chits = $this->mortality_chits($chitsToDraw) ;
                    foreach ($chits as $chit) {
                        if ($chit!='NONE' && $chit!='DRAW 2') {
                            if ($prosecutor->senatorID == (string)$chit || $censor->senatorID == (string)$chit || $prosecutor->statesmanFamily()==(string)$chit || $censor->statesmanFamily()==(string)$chit) {
                                $returnedMessage= $this->mortality_killSenator((string)$chit , TRUE) ;
                                array_push($messages , array(sprintf(_('Chit drawn : %s. %s'), $chit , $returnedMessage[0]) , (isset($returnedMessage[1]) ? $returnedMessage[1] : NULL) ));
                            }
                        } else {
                            array_push($messages , array(sprintf(_('Chit drawn : %s'),$chit)));
                        }
                    }
                    $this->proposals[count($this->proposals)-1]->parameters[4] = 'freed' ;
                    $this->proposals[count($this->proposals)-1]->outcome = FALSE ;
                } elseif ($appealEffects['special']=='killed') {
                    array_push($messages , array(_('The disgusted populace kills him themselves'))) ;
                    array_push($messages , array($this->mortality_killSenator($accused->senatorID , TRUE))) ;
                    $this->proposals[count($this->proposals)-1]->parameters[4] = 'killed' ;
                    $this->proposals[count($this->proposals)-1]->outcome = TRUE ;
                } else {
                    $this->proposals[count($this->proposals)-1]->parameters[4] = $appealEffects['votes'] ;
                    array_push($messages , array(sprintf(_('%s %s %d votes from his popular appeal.') , $accused->name , ($appealEffects['votes']>0 ? _('gains') : _('loses')) , $appealEffects['votes'] ))) ;
                }
            }
        // Handle mandatory popular appeal during Special Major Prosecution for assassination
        } elseif ($this->phase == 'Senate' && $this->subPhase == 'Assassin prosecution') {
            $accused = $this->getSenatorWithID($this->party[$this->assassination['assassinParty']]->leaderID) ;
            $roll = $this->rollDice(2, -1) ;
            array_push($messages , array(sprintf(_('Popular Appeal : %s rolls %d%s%s.') ,
                $accused->name ,
                $roll['total'] ,
                ($this->assassination['victimPOP']!=0 ? sprintf(_(' modified by his victim\'s POP (%d)') , $this->assassination['victimPOP']) : ''),
                $this->getEvilOmensMessage(-1) 
            ))) ;
            $modifiedResult = $roll['total'] - $this->assassination['victimPOP'] ;
            $appealEffects = $this->appealTable[max(2 , min(12 , $modifiedResult))] ;
            if ($appealEffects['special']=='freed') {
                // The accused is freed, and mortality chits are drawn to potentially kill the censor
                $chitsToDraw = 12 - $modifiedResult ;
                array_push($messages , array(sprintf(_('The righteous populace frees him. Since a %d was rolled, %d mortality chits are drawned to see if they kill the Censor for this obvious frame-up.') , $modifiedResult , $chitsToDraw))) ;
                $censor = $this->senate_findOfficial('Censor')['senator'] ;
                $chits = $this->mortality_chits($chitsToDraw) ;
                foreach ($chits as $chit) {
                    if ($chit!='NONE' && $chit!='DRAW 2') {
                        if ($censor->senatorID == (string)$chit || $censor->statesmanFamily()==(string)$chit) {
                            $returnedMessage= $this->mortality_killSenator((string)$chit , TRUE) ;
                            array_push($messages , array(sprintf(_('Chit drawn : %s. %s'), $chit , $returnedMessage[0]) , (isset($returnedMessage[1]) ? $returnedMessage[1] : NULL) ));
                        }
                    } else {
                        array_push($messages , array(sprintf(_('Chit drawn : %s'),$chit)));
                    }
                }
                $this->proposals[count($this->proposals)-1]->outcome = FALSE ;
                $this->assassination['appealResult'] = 'freed' ;
            } elseif ($appealEffects['special']=='killed') {
                array_push($messages , array(_('The disgusted populace kills him themselves'))) ;
                array_push($messages , array($this->mortality_killSenator($accused->senatorID , TRUE))) ;
                $this->proposals[count($this->proposals)-1]->outcome = TRUE ;
                $this->assassination['appealResult'] = 'killed' ;
            } else {
                $this->assassination['appealResult'] = $appealEffects['votes'] ;
                array_push($messages , array(sprintf(_('%s %s %d votes from his popular appeal.' , $accused->name , ($appealEffects['votes']>0 ? _('gains') : _('loses')) , abs($appealEffects['votes']) )))) ;
            }
        }
        return $messages ;
    }

    /**
     * Proceed with the effects of a successful prosecutions : gain/loss of POP,INF,Prior Consul,Concessions, Life<br>
     * Handles both 'Prosecutions' and 'Assassin prosecution' proposal types
     * @param string $user_id
     * @return array messages
     */
    public function senate_prosecutionSuccessful($user_id) {
        $messages = array() ;
        if (end($this->proposals)->type=='Prosecutions' && end($this->proposals)->outcome==TRUE) {
            $accused = $this->getSenatorWithID(end($this->proposals)->parameters[0]) ;
            $prosecutor = $this->getSenatorWithID(end($this->proposals)->parameters[2]) ;
            $INFloss = min(5 , $accused->INF) ;
            $priorConsulMarker = $accused->priorConsul ;
            
            // This was a major prosecution
            if (end($this->proposals)->parameters[1]=='major') {
                array_push ($messages , array( sprintf(_('Major prosecution successful : %s is executed for his wrongdoings. ') , $accused->name ) ));
                array_push ($messages , $this->mortality_killSenator($accused->senatorID , TRUE) );
                $prosecutor->changeINF( (int)($INFloss/2) );
                $message2 = sprintf(_('The prosecutor %s gains %d INF.') , $prosecutor->name , $INFloss);
                if ($priorConsulMarker) {
                    $prosecutor->priorConsul = TRUE ;
                    $message2.=' As well as the accused\'s prior consul marker.';
                }
                array_push($messages , array($message2)) ;
                // Sub phase will turn either to 'Governors' or 'Other business'
                $messages = array_merge($messages , $this->senate_nextSubPhase()) ;
            // This was a minor prosecution
            } else {
                $accused->changePOP(-5) ;
                $accused->changeINF(-$INFloss) ;
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
                $prosecutor->changeINF( (int)($INFloss/2) );
                $message2 = sprintf(_('The prosecutor %s gains %d INF.') , $prosecutor->name , $INFloss);
                if ($priorConsulMarker) {
                    $prosecutor->priorConsul = TRUE ;
                    $message2.=' As well as the accused\'s prior consul marker.';
                }
                array_push($messages , array($message2)) ;
                // Sub phase will turn either to 'Governors' or 'Other business'
                $messages = array_merge($messages , $this->senate_nextSubPhase()) ;
            }
        // Case of a Special Major Prosecution for Assassination
        } elseif (end($this->proposals)->type=='Assassin prosecution' && end($this->proposals)->outcome==TRUE) {
            $accused = $this->getSenatorWithID(end($this->proposals)->parameters[0]) ;
            array_push ($messages , array( sprintf(_('Major prosecution successful : %s is executed for leading a murderous party. ') , $accused->name ) ));
            array_push ($messages , $this->mortality_killSenator($accused->senatorID , TRUE) );
            $mobJusticeMessages = $this->senate_assassinationMobJustice() ;
            foreach($mobJusticeMessages as $message) {
                array_push($messages , $message) ;
            }
            unset($this->assassination);
            $messages = array_merge($messages , $this->senate_nextSubPhase()) ;
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
                array_push ($messages , array(sprintf(_('%s steps down as presiding magistrate after his unanimous defeat.') , $HRAO['senator']->name )) );
            }
            // If the Censor steps down, move to Governors election.
            // TO DO : what if the latest proposal was interrupted by an assassin prosecution ? (and therefore the test below doesn't work)
            if ($stepDown==1 && end($this->proposals)->type == 'Prosecutions') {
                $this->censorIsDone = TRUE ;
                array_push($messages , array(_('With the Censor stepping down, the Prosecution phase ends.')) );
            }
            // Set the subphase back to where it belongs based on the latest proposal
            $messages = array_merge($messages , $this->senate_nextSubPhase()) ;
        } else {
            return array(array(_('You are not presiding magistrate, hence cannot step down.') , 'error' , $user_id));
        }
        return $messages ;
    }

    /**
     * Sets the subPhase to what it should be based on the latest successful proposal, available candidates, possibl dictators, available provinces, etc
     * @return array messages
     */    
    private function senate_nextSubPhase() {
        $messages = array() ;
        // If the subPhase is not standard, determine the current subphase based on the latest proposal
        if ($this->subPhase == 'Unanimous defeat' || $this->subPhase == 'Assassin prosecution' || $this->subPhase =='Consul for life') {
            foreach ($this->proposals as $latestProposal) {
                if ($latestProposal->type!='Assassin prosecution' && $latestProposal->type!='Consul for life') {
                    $this->subPhase = $latestProposal->type ;
                }
            }
        }
        $previousSubPhase = $this->subPhase ;
        switch ($this->subPhase) {
            case 'Consuls' :
                // Check if Consuls have been elected and have chosen who is who, then move to Pontifex
                foreach ($this->proposals as $proposal) {
                    if ($proposal->type=='Consuls' && $proposal->outcome==TRUE && $proposal->parameters[2]!=NULL && $proposal->parameters[3]!=NULL) {
                        $this->subPhase = 'Pontifex Maximus' ;
                    }
                }
                break ;
            case 'Censor' :
                break ;
            case 'Dictator' :
                // Check if a Dictator has been appointed/elected and has chosen his MoH, then move to Censor
                foreach ($this->proposals as $proposal) {
                    if ($proposal->type=='Dictator' && $proposal->outcome==TRUE && $proposal->parameters[2]!=NULL) {
                        $this->subPhase = 'Censor';
                    }
                }
                break ;
            // Prosecutions are finished if : there was 2 minor, 1 major or the Censor has declared he was done
            // Once prosecutions are finished, move to Governors if there is at least one available province, otherwise move to Other business.
            // Also, even if we had a successful 'Governors' proposal, we need to test if some provinces are still available, though in that case the prosecutions test is actually superfluous
            case 'Prosecutions' :
            case 'Governors' :
                $finishedProsecutions=$this->senate_getFinishedProsecutions();
                if ($finishedProsecutions['minor']==2 || $finishedProsecutions['major']==1 || $this->censorIsDone) {
                    if (count($this->senate_getListAvailableProvinces()) >0) {
                        $this->subPhase='Governors' ;
                    } else {
                        $this->subPhase='Other business' ;
                    }
                }
                break ;
            // For any proposal of the type below, the subPhase should be 'Other business', as they can be proposed in any order
            case 'Concessions' :
            case 'Land Bills' :
            case 'Forces' :
            case 'Garrison' :
            case 'Deploy' :
            case 'Recall' :
            case 'Reinforce' :
            case 'Recall Pontifex' :
            case 'Priests' :
            case 'Minor' :
            case 'Other business' :
                $this->subPhase = 'Other business' ;
                break ;
        }
        if ($this->subPhase=='Pontifex Maximus') {
            $this->subPhase='Dictator';
            // TO DO : Change this once Pontifex variant is implemented
        }
        // Handles possibility of Dictator appointment
        if ($this->subPhase=='Dictator') {
            $dictatorFlag = $this->senate_getDictatorFlag();
            if (count($dictatorFlag)==0) {
                $messages[] = array(_('A dictator cannot be appointed or elected. Moving on to Censor election.'));
                $this->subPhase='Censor';
            // A dictator can be appointed/elected. Set each party's "bidDone" to FALSE to give them all a chance to do so.
            } else {
                foreach ($dictatorFlag as $flag) {
                    $messages[] = array($flag) ;
                }
                $messages[] = array(_('A dictator can be appointed or elected.')) ;
                foreach($this->party as $party) {
                    $party->bidDone = FALSE ;
                }
            }
        }
        // Handles automatic Censor appointment
        if ($this->subPhase=='Censor') {
            $candidates = $this->senate_possibleCensors() ;
            // Only one eligible candidate : appoint him and move to prosecution phase
            if (count($candidates)==1) {
                $this->senate_appointOfficial('Censor', $candidates[0]->senatorID) ;
                // Automatically put this proposal in the array of proposals
                $proposal = new Proposal ;
                $proposal->init('Censor' , NULL , NULL , $this->party , array($candidates[0]->senatorID) , NULL) ;
                $proposal->outcome = TRUE ;
                $this->proposals[] = $proposal ;
                $this->subPhase='Prosecutions';
                $messages[] = array(sprintf(_('Only %s is eligible for Censorship, so he is automatically elected. The Senate sub Phase is now : Prosecutions.') , $candidates[0]->name) );
            }
        }
        // Only give a message about a new subPhase if it has actually changed
        if ($previousSubPhase != $this->subPhase) {
            $messages[] = array(sprintf(_('The senate sub phase is now : %s') , $this->subPhase ) , 'alert') ;
        }
        return $messages ;
    }
    /**
     * A decision (not a vote) has been made on a proposal. Some decisions occur before the outcome of the proposal, some after :<br>
     * - Consuls deciding who will do what<br>
     * - Consuls appoiting a Dictator<br>
     * - Prosecutor accepting/refusing nomination (that's before the vote)<br>
     * - Governors decision : returning governors agreeing to go again on the same turn (that's before the vote)
     * - Dictator appointing MoH<br>
     * @param string $user_id user_id
     * @param array $request the POST data
     * @return array message array
     */
    public function senate_decision($user_id , $request) {
        $messages = array() ;
        /*
         * Consuls decision
         */
        if ($this->phase=='Senate' && $this->subPhase=='Consuls' && end($this->proposals)->outcome===TRUE && $request['type']=='consuls') {
            for ($i=0 ; $i<2 ; $i++) {
                $senator[$i] = $this->getSenatorWithID(end($this->proposals)->parameters[$i]) ;
                $party[$i] = $this->getPartyOfSenator($senator[$i])->user_id;
                $choice[$i] = (isset($request[end($this->proposals)->parameters[$i]]) ? $request[end($this->proposals)->parameters[$i]] : FALSE) ;
            }
            if ($party[0]!=$user_id && $party[1]!=$user_id) {
                return array(array(_('You cannot take such a decision at this moment.')  , 'error' , $user_id));
            }
            $disagreement = FALSE ;
            for ($i=0 ; $i<2 ; $i++) {
                // The player has made a choice for this senator
                if ($party[$i]==$user_id && $choice[$i]!==FALSE) {
                    if ( ($choice[$i]=='ROME' && end($this->proposals)->parameters[2]!==NULL) || ($choice[$i]=='FIELD' && end($this->proposals)->parameters[3]!==NULL) ) {
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
                end($this->proposals)->parameters[($roll<=3 ? 2 : 3)] = end($this->proposals)->parameters[0];
                end($this->proposals)->parameters[($roll<=3 ? 3 : 2)] = end($this->proposals)->parameters[1];
                array_push($messages , array(_('As the newly elected pair couldn\'t agree, the senators are randomely appointed.')));
                $romeConsul = $this->senate_appointOfficial('Rome Consul' , end($this->proposals)->parameters[2]);
                $fieldConsul = $this->senate_appointOfficial('Field Consul' , end($this->proposals)->parameters[3]);
                array_push($messages , array(sprintf(_('%s becomes Rome Consul, and %s becomes Field Consul') , $romeConsul->name , $fieldConsul->name)));
            // Both consuls picked
            } elseif (end($this->proposals)->parameters[2]!==NULL && end($this->proposals)->parameters[3]!==NULL) {
                $romeConsul = $this->senate_appointOfficial('Rome Consul' , end($this->proposals)->parameters[2]);
                $fieldConsul = $this->senate_appointOfficial('Field Consul' , end($this->proposals)->parameters[3]);
                array_push($messages , array(sprintf(_('%s becomes Rome Consul, and %s becomes Field Consul') , $romeConsul->name , $fieldConsul->name)));
            // One consul hasn't been picked yet
            } else {
                array_push($messages , array(_('Now waiting for the other elected consul to pick a position.') , 'message' , $user_id ));
            }
            // Consuls have been elected : move on to next subPhase (Pontifex, Dictator, or Censor)
            if (end($this->proposals)->parameters[2]!==NULL && end($this->proposals)->parameters[3]!==NULL) {
                $messages = array_merge($messages , $this->senate_nextSubPhase()) ;
            }
        /*
         * Appoint dictator decision (A consul suggested to appoint a Senator as Dictator, and the other accepts/refuses)
         */
        } elseif ($this->phase=='Senate' && $this->subPhase=='Dictator' && end($this->proposals)->outcome===NULL && $request['type']=='AppointDictator') {
            if ($request['accept']=='YES') {
                end($this->proposals)->outcome = TRUE ;
                $this->senate_appointOfficial('Dictator', end($this->proposals)->parameters[0]) ;
                array_push($messages , array(sprintf(_('The Consuls appoint %s {%s} as a Dictator.') , $this->getSenatorWithID(end($this->proposals)->parameters[0])->name , $this->getPartyOfSenatorWithID(end($this->proposals)->parameters[0])->user_id )) );
            } else {
                array_push($messages , array(_('The Consuls decide not to appoint a dictator.'))) ;
                end($this->proposals)->outcome=FALSE ;
            }
        /*
         * Prosecutions decision (prosecutor accepting/refusing appointment)
         */
        } elseif ($this->phase=='Senate' && $this->subPhase=='Prosecutions' && end($this->proposals)->outcome===NULL && $request['type']=='prosecutions') {
            $prosecutor = $this->getSenatorWithID(end($this->proposals)->parameters[2]) ;
            $prosecutorParty = $this->getPartyOfSenator($prosecutor)->user_id ;
            if ($prosecutorParty == $user_id) {
                if ($request['accept']=='YES') {
                    $this->proposals[count($this->proposals)-1]->parameters[3] = TRUE ;
                    array_push($messages , array(sprintf(_('%s accepts to be prosecutor.') , $prosecutor->name))) ;
                } else {
                    // Since this proposal was never actually put forward, simply discard it
                    unset ($this->proposals[count($this->proposals)-1]) ;
                    return array(array(sprintf(_('The appointed prosecutor %s chickens out.') , $prosecutor->name)));
                }
            } else {
                return array(array(_('You cannot take such a decision at this moment.')  , 'error' , $user_id));
            }
        /*
         * Governors decision (returning governors agreeing to go again on the same turn)
         */
        } elseif ($this->phase=='Senate' && $this->subPhase=='Governors' && end($this->proposals)->outcome===NULL && $request['type']=='governors') {
            if ($request['accept']=='YES') {
                // Set all this user_id's SenatorAccepts to TRUE
                foreach (end($this->proposals)->parameters as $key => $parameter) {
                    if ($key % 3 == 0) {
                        if ($this->getPartyOfSenatorWithID($parameter)->user_id == $user_id) {
                            end($this->proposals)->parameters[$key+1] = TRUE ;
                        }
                    }
                }
                return array(array(sprintf(_('{%s} agrees that his returning governor(s) go to a province again this turn.') , $user_id)));
            } else {
                // Since this proposal was never actually put forward, simply discard it
                unset ($this->proposals[count($this->proposals)-1]) ;
                return array(array(sprintf(_('{%s} doesn\'t want his return governor(s) to go to a province again this turn. The proposal is discarded.') , $user_id)));
            }
        /*
         * Deploy decision (commander accepting/refusing to be deployed with unsufficient forces)
         */
        } elseif ($this->phase=='Senate' && $this->subPhase=='Other business' && end($this->proposals)->outcome===NULL && $request['type']=='deploy') {
            if ($request['accept']=='YES') {
                // Set all this user_id's Consent to TRUE
                foreach (end($this->proposals)->parameters as $key => $parameter) {
                    if ($key % 5 == 4) {
                        if ($this->getPartyOfSenatorWithID($parameter)->user_id == $user_id) {
                            end($this->proposals)->parameters[$key] = TRUE ;
                        }
                    }
                }
                return array(array(sprintf(_('{%s} agrees for his commander(s) to be deployed with insufficient forces.') , $user_id)));
            } else {
                // Since this proposal was never actually put forward, simply discard it
                unset ($this->proposals[count($this->proposals)-1]) ;
                return array(array(sprintf(_('{%s} doesn\'t want his commander(s) to be deployed with unsufficient forces. The proposal is discarded.') , $user_id)));
            }
        /*
         * Master of Horse appointment
         */
        } elseif ($this->phase=='Senate' && $this->subPhase=='Dictator' && end($this->proposals)!==FALSE && end($this->proposals)->outcome===TRUE && end($this->proposals)->parameters[2]===NULL && $request['type']=='masterOfHorse') {
            $masterOfHorse = $this->getSenatorWithID($request['senator']) ;
            if ($masterOfHorse!==FALSE) {
                $this->senate_appointOfficial('Master of Horse', $masterOfHorse->senatorID) ;
                end($this->proposals)->parameters[2] = $masterOfHorse->senatorID ;
                $messages[] = array(sprintf(_('The dictator appoints %s (%s) as Master of Horse.') , $masterOfHorse->name , $this->getPartyOfSenator($masterOfHorse)->fullName()));
            } else {
                return array(array(_('Error retrieving information on the senator appointed Master of Horse.')  , 'error' , $user_id));
            }
        } else {
            return array(array(_('Error on decision.')  , 'error' , $user_id));
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
     * @return array message
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
     * This function returns an array indicating all the ways user_id can currently make a proposal ('president' , 'tribune card' , 'free tribune' , 'Dictator appointed by Consuls')
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
        // Dictator appointment :Short-circuit the function if Consuls haven't decided to appoint a dictator, they must have the chance to do so, and nothing else until they decide to do it or not
        if ($this->subPhase=='Dictator') {
            // Has there been a Dictator proposal already ?
            // The Dictator proposal with parameters[1]=TRUE will ALWAYS be the appointment proposed by one of the Consuls, and its outcome will ALWAYS be determined by the other consul accepting/refusing
            $canAppoint = TRUE ;
            foreach ($this->proposals as $proposal) {
                if ($proposal->type=='Dictator' && $proposal->parameters[1]==TRUE && $proposal->outcome==NULL) {
                    $canAppoint = FALSE ;
                }
            }
            if ($canAppoint && ( $this->senate_findOfficial('Rome Consul')['user_id'] == $user_id || $this->senate_findOfficial('Field Consul')['user_id'] == $user_id ) ) {
                return array('Dictator appointed by Consuls');
            } elseif ($canAppoint) {
                // At this stage, no one else can propose anything : Consuls must decide first
                return array('Waiting for Consuls to appoint Dictator');
            }
            // Note : we will get out of here unsacthed it $canAppoint is FALSE, so normal "canMakeProposal" results can be obtained for Dictator proposals.
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
     * Return an array of the possible vetos for the user<br>
     * Vetos are impossible for : Consul for Life , Special prosecution of assassins , any proposal by a Dictator
     * @param string $user_id The user
     * @return array 'Tribune Card'|free Tribune array ('senatorID' , 'name')
     */
    public function senate_getVetoList($user_id) {
        $result=array() ;
        if (end($this->proposals)->outcome==NULL) {
            if ( end($this->proposals)->type!='Consul for life' && end($this->proposals)->type!='Assassin prosecution' && $this->getHRAO(TRUE)['senator']->office!='Dictator' ) {
                foreach ($this->party[$user_id]->freeTribunes as $freeTribune) {
                    $result[] = $freeTribune;
                }
                foreach ($this->party[$user_id]->hand->cards as $card) {
                    if ($card->name == 'TRIBUNE') {
                        $result[] = 'Tribune card';
                    }
                }
            }
        }
        return $result ;
    }
    
    /**
     * Uses a Veto in a vote.<br>
     * The POST data is either 'Card' (Tribune card) or a SenatorID (Statesman with a free tribune)
     * @param string $user_id The user_id of the player playing the Veto
     * @param array $vetoRequest POST data
     * @return string message or FALSE if it failed
     */
    public function senate_useVeto($user_id , $vetoRequest) {
        if ($vetoRequest=='Card') {
            $tribuneCard = $this->party[$user_id]->hand->drawCardWithValue('name' , 'TRIBUNE') ;
            if ($tribuneCard !==FALSE) {
                $this->discard->putOnTop($tribuneCard);
                return _('using a Tribune card') ;
            } else {
                return FALSE ;
            }
        } else {
            foreach ($this->party[$user_id]->freeTribunes as $key=>$value) {
                if ($value['senatorID'] == $vetoRequest) {
                    unset ($this->party[$user_id]->freeTribunes[$key]) ;
                    return sprintf(_('using the Tribune ability of %s') , $value['name']);
                }
            }
            return FALSE ;
        }
        return FALSE ;
    }
    
    /**
     * Checks if this player hasn't make an assassination attempt this turn, then triggers the assassination process
     * @param type $user_id The player
     * @return array Messages
     */
    public function senate_assassination($user_id) {
        $messages = array() ;
        if ($this->party[$user_id]->assassinationAttempt) {
            return array(array(_('You can only make one assassination attempt per turn.' , 'error' , $user_id))) ;
        }
        unset ($this->assassination) ;
        $this->assassination = array('assassinID' => NULL , 'assassinParty' => $user_id , 'victimID' => NULL , 'victimPOP' => 0 , 'victimParty' => NULL , 'roll' => NULL , 'assassinCards' => NULL , 'victimCards' => array() , 'appealResult' => 0) ;
        $this->subPhase = 'Assassination' ;
        return $messages ;
    }

    /**
     * Function processing the target, assassin, and card played at the beginning of an assassination attempt<br>
     * Rolls the die and sets $this->assassination properly
     * @param string $user_id The assassinating player
     * @param string $target The senator ID of the target
     * @param string $assassin The senator ID of the assassin
     * @param string $card The card ID of the assassin card played or 'NONE'
     * @return array Messages
     */
    public function senate_chooseAssassin($user_id , $target , $assassin , $card) {
        $messages = array() ;
        if ($this->phase=='Senate' && $this->subPhase == 'Assassination' && $user_id==$this->assassination['assassinParty']) {
            // Heavy-handed validation
            if ($this->party[$user_id]->assassinationAttempt) {
                return array(array(_('You can only make one assassination attempt per turn.') , 'error' , $user_id)) ;
            }
            $error1 = TRUE ;
            foreach ($this->senate_getListAssassinationTargets($user_id) as $potentialTarget) {
                if ($potentialTarget['senatorID']==$target) {
                    $error1=FALSE ;
                }
            }
            $error2 = TRUE ;
            foreach ($this->senate_getListPotentialAssassins($user_id) as $potentialAssassin) {
                if ($potentialAssassin['senatorID']==$assassin) {
                    $error2=FALSE ;
                }
            }
            if ($card!='NONE') {
                $error3 = TRUE ;
                foreach ($this->senate_getListAssassinationCards($user_id , 'ASSASSIN') as $possibleCard) {
                    if ($possibleCard['id']==$card) {
                        $error3=FALSE ;
                    }
                }
            } else {
                $error3=FALSE ;
            }
            if ($error1) {
                array_push($messages , array(_('Wrong assassination target.') , 'error' , $user_id)) ;
                $messages = array_merge($messages , $this->senate_nextSubPhase()) ;
                return $messages ;
            }
            if ($error2) {
                array_push($messages , array(_('Wrong assassin.') , 'error' , $user_id)) ;
                $messages = array_merge($messages , $this->senate_nextSubPhase()) ;
                return $messages ;
            }
            if ($error3) {
                array_push($messages , array(_('Wrong assassination card.') , 'error' , $user_id)) ;
                $messages = array_merge($messages , $this->senate_nextSubPhase()) ;
                return $messages ;
            }
            $this->assassination['assassinID'] = $assassin ;
            $this->assassination['victimID'] = $target ;
            $this->assassination['victimPOP'] = $this->getSenatorWithID($target)->POP ;
            $this->assassination['victimParty'] = $this->getPartyOfSenatorWithID($target)->user_id ;
            $this->party[$user_id]->assassinationAttempt = TRUE;
            $this->party[$this->assassination['victimParty']]->assassinationTarget = TRUE;
            $assassinSenator = $this->getSenatorWithID($assassin) ;
            $victimSenator =  $this->getSenatorWithID($target) ;
            $assassinationMessage = sprintf(_('%s ({%s}) makes an assassination attempt on %s ({%s})') , $assassinSenator->name , $user_id , $victimSenator->name , $this->assassination['victimParty']) ;
            if ($card!='NONE') {
                $this->assassination['assassinCards'] = $card ;
                $assassinationCard = $this->party[$user_id]->hand->drawCardWithValue('id' , $card) ;
                $assassinationMessage.= sprintf(_(' and plays %s from his hand.') , $assassinationCard->name );
                $this->discard->putOnTop($assassinationCard) ;
            }
            array_push($messages , array( $assassinationMessage , 'alert')) ;
            $roll = $this->rollOneDie(-1) ;
            $this->assassination['roll'] = $roll + ($this->assassination['assassinCards']===NULL ? 0 : 1 ) ;
            $rollMessage =  sprintf(_('He rolls a %d%s%s.') , 
                                $roll ,
                                $this->getEvilOmensMessage(-1) ,
                                ($this->assassination['assassinCards']===NULL ? '' : _(' +1 by playing an assassin card') ) 
                            ) ;
            $caughtMessages = FALSE ;
            // Killed
            if ($this->assassination['roll']>=5) {
                $rollMessage.=sprintf(_(' With a total of %d, the target would be killed, but {%s} now has a chance to play bodyguards.') , $this->assassination['roll'] , $this->assassination['victimParty']);
            // Caught
            } elseif ($this->assassination['roll']<=2) {
                $rollMessage.=sprintf(_(' With a total of %d, the assassin is caught & killed.') , $this->assassination['roll']);
                $caughtMessages = $this->senate_assassinCaught() ;
            // No effect
            } else {
                $rollMessage.=sprintf(_(' With a total of %d, there should be no effect, but {%s} now has a chance to play bodyguards.') , $this->assassination['roll'] , $this->assassination['victimParty']);
            }
            array_push($messages , array($rollMessage)) ;
            // Only push 'caught' messages if they exist (the result was 'caught')
            if ($caughtMessages!==FALSE) {
                foreach($caughtMessages as $message) {
                    array_push($messages , $message) ;
                }
            }
        }
        return $messages ;
    }
    
    /**
     * Function that handles a caught assassin : special major prosecution or not (if he was leader or not) & mob justice
     * @return array Messages
     */
    private function senate_assassinCaught() {
        $messages = array() ;
        $leaderOfAssassinParty = $this->getSenatorWithID($this->party[$this->assassination['assassinParty']]->leaderID) ;
        array_push($messages , $this->mortality_killSenator($this->assassination['assassinID'], TRUE)) ;
        // If the assassin was faction leader, don't prosecute the new leader, but still draw chits based on POP of victim using senate_assassinationMobJustice()
        if ($leaderOfAssassinParty->senatorID == $this->assassination['assassinID']) {
            array_push($messages , array(_('Since the leader was the assassin, there is no special major prosecution.'))) ;
            // Mob justice : draw mortality chits based on POP of victim
            $mobJusticeMessages = $this->senate_assassinationMobJustice() ;
            foreach($mobJusticeMessages as $message) {
                array_push($messages , $message) ;
            }
            unset($this->assassination);
            // TO DO : Eliminate player if party leader was alone.
            $messages = array_merge($messages , $this->senate_nextSubPhase()) ;
        } else {
            $leaderOfAssassinParty->changeINF(-5) ;
            sprintf(_('%s, leader of the assassin\'s party loses 5 INF (now %d) and is immediately subject to a special major prosecution.') , $leaderOfAssassinParty->name , $leaderOfAssassinParty->INF);
            $proposal = new Proposal ;
            // DON'T forget to reset assassination to NULL after a specialprosecution
            $proposal->init('Assassin prosecution' , NULL , NULL , $this->party , array($this->assassination['assassinParty'])  , NULL ) ;
            $this->proposals[] = $proposal ;
            $this->subPhase = 'Assassin prosecution' ;
        }
        return $messages ;
    }

    /**
     * This function draws chits based on the assassination's victim POP, targetting the assassin's party senators.
     * @return array Messages
     */
    private function senate_assassinationMobJustice() {
        $messages = array() ;
        $chitsToDraw = max (0 , $this->assassination['victimPOP']) ;
        if ($chitsToDraw>0) {
            array_push($messages , array(sprintf(_('The victim had %d POP, so %d mortality chits are drawn against the assassin\'s party.') , $this->assassination['victimPOP'] , $chitsToDraw )));
            $chits = $this->mortality_chits($chitsToDraw) ;
            foreach ($chits as $chit) {
                if ($chit!='NONE' && $chit!='DRAW 2') {
                    $returnedMessage= $this->mortality_killSenator((string)$chit , FALSE , $this->assassination['assassinParty']) ;
                    array_push( $messages , array(sprintf(_('Chit drawn : %s. ') , $chit).$returnedMessage[0] , (isset($returnedMessage[1]) ? $returnedMessage[1] : NULL) ));
                } else {
                    array_push( $messages , array(sprintf(_('Chit drawn : %s') , $chit)) );
                }
            }
        } else {
            array_push($messages , array(sprintf(_('The victim had %d POP, so no mortality chits are drawn against the assassin\'s party.') , $this->assassination['victimPOP'] )));
        }
        return $messages ;
    }
    
    /**
     * Gives a chance to the target of an assassination to play bodyguards cards<br>
     * Then resolves the assassination<br>
     * @param string $user_id The user_id of theplayer playing body guard card(s)
     * @param array $cards An array of cards IDs or 'NONE'
     * @return array Messages
     */
    public function senate_playBodyguards($user_id , $cards) {
        $messages = array() ;
        if ($this->phase=='Senate' && $this->subPhase == 'Assassination' && $user_id==$this->assassination['victimParty']) {
            $nbOfBodyGuards = 0 ;
            // No cards played (couldn't or wouldn't)
            if ($cards === 'NONE' || count($cards)==0 ) {
                array_push( $messages , array(sprintf(_('{%s} doesn\'t play any bodyguard card.') , $user_id)) );
            // Cards played    
            } else {
                foreach ($cards as $card) {
                    // Play the card with ID : $card
                    if ($card!==NULL) {
                        $bodyGuardCard = $this->party[$user_id]->hand->drawCardWithValue('id' , $card) ;
                        if ($bodyGuardCard !== FALSE) {
                            $nbOfBodyGuards++;
                            array_push( $messages , array(sprintf(_('{%s} plays %s.') , $user_id , $bodyGuardCard->name )) );
                            $this->discard->putOnTop($bodyGuardCard) ;
                        } else {
                            array_push($messages , array(sprintf(_('Tried to play card %d, which is not a valid bodyguard card') , $card) , 'error' , $user_id ));
                        }
                    }
                }
            }
            // Then handle the modified roll result
            $this->assassination['roll']-=$nbOfBodyGuards ;
            $caught = FALSE ;
            if ($this->assassination['roll']>=5) {
                array_push($messages , array( sprintf(_('With a modified total of %d, the target is killed.') , $this->assassination['roll']) ));
                array_push($messages , $this->mortality_killSenator($this->assassination['victimID'], TRUE)) ;
            } elseif ($this->assassination['roll']<=2) {
                array_push($messages , array(sprintf(_('With a modified total of %d, the assassin is caught & killed.') , $this->assassination['roll']) ));
                $caught = TRUE ;
            } else {
                array_push($messages , array(sprintf(_('With a modified total of %d, there is no effect.') , $this->assassination['roll'])));
            }
            // See if body guards catch an assassin following a 'killed' or 'no effect' result
            if ($nbOfBodyGuards>0 && ($this->assassination['roll']>=5 || $this->assassination['roll']<=2) ) {
                for ($bodyGuardNumber = 1 ; $bodyGuardNumber <= $nbOfBodyGuards ; $bodyGuardNumber++) {
                    $bodyGuardRoll = $this->rollOneDie(-1) ;
                    $bodyGuardRoll += ($this->assassination['assassinCards']===NULL ? 0 : 1 )  - $nbOfBodyGuards;
                    array_push($messages , array(
                        sprintf(_('{%s} rolls %d for bodyguard number %d %s%s -%d for body guards.') , 
                            $user_id ,
                            $bodyGuardRoll ,
                            $bodyGuardNumber ,
                            $this->getEvilOmensMessage(-1) ,
                            ($this->assassination['assassinCards']===NULL ? '' : _(' +1 for the assassin card,') ) ,
                            $nbOfBodyGuards
                        )
                    ));
                    if ($bodyGuardRoll<=2) {
                        $caught = TRUE ;
                        break ;
                    }
                }
            }
            // Only push 'caught' messages if they exist (the result was 'caught')
            if ($caught) {
                array_push($messages , array(sprintf(_('The assassin is caught & killed.')) ));
                $caughtMessages = $this->senate_assassinCaught() ;
                foreach($caughtMessages as $message) {
                    array_push($messages , $message) ;
                }
            // Return to a normal Senate phase after the bloodshed
            } else {
                unset($this->assassination);
                $messages = array_merge($messages , $this->senate_nextSubPhase()) ;
            }
        }
        return $messages ;
    }

    /*
     * Adjourns the Senate :
     * - Sets subPhase to 'Adjourn'
     * - Sets all parties (but the President's) bidDone to FALSE if they have a tribune
     */
    public function senate_adjourn($user_id) {
        $messages = array() ;
        // Only the President can adjourn the senate
        if ($this->getHRAO(TRUE)['user_id']==$user_id) {
            $this->senateAdjourned = TRUE;
            // Set bidDone to FALSE for those parties with the ability to keep it open with a Tribune
            foreach ($this->party as $party) {
                $party->bidDone = ( (count($this->senate_canMakeProposal($user_id))==0) ? TRUE : FALSE ) ;
            }
            $this->party[$user_id]->bidDone = TRUE ;
            $messages[] = array(_('The President has adjourned the senate. Other parties have a chance to keep it open by playing a tribune.'),'alert') ;
        } else {
            $messages[] = array(_('Only the President can adjourn the Senate') , 'error' , $user_id) ;
        }
        return $messages ;
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
                // Major prosecutions (held an office)
                if ($senator->major) {
                    // Cannot have a major prosecution if there already was a minor prosecution
                    if ($nbProsecutions['minor']==0) {
                        $result[] = array('senator' => $senator , 'reason' => 'major' , 'text' => sprintf(_('MAJOR prosecution - %s (%s) for holding an office') , $senator->name , $party->fullname())) ;
                    }
                    $result[] = array('senator' => $senator , 'reason' => 'minor' , 'text' => sprintf(_('Minor prosecution - %s (%s) for holding an office') , $senator->name , $party->fullname())) ;
                }
                // Minor prosecutions (took provincial spoils)
                if ($senator->corrupt) {
                    $alreadyprosecuted = FALSE ;
                    foreach ($this->proposals as $proposal) {
                        if ($proposal->type=='Prosecutions' && $proposal->outcome!==NULL && $proposal->parameters[0]==$senator->senatorID && $proposal->parameters[1]=='province' ) {
                            $alreadyprosecuted = TRUE ;
                        }
                    }
                    if (!$alreadyprosecuted) {
                        $result[] = array('senator' => $senator , 'reason' => 'province' , 'text' => sprintf(_('Minor prosecution - %s (%s) for governing a province') , $senator->name , $party->fullname())) ;
                    }
                }
                // Minor prosecutions (profited from a concession)
                foreach ($senator->controls->cards as $card) {
                    if ($card->type=='Concession' && $card->corrupt) {
                        $alreadyprosecuted = FALSE ;
                        foreach ($this->proposals as $proposal) {
                            if ($proposal->type=='Prosecutions' && $proposal->outcome!==NULL && $proposal->parameters[0]==$senator->senatorID && $proposal->parameters[1]==$card->id ) {
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
     * Returns an array of senators satisfying a criteria
     * @param filter 'prosecutor' , 'concession' , 'landBillSponsor' , 'possibleDictators' , 'possibleMastersOfHorse'
     * @return array 'senatorID' , 'name' , 'user_id' , optional : 'POP' (only for 'landBillSponsor') , 'MIL' (only for 'commanders') , 'office' (only for 'commanders')
     */
    
    public function senate_getFilteredListSenators($filter) {
        // TO DO : Integrate senate_getListAssassinationTargets($user_id) functions
        $result = array() ;
        foreach ($this->party as $party) {
            foreach ($party->senators->cards as $senator) {
                switch($filter) {
                    case 'prosecutor' :
                        if ($senator->inRome() && $senator->office!='Censor') {
                            $result[] = array('senatorID' => $senator->senatorID , 'name' => $senator->name , 'party_name' => $party->fullName(), 'user_id' => $party->user_id) ;
                        }
                        break;
                    case 'concession' :
                        if ($senator->inRome()) {
                            $result[] = array('senatorID' => $senator->senatorID , 'name' => $senator->name , 'party_name' => $party->fullName() , 'user_id' => $party->user_id) ;
                        }
                        break;
                    case 'landBillSponsor' :
                        if ($senator->inRome()) {
                            $result[] = array('senatorID' => $senator->senatorID , 'name' => $senator->name , 'party_name' => $party->fullName() , 'user_id' => $party->user_id , 'POP' => $senator->POP ) ;
                        }
                        break;
                    case 'possibleDictators' :
                    case 'possibleMastersOfHorse' :
                        if ($senator->inRome() && ($senator->office===NULL || $senator->office==='Censor')) {
                           $result[] = array('senatorID' => $senator->senatorID , 'name' => $senator->name , 'party_name' => $party->fullName() , 'user_id' => $party->user_id) ;
                        }
                        break ;
                    case 'commanders' :
                        if (in_array($senator->office , array('Rome Consul' , 'Field Consul' , 'Dictator'))) {
                            $result[] = array('senatorID' => $senator->senatorID , 'name' => $senator->name , 'party_name' => $party->fullName() , 'user_id' => $party->user_id , 'office' => $senator->office , 'MIL' => $senator->MIL ) ;
                        }
                        break ;
                    default :
                        break;
                }
            }
        }
        return $result ;
    }

    /**
     * Returns information on available provinces as an array of arrays<br>
     * 
     * Each element array is of the form : 'province_name' , 'province_id' , 'senator_name' , 'senator_id' , 'user_id'<br>
     * - 'senator_name' , 'senator_id' , 'user_id' : can be NULL<br>
     * - 'user_id' : can be 'forum'<br>
     * @return array an array of arrays. Empty if no province
     */
    public function senate_getListAvailableProvinces() {
        $result = array() ;
        foreach ($this->party as $party) {
            foreach ($party->senators->cards as $senator) {
                foreach ($senator->controls->cards as $card) {
                    if ($card->type == 'Province') {
                        array_push($result , array ('province_name' => $card->name , 'province_id' => $card->id , 'senator_name' => $senator->name , 'senator_id' => $senator->senatorID , 'user_id' => $party->user_id ));
                    }
                }
            }
        }
        foreach ($this->forum->cards as $card) {
            if ($card->type == 'Province') {
                array_push($result , array ('province_name' => $card->name , 'province_id' => $card->id , 'senator_name' => NULL , 'senator_id' => NULL , 'user_id' => NULL ));
            }
            if ($card->type=='Family' || $card->type=='Statesman') {
                foreach ($card->controls->cards as $card2) {
                    if ($card2->type == 'Province') {
                        array_push($result , array ('province_name' => $card2->name , 'province_id' => $card2->id , 'senator_name' => $card->name , 'senator_id' => $card->senatorID , 'user_id' => 'forum'));
                    }
                }
            }
        }
        return $result ;
    }
    
    /**
     * Returns a list of concessions that are avilable in the forum, and not flipped
     * @return array 'name' , 'id'
     */
    public function senate_getListAvailableConcessions() {
        $result = array() ;
        foreach ($this->forum->cards as $card) {
            if ($card->type == 'Concession' && $card->flipped == FALSE) {
                $result[] = array ('name' => $card->name , 'id' => $card->id);
            }
        }
        return $result ;
    }

    /**
     * Returns an array of possible governors. The value 'user_id' is equal to 'forum' for non-aligned senators.
     * @return array ('senatorID' , 'user_id' , 'returning')
     */
    public function senate_getListAvailableGovernors() {
        $result = array() ;
        foreach ($this->party as $party) {
            foreach ($party->senators->cards as $senator) {
                if ($senator->inRome()) {
                    array_push($result , array('senatorID' => $senator->senatorID , 'name' => $senator->name , 'user_id' => $party->user_id , 'returning' => $senator->returningGovernor) ) ;
                }
            }
        }
        foreach ($this->forum->cards as $card) {
            if ( ($card->type=='Family' || $card->type=='Statesman') && ($card->inRome()) ) {
                array_push($result , array('senatorID' => $card->senatorID , 'name' => $card->name , 'user_id' => 'forum' , 'returning' => $card->returningGovernor) ) ;
            }
        }
        return $result ;
    }
    
    /**
     * This function returns TRUE if returning governors can choose not to go to a province
     * @param array $parameters
     * @return boolean
     */
    public function senate_getReturningGovernorsCanChoose($parameters) {
        // Step 1 : go through the proposal's parameters and check if there is any SenatorAccepts parameter equal to 'PENDING'
        $dontBother = TRUE ;
        foreach ($parameters as $key => $parameter) {
            // This is the 'SenatorAccepts' parameter
            if ($key % 3 == 1) {
                if ($parameter==='PENDING') {
                    $dontBother = FALSE ;
                }
            }
        }
        if ($dontBother) {
            return FALSE ;
        }
        // Step 2 : If there is, check if there is only one possible Governor
        $availableGovernors = $this->senate_getListAvailableGovernors() ;
        return (count($availableGovernors) >1 ) ;
    }
    
    /**
     * Returns a list (string) of governors who are :<br>
     * > part of the current governor proposal<br>
     * > returning governors<br>
     * > in user_id's party<br><br>
     * or FALSE if the array is empty
     * @param array $parameters The parameters array from the governors proposal
     * @param string $user_id The user_id
     * @return string|FALSE
     */
    public function senate_getListReturningGovernors($parameters , $user_id) {
        $result = '' ;
        foreach ($parameters as $key => $parameter) {
            // This is the 'SenatorID' parameter
            if ($key % 3 == 0) {
                $senator = $this->getSenatorWithID($parameter) ;
                if ($senator!==FALSE && $senator->returningGovernor && $this->getPartyOfSenator($senator) == $user_id) {
                    $result.=$senator->name.', ';
                }
            }
        }
        return ( ($result == '') ? FALSE : substr($result , 0 , -2) ) ;
    }
    
    /**
     * This function returns a list of all the land bills proposals that are possible at the time the function is called.
     * A land bill proposal is impossible if :
     * - A land bill of that type was already proposed this turn
     * - There is no more land bill of that type available
     * - A land bill level 2 (or 3) repeal is only possible if a level 2 (or 3) land bill is in place
     * - A land bill level 2 (or 3) repeal is impossible if no Senator has at least 2 (or 3) popularity to repeal it
     * - There can be no more than one land bill repeal proposal per turn
     * @return array 'type' => 'text'
     */
    public function senate_getListPossibleLandBills() {
        $result = array();
        $result[1] = _('Pass Land Bill I');
        $result[2] = _('Pass Land Bill II');
        $result[3] = _('Pass Land Bill III');
        $result[-2] = _('Repeal Land Bill II');
        $result[-3] = _('Repeal Land Bill III');
        // A land bill of that type was already proposed this turn
        // There can be no more than one land bill repeal proposal per turn
        foreach ($this->proposals as $proposal) {
            if ($proposal->type=='Land Bills') {
                switch ($proposal->parameters[0]) {
                    case -2:
                    case -3:
                        unset ($result[-2]);
                        unset ($result[-3]);
                        break;
                    case 1:
                        unset ($result[1]);
                        break;
                    case 2:
                        unset ($result[2]);
                        break;
                    case 3:
                        unset ($result[3]);
                        break;
                }
            }
        }
        // There is no more land bill of that type available
        if ($this->landBill[1]==3) {
            unset ($result[1]);
        }
        if ($this->landBill[2]==2) {
            unset ($result[2]);
        }
        if ($this->landBill[3]==1) {
            unset ($result[3]);
        }
        // A land bill level 2 (or 3) repeal is only possible if a level 2 (or 3) land bill is in place
        if ($this->landBill[2]==0) {
            unset ($result[-2]);
        }
        if ($this->landBill[3]==0) {
            unset ($result[-3]);
        }
        // A Senator with a least 2 / 4 POP is needed to sponsor the repeal of a land bill level 1,2 / 3
        $maxPOP = 1 ;
        foreach ($this->party as $party) {
            foreach ($party->senators->cards as $senator) {
                if ($senator->POP > $maxPOP && $senator->inRome()) {
                    $maxPOP = $senator->POP ;
                }
            }
        }
        if ($maxPOP<4) {
            unset ($result[-3]);
        }
        if ($maxPOP<2) {
            unset ($result[-2]);
            unset ($result[-1]);
        }
        return $result ;
    }

    /**
     * Returns an array of all the Senators that can be assassinated by $user_id, which means :<br>
     * Not his own, not in a party that was already the target of an assassination this turn, in Rome.
     * @param string $user_id The user_id of the would be assassin
     * @return array Array of arrays ('senatorID' , 'name' , 'user_id' , 'party_name')
     */
    public function senate_getListAssassinationTargets($user_id) {
        $result = array() ;
        foreach ($this->party as $party) {
            if ($party->user_id!=$user_id && $party->assassinationTarget===FALSE) {
                foreach ($party->senators->cards as $senator) {
                    if ($senator->inRome()) {
                        $result[] = array('senatorID' => $senator->senatorID , 'name' => $senator->name , 'user_id' => $party->user_id , 'party_name' => $party->fullName());
                    }
                }
            }
        }
        return $result ;
    }
    
    /**
     * Returns a list of senators who can be assassins
     * @param string $user_id The user_id
     * @return array Array of ('senatorID' , 'name')
     */
    private function senate_getListPotentialAssassins($user_id) {
        $result = array () ;
        foreach($this->party[$user_id]->senators->cards as $senator) {
            if ($senator->inRome()) {
                array_push ($result , array('senatorID' => $senator->senatorID , 'name' => $senator->name ));
            }
        }
        return $result ;
    }

    /**
     * Returns a list of cards that can be used for assassination<br>
     * By the assassin if type is ASSASSIN, by the target (bodyguards) if type is TARGET<br>
     * @param string $user_id The user_id
     * @param string $type ASSASSIN|TARGET
     * @return array Array of ('id' , 'name')
     */
    private function senate_getListAssassinationCards($user_id , $type) {
        $result = array () ;
        foreach($this->party[$user_id]->hand->cards as $card) {
            if ($card->name === 'ASSASSIN' && $type === 'ASSASSIN') {
                array_push ($result , array('id' => $card->id , 'name' => $card->name ));
            }
            if ($card->name === 'BODYGUARD' && $type === 'TARGET') {
                array_push ($result , array('id' => $card->id , 'name' => $card->name ));
            }
        }
        return $result ;
    }
    
    /**
     * Returns a list of possible other business
     * @return array of proposal types (as defined in Proposal class)
     */
    private function senate_getListOtherBusiness() {
        $result = array () ;
        // TO DO : List of all possible proposals that are 'other business' :
        // 'Concessions' , 'Land Bills' , 'Forces' , 'Garrison' , 'Deploy' , 'Recall' , 'Reinforce' ,  'Recall Pontifex' , 'Priests' , 'Consul for life' , 'Minor'

        // There is at least one concession in the forum
        if ($this->forum->getIdOfCardWithValue('type','Concession')!==FALSE) {
            $result[]=array('Concessions',_('Assign concessions'));
        }
        
        // This covers Land bills & their repeal
        foreach ($this->senate_getListPossibleLandBills() as $key=>$value) {
            $result[]=array('LandBill'.$key,$value);
        }
        $result[]=array('Forces',_('Raise/Disband forces'));
        
        // Garrisons depend on whether or not there are provinces
        if (count($this->senate_getListAvailableProvinces())>0) {
            $result[]=array('Garrison',_('Send and recall garrisons'));
        }
        $result[]=array('Deploy',_('Deploy forces to fight a Conflict'));
        $result[]=array('Minor',_('Minor motion'));
        return $result ;
    }
    
    /**
     * Returns a detailed description of a Concession proposal
     * @param type $parameters
     * @return String
     */
    private function senate_getConcessionProposalDetails ($parameters) {
        $result='';
        foreach($parameters as $key=>$parameter) {
            if ($key%2==0) {
                $result.= sprintf (' %s to %s (%s) %s' ,
                    $this->getSpecificCard('id', $parameters[$key+1])['card']->name ,
                    $this->getSenatorWithID($parameter)->name ,
                    $this->getPartyOfSenatorWithID($parameter)->fullName() ,
                    ($key==count($parameters)-2 ? '.' : ($key==count($parameters)-4 ? ' and ' : ' , '))
                );
            }
        }
        return $result;
    }

    private function senate_getForcesProposalDetails ($parameters) {
        $veteranMessage = '';
        $specificLegionsToDisband = explode(',' , $parameters[5]) ;
        if ($specificLegionsToDisband[0] != NULL) {
            $veteranMessage.=' Also disband ';
            $veteranLoyalTo = array() ;
            for ($i=1 ; $i<count($specificLegionsToDisband) ; $i++) {
                $senatorID = $this->legion[$specificLegionsToDisband[$i]]->loyalty;
                if (!isset($veteranLoyalTo[$senatorID])) {
                    $veteranLoyalTo[$senatorID] = 1 ;
                } else {
                    $veteranLoyalTo[$senatorID]++;
                }
            }
            foreach($veteranLoyalTo as $key=>$value) {
                $veteranMessage.=sprintf(_('%d legion%s loyal to %s, ') , $value , ($value>1 ? 's' : '') , $this->getSenatorWithID($key)->name);
            }
            $veteranMessage = substr($veteranMessage , 0 , -2) ;
        }
        return sprintf(_('Recruit %d legions & %d fleets. Disband %d regular legions, %d veteran legions & %d Fleets.%s') ,
            $parameters[0] , $parameters[1] , $parameters[2] , $parameters[3] , $parameters[4] , $veteranMessage
        ) ;
    }

    private function senate_getDeployProposalDetails ($parameters) {
        $fullMessage='';
        $groupedProposals = (int) (count($parameters)/5) ;
        $commanders = array() ;
        $conflicts = array() ;
        for ($i=0; $i<$groupedProposals; $i++) {
            if ($i>0) {
                $fullMessage.=_(', ');
            }
            $veteranLoyalTo = array() ;
            $commanders[$i] = $this->getSenatorWithID($parameters[5*$i]) ;
            $conflicts[$i] = $this->getSpecificCard('id' , $parameters[1+5*$i]) ;
            $landForces = explode(',' , $parameters[2+5*$i]) ;
            for ($j=2 ; $j<count($landForces) ; $j++) {
                $senatorID = $this->legion[$landForces[$j]]->loyalty;
                if (!isset($veteranLoyalTo[$senatorID])) {
                    $veteranLoyalTo[$senatorID] = 1 ;
                } else {
                    $veteranLoyalTo[$senatorID]++;
                }
            }
            $fullMessage.= sprintf(_('Sending %s to fight %s with %d regulars, %d veterans and %d fleets') , $commanders[$i]->name , $conflicts[$i]['card']->name , $landForces[0] , $landForces[1] , $parameters[3+5*$i]);
            if (count($veteranLoyalTo)>0) {
                $fullMessage.='. This includes ';
                foreach($veteranLoyalTo as $key=>$value) {
                    $fullMessage.=sprintf(_('%d legion%s loyal to %s, ') , $value , ($value>1 ? 's' : '') , $this->getSenatorWithID($key)->name);
                }
                $fullMessage = substr($fullMessage , 0 , -2) ;
            }
        }
        $fullMessage.='.';
        return $fullMessage ;
    }
    
    /**
     * Returns a list of potential weasels : user_ids of the commanders who are sent with insufficient forces against conflicts
     * Note : For this function to fire, the subPhase is 'Other Business', there is a proposal, its type is 'Deploy', its outcome is NULL
     * @return weasel array 
     */
    public function senate_getDeployWeaselCheck() {
        $weasel = array();
        $proposal = end($this->proposals) ;
        $groupedProposals = (int) (count($proposal->parameters)/5) ;
        for ($i=0; $i<$groupedProposals; $i++) {
            $commander = $this->getSenatorWithID($proposal->parameters[5*$i]) ;
            $conflict = $this->getSpecificCard('id' , $proposal->parameters[1+5*$i]) ;
            $modifiedStrength = $this->getModifiedConflictStrength($conflict['card']);
            $landForces = explode(',' , $proposal->parameters[2+5*$i]) ;
            $landTotal = $landForces[0] + 2*$landForces[1] ;
            $fleets = $proposal->parameters[3+5*$i] ;
            if ( ($fleets>0 && $fleets<$modifiedStrength['fleet']) || ($landTotal>0 && $landTotal<$modifiedStrength['land']) && $proposal->parameters[4+5*i]===NULL ) {
                $commandersParty = $this->getPartyOfSenator($commander)->user_id ;
                if (!in_array($commandersParty, $weasel)) {
                    $weasel[] = $commandersParty ;
                }
            }
        }
        return $weasel ;
    }
    
     /**
     * senate_view returns all the data needed to render senate templates. The function returns an array $output :
     * $output['state'] (Mandatory) gives the name of the current state to be rendered :
     * - Vote : A vote on a proposal is underway
     * - Proposal : Make a proposal
     * - Proposal impossible : Unable to make a proposal
     * - Decision : Need to decided on a proposal before/after vote (e.g. Consuls decide who does what, Accusator agrees to prosecute, etc)
     * - Error : A problem occured
     * $output[{other}] : values or array of values based on context
     * @param string $user_id
     * @return array $output
     */
    public function senate_view($user_id) {
        $output = array() ;
        /*
         * Short-circuit the normal view if the state is 'Unanimous defeat'
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
        /**
         * Short-circuit the normal view if the state is 'Assassination'
         * - Allows the assassin to chose a victim, a Senator to make the attempt and cards to play
         * - Roll (don't resolve yet)
         * - Allows the victim to play bodyguard cards
         * - Resolve assassination & bodyguards catching the assassin
         */
        } elseif (($this->phase=='Senate') && ($this->subPhase=='Assassination') ) {
            $output['state'] = 'Assassination' ;
            // Pick the assassin & the cards played
            if ($this->assassination['assassinID'] === NULL) {
                if ($user_id == $this->assassination['assassinParty']) {
                    $output['subState'] = 'choose assassin' ;
                    $output['potentialVictims'] = $this->senate_getListAssassinationTargets($user_id) ;
                    $output['potentialAssassins'] = $this->senate_getListPotentialAssassins($user_id) ;
                    $output['cards'] = $this->senate_getListAssassinationCards($user_id , 'ASSASSIN')  ;
                } else {
                    $output['subState'] = 'waiting for assassin' ;
                }
            // Give a chance for the victim to play bodyguard(s)
            } elseif ($this->assassination['roll'] !== NULL) {
                if ($user_id == $this->assassination['victimParty']) {
                    $output['subState'] = 'play bodyguards' ;
                    $output['cards'] = $this->senate_getListAssassinationCards($user_id , 'TARGET')  ;
                } else {
                    $output['subState'] = 'waiting for victim' ;
                }
            }
        /*
         * Once the President has adjourned, give a chance to other players to keep it open with a Tribune
         */
        } elseif (($this->phase=='Senate') && ($this->subPhase=='Adjourn') ) {
            $output['state'] = 'Adjourn' ;
            if ($this->getHRAO(TRUE)['user_id'] == $user_id) {
                $output['subState'] = 'President' ;
            } elseif ($this->party[$user_id]->bidDone) {
                $output['subState'] = 'Wont play tribune' ;
            } else {
                $output['subState'] = 'Can play tribune' ;
                $output['proposalHow'] = $this->senate_canMakeProposal($user_id) ;
            }
        /*
         * Now handle all the normal cases
         */
        } else {
            /*
             *  There is a proposal underway : Either a decision has to be made (before or after a vote), or a vote is underway
             */
            if (end($this->proposals)!==FALSE) {
                $output['type'] = end($this->proposals)->type ;
                // The proposal's long description
                $output['longDescription'] = $this->senate_viewProposalDescription(end($this->proposals)) ;
            }
            /*
             * Decisions
             */
            // Consuls : The outcome is TRUE (the proposal was voted), but Consuls have yet to decide who will be Consul of Rome / Field Consul
            if ($this->phase=='Senate' && $this->subPhase=='Consuls' && end($this->proposals)!==FALSE && end($this->proposals)->outcome===TRUE && (end($this->proposals)->parameters[2]===NULL || end($this->proposals)->parameters[3]===NULL) ) {
                $output['state'] = 'Decision' ;
                $output['type'] = 'Consuls' ;
                $senator = array() ; $party = array() ;
                for ($i=0 ; $i<2 ; $i++) {
                    $senator[$i] = $this->getSenatorWithID(end($this->proposals)->parameters[$i]) ;
                    $party[$i] = $this->getPartyOfSenator($senator[$i]);
                }
                if ($party[0]->user_id!=$user_id && $party[1]->user_id!=$user_id) {
                    $output['canDecide'] = FALSE ;
                } else {
                    $output['canDecide'] = TRUE ;
                    for ($i=0 ; $i<2 ; $i++) {
                        if ($party[$i]->user_id == $user_id) {
                            if ( (end($this->proposals)->parameters[2]===NULL || end($this->proposals)->parameters[2]!=$senator[$i]->senatorID) && (end($this->proposals)->parameters[3]===NULL || end($this->proposals)->parameters[3]!=$senator[$i]->senatorID) ) {
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
            } elseif ($this->phase=='Senate' && $this->subPhase=='Prosecutions' && end($this->proposals)!==FALSE && end($this->proposals)->outcome===NULL && end($this->proposals)->parameters[3]===NULL ) {
                $output['state'] = 'Decision' ;
                $output['type'] = 'Prosecutions' ;
                $prosecutor = $this->getSenatorWithID(end($this->proposals)->parameters[2]) ;
                $output['prosecutorName'] = $this->getSenatorFullName(end($this->proposals)->parameters[2]) ;
                $output['prosecutorID'] = $prosecutor -> senatorID ;
                $output['prosecutorParty'] = $this->getPartyOfSenator($prosecutor)->user_id ;
                $output['canDecide'] = ( $output['prosecutorParty'] == $user_id ) ;
            // Governors : Returning governors agreeing to be nominated for governorships
            } elseif($this->phase=='Senate' && $this->subPhase=='Governors' && end($this->proposals)!==FALSE && end($this->proposals)->outcome===NULL && in_array('PENDING' , end($this->proposals)->parameters) && $this->senate_getReturningGovernorsCanChoose(end($this->proposals)->parameters) ) {
                $output['state'] = 'Decision' ;
                $output['type'] = 'Governors' ;
                $output['list'] = $this->senate_getListReturningGovernors(end($this->proposals)->parameters , $user_id) ;
                $output['canDecide'] = ( $output['list']!==FALSE ) ;
            // Dictator : If the 'Appointment' parameter (second parameter) is set to TRUE the other Consul must decide. This decision short-circuits the Vote process entirely : Here, Decision = outcome
            } elseif($this->phase=='Senate' && $this->subPhase=='Dictator' && end($this->proposals)!==FALSE && end($this->proposals)->parameters[1]===TRUE && end($this->proposals)->outcome===NULL) {
                $output['state'] = 'Decision' ;
                $output['type'] = 'Dictator' ;
                $output['SenatorName'] = $this->getSenatorWithID(end($this->proposals)->parameters[0])->name ;
                $output['canDecide'] = end($this->proposals)->proposedBy!=$user_id && ( ($this->senate_findOfficial('Rome Consul')['user_id'] == $user_id) || ($this->senate_findOfficial('Field Consul')['user_id'] == $user_id) );
            // Dictator appoints Master of horse
            } elseif ($this->phase=='Senate' && $this->subPhase=='Dictator' && end($this->proposals)!==FALSE && end($this->proposals)->parameters[2]===NULL && end($this->proposals)->outcome===TRUE) {
                $output['state'] = 'Decision' ;
                $output['type'] = 'Master of horse' ;
                $output['list'] = $this->senate_getFilteredListSenators('possibleMastersOfHorse');
                $output['canDecide'] = ($this->senate_findOfficial('Dictator')['user_id'] == $user_id);
            // 'Deploy' Proposal : Check if a decision is needed by commander(s) to accept being sent to a Conflict with less than adequate Forces
            } elseif ($this->phase=='Senate' && $this->subPhase=='Other business' && end($this->proposals)!==FALSE && end($this->proposals)->type=='Deploy' && end($this->proposals)->outcome===NULL && (count($checkWeasel=$this->senate_getDeployWeaselCheck())>0) ) {
                $output['state'] = 'Decision' ;
                $output['type'] = 'Weasel' ;
                $output['canDecide'] = in_array($user_id, $checkWeasel) ;
            // Assassin special prosecution : allow the Censor to chose voting order
            } elseif ($this->phase=='Senate' && $this->subPhase=='Assassin prosecution' && end($this->proposals)!==FALSE && end($this->proposals)->votingOrder===NULL) {
                $output['state'] = 'Assassin prosecution voting order' ;
                $output['canChoose'] = ($this->senate_findOfficial('Censor')['user_id'] == $user_id) ;
            /*
             * Vote (The proposal has no outcome but no decision is required : make vote possible)
             */
            } elseif ($this->phase=='Senate' && end($this->proposals)!==FALSE && end($this->proposals)->outcome===NULL) {
                $output['state'] = 'Vote' ;
                $output['type'] = end($this->proposals)->type ;
                // The proposal's long description
                $output['longDescription'] = $this->senate_viewProposalDescription(end($this->proposals)) ;
                $output['voting'] = end($this->proposals)->voting ;
                $output['treasury'] = array() ;
                foreach($this->party[$user_id]->senators->cards as $senator) {
                    $output['treasury'][$senator->senatorID] = $senator->treasury ;
                }
                $output['votingOrder'] = end($this->proposals)->votingOrder ;
                $output['votingOrderNames'] = array() ;
                foreach ($output['votingOrder'] as $key=>$value) {
                    $output['votingOrderNames'][$key] = $this->party[$value]->fullName();
                }
                // Popular appeal possible or not
                if ( (end($this->proposals)->type=='Prosecutions') && ($this ->getPartyOfSenatorWithID(end($this->proposals)->parameters[0]) -> user_id == $user_id) && end($this->proposals)->parameters[4]===NULL ) {
                    $output['appeal'] = TRUE ;
                    $output['popularity'] = $this->getSenatorWithID(end($this->proposals)->parameters[0])->POP ;
                } else {
                    $output['appeal'] = FALSE ;
                }
                // This is not your turn to vote
                if ($output['votingOrder'][0]!=$user_id) {
                    $output['canVote'] = FALSE ;
                // This is your turn to vote. Also give a list of possible vetos
                } else {
                    $output['canVote'] = TRUE ;
                    $output['possibleVetos'] = $this->senate_getVetoList($user_id) ;
                }
            /*
             * There is no proposal underway, give the possibility to make proposals
             */
            } elseif (end($this->proposals)===FALSE || end($this->proposals)->outcome!==NULL) {
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
                    // TO DO
                    /*
                     * Dictator
                     */
                    } elseif (($this->phase=='Senate') && ($this->subPhase=='Dictator')) {
                        /*
                         * How this works :
                         * Consuls have the 'Dictator appointed by Consuls' ProposalHow
                         * - If they arrive here, the other Consul hasn't proposed yet (otherwise, we would be at the decision level)
                         * - They have the following choices :
                         * --> Decline to Appoint (which creates a Dictator Proposal with the 'Appointment' flag set to TRUE and the outcome set to FALSE
                         * --> Propose Senator X (give a list) as a Dictator (This will lead to a Decision by the other Consul, the Decision will replace the vote)
                         * If the 'Dictator appointed by Consuls' ProposalHow is not available, this is a normal Dictator proposal , players can :
                         * --> Propose Senator X (using President or Tribune)
                         * --> Decline to propose a Dictator for the turn, which sets their BidDone flag to FALSE
                         */
                        // Give a chance to propose a dictator if conditions are met (they should be otherwise the phase wouldn't be 'Dictator')
                        $output['state'] = 'Proposal';
                        // First : Give a chance to the consuls to appoint a dictator
                        if ($output['proposalHow'][0] == 'Dictator appointed by Consuls') {
                            $output['type'] = 'Dictator Appointment';
                            $output['possibleDictators'] = $this->senate_getFilteredListSenators('possibleDictators') ;
                        // You are not a Consul, but Consuls are still deciding whether or not to appoint a Dictator : You are waiting for the Consuls
                        } elseif ($output['proposalHow'][0] == 'Waiting for Consuls to appoint Dictator') {
                            $output['state'] = 'Proposal impossible';
                            $output['reason'] = 'Waiting for Consuls to appoint Dictator';
                        // In the view, give the option to set 'bidDone' to TRUE, so the player can indicate he doesn't want to propose a dictator this turn
                        // Once bidDone for all parties are TRUE, move on with our lives.
                        } elseif (!$this->party[$user_id]->bidDone) {
                            $output['possibleDictators'] = $this->senate_getFilteredListSenators('possibleDictators') ;
                        } else {
                            $output['state'] = 'Proposal impossible';
                            $output['reason'] = 'You do not wish to propose a dictator this turn. Waiting on other players';
                        }
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
                        $output['possibleProsecutors'] = $this->senate_getFilteredListSenators('prosecutor') ;
                    /*
                     * Governors
                     */
                    } elseif ( ($this->phase=='Senate') && ($this->subPhase=='Governors') ) {
                        $output['state'] = 'Proposal';
                        $output['type'] = 'Governors';
                        $output['list'] = $this->senate_getListAvailableProvinces();
                        $output['possibleGovernors'] = $this->senate_getListAvailableGovernors();
                    /*
                     * Other Business : 'Concessions' , 'Land Bills' , 'Forces' , 'Garrison' , 'Deploy' , 'Recall' , 'Reinforce' , 'Recall Pontifex' , 'Priests' , 'Consul for life' , 'Minor'
                     */
                    } elseif ( ($this->phase=='Senate') && ($this->subPhase=='Other business') ) {
                        $output['state'] = 'Proposal';
                        $output['type'] = 'Other Business';
                        $output['adjourned'] = $this->senateAdjourned;
                        $output['wontKeepItOpen'] = $this->party[$user_id]->bidDone ;
                        if (!$output['adjourned'] || !$output['wontKeepItOpen']) {
                            $output['list'] = $this->senate_getListOtherBusiness();
                            $output['possibleConcessionSenators'] = $this->senate_getFilteredListSenators('concession');
                            $output['concessions'] = $this->senate_getListAvailableConcessions();
                            $output['possibleLandBillsSponsors'] = $this->senate_getFilteredListSenators('landBillSponsor');
                            $output['legions'] = $this->getLegionDetails();
                            $output['fleets'] = $this->getFleetDetails();
                            $output['commanders'] = $this->senate_getFilteredListSenators('commanders') ;
                            $output['conflicts'] = $this->getListOfConflicts() ;
                            /*
                             * TO DO
                             */
                            $output['adjourn'] = $this->getHRAO()['user_id']==$user_id;
                        } else {
                            $output['state'] = 'Proposal impossible';
                            $output['reason'] = 'The Senate has been adjourned and you don\'t wish to keep it open.';
                        }
                    } else {
                        $output['state'] = 'Error';
                    }
                }
            } else {
                $output['state'] = 'Error';
            }
        }
        // Finally, always set the $output['assassinate'] variable that give the possibility to assissassinasste.
        // Not possible if the state is 'Error', 'assassination',  or 'Decision' (TO DO : confirm the latter), nor if the party already made ane attempt
        if ($output['state']!='Error' && $output['state']!='Assassination' && $output['state']!='Decision' && !$this->party[$user_id]->assassinationAttempt) {
            $output['assassinate'] = $this->senate_getListAssassinationTargets($user_id) ;
        } else {
            $output['assassinate'] = FALSE ;
        }
        return $output ;
    }

    /**
     * Returns a description of the current proposal
     * @return type
     */
    private function senate_viewProposalDescription($targetProposal) {
        if ($targetProposal->proposedBy == NULL) {
            $description = _('Automatic proposal for : ');
        } else {
            $description = sprintf(_('%s is proposing ') , $this->party[$targetProposal->proposedBy]->fullName());
        }
        switch ($targetProposal->type) {
            case 'Consuls' :
                $senator1 = $this->getSenatorWithID($targetProposal->parameters[0]) ;
                $senator2 = $this->getSenatorWithID($targetProposal->parameters[1]) ;
                $description.= sprintf(_('%s and %s as consuls.') , $senator1->name , $senator2->name);
                break ;
            case 'Censor' :
                $censor = $this->getSenatorWithID($targetProposal->parameters[0]) ;
                $description.= sprintf(_('%s as Censor.') , $censor->name);
                break ;
            case 'Prosecutions' :
                $reasonsList = $this->senate_getListPossibleProsecutions() ;
                $reasonText = '';
                foreach ($reasonsList as $reason) {
                    if ($reason['reason']==$targetProposal->parameters[1]) {
                        $reasonText = $reason['text'] ;
                    }
                }
                $description.= sprintf(_('%s. Prosecutor : %s') , $reasonText , $this->getSenatorFullName($targetProposal->parameters[2]) );
                break ;
            case 'Assassin prosecution' :
                $description.= _('Special Major Prosecution for assassination');
                break ;
            case 'Concessions' :
                $description.='to assign Concessions as follows : '.$this->senate_getConcessionProposalDetails($targetProposal->parameters);
                break ;
            case 'Forces' :
                $description.=' : '.$this->senate_getForcesProposalDetails($targetProposal->parameters);
                break ;
            // TO DO : other types of proposals
        }
        return $description ;
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
                array_push($messages , array( sprintf(_('The card is not in {%s}\'s hand') , $user_id) ));
                return $messages ;   
            } else {
                if ($statesman->type!='Statesman') {
                    // This is not a statesman, put the card back and report an error
                    $this->party[$user_id]->hand->putOnTop($statesman);
                    array_push($messages , array( sprintf(_('"%s" is not a statesman','error') , $statesman->name) ));
                    return $messages ;
                } else {
                    // This is a Statesman, proceed
                    // First, get the statesman's family number
                    $family = $statesman->statesmanFamily() ;
                    if ($family === FALSE) {
                        // This family is weird. Put the card back and report the error
                        $this->party[$user_id]->hand->putOnTop($statesman);
                        array_push($messages , array(_('Weird family.') , 'error'));
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
                                array_push($messages , array(_('Weird family.') , 'error'));
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
                                if ($this->party[$user_id]->leaderID == $matchedFamily->senatorID) {
                                    $this->party[$user_id]->leaderID = $statesman->senatorID;
                                }
                                array_push($messages , array( sprintf(_('{%s} plays Statesman %s on top of senator %s') , $user_id ,$statesman->name ,$matchedFamily->name ) ));
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
                            array_push($messages , array( sprintf(_('{%s} plays Statesman %s and gets the matching unaligned family from the Forum.') , $user_id , $statesman->name) ));
                            return $messages ;
                        }

                    }
                    // SUCCESS : There was no matched family in the player's party or the Forum
                    $this->party[$user_id]->senators->putOnTop($statesman) ;
                    array_push($messages , array( sprintf(_('{%s} plays Statesman %s') , $user_id , $statesman->name) ));
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
                array_push($messages , array( _('This senator is not in play') , 'alert' , $user_id)) ;
                return $messages;
            }
            $senator = $this->party[$user_id]->senators->drawCardWithValue('senatorID', $senator_id);
            if ($senator === FALSE ) {
                array_push($messages , array( sprintf(_('The senator is not in {%s}\'s party') , $user_id) ));
                return $messages ;   
            }
            $concession = $this->party[$user_id]->hand->drawCardWithValue('id', $card_id);
            if ($concession === FALSE ) {
                $this->party[$user_id]->senators->putOnTop($senator);
                array_push($messages , array( sprintf(_('The card is not in {%s}\'s hand') , $user_id) , 'error',$user_id));
                return $messages ;   
            } else {
                if ($concession->type!='Concession') {
                   $this->party[$user_id]->senators->putOnTop($senator);
                   $this->party[$user_id]->hand->putOnTop($concession);
                   array_push($messages , array(sprintf(_('"%s" is not a concession') , $concession->name) , 'error',$user_id));
                   return $messages ;
                } elseif($concession->special=='land bill' && !$this->landCommissionerPlaybale()) {
                   $this->party[$user_id]->senators->putOnTop($senator);
                   $this->party[$user_id]->hand->putOnTop($concession);
                   array_push($messages , array(_('The Land commissioner can only be played while Land bills are enacted.'),'error',$user_id));
                   return $messages ;
                } else {
                    $senator->controls->putOnTop($concession);
                    $this->party[$user_id]->senators->putOnTop($senator);
                    array_push($messages , array( sprintf(_('{%s} plays Concession %s on Senator %s') , $user_id , $concession->name , $senator->name) ));
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

     /************************************************************
     * Other functions
     ************************************************************/

    /**
     * Function for paying captives' ransoms.
     * @param string $user_id The user_id of the player paying the ransom
     * @param array $request the POST data (list of 'senator_ID' and  'senator_ID_SCREWYOU')<br>
     * The value of 'senator_ID' is equal to the number of talents spent from the senator's personal treasury<br>
     * 'senator_ID_SCREWYOU' is 'on' if the user doesn't pay the ransom for this senator at the moment<br>
     * @return array messages
     */
    public function other_payRansom($user_id , $request) {
        $messages = array () ;
        $listOfCaptives = $this->getListOfCaptives($user_id) ;
        foreach ($listOfCaptives as $captive) {
            if (isset($request[$captive['senatorID'].'_SCREWYOU']) && ($request[$captive['senatorID'].'_SCREWYOU'] == 'on') ) {
                array_push($messages , array( sprintf(_('You decide not to pay the ransom of %s at the moment.') , $user_id , $captive['name']) , 'message' , $user_id ) ) ;
            } else {
                $fromPersonalTreasury = min ($request[$captive['senatorID']] , $captive['ransom']) ;
                $fromPartyTreasury = $captive['ransom'] - $fromPersonalTreasury ;
                if ( ($fromPartyTreasury > $this->party[$user_id]->treasury) || ($fromPersonalTreasury > $this->getSenatorWithID($captive['senatorID'])->treasury) ) {
                    array_push($messages , array( sprintf(_('{%s} decides to pay the ransom of %s but fails at basic math.') , $user_id , $captive['name']) , 'error' , $user_id ) ) ;
                } else {
                    $this->party[$user_id]->treasury -= $fromPartyTreasury ;
                    $this->getSenatorWithID($captive['senatorID'])->treasury -= $fromPersonalTreasury ;
                    $this->getSenatorWithID($captive['senatorID'])->captive = FALSE ;
                    array_push($messages , array( sprintf(_('{%s} decides to pay the %dT ransom of %s : %d from personal treasury and %d from party treasury') , $user_id , $captive['ransom'] , $captive['name'] , $fromPersonalTreasury , $fromPartyTreasury)  ) ) ;
                }
            }
        }
        return $messages ;
    }

    /**
     * Returns an array representation of the object<br>
     * Each element of the array is either :<br>
     * - An array with a key and its type (either 'array' or an object class name)<br>
     * - A property's name (second before last element)<br>
     * - A property's value (last element)<br>
     * @param mixed $object 
     * @return mixed array
     */
    public function other_debugDescribeGameObject($object) {
        if (is_object($object)) {
            $object = (array)$object ;
        }
        if (is_array($object)) {
            $result = array() ;
            foreach ($object as $key=>$value) {
                $type = (is_object($value) ? (str_replace('ROR\\' , '' , get_class($value))) : (is_array($value) ? 'array' : '')) ;
                $valueDetail = $this->other_debugDescribeGameObject($value) ;
                if (is_array($valueDetail)) {
                    foreach($valueDetail as $item) {
                        $result[] = array_merge(array(array($key,$type)) , $item) ;
                    }
                } else {
                    $result[] = array($key , $valueDetail) ;
                }
            }
        } else {
            if (is_bool($object)) {
                $result = ($object ? '<TRUE>' : '<FALSE>') ;
            } elseif (is_null($object)) {
                $result = '<NULL>' ;
            } elseif ($object==='') {
                $result = '<EMPTY>' ;
            } else {
                $result = $object ;
            }
        }
        return $result ;
    }
    
    /**
     * Returns an array of Decks
     * The format is : (Descriptor array , key of the array in the description)
     * @return type
     */
    public function other_debugGetListOfDecks() {
        $result=array();
        $objectDescription = $this->other_debugDescribeGameObject($this) ;
        foreach ($objectDescription as $key=>$item) {
            $theKey=-1;
            foreach($item as $key2=>$detail) {
                if (is_array($detail) && $detail[1]=='Deck') {
                    $theKey = $key2 ;
                }
            }
            if ($theKey!=-1 && $item[$theKey+1]=='name') {
                $arrayToReturn=$item ;
                // We must remove the last two parameters as the are 'name' and the name itself, but we just new the path to the Deck
                array_pop($arrayToReturn) ;
                array_pop($arrayToReturn) ;
                $result[] = array($arrayToReturn,$item[$theKey+2]) ;
            }
        }
        return $result ;
    }
    
    // TO DO (or not) : 'Show value' function (to split show and change)
    
    /**
     * 
     * @param array $path an array representing the path of a property within this Game object<br>
     * e.g. :array('party' , 12 , 'senators' , 'cards' , 0 , 'controls' , 'cards' , 0 , 'name')<br>
     * @param mixed $newValue The new value to affect to this property
     */
    public function other_debugChangeValue($rawPath , $newValue=NULL) {
        /**
         * Inside-function
         * @param object $from The object from which the property must be retrieved
         * @param mixed $property A string representation of the property needed
         * @return mixed The property
         */
        function &returnProperty($from , $property) {
            if (is_object($from)) {
                return $from->$property ;
            } elseif (is_array($from)) {
                return $from[$property];
            }
        }
        $path = explode('|_|' , $rawPath) ;
        $target = &$this ;
        foreach ($path as $step) {
            $target = &returnProperty($target, $step) ;
        }
        if ($newValue===NULL) {
            return $target ;
        } else {
            $target = $newValue ;
        }
    }
}
