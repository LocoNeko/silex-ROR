TO DO :
- DELETE GAME
- i18n
- Chat
- Prevent player from entering the same party name in ViewGame
- Oh noes ! All the cards' numbers are wrong, they start from 0 but should start from 1 !

IDEA :
* iFrame page : add "overflow-x:hidden;"

TO DO :
- Change Early Republic decks to drawDeck or ForumDeck (isn't the latter too easy to mistake with Forum ?) Maybe simply mainDeck ??

*************
*   Setup   *
*************

TO DO :
- Check what happens of statemanPlayable['message'], it seems unused

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
- Possibility to earn more from some concessions in case of a drought (Sicilian and Egyptian grain)

Modify : Provinces roll revenues :
- Maybe separate senator and Rome revenue function as they are separated.

*************
*   Forum   *
*************

- Forum phase. See /docs for details on bidding
TO DO :
- RE-DO events structure. The key should be event #, for the sake of simplicity, then 'name', 'increased name', 'max level'
- Give the ability to get/play an event either by name or by #
- Finish events (forum_rollEvent)
- Wars and Leaders don't go to forum
- Persuasion : There may be more Persuasion cards than Seduction and Blackmail
- Confirm blackmail card effects
- Ruin concessions based on Punic War or slave revolt
- function forum_putEventInPlay($number) : change this function to accept both name or number

*************
*Population *
*************

TO DO :
- Confirm evil omen effects on speech
- Effects of the speech roll