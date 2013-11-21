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

Revenue :
PROPER Revenue Sequence :
---------------------- revenue_Base
- Personal revenues (none for rebel or captive) -> given as a lump sum
- Provincial spoils (+choose if Rome pays if negative or not) -> each senator chooses
- Develop province (NOT for Barbarian raids))
- Rebel maintenance (BEFORE redistribution)
---------------------- revenue_DoRedistribute
- Redistribute SUBPHASE : Redistribution
---------------------- revenue_stateRevenue
- State revenue (includes Rome's Provincial revenues) SUBPHASE : Done after redistribution, no need to create a new one
----------------------
- Contributions SUBPHASE : Contributions ("S" at the end !)
TO DO : Move contributions away from redistribution, create subPhase, create function (modify template as well)
----------------------
- Debits (Maintenance, active conflicts, land bills)
- Returning governors

Correct this :
- Senator taking spoil MAY decide to pay negative values or let Rome do so.
- All provinces revenue for Rome should happen after all senators revenue, because the province might have bedome developed this turn, which changes the income.
- Rebel legions maintenance.
