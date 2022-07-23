<?php declare(strict_types = 1);

/**
 * ISensorMapper.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Mappers
 * @since          0.37.0
 *
 * @date           21.07.22
 */

namespace FastyBird\ShellyConnector\Mappers;

use FastyBird\Metadata\Entities as MetadataEntities;
use Ramsey\Uuid;

/**
 * Device sensor to module property mapper interface
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Mappers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface ISensorMapper
{

	/**
	 * @param Uuid\UuidInterface $connector
	 * @param string $deviceIdentifier
	 * @param int $sensorIdentifier
	 *
	 * @return MetadataEntities\Modules\DevicesModule\IChannelDynamicPropertyEntity|null
	 */
	public function findProperty(
		Uuid\UuidInterface $connector,
		string $deviceIdentifier,
		int $sensorIdentifier
	): ?MetadataEntities\Modules\DevicesModule\IChannelDynamicPropertyEntity;

}
