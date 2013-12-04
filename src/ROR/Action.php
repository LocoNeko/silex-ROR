<?php
namespace ROR;

use Silex\Application;

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
function Action($request ,$game_id , $action , $user_id , Application $app) {
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
        $messages = $game->create($game_stmt['name'] , $game_stmt['scenario'], $partyNames , $userNames) ;
        if ($messages !== FALSE) {
            $game_data = serialize($game);
            $app['db']->update('games' , Array ('game_data' => $game_data ) , Array('game_id' => $game_id) );
            // First game save
            $app['db']->insert('saved_games' , Array ('game_id' => $game_id , 'turn' => $game->turn , 'phase' => $game->phase , 'subPhase' => $game->subPhase , 'game_data' => $game_data , 'time_saved' => microtime(TRUE) ) );
            log($app , $game_id , $user_id , $messages ) ;
        } else {
            // TO DO : Failure to create game
        }
    } else {
        $game = unserialize($game_stmt['game_data']);
    }
    /*
     * Second, we handle any action sent through $action and $request
     */
    $currentSubPhase = $game->subPhase ;
    switch ($action) {
        case 'setup_PickLeader' :
            log ($app , $game_id , $user_id , $game->setup_setPartyLeader( $user_id , $request->request->get('senatorID') ));
            break ;
        case 'playStateman' :
            log ($app , $game_id , $user_id , $game->playStateman ( $user_id , $request->request->get('card_id') ) ) ;
            break ;
        case 'playConcession' :
            log ($app , $game_id , $user_id , $game->playConcession ( $user_id , $request->request->get('card_id') , $request->request->get('senator_id') ) ) ;
            break ;
        case 'setup_Finished' :
            log ($app , $game_id , $user_id , $game->setup_Finished($user_id));
            break ;
        case 'revenue_ProvincialSPoils' :
            log ($app , $game_id , $user_id , $game->revenue_ProvincialSpoils( $user_id , $request->request->all() ));
            break ;
        case 'revenue_Redistribution' :
            log ($app , $game_id , $user_id , $game->revenue_Redistribution ($user_id , $request->request->get('fromRaw') , $request->request->get('toRaw') , $request->request->get('amount') ) ) ;
            break ;
        case 'revenue_RedistributionFinished' :
            log ($app , $game_id , $user_id , $game->revenue_RedistributionFinished ($user_id , $request->request->get('fromRaw') , $request->request->get('toRaw') , $request->request->get('amount') ) ) ;
            break ;
        case 'revenue_Contributions' :
            log ($app , $game_id , $user_id , $game->revenue_Contributions ($user_id , $request->request->get('senator') , $request->request->get('amount') ) ) ;
            break ;
        case 'revenue_Finished' :
            log ($app , $game_id , $user_id , $game->revenue_Finished ($user_id) );
            break ;
        case 'forum_bid' :
            log ($app , $game_id , $user_id , $game->forum_bid ($user_id , $request->request->get('senator') , $request->request->get('amount') ) );
            break ;
        case 'forum_rollEvent' :
            log ($app , $game_id , $user_id , $game->forum_rollEvent ($user_id) );
            break ;
        case 'forum_persuasion' :
            log ($app , $game_id , $user_id , $game->forum_persuasion ($user_id , $request->request->get('persuader') , $request->request->get('target') , $request->request->get('amount') , $request->request->get('card') ) );
            break ;
        case 'forum_noPersuasion' :
            log ($app , $game_id , $user_id , $game->forum_noPersuasion ($user_id) );
            break ;
        case 'forum_knights' :
            log ($app , $game_id , $user_id , $game->forum_knights ($user_id , $request->request->get('senator') , $request->request->get('amount') ) );
            break ;
        case 'forum_pressureKnights' :
            log ($app , $game_id , $user_id , $game->forum_pressureKnights ($user_id , $request->request->all()) );
            break ;
        case 'forum_sponsorGames' :
            log ($app , $game_id , $user_id , $game->forum_sponsorGames ($user_id , $request->request->get('senator') , $request->request->get('type') ) );
            break ;
        case 'forum_changeLeader' :
            log ($app , $game_id , $user_id , $game->forum_changeLeader ($user_id , $request->request->get('senatorID') ) );
            break ;
    }
    
    /* 
     * Finally serialize the $game object representing the new game state and store it in the database 
     */
    $game_data = serialize($game);
    $app['db']->update('games' , Array ('game_data' => $game_data ) , Array('game_id' => $game_id) );
    // Save game only if we've just moved to a new subPhase
    if ($game->subPhase != $currentSubPhase) {
        $app['db']->insert('saved_games' , Array ('game_id' => $game_id , 'turn' => $game->turn , 'phase' => ($game->phase == 'Forum' ? 'Forum - Initiative #'.$game->initiative : $game->phase ) , 'subPhase' => $game->subPhase , 'game_data' => $game_data , 'time_saved' => microtime(TRUE) ) );
    }
    // Get the log
    $content['log'] = getLog($app , $game_id , $user_id);
    $content['game'] = $game ;
    return $content;
}

/**
 * Logs one or more messages for a specific game
 * 
 * @param ($app) - The current Silex Application (used to get accees to the database)
 * @param ($game_id) - the game id
 * @param ($logs) - An array of logs which are an array of 'message'[0] , 'type'[1] and 'recipients'[2]
 */
function log ( Application $app , $game_id , $user_id , $logs) {
    foreach ($logs as $log) {
        if (!isset($log[1])) {$log[1]='message';}
        if (!isset($log[2])) {$log[2]=NULL;}
        if ($log[1]=='chat') {$flashType='info' ;}
        elseif ($log[1]=='alert') {$flashType='warning' ;}
        elseif ($log[1]=='error') {$flashType='danger' ;}
        else {$flashType='success' ;}
        // types : 'message','chat','alert','error'
        // translates to flashbag types : 'success' , 'info' , 'warning' , 'danger'
        // TO DO : limit flash bags to messages where $log[2] is NULL or "$user_id" or "$user_id;"
        if ( ($log[2]==NULL) || ($log[2]==$user_id) || (strstr($log[2],$user_id.';')!==FALSE) ) {
            $app['session']->getFlashBag()->add($flashType,$log[0]);
        }
        $app['db']->insert('logs' , Array ('game_id' => $game_id , 'message' => $log[0] , 'type' => $log[1] , 'recipients' => $log[2] , 'time_created' => microtime(TRUE) ) );
    }
}

/**
 * Returns the logs that match the recipients : NULL (everyone) , "$user_id" , "or $userid;" as the ";" is the delimiter for multiple recipients
 * @param \Silex\Application $app
 * @param type $game_id
 * @param type $user_id
 * @return type
 */
function getLog ( Application $app , $game_id , $user_id) {
    return $app['db']->fetchAll("SELECT * FROM logs WHERE game_id='".$game_id."' AND (recipients IS NULL OR recipients='".$user_id."' OR recipients LIKE '%".$user_id.";%')");
}