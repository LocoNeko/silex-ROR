silex-ROR
=========

Republic of Rome - Silex Framework

This project aims at making a fully playable online version of the board game "The Republic of Rome" published by Valley Games (originally by Avalon Hill).

The goal is to have :

- A full implementation of all the game features, without relying on any other tool
- All scenarios and Optional rules available
- In game text-based chat, including secret private chat between players

The tools used are :

- The Silex Framework
- The Twig template engine
- MySQL database (but using PDO so should easily translate to another)
- Jason Grime's SimpleUser for registration and authorisation
- PHPRatchet for WebSockets, so a user's browser is updated as soon as the Game object or the log changes through other users' input. _(e.g. : While you are waiting for other players to take their turn you can only watch the game, but once it's your turn, the interface accepts your inputs)_

The following will probably need to be implemented but is currently out of my skillset :

- Proper ORM like Doctrine 2, so changes to a "Game" object is reflected in the database
