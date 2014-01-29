<?php
namespace ROR;
include 'Action.php';

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use ROR\Game;
use ROR\Action;

class RORControllerProvider implements ControllerProviderInterface
{
    
     public function connect(Application $app)
    {
        // creates a new controller based on the default route
        $controllers = $app['controllers_factory'];

        /*
         * List existing games
         */
        $controllers->get('/ListGames', function(Request $request) use ($app)
        {
            return $app['twig']->render('lobby_gameList.twig', array(
               'layout_template' => 'layout.twig',
               'nb_games' => $this->numberOfGames ($app),
               'games' => $this->listGames($request , $app),
               'saved_games' => $this->listSavedGames($app),
               'is_admin' => in_array('ROLE_ADMIN', $app['user']->getRoles()),
            ));
        })
        ->bind('ListGames');

        /*
         * Join game with $game_id
         */
        $controllers->get('/JoinGame/{game_id}', function($game_id) use ($app)
        {
            $check = $this ->checkJoinGame($app, $game_id) ;
            switch ($check['type']) {
                case 'error' :
                    $app['session']->getFlashBag()->set($check['type'],$check['message']);
                    return $app->redirect($app['url_generator']->generate('ListGames'));
                default :
                    $this->joinGame($app , $game_id);
                    $app['session']->getFlashBag()->set('alert',_('You have joined this game'));
                    return $app->redirect($app['url_generator']->generate('ViewGame' , Array ('game_id' => $game_id )));
            }
        })
        ->bind('JoinGame');
        
        /*
         * View game with $game_id
         * Check what was sent by POST 
         * If start == 0, check if a party name has been sent by POST and if it was not blank
         * If start == 1 , attempt to start the game
         */
        $controllers->match('/ViewGame/{game_id}', function(Request $request , $game_id) use ($app)
        {
            if ($request->isMethod('POST')) {
                if ($request->request->get('start')==0) {
                    if ($this->setPartyName($app , $request , $game_id) ) {
                        $app['session']->getFlashBag()->add('alert',_('You have set your party\'s name'));
                    } else {
                        $app['session']->getFlashBag()->add('alert',_('Party name cannot be blank or the same as an existing party.'));
                    }
                } else {
                    if ($this->startGame($app , $game_id)) {
                        $app['session']->getFlashBag()->add('alert',_('The game has just started.'));
                        return $app->redirect($app['url_generator']->generate('Action' , Array ('game_id' => $game_id )));   
                    }
                }
            }
            return $app['twig']->render('lobby_viewGame.twig', array(
                   'layout_template' => 'layout.twig',
                   'game' => $this->gameDetails($app, $game_id),
                   'players' => $this->listPlayers($app, $game_id),
                ));    
        })
        ->bind('ViewGame');
        
        /*
         * Create game
         *  Upon failure : ListGames
         *  Upon success : ViewGame that was just created
         */
        $controllers->match('/CreateGame/', function(Request $request) use ($app) {
            if ($request->isMethod('POST')) {
                if ($this->createGame($app , $request)) {
                    $app['session']->getFlashBag()->add('alert',sprintf(_('You have created game %s') , $request->request->get('name')));
                } else {
                    $app['session']->getFlashBag()->add('error',sprintf(_('There was an error. Game %s was not created.') , $request->request->get('name')));
                }
                return $app->redirect($app['url_generator']->generate('ListGames'));
            } else {
                return $app['twig']->render('lobby_createGame.twig', array(
                        'layout_template' => 'layout.twig',
                        'valid_scenarios' => Game::$VALID_SCENARIOS,
                        'valid_variants' => Game::$VALID_VARIANTS,
                    ));
            }
        })
        ->bind('CreateGame');
        
        /*
         * Action
         */
        $controllers->match('/Action/{game_id}/{action}', function(Request $request , $game_id , $action) use ($app) {
            $user_id = $app['user']->getId() ;
            /*
             * Handle any action submitted by POST, then display the action page
             */
            $content = Action ( $request , $game_id , $action , $user_id , $app);
            return $app['twig']->render('action_main.twig', Array(
                'layout_template' => 'layout.twig',
                'content' => $content,
                'game_id' => $game_id,
                'user_id' => $user_id,
            ));
        })
        ->value ('action' , NULL )
        ->bind('Action');
        
        /**
         * For debug purposes : Load a saved game
         */
        $controllers->match('/Load/{game_id}', function(Request $request , $game_id) use ($app) {
            // grab game data from saved_games, erase all posterior data from games, insert game_data in games
            if ($request->isMethod('POST')) {
                $time = $request->request->get('SavedGame') ;
                $game_data = $app['db']->fetchColumn("SELECT game_data FROM saved_games WHERE game_id = ? AND time_saved = ?" , Array($game_id , $time) , 0) ;
                // Erase log & saved_games after that timestamp
                $app['db']->query("DELETE FROM logs WHERE game_id = '".$game_id."' AND time_created > ".$time);
                $app['db']->query("DELETE FROM saved_games WHERE game_id = '".$game_id."' AND time_saved > ".$time);
                $app['db']->update("games" , Array("game_data" => $game_data) , Array ("game_id" => $game_id) );
                $app['session']->set('POST' , NULL) ;
                $app['session']->getFlashBag()->add('alert',_('Game loaded'));
                return $app->redirect($app['url_generator']->generate('Action' , Array('game_id' => $game_id) ) );
            } else {
                return $app->redirect($app['url_generator']->generate('ListGames'));
            }
        })
        ->bind('Load');

         /**
         * For debug purposes : Delete a game
         */
        $controllers->match('/Delete/{game_id}', function(Request $request , $game_id) use ($app) {
            if ($request->isMethod('POST')) {
                // Erase log & saved_games after that timestamp
                $app['db']->query("DELETE FROM games WHERE game_id = '".$game_id."'");
                $app['db']->query("DELETE FROM players WHERE game_id = '".$game_id."'");
                $app['db']->query("DELETE FROM logs WHERE game_id = '".$game_id."'");
                $app['db']->query("DELETE FROM saved_games WHERE game_id = '".$game_id."'");
                $app['session']->getFlashBag()->add('alert',_('Game deleted'));
            }
            return $app->redirect($app['url_generator']->generate('ListGames'));
        })
        ->bind('Delete');

        /**
         * The log iframe
         */
        $controllers->match('/Log/{game_id}/{user_id}' , function($game_id , $user_id) use ($app) {
            $logs = getLogs ( $app , $game_id , $user_id) ;
            return $app['twig']->render('action_viewLog.twig', Array(
                'logs' => $logs
            ));
        })
        ->bind('Log');
        
        /*
         * Default route, displays basic layout
         */
        $controllers->get('/', function (Application $app)
        {
            return $app['twig']->render('layout.twig', Array(
                'layout_template' => 'layout.twig'
            ));
        });
        
        
        return $controllers;
    }
    
    public function numberOfGames (Application $app)
    {
        $row = $app['db']->fetchAssoc( "SELECT COUNT(*) AS Total FROM games" , Array() ) ;
        return $row['Total'] ;
    }
    
    public function listGames (Request $request , Application $app)
    {
        $order_by = $request->get('order_by') ?: 'game_id';
        $order_direction = $request->get('order_direction') == 'DESC' ? 'DESC' : 'ASC';
        $sql = "SELECT ga.*,(SELECT COUNT(user_id) FROM players as pl WHERE pl.game_id=ga.game_id) as nbPlayers,(SELECT COUNT(user_id) FROM players WHERE user_id=".$app['user']->getId()." AND game_id=ga.game_id) as alreadyJoined FROM games as ga ORDER BY ".$order_by." ".$order_direction ;
        return $app['db']->fetchAll( $sql , Array() ) ;    
    }
    
    public function checkJoinGame (Application $app , $game_id) {
        $sql = "SELECT COUNT(user_id) as nbPlayers FROM players as pl WHERE pl.game_id='".$game_id."'";
        $row = $app['db']->fetchAssoc( $sql , Array() ) ;
        if ($row['nbPlayers']==6) {
            return Array('type' => 'error' , 'message' => _('This game is full'));
        } else {
            $sql = "SELECT COUNT(user_id) AS alreadyIn FROM players WHERE game_id='".$game_id."' AND user_id = ".$app['user']->getId();
            $row = $app['db']->fetchAssoc( $sql , Array() ) ;
            if ($row['alreadyIn']==1) {
                return Array('type' => 'error' , 'message' => _('You have already joined this game.'));
            } else {
                return Array('type' => 'OK');
            }
        }
    }
    
    public function gameDetails (Application $app , $game_id) {
        $sql = "SELECT * FROM games WHERE game_id = '".$game_id."'" ;
        return $app['db']->fetchAssoc( $sql , Array() ) ;
    }
    
    public function listPlayers (Application $app , $game_id) {
        $sql = "SELECT * FROM players as pl , users as us WHERE pl.user_id = us.id AND pl.game_id ='".$game_id."'" ;
        return $app['db']->fetchAll( $sql , Array() ) ;
    }
    
    public function joinGame(Application $app , $game_id) {
        $app['db']->insert('players' , Array ('game_id' => $game_id , 'user_id' => $app['user']->getId() , 'party_name' => '' , 'time_joined' => time() ));
    }
    
    public function setPartyName(Application $app , Request $request , $game_id) {
        $samePartyName = $app['db']->fetchColumn("SELECT COUNT(user_id) FROM players WHERE game_id='".$game_id."' AND party_name='".$request->request->get('party_name')."'");
        if (strlen($request->request->get('party_name'))>0 && $samePartyName==0 ) {
            $app['db']->update('players', Array('party_name' => $request->request->get('party_name')) , Array('game_id' => $game_id , 'user_id' => $app['user']->getId()));
            return TRUE;
        } else {
            return FALSE;
        }
    }
    
    public function createGame(Application $app , Request $request) {
            $id = substr(md5(uniqid(rand())),0,8) ;
            if (strlen($request->request->get('name'))>0) {
                $name = $request->request->get('name') ;
            } else {
                return FALSE ;
            }
            if (in_array($request->request->get('scenario'), Game::$VALID_SCENARIOS)) {
                $scenario = $request->request->get('scenario') ;
            } else {
                return FALSE ;
            }
            $variantsArray = $request->request->get('variants') ;
            $variantList='';
            foreach ($variantsArray as $variant) {
                if (in_array($variant, Game::$VALID_VARIANTS)) {
                    $variantList.=$variant.',';
                }
            }
            $variantList = substr($variantList,0,-1);
            $app['db']->insert('games', Array( 'game_id' => $id , 'name' => $name , 'time_created' => time() , 'status' => 'Pre-game' , 'scenario' => $scenario , 'variants' => $variantList));
            return TRUE ;
    }
	
    /*
     * startGame : checks if there is 3 to 6 players, if they all have a party's name, and all other basic stuff (valid scenario, status == Pre-game)
     */
    public function startGame(Application $app , $game_id) {
        $playersList = $this->listPlayers ($app , $game_id);
        if (count($playersList)<3) {
            $app['session']->getFlashBag()->add('error',_('The game can\'t start with less than 3 players.'));
            return false;
        }
        foreach ($playersList as $player) {
            if (strlen($player['party_name'])<1) {
                $app['session']->getFlashBag()->add('error' , sprintf(_('The game can\'t start as party name of %s cannot be blank.') , $player['name']));
                return false ;
            }
        }
        $gameDetails = $this-> gameDetails ($app , $game_id) ;
        if (!in_array($gameDetails['scenario'], Game::$VALID_SCENARIOS)) {
            $app['session']->getFlashBag()->add('error' , _('The game can\'t start as the scenario is invalid.') );
            return false ;
        }
        if ($gameDetails['status']!='Pre-game'){
            $app['session']->getFlashBag()->add('error' , _('The game can\'t start as the game status is invalid.') );
            return false ;
        }
        // Everything is fine, we can switch the game status to 'Setup'
        $app['db']->update('games', Array('status' => 'Setup') , Array('game_id' => $game_id) );
        return true;
    }
    
    public function listSavedGames(Application $app) {
        $result = Array();
        $data = $app['db']->fetchAll("SELECT game_id , time_saved , turn , phase , subPhase FROM saved_games") ;
        foreach ($data as $row) {
            if (!isset($result[$row['game_id']])) {
                $result[$row['game_id']] = Array() ;
            }
            array_push ($result[$row['game_id']] , array('time_saved' => $row['time_saved'] , 'turn' => $row['turn'] , 'phase' => $row['phase'] , 'subPhase' => $row['subPhase']) );
        }
        return $result ;
    }

}
