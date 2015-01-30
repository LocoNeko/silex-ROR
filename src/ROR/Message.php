<?php

namespace ROR;

/**
 * Description of Message
 *
 */
class Message {
    
    private $text ;
    private $parameters ;
    private $type ;
    private $recipients ;
    private $from ;
    private $time ;
    public static $VALID_TYPES = array ('message' , 'alert' , 'error' , 'chat');
    
    /**
     * @param string $text A string with a sprintf format, including the order of parameters to handle possible mixing because of i18n
     * @param array $parameters An array of values to be used in the text or NULL if the text has no parameters
     * @param string $type message|alert|error|chat
     * @param array $recipients An array of all the recipients user IDs or NULL if everyone
     * @param string $from user_id of the sender of the message, or NULL
     * @param array $playerNames an array of user_id => name with the names of the players in the game for this log, which will allow to :
     *             - Change the text of a chat so it starts with 'A says to B,C & D' or 'A says to everyone'
     *             - Replace players id with names within the message
     */
    function __construct($text , $parameters=NULL , $type='message' , $recipients=NULL , $from=NULL) {
        /* Error if :
         * - Text is empty
         * - Not the same number of vsprintf arguments and elements in the parameters array
         * - Wrong type
         * - recipients has elements that are not number
         * - from is not a number
         * - from is in recipients
         * - from is set but type is not 'chat'
         * - from is not set but type is 'chat'
         * Note : There is no check on whether or not recipients & from are existing user_ids
         */
        $constructError = (strlen($text)==0);
        // TO DO : Not the same number of vsprintf arguments and elements in the parameters array
        if (!in_array($type, $this::VALID_TYPES)) {
            $constructError = TRUE ;
        }
        if ($recipients!=NULL) {
            foreach($recipients as $recipient) {
                if (!is_numeric($recipient)) {
                    $constructError = TRUE ;
                }
            }
        }
        if ($from!=NULL && !is_numeric($from)) {
            $constructError = TRUE ;
        }
        if ($from!=NULL && in_array($from , $recipients)) {
            $constructError = TRUE ;
        }
        if ($type!='chat' && $from!=NULL) {
            $constructError = TRUE ;
        }
        if ($type=='chat' && $from==NULL) {
            $constructError = TRUE ;
        }
        if ($constructError) {
            $this->text = _('Message creation error') ;
            $this->parameters = NULL ;
            $this->type='error' ;
            $this->recipients = NULL ;
            $this->from=NULL ;
        } else {
            $this->text = ($parameters===NULL ? $text : vsprintf($text , $parameters) ) ;
            $this->type = $type ;
            $this->recipients = $recipients ;
            $this->from = $from ;
        }
        $this->playerNames = $playerNames ;
        $this->time = microtime(TRUE) ;
    }
    
    /*
     * A simple mapping from $this->type to a Flash Type
     */
    public function getFlashType() {
        switch($this->type) {
            case 'chat' :
                return 'info' ;
            case  'alert' :
                return 'warning' ;
            case 'error' :
                return 'danger' ;
            default :
                return 'success' ;
        }
    }
    
    public function show($playerNames) {
        $formattedMessage = $this->text ;
        if ($this->type=='chat') {
            $recipientsList = '' ;
            if ($this->recipients===NULL) {
                $recipientsList = 'everyone';
            } else {
                foreach ($this->recipients as $user_id) {
                    $recipientsList.=$playerNames[$user_id].' , ';
                }
                $recipientsList=substr($recipientsList, 0 , -3);
            }
            $formattedMessage = $playerNames[$this->from].' says to '.$recipientsList.' : '.$this->text ;
        }
        return vsprintf($formattedMessage, $this->parameters) ;
    }
    
    public function colour() {
        switch($this->type) {
            case 'chat'     : $result='seagreen' ;  break ;
            case 'alert'    : $result='orange' ;    break ;
            case 'error'    : $result='red' ;       break ;
            default         : $result='indigo' ;
        }
        return $result ;
    }
    
    public function getTime() {
        return $this->time ;
    }
}
