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
 * Action-layout returns all the data that needs to be passed to the action_main.twig template for proper rendering
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
        $players_list = $app['db']->fetchAll("SELECT user_id , party_name FROM players WHERE game_id= ? ORDER BY time_joined" , Array($game_id));
        foreach ($players_list as $values) {
            $partyNames[$values['user_id']] = $values['party_name'];
        }
        $game = new Game ;
        $messages = $game->create($game_stmt['name'] , $game_stmt['scenario'], $partyNames , $app['db']) ;
        if ($messages !== FALSE) {
            $game_data = serialize($game);
            $app['db']->update('games' , Array ('game_data' => $game_data ) , Array('game_id' => $game_id) );
            log($app , $game_id , $messages ) ;
        } else {
            // TO DO : Failure to create game
        }
    } else {
        $game = unserialize($game_stmt['game_data']);
    }
    /*
     * Second, we handle any action sent through $action and $request
     */
    if ($action=='setup_PickLeader') {
        log($app , $game_id , $game->setup_setPartyLeader( $user_id , $request->request->get('senatorID') ));
    } elseif ($action=='playStateman') {
        log ($app , $game_id , $game->playStateman ( $user_id , $request->request->get('card_id') ) ) ;
    } elseif ($action=='playConcession') {
        log ($app , $game_id , $game->playConcession ( $user_id , $request->request->get('card_id') , $request->request->get('senator_id') ) ) ;
    } elseif ($action=='setupFinished') {
        if ( ($game->setup_Finished ( $user_id ) === TRUE) && ($game->phase=='Setup') )  {
            log ($app , $game_id , $game->mortality());
        }
    } elseif ($action=='revenue_ProvincialSPoils') {
        log ($app , $game_id , $game->revenue_ProvincialSpoils( $user_id , $request->request->all() ));
    } elseif ($action=='revenue_Redistribution') {
    }
    
    
    /* 
     * Finally serialize the $game object representing the new game state and store it in the database 
     */
    $game_data = serialize($game);
    $app['db']->update('games' , Array ('game_data' => $game_data ) , Array('game_id' => $game_id) );
    $app['db']->insert('saved_games' , Array ('game_id' => $game_id , 'game_data' => $game_data , 'time_saved' => microtime(TRUE) ) );
    // Get the log
    $content['log'] = getLog($app , $game_id);
    $content['game'] = $game ;
    return $content;
}

/**
 * Logs one or more messages for a specific game
 * If a message includes the substring [%USER_IDXX%], the number XX is replaced by the name of the user with the user_id XX
 * 
 * @param ($app) - The current Silex Application (used to get accees to the database)
 * @param ($game_id) - the game id
 * @param ($message) - An array of messages
 */
function log ( Application $app , $game_id , $messages) {
    foreach ($messages as $value) {
        $pos = strpos ($value , '[%USER_ID') ;
        if ($pos !== FALSE) {
            $pos2 = strpos ($value , '%]' , $pos) ;
            $user_id = (int)substr($value, $pos+9, $pos2-$pos-9);
            $player_name = $app['db']->fetchColumn('SELECT name FROM users WHERE id = ?', array($user_id), 0);
            $value = str_replace('[%USER_ID'.$user_id.'%]', $player_name , $value) ;
        }
        $app['session']->getFlashBag()->add('alert',$value);
        $app['db']->insert('logs' , Array ('game_id' => $game_id , 'message' => $value , 'time_created' => microtime(TRUE) ) );
    }
}

function getLog ( Application $app , $game_id) {
    return $app['db']->fetchAll("SELECT * FROM logs WHERE game_id = '".$game_id."'");
}