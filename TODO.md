TO DO :
- Move display logic away from Twig templates and into the Game object : that's a big overhaul, but could serve me well. Will use this method for the Senate anyway.
- i18n
- Chat
- Update Provinces : land forces and flotillas are lacking

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

