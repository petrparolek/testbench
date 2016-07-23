<?php

namespace Testbench\Mocks;

use Doctrine\Common;
use Doctrine\DBAL;

/**
 * @method onConnect(DoctrineConnectionMock $self)
 */
class DoctrineConnectionMock extends \Kdyby\Doctrine\Connection implements \Testbench\Providers\IDatabaseProvider
{

	/**
	 * @internal
	 * @see https://dev.mysql.com/doc/refman/5.7/en/implicit-commit.html
	 */
	const IMPLICIT_COMMIT_PHRASES = [
		'ALTER',
		'CREATE',
		'DROP',
		'INSTALL',
		'RENAME',
		'TRUNCATE',
		'UNINSTALL',
		'GRANT',
		'REVOKE',
		'SET[\s]+PASSWORD',
		'LOCK',
		'UNLOCK',
		'LOAD',
		'ANALYZE',
		'CACHE',
		'CHECK',
		'FLUSH',
		'OPTIMIZE',
		'REPAIR',
		'RESET',
		'START',
		'STOP',
		'CHANGE',
	];

	private $__testbench_allowImplicitCommit = FALSE;

	private $__testbench_databaseName;

	public $onConnect = [];

	public function connect()
	{
		if (parent::connect()) {
			$this->onConnect($this);
		}
	}

	public function __construct(
		array $params,
		DBAL\Driver $driver,
		DBAL\Configuration $config = NULL,
		Common\EventManager $eventManager = NULL
	) {
		$container = \Testbench\ContainerFactory::create(FALSE);
		$this->onConnect[] = function (DoctrineConnectionMock $connection) use ($container) {
			if (!$connection->getDatabasePlatform() instanceof DBAL\Platforms\MySqlPlatform) {
				throw new \Nette\NotSupportedException('Platform ' . $connection->getDatabasePlatform()->getName() . ' is not supported.');
			}

			try {
				$dataFile = 'nette.safe://' . \Testbench\Bootstrap::$tempDir . '/../databases.testbench';
				if (file_exists($dataFile)) {
					$data = file_get_contents($dataFile);
				} else {
					$data = '';
				}

				$dbName = $container->parameters['testbench']['prefix'] . getenv(\Tester\Environment::THREAD);
				$this->__testbench_databaseName = $dbName;

				$this->__testbench_allowImplicitCommit = TRUE;
				if (!preg_match('~' . $dbName . '~', $data)) { //database doesn't exist in log file
					$handle = fopen($dataFile, 'a+');
					fwrite($handle, $dbName . "\n");
					fclose($handle);

					$this->__testbench_database_setup($connection, $container);
				} else { //database already exists in log file
					$this->__testbench_switch_database($connection, $container);
				}
				$this->__testbench_allowImplicitCommit = FALSE;

				$connection->beginTransaction();
			} catch (\Exception $e) {
				\Tester\Assert::fail($e->getMessage());
			}
		};
		parent::__construct($params, $driver, $config, $eventManager);
	}

	/**
	 * @internal
	 *
	 * @param DoctrineConnectionMock $connection
	 */
	public function __testbench_database_setup($connection, \Nette\DI\Container $container)
	{
		try {
			$this->__testbench_database_create($connection, $container);
		} catch (\Doctrine\DBAL\Exception\DriverException $exc) {
			if ($exc->getErrorCode() === 1007) { //ER_DB_CREATE_EXISTS (delete and create new one)
				$this->__testbench_database_drop($connection, $container);
				$this->__testbench_database_create($connection, $container);
			} else {
				throw $exc;
			}
		}

		$config = $container->parameters['testbench'];

		if (isset($config['sqls'])) {
			foreach ($container->parameters['testbench']['sqls'] as $file) {
				\Kdyby\Doctrine\Dbal\BatchImport\Helpers::loadFromFile($connection, $file);
			}
		}

		if (isset($config['migrations']) && $config['migrations'] === TRUE) {
			if (class_exists(\Zenify\DoctrineMigrations\Configuration\Configuration::class)) {
				/** @var \Zenify\DoctrineMigrations\Configuration\Configuration $migrationsConfig */
				$migrationsConfig = $container->getByType(\Zenify\DoctrineMigrations\Configuration\Configuration::class);
				$migrationsConfig->__construct($container, $connection);
				$migrationsConfig->registerMigrationsFromDirectory($migrationsConfig->getMigrationsDirectory());
				$migration = new \Doctrine\DBAL\Migrations\Migration($migrationsConfig);
				$migration->migrate($migrationsConfig->getLatestVersion());
			}
		}
	}

	/**
	 * @internal
	 *
	 * @param \Kdyby\Doctrine\Connection $connection
	 */
	public function __testbench_switch_database($connection, \Nette\DI\Container $container)
	{
		try {
			$connection->exec("USE {$this->__testbench_databaseName}");
		} catch (\Doctrine\DBAL\Exception\DriverException $exc) {
			if ($exc->getErrorCode() === 1049) { //ER_BAD_DB_ERROR (database doesn't exist but it should)
				$this->__testbench_database_setup($connection, $container);
			} else {
				throw $exc;
			}
		}
	}

	/**
	 * @internal
	 *
	 * @param $connection \Kdyby\Doctrine\Connection
	 */
	public function __testbench_database_create($connection, \Nette\DI\Container $container)
	{
		$connection->exec("CREATE DATABASE {$this->__testbench_databaseName}");
		$this->__testbench_switch_database($connection, $container);
	}

	/**
	 * @internal
	 *
	 * @param $connection \Kdyby\Doctrine\Connection
	 */
	public function __testbench_database_drop($connection, \Nette\DI\Container $container)
	{
		$connection->exec("DROP DATABASE IF EXISTS {$this->__testbench_databaseName}");
	}

	/**
	 * @inheritdoc
	 */
	public function exec($statement)
	{
		$this->validateQuery($statement);
		return parent::exec($statement);
	}

	/**
	 * @inheritdoc
	 */
	public function query()
	{
		$args = func_get_args();
		$this->validateQuery($args[0]);
		return parent::query(...$args);
	}

	/**
	 * @inheritdoc
	 */
	public function prepare($statement)
	{
		$this->validateQuery($statement);
		return parent::prepare($statement);
	}

	/**
	 * @param string $sql
	 */
	private function validateQuery($sql)
	{
		if ($this->__testbench_allowImplicitCommit === TRUE) {
			return;
		}
		$pattern = sprintf('~^[\s]*(%s)~i', implode('|', self::IMPLICIT_COMMIT_PHRASES));
		if (preg_match($pattern, $sql) > 0) {
			$message = sprintf(
				'Cannot run query "%s" because it would cause implicit commit. This is not supported in Testbench because it uses transactional isolation.',
				trim($sql)
			);
			throw new \LogicException($message);
		}
	}

}
