<?php

namespace Testbench\Mocks\Nettrine;

use Doctrine\Common;
use Doctrine\DBAL;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Nette\SmartObject;

/**
 * @method onConnect(DoctrineConnectionMock $self)
 */
class DoctrineConnectionMock extends DBAL\Connection implements \Testbench\Providers\IDatabaseProvider
{

	use SmartObject;

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
	)
	{
		$container = \Testbench\ContainerFactory::create(FALSE);
		$this->onConnect[] = function (DoctrineConnectionMock $connection) use ($container) {
			if ($this->__testbench_databaseName !== NULL) { //already initialized (needed for pgsql)
				return;
			}
			try {
				$config = $container->parameters['testbench'];
				if ($config['shareDatabase'] === TRUE) {
					$registry = new \Testbench\DatabasesRegistry;
					$dbName = $container->parameters['testbench']['dbprefix'] . getenv(\Tester\Environment::THREAD);
					if ($registry->registerDatabase($dbName)) {
						$this->__testbench_database_setup($connection, $container, TRUE);
					} else {
						$this->__testbench_databaseName = $dbName;
						$this->__testbench_database_change($connection, $container);
					}
				} else { // always create new test database
					$this->__testbench_database_setup($connection, $container);
				}
			} catch (\Exception $e) {
				\Tester\Assert::fail($e->getMessage());
			}
		};
		parent::__construct($params, $driver, $config, $eventManager);
	}

	/** @internal
	 * @throws DBAL\Migrations\MigrationException
	 */
	public function __testbench_database_setup($connection, \Nette\DI\Container $container, $persistent = FALSE)
	{
		$config = $container->parameters['testbench'];
		$this->__testbench_databaseName = $config['dbprefix'] . getenv(\Tester\Environment::THREAD);
		$this->__testbench_database_drop($connection, $container);
		$this->__testbench_database_create($connection, $container);

		foreach ($config['sqls'] as $file) {
			\Kdyby\Doctrine\Dbal\BatchImport\Helpers::loadFromFile($connection, $file);
		}

		if ($config['migrations'] === TRUE) {
			if (class_exists(\Nettrine\Migrations\ContainerAwareConfiguration::class)) {
				/** @var \Nettrine\Migrations\ContainerAwareConfiguration $migrationsConfig */
				$migrationsConfig = $container->getByType(\Nettrine\Migrations\ContainerAwareConfiguration::class);
				$migrationsConfig->__construct($connection);
				$migrationsConfig->registerMigrationsFromDirectory($migrationsConfig->getMigrationsDirectory());
				$migration = new \Doctrine\DBAL\Migrations\Migration($migrationsConfig);
				$migration->migrate($migrationsConfig->getLatestVersion());
			} else if (interface_exists(\Nextras\Migrations\IConfiguration::class)) {
				$config = $container->getByType(\Nextras\Migrations\IConfiguration::class);
				$finder = new \Nextras\Migrations\Engine\Finder();
				$extensions = [1 => 'sql'];
				$migrations = $finder->find($config->getGroups(), $extensions);

				foreach ($migrations as $migration) {
					\Kdyby\Doctrine\Dbal\BatchImport\Helpers::loadFromFile($connection, $migration->path);
				}
			}
		}

		if ($persistent === FALSE) {
			register_shutdown_function(function () use ($connection, $container) {
				$this->__testbench_database_drop($connection, $container);
			});
		}
	}

	/**
	 * @internal
	 *
	 * @param $connection DBAL\Connection
	 */
	public function __testbench_database_create($connection, \Nette\DI\Container $container)
	{
		$connection->exec("CREATE DATABASE {$this->__testbench_databaseName}");
		$this->__testbench_database_change($connection, $container);
	}

	/**
	 * @internal
	 *
	 * @param $connection DBAL\\Connection
	 */
	public function __testbench_database_change($connection, \Nette\DI\Container $container)
	{
		if ($connection->getDatabasePlatform() instanceof MySqlPlatform) {
			$connection->exec("USE {$this->__testbench_databaseName}");
		} else {
			$this->__testbench_database_connect($connection, $container, $this->__testbench_databaseName);
		}
	}

	/**
	 * @internal
	 *
	 * @param $connection DBAL\\Connection
	 */
	public function __testbench_database_drop($connection, \Nette\DI\Container $container)
	{
		if (!$connection->getDatabasePlatform() instanceof MySqlPlatform) {
			$this->__testbench_database_connect($connection, $container);
		}
		$connection->exec("DROP DATABASE IF EXISTS {$this->__testbench_databaseName}");
	}

	/**
	 * @internal
	 *
	 * @param $connection DBAL\Connection
	 */
	public function __testbench_database_connect($connection, \Nette\DI\Container $container, $databaseName = NULL)
	{
		//connect to an existing database other than $this->_databaseName
		if ($databaseName === NULL) {
			$dbname = $container->parameters['testbench']['dbname'];
			if ($dbname) {
				$databaseName = $dbname;
			} elseif ($connection->getDatabasePlatform() instanceof PostgreSqlPlatform) {
				$databaseName = 'postgres';
			} else {
				throw new \LogicException('You should setup existing database name using testbench:dbname option.');
			}
		}

		$connection->close();
		$connection->__construct(
			['dbname' => $databaseName] + $connection->getParams(),
			$connection->getDriver(),
			$connection->getConfiguration(),
			$connection->getEventManager()
		);
		$connection->connect();
	}
}
