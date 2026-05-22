# PDO

Extension of the base PHP PDO class with some added shortcuts and built-in logging for use with O6 Web Properties projects. This was originally part of an internal package. We've opened it up in case it proves helpful for anyone else but future development will be based on O6's needs.

## Usage

```php
use o6web\PDO\Connection;
use o6web\PDO\ConnectionCollection;
use o6web\PDO\PDO;

// Build a collection of connections
$connections = [];

$connections['read'] = new Connection('read', $readDsn, $readUser, $readPassword, $readOptions);
$connections['write'] = new Connection('write', $writeDsn, $writeUser, $writePassword, $writeOptions)

$connectionCollection = new ConnectionCollection($connections);

// Get a collection from the connection
$pdo = $connectionCollection->get('read')->getConnection();

// Instantiate a single PDO connection
$pdo = new PDO($dsn, $user, $password, $options);

// Optional: Set a logger on the connection
$pdo->setLogger($logger);
```

## Installation

Simply add a dependency on o6web/pdo to your composer.json file if you use [Composer](https://getcomposer.org/) to manage the dependencies of your project:

```sh
composer require o6web/pdo
```

Although it's recommended to use Composer, you can actually include the file(s) any way you want.


## License

PDO is [MIT](http://opensource.org/licenses/MIT) licensed.