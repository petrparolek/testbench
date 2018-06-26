<?php

declare(strict_types=1);

namespace Testbench;

use Tester\TestCase;

class TransactionalTestCase extends TestCase
{
	use TDoctrine {
		getEntityManager as private getEM;
	}

	protected function setUp()
	{
		parent::setUp();
		$this->getEM()->beginTransaction();
	}

	protected function tearDown()
	{
		parent::tearDown();
		$this->getEM()->rollback();
	}

}