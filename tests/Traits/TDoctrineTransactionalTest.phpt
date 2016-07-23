<?php

namespace Tests\Traits;

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

/**
 * @testCase
 */
class TDoctrineTransactionalTest extends \Tester\TestCase
{

	use \Testbench\TCompiledContainer;
	use \Testbench\TDoctrine;

	public function testDatabaseSqlsRollback()
	{
		/** @var \Testbench\Mocks\DoctrineConnectionMock $connection */
		$connection = $this->getEntityManager()->getConnection();

		$connection->query('INSERT INTO `table_1` (`column_1`, `column_2`) VALUES (\'value_x\', \'value_y\')');

		$result = $connection->query('SELECT * FROM table_1')->fetchAll();

		//see: http://stackoverflow.com/a/14758690/3135248 (why doesn't auto-increment rollback)
		Assert::count(5, $result);
		Assert::same(['id' => '1', 'column_1' => 'value_1', 'column_2' => 'value_2'], $result[0]);
		Assert::same(['id' => '2', 'column_1' => 'value_1', 'column_2' => 'value_2'], $result[1]);
		Assert::same(['id' => '3', 'column_1' => 'value_1', 'column_2' => 'value_2'], $result[2]);
		Assert::same('from_migration_1', $result[3]['column_1']);
		Assert::same('from_migration_2', $result[3]['column_2']);
		Assert::same('value_x', $result[4]['column_1']);
		Assert::same('value_y', $result[4]['column_2']);
		Assert::match('testbench_initial', $connection->getDatabase());
	}

	public function tearDown()
	{
		/** @var \Testbench\Mocks\DoctrineConnectionMock $connection */
		$connection = $this->getEntityManager()->getConnection();
		$connection->rollBack();
		$result = $connection->query('SELECT * FROM table_1')->fetchAll();

		Assert::same([
			['id' => '1', 'column_1' => 'value_1', 'column_2' => 'value_2'],
			['id' => '2', 'column_1' => 'value_1', 'column_2' => 'value_2'],
			['id' => '3', 'column_1' => 'value_1', 'column_2' => 'value_2'],
			[
				'id' => '4',
				'column_1' => 'from_migration_1',
				'column_2' => 'from_migration_2',
			],
		], $result);
		Assert::match('testbench_initial', $connection->getDatabase());
	}

}

(new TDoctrineTransactionalTest)->run();
