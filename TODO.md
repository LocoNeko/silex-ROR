TO DO :
- Move display logic away from Twig templates and into the Game object : that's a big overhaul, but could serve me well. Will use this method for the Senate anyway.
- i18n
- Chat, by using the log the following way : recipients = "user_id1:user_id2;user_id3;..." the ':' means this is a message from player user_id1 to a list of other players.
- Update Provinces : land forces and flotillas are lacking
- Variants : Pontifex, 

IDEA :
* iFrame page : add "overflow-x:hidden;"

*************
*  Global   *
*************

SocketIO :
- Client side : refresh a page if a refresh event is received for the same game id as the one we are now watching
- When should I send refresh events to other clients : when data has been submitted (POST is not empty).
Send the refresh to all clients BUT the originating one. Always limit scope to this game id.
This is done with :
// sending to all clients except sender
socket.broadcast.emit('message', "this is a test");
// sending to all clients in 'game' room(channel) except sender
socket.broadcast.to('game').emit('message', 'nice game');

Or use Ratchet (PHP)
- Note  on ZMQ install : add /etc/php5/cli/conf.d/20-zmq.ini and restart php5-fpm

I will need 4 files :
- One file has some Javascript, using when.js an autobhan.js This file handles what to do when a notification is received from the server, which in my case means it should refresh the display.
In other words, this file is the MAIN VIEW
- One file is the push server, to be run from the command line, let's call it PUSH-SERVER.PHP, it has the port, the callback function, and calls the PUSHER class
- The PUSHER class, that broadcasts what is received.
- Finally, the post page will have some javascript that will push data to the websocket server.

In my case, data should be a list of {user_id}. If a user_id is in a list, it means the data just sent has an effect on that user's game state, so he should see some change,
which means, the screen should be refreshed.

My "Topic" should be a game ID, so only clients connected to this game can push and pull changes affecting it. Basically, other games can go on, this won't affect the state of those clients.

That should be it.

*************
*  Events   *
*************

TO DO - 161;Ally Deserts;Roman Auxiliary Deserts; COMBAT PHASE
DONE  - 162;Allied Enthusiasm;Extreme Allied Enthusiasm; REVENUE PHASE
TO DO - 163;Barbarian Raids;Barbarian Raids Increase; REVENUE PHASE
TO DO - 164;Drought;Severe Drought;
TO DO - 165;Enemy Leader Dies;Enemy Sues For peace; (END OF) FORUM PHASE
TO DO - 166;Enemy's Ally Deserts;Enemy Mercenaries Desert; COMBAT PHASE
TO DO - 167;Epidemic;Foreign Epidemic; IMMEDIATE
TO DO - 168;Evil Omens;More Evil Omens; IMMEDIATE
TO DO - 169;Internal Disorder;Increased Internal Disorder; REVENUE PHASE
TO DO - 170;Manpower Shortage;Increased Manpower Shortage; SENATE PHASE
TO DO - 171;Mob Violence;More Mob Violence; IMMEDIATE
TO DO - 172;Natural Disaster;Widespread Natural Disaster; IMMEDIATE
TO DO - 173;New Alliance;Another New Alliance; (END OF)SENATE PHASE
TO DO - 174;Pretender Emerges;Pretender Victorious; NEXT ACTIVE WAR
TO DO - 175;Refuge;Rise From Refuge; COMBAT PHASE
TO DO - 176;Rhodian Maritime Alliance; IMMEDIATE (can be rejected during SENATE PHASE)
TO DO - 177;Storm At Sea;Another Storm At Sea; IMMEDIATE
TO DO - 178;Trial Of Verres -70BC;Another Corruption Trial; REVENUE PHASE

*************
*   Setup   *
*************

TO DO :
- Other scenarios
- Oh noes ! All the cards' numbers are wrong, they start from 0 but should start from 1 ! For Conflicts, effects on provinces are also wrong
- Provinces need a "Frontier" property

*************
*  Revenue  *
*************

TO DO :
- Remove events that expire at the beginning of the forum phase
- Rebel legions maintenance.

*************
*   Forum   *
*************

TO DO :
- Finish events (forum_rollEvent)
- Wars and Leaders don't go to forum
- Ruin concessions based on Punic War or slave revolt

*************
*Population *
*************

************
*  Senate  *
************

