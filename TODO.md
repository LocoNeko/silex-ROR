TO DO :
- DELETE GAME
- i18n
- Chat
- Oh noes ! All the cards' numbers are wrong, they start from 0 but should start from 1 ! For Conflicts, effects on provinces are also wrong

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
*   Setup   *
*************

TO DO :
- Other scenarios

*************
*  Revenue  *
*************

TO DO :
- Remove events that expire at the beginning of the forum phase
- Rebel legions maintenance.
- A unaligned Senator might be Governor : handle that case.

*************
*   Forum   *
*************

TO DO :
- Finish events (forum_rollEvent)
- Wars and Leaders don't go to forum
- Ruin concessions based on Punic War or slave revolt
- the whoIsAfter($user_id) function should skip players who are unable to bid. This means the function should be aware of the current subPhase.

*************
*Population *
*************