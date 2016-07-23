<?php

namespace Tests\Traits;

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

/**
 * @testCase
 */
class TDoctrineTest extends \Tester\TestCase
{

	use \Testbench\TCompiledContainer;
	use \Testbench\TDoctrine;

	public function testLazyConnection()
	{
		$container = $this->getContainer();
		$db = $container->getByType('Doctrine\DBAL\Connection');
		$db->onConnect[] = function () {
			Assert::fail('\Testbench\ConnectionMock::$onConnect event should not be called if you do NOT need database');
		};
		\Tester\Environment::$checkAssertions = FALSE;
	}

	public function testEntityManager()
	{
		Assert::type('\Doctrine\ORM\EntityManagerInterface', $this->getEntityManager());
	}

	public function testDatabaseCreation()
	{
		/** @var \Testbench\Mocks\DoctrineConnectionMock $connection */
		$connection = $this->getEntityManager()->getConnection();
		Assert::match('testbench_initial', $connection->getDatabase());
		Assert::truthy(preg_match('~testbench_[1-8]~', $connection->query('SELECT DATABASE();')->fetchColumn()));
	}

	public function testDatabaseConnectionReplacementInApp()
	{
		/** @var \Kdyby\Doctrine\EntityManager $em */
		$em = $this->getService(\Kdyby\Doctrine\EntityManager::class);
		new \DoctrineComponentWithDatabaseAccess($em); //tests inside
		//app is not using onConnect from Testbench but it has to connect to the mock database
	}

	public function testImplicitCommit()
	{
		/** @var \Testbench\Mocks\DoctrineConnectionMock $connection */
		$connection = $this->getEntityManager()->getConnection();
		Assert::exception(
			function () use ($connection) {
				$connection->query(' TRUNCATE TABLE table_1');
			},
			\LogicException::class,
			'Cannot run query "TRUNCATE TABLE table_1" because it would cause implicit commit. This is not supported in Testbench because it uses transactional isolation.'
		);
	}

}

(new TDoctrineTest)->run();
