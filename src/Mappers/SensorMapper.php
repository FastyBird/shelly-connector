<?php declare(strict_types = 1);

/**
 * SensorMapper.php
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

use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\ShellyConnector;
use Nette;
use Ramsey\Uuid;

/**
 * Device sensor to module property mapper
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Mappers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class SensorMapper implements ISensorMapper
{

	use Nette\SmartObject;

	/** @var Array<string, Uuid\UuidInterface> */
	private array $sensorsToProperties = [];

	/** @var DevicesModuleModels\DataStorage\IDevicesRepository */
	private DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository;

	/** @var DevicesModuleModels\DataStorage\IChannelsRepository */
	private DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository;

	/** @var DevicesModuleModels\DataStorage\IChannelPropertiesRepository */
	private DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelPropertiesRepository;

	/**
	 * @param DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository
	 * @param DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository
	 * @param DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelPropertiesRepository
	 */
	public function __construct(
		DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository,
		DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository,
		DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelPropertiesRepository
	) {
		$this->devicesRepository = $devicesRepository;
		$this->channelsRepository = $channelsRepository;
		$this->channelPropertiesRepository = $channelPropertiesRepository;
	}

	/**
	 * {@inheritDoc}
	 */
	public function findProperty(
		Uuid\UuidInterface $connector,
		string $deviceIdentifier,
		int $sensorIdentifier
	): ?MetadataEntities\Modules\DevicesModule\IChannelDynamicPropertyEntity {
		$key = $deviceIdentifier . '-' . $sensorIdentifier;

		if (array_key_exists($key, $this->sensorsToProperties)) {
			$property = $this->channelPropertiesRepository->findById($this->sensorsToProperties[$key]);

			if ($property instanceof MetadataEntities\Modules\DevicesModule\IChannelDynamicPropertyEntity) {
				return $property;
			}
		}

		$property = $this->loadProperty($connector, $deviceIdentifier, $sensorIdentifier);

		if ($property !== null) {
			$this->sensorsToProperties[$key] = $property->getId();
		}

		return $property;
	}

	/**
	 * @param Uuid\UuidInterface $connector
	 * @param string $deviceIdentifier
	 * @param int $sensorIdentifier
	 *
	 * @return MetadataEntities\Modules\DevicesModule\IChannelDynamicPropertyEntity|null
	 */
	private function loadProperty(
		Uuid\UuidInterface $connector,
		string $deviceIdentifier,
		int $sensorIdentifier
	): ?MetadataEntities\Modules\DevicesModule\IChannelDynamicPropertyEntity {
		$device = $this->devicesRepository->findByIdentifier($connector, $deviceIdentifier);

		if ($device === null) {
			return null;
		}

		$channels = $this->channelsRepository->findAllByDevice($device->getId());

		foreach ($channels as $channel) {
			$properties = $this->channelPropertiesRepository->findAllByChannel($channel->getId());

			foreach ($properties as $property) {
				if (
					preg_match(ShellyConnector\Constants::GEN_1_PROPERTY_SENSOR, $property->getIdentifier(), $propertyMatches) === 1
					&& array_key_exists('identifier', $propertyMatches)
					&& array_key_exists('type', $propertyMatches)
					&& array_key_exists('description', $propertyMatches)
					&& intval($propertyMatches['identifier']) === $sensorIdentifier
				) {
					if ($property instanceof MetadataEntities\Modules\DevicesModule\IChannelDynamicPropertyEntity) {
						return $property;
					}

					return null;
				}
			}
		}

		return null;
	}

}
