<?php

namespace Testbench;

trait TDoctrine
{

	/**
	 * @return \Kdyby\Doctrine\EntityManager
	 */
	protected function getEntityManager()
	{
		$container = \Testbench\ContainerFactory::create(FALSE);
		/** @var Mocks\DoctrineConnectionMock $connection */
		$connection = $container->getByType('Doctrine\DBAL\Connection');
		if (class_exists(\Kdyby\Doctrine\EntityManager::class)) {
			if (!$connection instanceof Mocks\Kdyby\DoctrineConnectionMock) {
				$serviceNames = $container->findByType('Doctrine\DBAL\Connection');
				throw new \LogicException(sprintf(
						'The service %s should be instance of Testbench\Mocks\Kdyby\DoctrineConnectionMock, to allow lazy schema initialization.',
						reset($serviceNames)
				));
			}
			return $container->getByType('Kdyby\Doctrine\EntityManager');
		} elseif (class_exists(\Nettrine\ORM\EntityManager::class)) {
			if (!$connection instanceof Mocks\Nettrine\DoctrineConnectionMock) {
				$serviceNames = $container->findByType('Doctrine\DBAL\Connection');
				throw new \LogicException(sprintf(
						'The service %s should be instance of Testbench\Mocks\Nettrine\DoctrineConnectionMock, to allow lazy schema initialization.',
						reset($serviceNames)
				));
			}
			return $container->getByType(\Nettrine\ORM\EntityManager::class);
		}
	}
}
