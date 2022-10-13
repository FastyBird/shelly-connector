<?php declare(strict_types = 1);

namespace Tests\Cases\Unit\DI;

use FastyBird\ShellyConnector\Hydrators;
use FastyBird\ShellyConnector\Schemas;
use Nette;
use Tests\Cases\Unit\BaseTestCase;

final class ServicesTest extends BaseTestCase
{

	/**
	 * @throws Nette\DI\MissingServiceException
	 */
	public function testServicesRegistration(): void
	{
		$container = $this->createContainer();

		self::assertNotNull($container->getByType(Schemas\ShellyDevice::class, false));
		self::assertNotNull($container->getByType(Schemas\ShellyConnector::class, false));

		self::assertNotNull($container->getByType(Hydrators\ShellyDevice::class, false));
		self::assertNotNull($container->getByType(Hydrators\ShellyConnector::class, false));
	}

}
