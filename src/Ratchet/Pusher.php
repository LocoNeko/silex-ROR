<?php
namespace Ratchet;
use Ratchet\ConnectionInterface;
use Ratchet\Wamp\WampServerInterface;

class Pusher implements WampServerInterface {
    // The game_id for this pusher
    protected $game_id ;

    public function onSubscribe(ConnectionInterface $conn, $game_id) {
        // When a visitor subscribes to a game, link the Topic object in a lookup array
        if ($game_id!='' && $game_id!=$this->game_id) {
            $this->game_id = $game_id;
            echo 'onSubscribe('.$game_id.')'.PHP_EOL;
        }
    }
    public function onUnSubscribe(ConnectionInterface $conn, $game_id) {
    }
    public function onOpen(ConnectionInterface $conn) {
    }
    public function onClose(ConnectionInterface $conn) {
    }
    public function onCall(ConnectionInterface $conn, $id, $game_id, array $params) {
        // In this application if clients send data it's because the user hacked around in console
        $conn->callError($id, $game_id, 'You are not allowed to make calls')->close();
    }
    public function onPublish(ConnectionInterface $conn, $game_id, $event, array $exclude, array $eligible) {
        // In this application if clients send data it's because the user hacked around in console
        $conn->close();
    }
    public function onError(ConnectionInterface $conn, \Exception $e) {
    }
    /**
     * @param string JSON'ified string we'll receive from ZeroMQ
     */
    public function onAction($entry) {
        // This will be a game number and list of players
        $entryData = json_decode($entry, true);

        // If the lookup game_id is not right, there is no one to publish to
        if ($entryData['game_id'] != $this->game_id) {
            return;
        }
        if (count($entryData['players'])>0) {
            $listPlayers = implode(',',$entryData['players']);
        } else {
            $listPlayers = 'Everyone.';
        }
        echo date('Y-m-d, G:H:s (O)').' game_id : '.$this->game_id.', From : '.$entryData['from'].', Recipients : '.$listPlayers.PHP_EOL;
        $this->game_id->broadcast($entryData);
    }
}