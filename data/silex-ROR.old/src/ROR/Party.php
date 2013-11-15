<?php
namespace ROR;

class Party
{

    /*
     * $name (string)
     * $hand (Deck) : The party/player's hand
     * $senators (Deck) : The Deck of cards with senators in the party
     * $leader (Senator) : The Senator who is the party's leader (must be in the $senators Deck) or NULL
     * $treasury (int)  : Current Party's treasury
     * $phase_done (bool) : Whether this party has played its phase or not
     * $initiativeBid (int) : The party's current initiative bid or NULL
     * $initiativeBidDone (bool) : whether or not this party is done bidding for initiative or NULL
     * $initiativeBidWith (Senator) : the senator the party is bidding with or NULL
     */
    public $user_id;
    public $name ;
    public $hand ;
    public $senators ;
    public $leader ;
    public $treasury ;
    public $phase_done ;
    public $initiativeBid , $initiativeBidDone , $initiativeBidWith  ;
    
    public function __construct() {
    }
        
    public function create ($name , $user_id = 'none') {
        $this->user_id = $user_id ;
        $this->name = (is_string($name) ? $name : NULL );
        $this->hand = new Deck ;
        $this->hand->name = $this->name.'\'s cards.';
        $this->senators = new Deck() ;
        $this->senators->name = $this->name.'\'s senators.';
        $this->leader = NULL ;
        $this->treasury = 0 ;
        $this->phase_done = FALSE ;
        $this->initiativeBid = NULL ;
        $this->initiativeBidDone = NULL ;
        $this->initiativeBidWith = NULL ;
    }
    
}