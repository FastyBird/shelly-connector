<?php declare(strict_types = 1);

/**
 * DiscoveryMessageConsumer.php
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
use FastyBird\Metadata;
use FastyBird\ShellyConnector\Entities;
use FastyBird\ShellyConnector\Helpers;
use Nette;
use Nette\Utils;
use Psr\Log;

/**
 * Device discovery message consumer
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DiscoveryMessageConsumer implements IConsumer
{

	use Nette\SmartObject;
	use TConsumeIpAddress;

	/** @var DevicesModuleModels\Devices\IDevicesManager */
	private DevicesModuleModels\Devices\IDevicesManager $devicesManager;

	/** @var DevicesModuleModels\Devices\Properties\IPropertiesRepository */
	protected DevicesModuleModels\Devices\Properties\IPropertiesRepository $propertiesRepository;

	/** @var DevicesModuleModels\Devices\Properties\IPropertiesManager */
	protected DevicesModuleModels\Devices\Properties\IPropertiesManager $propertiesManager;

	/** @var DevicesModuleModels\DataStorage\IDevicesRepository */
	private DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository;

	/** @var DevicesModuleModels\DataStorage\IDevicePropertiesRepository */
	protected DevicesModuleModels\DataStorage\IDevicePropertiesRepository $propertiesDataStorageRepository;

	/** @var Helpers\DatabaseHelper */
	protected Helpers\DatabaseHelper $databaseHelper;

	/** @var Log\LoggerInterface */
	protected Log\LoggerInterface $logger;

	/**
	 * @param DevicesModuleModels\Devices\IDevicesManager $devicesManager
	 * @param DevicesModuleModels\Devices\Properties\IPropertiesRepository $propertiesRepository
	 * @param DevicesModuleModels\Devices\Properties\IPropertiesManager $propertiesManager
	 * @param DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository
	 * @param DevicesModuleModels\DataStorage\IDevicePropertiesRepository $propertiesDataStorageRepository
	 * @param Helpers\DatabaseHelper $databaseHelper
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		DevicesModuleModels\Devices\IDevicesManager $devicesManager,
		DevicesModuleModels\Devices\Properties\IPropertiesRepository $propertiesRepository,
		DevicesModuleModels\Devices\Properties\IPropertiesManager $propertiesManager,
		DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository,
		DevicesModuleModels\DataStorage\IDevicePropertiesRepository $propertiesDataStorageRepository,
		Helpers\DatabaseHelper $databaseHelper,
		?Log\LoggerInterface $logger = null
	) {
		$this->devicesManager = $devicesManager;
		$this->propertiesRepository = $propertiesRepository;
		$this->propertiesManager = $propertiesManager;

		$this->devicesDataStorageRepository = $devicesDataStorageRepository;
		$this->propertiesDataStorageRepository = $propertiesDataStorageRepository;

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
		if (!$entity instanceof Entities\Messages\DeviceFoundEntity) {
			return false;
		}

		$device = $this->devicesDataStorageRepository->findByIdentifier($entity->getConnector(), $entity->getIdentifier());

		if ($device === null) {
			/** @var DevicesModuleEntities\Devices\IDevice $device */
			$device = $this->databaseHelper->transaction(
				function () use ($entity): DevicesModuleEntities\Devices\IDevice {
					return $this->devicesManager->create(Utils\ArrayHash::from([
						'entity'     => Entities\ShellyDeviceEntity::class,
						'connector'  => $entity->getConnector(),
						'identifier' => $entity->getIdentifier(),
					]));
				}
			);

			$this->logger->info(
				'Creating new device',
				[
					'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
					'type'   => 'discovery-message-consumer',
					'device' => [
						'id'         => $device->getPlainId(),
						'identifier' => $entity->getIdentifier(),
						'address'    => $entity->getIpAddress(),
					],
				]
			);
		}

		$this->setDeviceIpAddress($device->getId(), $entity->getIpAddress());

		$this->logger->debug(
			'Consumed device found message',
			[
				'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type'   => 'discovery-message-consumer',
				'device' => [
					'id' => $device->getId()->toString(),
				],
				'data'   => $entity->toArray(),
			]
		);

		return true;
	}

}
