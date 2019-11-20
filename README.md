DB management assistance classes

Install:
`composer require linkuha/db:dev-master`

Example of usage:

MySQLi with keeping connected instances. As example can show part of object factory with any DI container.
```
public function __invoke(...)
{
    $configDb = $container->get('config')['db'];
    $configEnv = $container->get('config')['environment'];    // server environment
    
    // ...

    return MysqliManager::getInstance(
        $configResult['db_user'],
        $configResult['db_pass'],
        $configResult['db_name'],
        $configResult['db_host'],
        $configResult['db_port'],
        true
    );
}
```
Next you can get this persistent connection with `$container->build(MysqliManager::class, ["srv" => SERVER_CONSTANT])`


SQL import with PDO. Init migration as example.
```
use Phinx\Migration\AbstractMigration;

class InitMigration extends AbstractMigration
{
    public function up()
    {
        $sqlFilePath = __DIR__ . "/../init.sql";

        $result = PDOSqlFileImporter::tryImport($this->getAdapter()->getConnection(), $sqlFilePath);

        if ($result["status"] === "fail") {
            throw new \Exception($result["status"] . ": " . $result["details"]);
        }
        return true;
    }
...
```

HandlerSocket usage for fast writing (InnoDB as NoSQL).
```
$handlerSocket = HandlerSocketMySql::getInstance($configResult['db_host'], $configResult['db_name']);

$cols = "msg,url,ua,date";
$vals = array($msg, $url, $ua, $date);

$handlerSocket->insert("JsErrorsLog", $cols, $vals);
```