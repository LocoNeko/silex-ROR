<?php
namespace ROR;

class Party
{

    /*
     * $name (string)
     * $user_name (string)
     * $hand (Deck) : The party/player's hand
     * $senators (Deck) : The Deck of cards with senators in the party
     * $leader (Senator) : The Senator who is the party's leader (must be in the $senators Deck) or NULL
     * $treasury (int)  : Current Party's treasury
     * $phase_done (bool) : Whether this party has played its phase or not
     * $bid (int) : The party's current initiative/persuasion bid
     * $bidDone (bool) : whether or not this party is done bidding for initiative or NULL (WARNING : This is NOT used for persuasion)
     *                  Also using bidDone for Senate (Dictator appointment/election)
     * $bidWith (Senator) : the senator the party is bidding with for initiative/persuasion or NULL
     */
    public $user_id;
    public $name ;
    public $user_name ;
    public $hand ;
    public $senators ;
    public $leader ;
    public $treasury ;
    public $phase_done ;
    public $bid , $bidDone , $bidWith  ;
    public $freeTribunes ;
    public $assassinationAttempt ;
    public $assassinationTarget ;
    
    public function __construct() {
    }
        
    public function create ($name , $user_id = 'none' , $user_name = '') {
        $this->user_id = $user_id ;
        $this->name = (is_string($name) ? $name : NULL );
        $this->user_name = (is_string($user_name) ? $user_name : '' );
        $this->hand = new Deck ;
        $this->hand->name = sprintf(_('%s \'s cards') , $this->name) ;
        $this->senators = new Deck ;
        $this->senators->name = sprintf(_('%s \'s senators') , $this->name) ;
        $this->leader = NULL ;
        $this->treasury = 0 ;
        $this->phase_done = FALSE ;
        $this->bid = 0 ;
        $this->bidDone = NULL ;
        $this->bidWith = NULL ;
        $this->freeTribunes = array() ;
        $this->assassinationAttempt = FALSE ;
        $this->assassinationTarget = FALSE ;
    }
    
    public function fullName() {
        return $this->name.' ['.$this->user_name.']';
    }
    
    public function getRichestSenator() {
        $result = array() ;
        $result['senator'] = NULL ;
        $result['amount'] = 0 ;
        foreach ($this->senators->cards as $senator) {
            if ($result['senator'] == NULL || ($senator->treasury > $result['amount']) ) {
                $result['senator']= $senator ;
                $result['amount'] = $senator->treasury ;
            }
        }
        return $result;
    }
    
}