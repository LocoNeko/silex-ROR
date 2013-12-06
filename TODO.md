TO DO :
- DELETE GAME
- i18n
- Chat
- Prevent player from entering the same party name in ViewGame
- Oh noes ! All the cards' numbers are wrong, they start from 0 but should start from 1 !

IDEA :
* iFrame page : add "overflow-x:hidden;"

*************
*  Global   *
*************

SocketIO :
- Client side : refresh a page if a refresh event is received for the same game id as the one we are now watching
- When should I send refresh events to other clients : when data has been submitted (POST is not empty). Send the refresh to all clients BUT the originating one. Always limit scope to this game id.

*************
*   Setup   *
*************

TO DO :
- Other scenarios

*************
*  Revenue  *
*************

PROPER Revenue Sequence :
---------------------- revenue_Base
- Personal revenues (none for rebel or captive) -> given as a lump sum
- Provincial spoils (+choose if Rome pays if negative or not) -> each senator chooses
- Develop province (NOT for Barbarian raids))
- Rebel maintenance (BEFORE redistribution)
---------------------- revenue_Redistribution
- Redistribute SUBPHASE : Redistribution
- revenue_stateRevenue :
- State revenue (includes Rome''s Provincial revenues) SUBPHASE : Done after redistribution, no need to create a new one
----------------------
- Contributions SUBPHASE : Contributions ("S" at the end !)
----------------------
- Debits (Maintenance, active conflicts, land bills)
- Returning governors
/---------------------------/

TO DO :
- Remove events that expire at the beginning of the forum phase
- Rebel legions maintenance.

Modify : Provinces roll revenues :
- Maybe separate senator and Rome revenue function as they are separated.

*************
*   Forum   *
*************

TO DO :
- Finish events (forum_rollEvent)
- Wars and Leaders don't go to forum
- Persuasion : There may be more Persuasion cards than Seduction and Blackmail
- Confirm blackmail card effects
- Ruin concessions based on Punic War or slave revolt

*************
*Population *
*************

TO DO :
- Confirm evil omens effects on speech
- Effects of the speech roll