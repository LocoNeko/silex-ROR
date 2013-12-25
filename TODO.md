TO DO :
- Move display logic away from Twig templates and into the Game object : that's a big overhaul, but could serve me well. Will use this method for the Senate anyway.
- i18n
- Chat, by using the log the following way : recipients = "user_id1:user_id2;user_id3;..." the ':' means this is a message from player user_id1 to a list of other players.
- Update Provinces : land forces and flotillas are lacking
- Variants : Pontifex, 
- Now that getSpecificCard is done, shouldn't it replace SOOO many call to foreach party, foreach senator, foreach card ???

IDEA :
* iFrame page : add "overflow-x:hidden;"

*************
*  Global   *
*************

Ratchet (PHP)
- Follow these instructions : http://socketo.me/docs/push
- Note  on ZMQ install : add /etc/php5/cli/conf.d/20-zmq.ini and restart php5-fpm


I have created/modified 4 files :
- /bin/push-server : This is the server, to be run from the command line. It has the port, the callback function, and calls the PUSHER class
- /Ratchet/Pusher.php : The class called by the server, which subscribes to the connection and has the function onAction($entry) used to broadcast data to all clients
- ROR/Action.php has been modified to send data when anything happens. Currently in the log function, could/should be moved to the Action function
- views/layout.twig has been modified to connect to the web socket, and handle the client-side subscription and action (which should conditionally reload the page)

TO DO : data should be a list of {user_id}. If a user_id is in a list, it means the data just sent has an effect on that user's game state (his screen should be refreshed).

The pusher has a game_id, so only clients connected to this game can push and pull changes affecting it. Basically, other games can go on, this won't affect the state of those clients.

*************
*  Events   *
*************

TO DO - 161;Ally Deserts;Roman Auxiliary Deserts; COMBAT PHASE
*DONE - 162;Allied Enthusiasm;Extreme Allied Enthusiasm; REVENUE PHASE
**80% - 163;Barbarian Raids;Barbarian Raids Increase; REVENUE PHASE
**80% - 164;Drought;Severe Drought;
TO DO - 165;Enemy Leader Dies;Enemy Sues For peace; (END OF) FORUM PHASE
TO DO - 166;Enemy's Ally Deserts;Enemy Mercenaries Desert; COMBAT PHASE
TO DO - 167;Epidemic;Foreign Epidemic; IMMEDIATE
TO DO - 168;Evil Omens;More Evil Omens; IMMEDIATE
**60% - 169;Internal Disorder;Increased Internal Disorder; REVENUE PHASE
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
- Provinces need new propoerties : 'land', 'fleet', and 'frontier'

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

