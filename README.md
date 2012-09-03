D3Parser
=========
D3Parser is a PHP class that can parse the new Battle.net HTML armory like profile pages. The aim of this library is to provide a class that is simple and efficient in gathering raw data from the new Battle.net armory profile pages. Since Blizzard has decided to deprecate XML, this library is meant to provide a basis for new tools.

Requirements
------------
D3Parser required the [Simple DOM PHP Parser](http://simplehtmldom.sourceforge.net/) to be available. You can configure the path to the library by editing the definition of the script.

Available Features
--------
* Supports all known armory configurations
 * All profile data (career information) is parsed
* Uses cURL to get pages
* Can set User-Agent in requests
* Caches to static class methods
* Allows for profile storage 
 * Saves profiles to JSON encoded file
 * Supports TTL to avoid re-querying

Coming Features
----------------
* Hero data
 * Hero stats
 * Hero items
 * Item caching
* SQL storage
 * MySQL 
 * SQLite 
