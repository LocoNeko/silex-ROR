<?php
namespace ROR;

class Deck
{
    public $cards = array() ;
    public $name;
    
    public function __construct() {
    }

    public function shuffle() {
        shuffle($this->cards) ;
    }
    
    /**
     * Puts the $card object on top of this deck
     * @param \ROR\Card $card
     */
    public function putOnTop(Card $card) {
        array_unshift($this->cards, $card) ;
    }
    
    /**
     * Puts the $card object under the deck
     * @param \ROR\Card $card
     */
    public function putUnder(Card $card) {
        array_push($this->cards, $card);
    }
    
    /**
     * Draws a card from a deck (reducing its size by 1)<br>
     * The card's property <b>$name</b> must have the value <b>$target</b>
     * @param string $property The property being checked
     * @param mixed $value The value the property must be equal to
     * @return card $card<br> or FALSE if card not found
     */
    public function drawCardWithValue($property , $value) {
        foreach ($this->cards as $key=>$card) {
            if ($card->$property == $value) {
                array_splice($this->cards,$key,1);
                return $card ;
            }
        }
        return FALSE;
    }
    
    public function getIdOfCardWithValue($name , $target) {
        foreach ($this->cards as $key=>$value) {
            if ($value->$name == $target) {
                return $value->id ;
            }
        }
        return FALSE;
    }
    
    /**
     * This function returns the top card of the deck (reducing its size by 1)
     * @return card The top card
     */
    public function drawTopCard() {
        return array_shift($this->cards) ;
    }
    
    public function nbCards() {
        return count($this->cards);
    }
    
    /**
     * Creates a deck from a scenario csv file located in <b>/../../data/scenarios/</b>
     * @param string $scenarioName The name of the scenario
     * @throws Exception
     */
    public function createFromFile($scenarioName) {
        $filePointer = fopen(dirname(__FILE__).'/../../data/scenarios/'.$scenarioName.'.csv', 'r');
        if (!$filePointer) {
            throw new Exception(_('Could not open the file'));
        }
        while (($data = fgetcsv($filePointer, 0, ";")) !== FALSE) {
            if ($data[0]!='') {
                switch ($data[2]) {
                    case 'Family' :
                    case 'Statesman' :
                        $card = new Senator;
                        $card->create($data);
                        break;
                    /*
                     * This is a bit ugly, but the alternative is to have Concessions as Faction cards
                     * This is not very practical, so we create a separate type 'Concession' and
                     * simply make sur that we check both types when looking for Faction cards
                     */
                    case 'Faction' :
                        if ($data[3]==2) {
                            $card = new Concession;
                            $card->create($data);
                        } else {
                            $card = new Card;
                            $card->create($data);
                        }
                        break;
                    case 'Province' :
                        $card = new Province;
                        $card->create($data);
                        break;
                    case 'Conflict' :
                        $card = new Conflict;
                        $card->create($data);
                        break;
                    case 'Leader' :
                        $card = new Leader;
                        $card->create($data);
                        break;
                    default :
                        $card = new Card;
                        $card->create($data);
                        
                }
                $this->putUnder($card);
            }
        }
        fclose($filePointer);
    }
    
}