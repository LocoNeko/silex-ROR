* DELETE GAME
* i18n
* Chat

IDEA :
Maybe drawCardWithValue could be getCardWithValue, and only draw it when necessary (with a boolean flag ?)
* iFrame page : add "overflow-x:hidden;"

CURRENT :
- Forum phase. See /docs for details on bidding
- Files : action_forum.twig

BUG :
- Prevent player from entering the same party name in ViewGame
- Oh noes ! All the cards' numbers are wrong, they start from 0 but should start from 1 !

TO DO : remove events that expire at the beginning of the forum phase
TO DO : Change Early/Middle/Late Republic decks to drawDeck or ForumDeck (but the latter is too easy to mistake with Forum) Maybe simply mainDeck ??

***********
* Revenue *
***********

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
TO DO : Modify template for Contributions
TO DO : Check where to branch revenu finished
----------------------
- Debits (Maintenance, active conflicts, land bills)
- Returning governors
/---------------------------/

TO ADD :
- Rebel legions maintenance.
- Possibility to earn more from some concessions in case of a drought (Sicilian and Egyptian grain)

Modify : Provinces roll revenues :
- Maybe separate senator and Rome revenue function as they are separated.

***********
*  Forum  *
***********

TO DO : finish events (HUGE)
TO DO : persuasion cards