<?php declare(strict_types = 1);

/**
 * ShellyDeviceHydrator.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Hydrators
 * @since          0.1.0
 *
 * @date           22.01.22
 */

namespace FastyBird\ShellyConnector\Hydrators;

use FastyBird\DevicesModule\Hydrators as DevicesModuleHydrators;
use FastyBird\ShellyConnector\Entities;

/**
 * Shelly device entity hydrator
 *
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Hydrators
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 *
 * @phpstan-extends DevicesModuleHydrators\Devices\DeviceHydrator<Entities\IShellyDeviceEntity>
 */
final class ShellyDeviceHydrator extends DevicesModuleHydrators\Devices\DeviceHydrator
{

	/**
	 * {@inheritDoc}
	 */
	public function getEntityName(): string
	{
		return Entities\ShellyDeviceEntity::class;
	}

}
