<?php

class Testbench
{

	const QUICK = 0;
	const FINE = 5;
	const SLOW = 10;

}

if (class_exists('Kdyby\Doctrine\Connection')) { //BC:
	class_alias('Testbench\Mocks\ApplicationRequestMock', 'Testbench\ApplicationRequestMock');
	class_alias('Testbench\Mocks\Kdyby\DoctrineConnectionMock', 'Testbench\ConnectionMock');
	class_alias('Testbench\Mocks\Kdyby\DoctrineConnectionMock', 'Testbench\Mocks\ConnectionMock');
	class_alias('Testbench\Mocks\ControlMock', 'Testbench\ControlMock');
	class_alias('Testbench\Mocks\HttpRequestMock', 'Testbench\HttpRequestMock');
	class_alias('Testbench\Mocks\PresenterMock', 'Testbench\PresenterMock');
} elseif (class_exists('Nettrine\DBAL\ConnectionFactory')) { //BC:
	class_alias('Testbench\Mocks\ApplicationRequestMock', 'Testbench\ApplicationRequestMock');
	class_alias('Testbench\Mocks\Nettrine\DoctrineConnectionMock', 'Testbench\ConnectionMock');
	class_alias('Testbench\Mocks\Nettrine\DoctrineConnectionMock', 'Testbench\Mocks\ConnectionMock');
	class_alias('Testbench\Mocks\ControlMock', 'Testbench\ControlMock');
	class_alias('Testbench\Mocks\HttpRequestMock', 'Testbench\HttpRequestMock');
	class_alias('Testbench\Mocks\PresenterMock', 'Testbench\PresenterMock');
}
