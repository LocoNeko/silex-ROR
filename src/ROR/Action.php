<?php
namespace ROR;

use Silex\Application;
use ZMQContext;


/*
 * This is where all actions are handled, based on the following context :
 * $request : POST vars
 * $game_id
 * $action
 * $user_id
 */

/**
 * @param ($game_id)
 * 
 */
function Action($request , $game_id , $action , $user_id , Application $app) {
    $content = NULL ;
    /* 
     * First, we get the serialised game data
     * If NULL, it means we have to create it, serialize it, and store it because the game hasn't started
     */
    $game_stmt = $app['db']->fetchAssoc("SELECT * FROM games WHERE game_id= ? " , Array($game_id));
    if (is_null($game_stmt['game_data'])) {
        $partyNames = Array() ;
        $userNames = Array() ;
        $players_list = $app['db']->fetchAll("SELECT user_id , party_name FROM players WHERE game_id= ? ORDER BY time_joined" , Array($game_id));
        foreach ($players_list as $values) {
            $partyNames[$values['user_id']] = $values['party_name'];
            $userNames[$values['user_id']] = $app['user.manager']->getUser($values['user_id'])->getName();
        }
        $game = new Game ;
        $messages = $game->create($game_stmt['name'] , $game_stmt['scenario'], $partyNames , $userNames , $game_stmt['variants']) ;
        if ($messages !== FALSE) {
            $game_data = serialize($game);
            $app['db']->update('games' , Array ('game_data' => $game_data ) , Array('game_id' => $game_id) );
            // First game save
            $app['db']->insert('saved_games' , Array ('game_id' => $game_id , 'turn' => $game->turn , 'phase' => $game->phase , 'subPhase' => $game->subPhase , 'game_data' => $game_data , 'time_saved' => microtime(TRUE) ) );
            // Initialise the $playerNames array, so we can pass it to the log function to properly display logs. (This is ugly as it's done once more below, but can't be bothered)
            $playerNames = array() ;
            foreach ($game->party as $key=>$party) {
                $playerNames[$key] = ($party->user_id==$user_id ? $party->name._(' [you]') : $party->fullName()) ;
            }
            log($app , $game_id , $user_id , $messages , $playerNames) ;
        }
    } else {
        $game = unserialize($game_stmt['game_data']);
    }
    $playerNames = array() ;
    foreach ($game->party as $key=>$party) {
        $playerNames[$key] = ($party->user_id==$user_id ? $party->name._(' [you]') : $party->fullName()) ;
    }

    /*
     * Second, we handle any action sent through $action and $request
     */
    // We have POST DATA
    if ($request->getMethod()=='POST') {
        $session = $request->getSession();
        if (($session->get('POST')!==NULL) && (count($session->get('POST'))>0) && ($session->get('POST') == $request->request->all()) ) {
            $app['session']->getFlashBag()->add('error' , _('Your feeble attempts at re-posting the same data have been thwarted by the shrewdness of the creator of this program.'));
        } else {
            $session->set('POST' , $request->request->all()) ;
            $currentSubPhase = $game->subPhase ;
            $nbOfProposals = count($game->proposals) ;

            switch ($action) {
                case 'setup_PickLeader' :
                    log ($app , $game_id , $user_id , $game->setup_setPartyLeader( $user_id , $request->request->get('senatorID') ) , $playerNames);
                    break ;
                case 'revolution_playStatesman' :
                    log ($app , $game_id , $user_id , $game->revolution_playStatesman ( $user_id , $request->request->get('card_id') )  , $playerNames) ;
                    break ;
                case 'revolution_playConcession' :
                    log ($app , $game_id , $user_id , $game->revolution_playConcession ( $user_id , $request->request->get('card_id') , $request->request->get('senator_id') )  , $playerNames) ;
                    break ;
                case 'setup_Finished' :
                    log ($app , $game_id , $user_id , $game->setup_Finished($user_id) , $playerNames);
                    break ;
                case 'revenue_ProvincialSPoils' :
                    log ($app , $game_id , $user_id , $game->revenue_ProvincialSpoils( $user_id , $request->request->all() ) , $playerNames);
                    break ;
                case 'revenue_Redistribution' :
                    log ($app , $game_id , $user_id , $game->revenue_Redistribution ($user_id , $request->request->get('fromRaw') , $request->request->get('toRaw') , $request->request->get('amount') ) , $playerNames ) ;
                    break ;
                case 'revenue_RedistributionFinished' :
                    log ($app , $game_id , $user_id , $game->revenue_RedistributionFinished ($user_id , $request->request->all()) , $playerNames ) ;
                    break ;
                case 'revenue_Contributions' :
                    log ($app , $game_id , $user_id , $game->revenue_Contributions ($user_id , $request->request->get('senator') , $request->request->get('amount') ) , $playerNames ) ;
                    break ;
                case 'revenue_Finished' :
                    log ($app , $game_id , $user_id , $game->revenue_Finished ($user_id)  , $playerNames);
                    break ;
                case 'forum_bid' :
                    log ($app , $game_id , $user_id , $game->forum_bid ($user_id , $request->request->get('senator') , $request->request->get('amount') ) , $playerNames );
                    break ;
                case 'forum_rollEvent' :
                    log ($app , $game_id , $user_id , $game->forum_rollEvent ($user_id) , $playerNames );
                    break ;
                case 'forum_persuasion' :
                    log ($app , $game_id , $user_id , $game->forum_persuasion ($user_id , $request->request->get('persuader') , $request->request->get('target') , $request->request->get('amount') , $request->request->get('card') )  , $playerNames);
                    break ;
                case 'forum_noPersuasion' :
                    log ($app , $game_id , $user_id , $game->forum_noPersuasion ($user_id)  , $playerNames);
                    break ;
                case 'forum_knights' :
                    log ($app , $game_id , $user_id , $game->forum_knights ($user_id , $request->request->get('senator') , $request->request->get('amount') ) , $playerNames );
                    break ;
                case 'forum_pressureKnights' :
                    log ($app , $game_id , $user_id , $game->forum_pressureKnights ($user_id , $request->request->all()) , $playerNames );
                    break ;
                case 'forum_sponsorGames' :
                    log ($app , $game_id , $user_id , $game->forum_sponsorGames ($user_id , $request->request->get('senator') , $request->request->get('type') ) , $playerNames );
                    break ;
                case 'forum_changeLeader' :
                    log ($app , $game_id , $user_id , $game->forum_changeLeader ($user_id , $request->request->get('senatorID') ) , $playerNames );
                    break ;
                case 'population_speech' :
                    log ($app , $game_id , $user_id , $game->population_speech ($user_id) , $playerNames );
                    break ;
                case 'senate_proposal' :
                    $votingOrder = explode(',', $request->request->get('votingOrder')) ;
                    log ($app , $game_id , $user_id , $game->senate_proposal($user_id , $request->request->get('type') , $request->request->get('description') , $request->request->get('proposalHow') , $request->request->get('parameters')  , $votingOrder ) , $playerNames );
                    break ;
                case 'senate_endProscutions' :
                    log ($app , $game_id , $user_id , $game->senate_endProscutions($user_id) , $playerNames );
                    break ;
                case 'senate_vote' :
                    log ($app , $game_id , $user_id , $game->senate_vote($user_id , $request->request->all() )  , $playerNames);
                    break ;
                case 'senate_appeal' :
                    log ($app , $game_id , $user_id , $game->senate_appeal($user_id)  , $playerNames);
                    break ;
                case 'senate_stepDown' :
                    log ($app , $game_id , $user_id , $game->senate_stepDown($user_id , $request->request->get('stepDown') ) , $playerNames );
                    break ;
                case 'senate_decision' :
                    log ($app , $game_id , $user_id , $game->senate_decision($user_id , $request->request->all() )  , $playerNames);
                    break ;
                case 'senate_assassination' :
                    log ($app , $game_id , $user_id , $game->senate_assassination($user_id)  , $playerNames);
                    break ;
                case 'senate_chooseAssassin' :
                    log ($app , $game_id , $user_id , $game->senate_chooseAssassin($user_id , $request->request->get('target') , $request->request->get('assassin') , $request->request->get('card'))  , $playerNames);
                    break ;
                case 'senate_playBodyguards' :
                    log ($app , $game_id , $user_id , $game->senate_playBodyguards($user_id , $request->request->get('cards'))  , $playerNames);
                    break ;
                case 'senate_adjourn' :
                    log ($app , $game_id , $user_id , $game->senate_adjourn($user_id) , $playerNames );
                    break ;
                case 'other_payRansom' :
                    log ($app , $game_id , $user_id , $game->other_payRansom($user_id , $request->request->all() )  , $playerNames);
                    break ;
                case 'chat' :
                    $recipients = implode(';', $request->request->get('recipients')).';';
                    log ($app , $game_id , $user_id , array(array($request->request->get('message') , 'chat' , $user_id.':'.$recipients)) , $playerNames) ;
                    break ;
            }

            /* 
             * Finally serialize the $game object representing the new game state and store it in the database 
             */
            $game_data = serialize($game);
            $app['db']->update('games' , Array ('game_data' => $game_data ) , Array('game_id' => $game_id) );
            // Save game only if we've just moved to a new subPhase or we have a new proposal during the Senate phase
            if ( ($game->subPhase != $currentSubPhase) && ($game->phase!='Senate') ) {
                $app['db']->insert('saved_games' , Array ('game_id' => $game_id , 'turn' => $game->turn , 'phase' => ($game->phase == 'Forum' ? 'Forum - Initiative #'.$game->initiative : $game->phase ) , 'subPhase' => $game->subPhase , 'game_data' => $game_data , 'time_saved' => microtime(TRUE) ) );
            } elseif ($game->phase=='Senate' && $nbOfProposals!=count($game->proposals) ) {
                $app['db']->insert('saved_games' , Array ('game_id' => $game_id , 'turn' => $game->turn , 'phase' => 'Senate' , 'subPhase' => $game->subPhase.' (Proposal #'.count($game->proposals).')' , 'game_data' => $game_data , 'time_saved' => microtime(TRUE) ) );
            } elseif ($game->phase=='Senate' && $game->subPhase=='Assassination') {
                $app['db']->insert('saved_games' , Array ('game_id' => $game_id , 'turn' => $game->turn , 'phase' => 'Senate' , 'subPhase' => $game->subPhase.' (assassination)' , 'game_data' => $game_data , 'time_saved' => microtime(TRUE) ) );
            }
        }
    }
    $content['game'] = $game ;
    return $content;
}

/**
 * Logs one or more messages for a specific game
 * 
 * @param ($app) - The current Silex Application (used to get accees to the database)
 * @param ($game_id) - the game id
 * @param ($logs) - An array of logs which are an array of 'message'[0] , 'type'[1] and 'recipients'[2]
 * @param ($playerNames) - An array with the full names of parties & players
 */
function log ( Application $app , $game_id , $user_id , $logs , $playerNames) {
    // Do nothing here if $logs is empty. That can happen if an action function returns nothing
    $listOfRecipients = array();
    if (count($logs)>0) {
        foreach ($logs as $log) {
            if (!isset($log[1])) {$log[1]='message';}
            if (!isset($log[2])) {$log[2]=NULL;}
            if ($log[1]=='chat') {$flashType='info' ;}
            elseif ($log[1]=='alert') {$flashType='warning' ;}
            elseif ($log[1]=='error') {$flashType='danger' ;}
            else {$flashType='success' ;}
            /*
             * types : 'message','chat','alert','error'
             * translates to flashbag types : 'success' , 'info' , 'warning' , 'danger'
             * Limit flash bags to messages where $log[2] is NULL or "$user_id" or "$user_id;"
             * - NULL : Is a conveninet way to mean "everyone"
             * - $user_id : only for this user, which means no other user should see anything.
             * - list of $user_id : only for these users
             * - user_id1:user_id2;user_id3;... the ":" means this is a message from a player to a list of other players.
             */
            if ( ($log[2]==NULL) || ($log[2]==$user_id) || (strstr($log[2],$user_id.';')!==FALSE) || (strstr($log[2],$user_id.':')!==FALSE) ) {
                $text = $log[0] ;
                $recipients = $log[2] ;
                foreach ($playerNames as $key => $playerName) {
                    $text = str_replace('{'.$key.'}', $playerName, $text) ;
                    $recipients = str_replace($key, $playerName, $recipients) ;
                }
                if ($log[1] == 'chat') {
                    $recipients = str_replace(':', _(' says to '), $recipients) ;
                    $nbPeople = substr_count($recipients, ';') +1 ;
                    if ($nbPeople==count($playerNames)) {
                        $text = substr ($recipients , 0 , strpos($recipients , _(' says to ')) )._(' says to everyone : "').$text.'"';
                    } else {
                        $recipients = str_replace(';', ' , ', $recipients) ;
                        $recipients = substr($recipients, 0 , -3);
                        $text = $recipients.' : "'.$text.'"' ;
                    }
                }

                $app['session']->getFlashBag()->add($flashType,$text);
            }
            $app['db']->insert('logs' , Array ('game_id' => $game_id , 'message' => $log[0] , 'type' => $log[1] , 'recipients' => $log[2] , 'time_created' => microtime(TRUE) ) );
            // If a message is aimed at all players, set the listOfRecipients to NULL (which means everyone)
            if ($log[2]==NULL) {
                $listOfRecipients = NULL ;
            // Otherwise, check if the listOfRecipients is not already equal to NULL (then we have nothing to do)
            } elseif($listOfRecipients!=NULL) {
                // If it's not, go through the list of recipients for this message and add them to listOfRecipients if they're not already there.
                $explodedList = explode(';', $log[2]) ;
                foreach ($explodedList as $recipient) {
                    if (!in_array($recipient, $listOfRecipients)) {
                        $listOfRecipients[]=$recipient ;
                    }
                }
            }
        }
    }
    // Ratchet web sockets - broadcast update data to all clients
    $entryData = array(
        'game_id'   => $game_id
      , 'from'      => $user_id
      , 'players'   => $listOfRecipients
    );
    $context = new \ZMQContext();
    $socket = $context->getSocket(\ZMQ::SOCKET_PUSH, 'my pusher');
    $socket->connect("tcp://localhost:5555");
    $socket->send(json_encode($entryData));
}

/**
 * Returns a properly formatted log array : user_id are replaced by full names, chat recipients are properly displayd, etc
 * @param \Silex\Application $app
 * @param string $game_id
 * @param string  $user_id
 * @return boolean|array
 */
function getLogs ( Application $app , $game_id , $user_id) {
    $game_data = $app['db']->fetchColumn("SELECT game_data FROM games WHERE game_id= ? " , Array($game_id));
    if (!is_null($game_data)) {
        $game = unserialize($game_data);
    } else {
        return FALSE ;
    }
    $playerNames = array() ;
    foreach ($game->party as $key=>$party) {
        $playerNames[$key] = ($party->user_id==$user_id ? $party->name._(' [you]') : $party->fullName()) ;
    }
    $logs = array() ;
    $rawLogs = $app['db']->fetchAll("SELECT * FROM logs WHERE game_id='".$game_id."' AND (recipients IS NULL OR recipients='".$user_id."' OR recipients LIKE '%".$user_id.";%' OR recipients LIKE '%".$user_id.":%') ORDER BY time_created DESC");
    foreach ($rawLogs as $log) {
        $text = $log['message'] ;
        $recipients = $log['recipients'] ;
        foreach ($playerNames as $key => $playerName) {
            $text = str_replace('{'.$key.'}', $playerName, $text) ;
            $recipients = str_replace($key, $playerName, $recipients) ;
        }
        if ($log['type'] == 'chat') {
            $recipients = str_replace(':', _(' says to '), $recipients) ;
            $recipients = str_replace(';', ' , ', $recipients) ;
            $recipients = substr($recipients, 0 , -3);
            $text = $recipients.' : "'.$text.'"' ;
        }
        array_push($logs , array('type' => $log['type'] , 'time_created' => $log['time_created'] , 'message' => $text));
    }
    return $logs ;
}
