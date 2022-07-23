<?php declare(strict_types = 1);

/**
 * DescriptionMessageConsumer.php
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

namespace FastyBird\ShellyConnector\Consumers;

use Doctrine\DBAL;
use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\DevicesModule\Queries as DevicesModuleQueries;
use FastyBird\Metadata;
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
final class DescriptionMessageConsumer implements IConsumer
{

	use Nette\SmartObject;
	use TConsumeIpAddress;

	/** @var DevicesModuleModels\Devices\IDevicesRepository */
	private DevicesModuleModels\Devices\IDevicesRepository $devicesRepository;

	/** @var DevicesModuleModels\Devices\IDevicesManager */
	private DevicesModuleModels\Devices\IDevicesManager $devicesManager;

	/** @var DevicesModuleModels\Devices\Properties\IPropertiesRepository */
	protected DevicesModuleModels\Devices\Properties\IPropertiesRepository $propertiesRepository;

	/** @var DevicesModuleModels\Devices\Properties\IPropertiesManager */
	protected DevicesModuleModels\Devices\Properties\IPropertiesManager $propertiesManager;

	/** @var DevicesModuleModels\Channels\IChannelsManager */
	private DevicesModuleModels\Channels\IChannelsManager $channelsManager;

	/** @var DevicesModuleModels\Channels\Properties\IPropertiesRepository */
	private DevicesModuleModels\Channels\Properties\IPropertiesRepository $channelsPropertiesRepository;

	/** @var DevicesModuleModels\Channels\Properties\IPropertiesManager */
	private DevicesModuleModels\Channels\Properties\IPropertiesManager $channelsPropertiesManager;

	/** @var DevicesModuleModels\DataStorage\IDevicesRepository */
	private DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository;

	/** @var DevicesModuleModels\DataStorage\IDevicePropertiesRepository */
	protected DevicesModuleModels\DataStorage\IDevicePropertiesRepository $propertiesDataStorageRepository;

	/** @var DevicesModuleModels\DataStorage\IChannelsRepository */
	private DevicesModuleModels\DataStorage\IChannelsRepository $channelsDataStorageRepository;

	/** @var Mappers\SensorMapper */
	private Mappers\SensorMapper $sensorMapper;

	/** @var Helpers\DatabaseHelper */
	protected Helpers\DatabaseHelper $databaseHelper;

	/** @var Log\LoggerInterface */
	protected Log\LoggerInterface $logger;

	/**
	 * @param DevicesModuleModels\Devices\IDevicesRepository $devicesRepository
	 * @param DevicesModuleModels\Devices\IDevicesManager $devicesManager
	 * @param DevicesModuleModels\Devices\Properties\IPropertiesRepository $propertiesRepository
	 * @param DevicesModuleModels\Devices\Properties\IPropertiesManager $propertiesManager
	 * @param DevicesModuleModels\Channels\IChannelsManager $channelsManager
	 * @param DevicesModuleModels\Channels\Properties\IPropertiesRepository $channelsPropertiesRepository
	 * @param DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository
	 * @param DevicesModuleModels\DataStorage\IDevicePropertiesRepository $propertiesDataStorageRepository
	 * @param DevicesModuleModels\DataStorage\IChannelsRepository $channelsDataStorageRepository
	 * @param Mappers\SensorMapper $sensorMapper
	 * @param Helpers\DatabaseHelper $databaseHelper
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		DevicesModuleModels\Devices\IDevicesRepository $devicesRepository,
		DevicesModuleModels\Devices\IDevicesManager $devicesManager,
		DevicesModuleModels\Devices\Properties\IPropertiesRepository $propertiesRepository,
		DevicesModuleModels\Devices\Properties\IPropertiesManager $propertiesManager,
		DevicesModuleModels\Channels\IChannelsManager $channelsManager,
		DevicesModuleModels\Channels\Properties\IPropertiesRepository $channelsPropertiesRepository,
		DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository,
		DevicesModuleModels\DataStorage\IDevicePropertiesRepository $propertiesDataStorageRepository,
		DevicesModuleModels\DataStorage\IChannelsRepository $channelsDataStorageRepository,
		Mappers\SensorMapper $sensorMapper,
		Helpers\DatabaseHelper $databaseHelper,
		?Log\LoggerInterface $logger = null
	) {
		$this->devicesRepository = $devicesRepository;
		$this->devicesManager = $devicesManager;
		$this->propertiesRepository = $propertiesRepository;
		$this->propertiesManager = $propertiesManager;
		$this->channelsManager = $channelsManager;
		$this->channelsPropertiesRepository = $channelsPropertiesRepository;

		$this->devicesDataStorageRepository = $devicesDataStorageRepository;
		$this->propertiesDataStorageRepository = $propertiesDataStorageRepository;
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
	public function consume(Entities\Messages\IEntity $entity): bool
	{
		if (!$entity instanceof Entities\Messages\DeviceDescriptionEntity) {
			return false;
		}

		$device = $this->devicesDataStorageRepository->findByIdentifier(
			$entity->getConnector(),
			$entity->getIdentifier()
		);

		if ($device === null) {
			return true;
		}

		if ($device->getName() === null && $device->getName() !== $entity->getType()) {
			/** @var DevicesModuleEntities\Devices\IDevice|null $device */
			$device = $this->databaseHelper->query(function () use ($device): ?DevicesModuleEntities\Devices\IDevice {
				$findDeviceQuery = new DevicesModuleQueries\FindDevicesQuery();
				$findDeviceQuery->byId($device->getId());

				return $this->devicesRepository->findOneBy($findDeviceQuery);
			});

			if ($device === null) {
				return true;
			}

			/** @var DevicesModuleEntities\Devices\IDevice $device */
			$device = $this->databaseHelper->transaction(
				function () use ($entity, $device): DevicesModuleEntities\Devices\IDevice {
					return $this->devicesManager->update($device, Utils\ArrayHash::from([
						'name' => $entity->getType(),
					]));
				}
			);
		}

		$this->setDeviceIpAddress($device->getId(), $entity->getIpAddress());

		foreach ($entity->getBlocks() as $block) {
			$channel = $this->channelsDataStorageRepository->findByIdentifier(
				$device->getId(),
				$block->getIdentifier() . '_' . $block->getDescription()
			);

			if ($channel === null) {
				/** @var DevicesModuleEntities\Channels\IChannel $channel */
				$channel = $this->databaseHelper->transaction(
					function () use ($block, $device): DevicesModuleEntities\Channels\IChannel {
						return $this->channelsManager->create(Utils\ArrayHash::from([
							'device'     => $device->getId(),
							'identifier' => sprintf('%d_%s', $block->getIdentifier(), $block->getDescription()),
							'name'       => $block->getDescription(),
						]));
					}
				);
			}

			foreach ($block->getSensors() as $sensor) {
				$channelProperty = $this->sensorMapper->findProperty(
					$entity->getConnector(),
					$entity->getIdentifier(),
					$sensor->getIdentifier()
				);

				if ($channelProperty === null) {
					/** @var DevicesModuleEntities\Channels\Properties\IProperty $property */
					$property = $this->databaseHelper->transaction(
						function () use ($sensor, $channel): DevicesModuleEntities\Channels\Properties\IProperty {
							return $this->channelsPropertiesManager->create(Utils\ArrayHash::from([
								'channel'    => $channel->getId(),
								'identifier' => sprintf(
									'%d_%s_%s',
									$sensor->getIdentifier(),
									$sensor->getType()->getValue(),
									$sensor->getDescription()
								),
								'name'       => $sensor->getDescription(),
								'unit'       => $sensor->getUnit()?->getValue(),
								'data_type'  => $sensor->getDataType()->getValue(),
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
								'id' => $device->getId()->toString(),
							],
							'channel'  => [
								'id' => $channel->getId()->toString(),
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
										$sensor->getType()->getValue(),
										$sensor->getDescription()
									),
									'name'       => $sensor->getDescription(),
									'unit'       => $sensor->getUnit()?->getValue(),
									'data_type'  => $sensor->getDataType()->getValue(),
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
									'id' => $device->getId()->toString(),
								],
								'channel'  => [
									'id' => $channel->getId()->toString(),
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
									'id' => $device->getId()->toString(),
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
					'id' => $device->getId()->toString(),
				],
				'data'   => $entity->toArray(),
			]
		);

		return true;
	}

}
