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
    
    public function putOnTop(Card $card) {
        array_unshift($this->cards, $card) ;
    }
    
    public function putUnder(Card $card) {
        array_push($this->cards, $card);
    }
    
    /*
     * 
     * @param name : name of the Card's property to check
     * @param target : value to find
     * @return Card
     */
    public function drawCardWithValue($name , $target) {
        foreach ($this->cards as $key=>$value) {
            if ($value->$name == $target) {
                array_splice($this->cards,$key,1);
                return $value ;
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
    
    public function drawTopCard() {
        return array_shift($this->cards) ;
    }
    
    public function createFromFile($scenarioName) {
        $filePointer = fopen(dirname(__FILE__).'/../../data/scenarios/'.$scenarioName.'.csv', 'r');
        if (!$filePointer) {
            throw new Exception("Could not open the file");
        }
        while (($data = fgetcsv($filePointer, 0, ";")) !== FALSE) {
            if ($data[0]!='') {
                switch ($data[2]) {
                    case 'Family' :
                    case 'Stateman' :
                        $card = new Senator;
                        $card->create($data);
                        break;
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