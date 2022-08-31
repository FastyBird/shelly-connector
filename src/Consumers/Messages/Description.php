<?php declare(strict_types = 1);

/**
 * Description.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Consumers
 * @since          0.37.0
 *
 * @date           20.07.22
 */

namespace FastyBird\ShellyConnector\Consumers\Messages;

use Doctrine\DBAL;
use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\DevicesModule\Queries as DevicesModuleQueries;
use FastyBird\Metadata;
use FastyBird\ShellyConnector\Consumers\Consumer;
use FastyBird\ShellyConnector\Entities;
use FastyBird\ShellyConnector\Helpers;
use FastyBird\ShellyConnector\Mappers;
use Nette;
use Nette\Utils;
use Psr\Log;

/**
 * Device description message consumer
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Description implements Consumer
{

	use Nette\SmartObject;
	use TConsumeIpAddress;
	use TConsumeDeviceType;

	/** @var DevicesModuleModels\Devices\IDevicesRepository */
	private DevicesModuleModels\Devices\IDevicesRepository $devicesRepository;

	/** @var DevicesModuleModels\Devices\IDevicesManager */
	private DevicesModuleModels\Devices\IDevicesManager $devicesManager;

	/** @var DevicesModuleModels\Devices\Properties\IPropertiesRepository */
	private DevicesModuleModels\Devices\Properties\IPropertiesRepository $propertiesRepository;

	/** @var DevicesModuleModels\Devices\Properties\IPropertiesManager */
	private DevicesModuleModels\Devices\Properties\IPropertiesManager $propertiesManager;

	/** @var DevicesModuleModels\Devices\Attributes\IAttributesRepository */
	private DevicesModuleModels\Devices\Attributes\IAttributesRepository $attributesRepository;

	/** @var DevicesModuleModels\Devices\Attributes\IAttributesManager */
	private DevicesModuleModels\Devices\Attributes\IAttributesManager $attributesManager;

	/** @var DevicesModuleModels\Channels\IChannelsRepository */
	private DevicesModuleModels\Channels\IChannelsRepository $channelsRepository;

	/** @var DevicesModuleModels\Channels\IChannelsManager */
	private DevicesModuleModels\Channels\IChannelsManager $channelsManager;

	/** @var DevicesModuleModels\Channels\Properties\IPropertiesRepository */
	private DevicesModuleModels\Channels\Properties\IPropertiesRepository $channelsPropertiesRepository;

	/** @var DevicesModuleModels\Channels\Properties\IPropertiesManager */
	private DevicesModuleModels\Channels\Properties\IPropertiesManager $channelsPropertiesManager;

	/** @var DevicesModuleModels\DataStorage\IDevicesRepository */
	private DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository;

	/** @var DevicesModuleModels\DataStorage\IDevicePropertiesRepository */
	private DevicesModuleModels\DataStorage\IDevicePropertiesRepository $propertiesDataStorageRepository;

	/** @var DevicesModuleModels\DataStorage\IDeviceAttributesRepository */
	private DevicesModuleModels\DataStorage\IDeviceAttributesRepository $attributesDataStorageRepository;

	/** @var DevicesModuleModels\DataStorage\IChannelsRepository */
	private DevicesModuleModels\DataStorage\IChannelsRepository $channelsDataStorageRepository;

	/** @var Mappers\Sensor */
	private Mappers\Sensor $sensorMapper;

	/** @var Helpers\Database */
	private Helpers\Database $databaseHelper;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param DevicesModuleModels\Devices\IDevicesRepository $devicesRepository
	 * @param DevicesModuleModels\Devices\IDevicesManager $devicesManager
	 * @param DevicesModuleModels\Devices\Properties\IPropertiesRepository $propertiesRepository
	 * @param DevicesModuleModels\Devices\Properties\IPropertiesManager $propertiesManager
	 * @param DevicesModuleModels\Devices\Attributes\IAttributesRepository $attributesRepository
	 * @param DevicesModuleModels\Devices\Attributes\IAttributesManager $attributesManager
	 * @param DevicesModuleModels\Channels\IChannelsRepository $channelsRepository
	 * @param DevicesModuleModels\Channels\IChannelsManager $channelsManager
	 * @param DevicesModuleModels\Channels\Properties\IPropertiesRepository $channelsPropertiesRepository
	 * @param DevicesModuleModels\Channels\Properties\IPropertiesManager $channelsPropertiesManager
	 * @param DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository
	 * @param DevicesModuleModels\DataStorage\IDevicePropertiesRepository $propertiesDataStorageRepository
	 * @param DevicesModuleModels\DataStorage\IDeviceAttributesRepository $attributesDataStorageRepository
	 * @param DevicesModuleModels\DataStorage\IChannelsRepository $channelsDataStorageRepository
	 * @param Mappers\Sensor $sensorMapper
	 * @param Helpers\Database $databaseHelper
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		DevicesModuleModels\Devices\IDevicesRepository $devicesRepository,
		DevicesModuleModels\Devices\IDevicesManager $devicesManager,
		DevicesModuleModels\Devices\Properties\IPropertiesRepository $propertiesRepository,
		DevicesModuleModels\Devices\Properties\IPropertiesManager $propertiesManager,
		DevicesModuleModels\Devices\Attributes\IAttributesRepository $attributesRepository,
		DevicesModuleModels\Devices\Attributes\IAttributesManager $attributesManager,
		DevicesModuleModels\Channels\IChannelsRepository $channelsRepository,
		DevicesModuleModels\Channels\IChannelsManager $channelsManager,
		DevicesModuleModels\Channels\Properties\IPropertiesRepository $channelsPropertiesRepository,
		DevicesModuleModels\Channels\Properties\IPropertiesManager $channelsPropertiesManager,
		DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository,
		DevicesModuleModels\DataStorage\IDevicePropertiesRepository $propertiesDataStorageRepository,
		DevicesModuleModels\DataStorage\IDeviceAttributesRepository $attributesDataStorageRepository,
		DevicesModuleModels\DataStorage\IChannelsRepository $channelsDataStorageRepository,
		Mappers\Sensor $sensorMapper,
		Helpers\Database $databaseHelper,
		?Log\LoggerInterface $logger = null
	) {
		$this->devicesRepository = $devicesRepository;
		$this->devicesManager = $devicesManager;
		$this->propertiesRepository = $propertiesRepository;
		$this->propertiesManager = $propertiesManager;
		$this->attributesRepository = $attributesRepository;
		$this->attributesManager = $attributesManager;
		$this->channelsRepository = $channelsRepository;
		$this->channelsManager = $channelsManager;
		$this->channelsPropertiesRepository = $channelsPropertiesRepository;
		$this->channelsPropertiesManager = $channelsPropertiesManager;

		$this->devicesDataStorageRepository = $devicesDataStorageRepository;
		$this->propertiesDataStorageRepository = $propertiesDataStorageRepository;
		$this->attributesDataStorageRepository = $attributesDataStorageRepository;
		$this->channelsDataStorageRepository = $channelsDataStorageRepository;

		$this->sensorMapper = $sensorMapper;
		$this->databaseHelper = $databaseHelper;

		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBAL\Exception
	 */
	public function consume(Entities\Messages\Entity $entity): bool
	{
		if (!$entity instanceof Entities\Messages\DeviceDescription) {
			return false;
		}

		$deviceItem = $this->devicesDataStorageRepository->findByIdentifier(
			$entity->getConnector(),
			$entity->getIdentifier()
		);

		if ($deviceItem === null) {
			return true;
		}

		if ($deviceItem->getName() === null && $deviceItem->getName() !== $entity->getType()) {
			/** @var DevicesModuleEntities\Devices\IDevice|null $device */
			$device = $this->databaseHelper->query(
				function () use ($deviceItem): ?DevicesModuleEntities\Devices\IDevice {
					$findDeviceQuery = new DevicesModuleQueries\FindDevicesQuery();
					$findDeviceQuery->byId($deviceItem->getId());

					return $this->devicesRepository->findOneBy($findDeviceQuery);
				}
			);

			if ($device === null) {
				return true;
			}

			$this->databaseHelper->transaction(
				function () use ($entity, $device): void {
					$this->devicesManager->update($device, Utils\ArrayHash::from([
						'name' => $entity->getType(),
					]));
				}
			);
		}

		$this->setDeviceIpAddress($deviceItem->getId(), $entity->getIpAddress());
		$this->setDeviceHardwareModel($deviceItem->getId(), $entity->getType());

		foreach ($entity->getBlocks() as $block) {
			$channelItem = $this->channelsDataStorageRepository->findByIdentifier(
				$deviceItem->getId(),
				$block->getIdentifier() . '_' . $block->getDescription()
			);

			if ($channelItem === null) {
				/** @var DevicesModuleEntities\Devices\IDevice|null $device */
				$device = $this->databaseHelper->query(
					function () use ($deviceItem): ?DevicesModuleEntities\Devices\IDevice {
						$findDeviceQuery = new DevicesModuleQueries\FindDevicesQuery();
						$findDeviceQuery->byId($deviceItem->getId());

						return $this->devicesRepository->findOneBy($findDeviceQuery);
					}
				);

				if ($device === null) {
					return true;
				}

				$this->databaseHelper->transaction(function () use ($block, $device): void {
					$this->channelsManager->create(Utils\ArrayHash::from([
						'device'     => $device,
						'identifier' => sprintf('%d_%s', $block->getIdentifier(), $block->getDescription()),
						'name'       => $block->getDescription(),
					]));
				});
			}

			foreach ($block->getSensors() as $sensor) {
				$channelProperty = $this->sensorMapper->findProperty(
					$entity->getConnector(),
					$entity->getIdentifier(),
					$sensor->getIdentifier()
				);

				if ($channelProperty === null) {
					/** @var DevicesModuleEntities\Channels\IChannel|null $channel */
					$channel = $this->databaseHelper->query(
						function () use ($deviceItem, $block): ?DevicesModuleEntities\Channels\IChannel {
							$findChannelQuery = new DevicesModuleQueries\FindChannelsQuery();
							$findChannelQuery->byDeviceId($deviceItem->getId());
							$findChannelQuery->byIdentifier($block->getIdentifier() . '_' . $block->getDescription());

							return $this->channelsRepository->findOneBy($findChannelQuery);
						}
					);

					if ($channel === null) {
						continue;
					}

					/** @var DevicesModuleEntities\Channels\Properties\IProperty $property */
					$property = $this->databaseHelper->transaction(
						function () use ($sensor, $channel): DevicesModuleEntities\Channels\Properties\IProperty {
							return $this->channelsPropertiesManager->create(Utils\ArrayHash::from([
								'channel'    => $channel,
								'entity'     => DevicesModuleEntities\Channels\Properties\DynamicProperty::class,
								'identifier' => sprintf(
									'%d_%s_%s',
									$sensor->getIdentifier(),
									strval($sensor->getType()->getValue()),
									$sensor->getDescription()
								),
								'name'       => $sensor->getDescription(),
								'unit'       => $sensor->getUnit()?->getValue(),
								'dataType'   => $sensor->getDataType(),
								'format'     => $sensor->getFormat(),
								'invalid'    => $sensor->getInvalid(),
								'queryable'  => $sensor->isQueryable(),
								'settable'   => $sensor->isSettable(),
							]));
						}
					);

					$this->logger->debug(
						'Device sensor was created',
						[
							'source'   => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
							'type'     => 'discovery-message-consumer',
							'device'   => [
								'id' => $deviceItem->getId()->toString(),
							],
							'channel'  => [
								'id' => $property->getChannel()->getPlainId(),
							],
							'property' => [
								'id' => $property->getPlainId(),
							],
						]
					);

				} else {
					/** @var DevicesModuleEntities\Channels\Properties\IProperty|null $property */
					$property = $this->databaseHelper->query(
						function () use ($channelProperty): ?DevicesModuleEntities\Channels\Properties\IProperty {
							$findPropertyQuery = new DevicesModuleQueries\FindChannelPropertiesQuery();
							$findPropertyQuery->byId($channelProperty->getId());

							return $this->channelsPropertiesRepository->findOneBy($findPropertyQuery);
						}
					);

					if ($property !== null) {
						/** @var DevicesModuleEntities\Channels\Properties\IProperty $property */
						$property = $this->databaseHelper->transaction(
							function () use ($sensor, $property): DevicesModuleEntities\Channels\Properties\IProperty {
								return $this->channelsPropertiesManager->update($property, Utils\ArrayHash::from([
									'identifier' => sprintf(
										'%d_%s_%s',
										$sensor->getIdentifier(),
										strval($sensor->getType()->getValue()),
										$sensor->getDescription()
									),
									'name'       => $sensor->getDescription(),
									'unit'       => $sensor->getUnit()?->getValue(),
									'dataType'   => $sensor->getDataType(),
									'format'     => $sensor->getFormat(),
									'invalid'    => $sensor->getInvalid(),
									'queryable'  => $sensor->isQueryable(),
									'settable'   => $sensor->isSettable(),
								]));
							}
						);

						$this->logger->debug(
							'Device sensor was updated',
							[
								'source'   => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
								'type'     => 'discovery-message-consumer',
								'device'   => [
									'id' => $deviceItem->getId()->toString(),
								],
								'channel'  => [
									'id' => $property->getChannel()->getPlainId(),
								],
								'property' => [
									'id' => $property->getPlainId(),
								],
							]
						);

					} else {
						$this->logger->error(
							'Device sensor could not be updated',
							[
								'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
								'type'   => 'description-message-consumer',
								'device' => [
									'id' => $deviceItem->getId()->toString(),
								],
								'block'  => [
									'identifier'  => $block->getIdentifier(),
									'description' => $block->getDescription(),
								],
								'sensor' => [
									'identifier'  => $sensor->getIdentifier(),
									'description' => $sensor->getDescription(),
								],
							]
						);
					}
				}
			}
		}

		$this->logger->debug(
			'Consumed device description message',
			[
				'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type'   => 'description-message-consumer',
				'device' => [
					'id' => $deviceItem->getId()->toString(),
				],
				'data'   => $entity->toArray(),
			]
		);

		return true;
	}

}
