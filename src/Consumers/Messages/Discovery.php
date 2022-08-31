<?php declare(strict_types = 1);

/**
 * Discovery.php
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
use FastyBird\ShellyConnector\Types;
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
final class Discovery implements Consumer
{

	use Nette\SmartObject;
	use TConsumeDeviceAttribute;
	use TConsumeDeviceProperty;

	/** @var DevicesModuleModels\Connectors\IConnectorsRepository */
	private DevicesModuleModels\Connectors\IConnectorsRepository $connectorsRepository;

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

	/** @var DevicesModuleModels\DataStorage\IDevicesRepository */
	private DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository;

	/** @var DevicesModuleModels\DataStorage\IDevicePropertiesRepository */
	private DevicesModuleModels\DataStorage\IDevicePropertiesRepository $propertiesDataStorageRepository;

	/** @var DevicesModuleModels\DataStorage\IDeviceAttributesRepository */
	private DevicesModuleModels\DataStorage\IDeviceAttributesRepository $attributesDataStorageRepository;

	/** @var Helpers\Database */
	private Helpers\Database $databaseHelper;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param DevicesModuleModels\Connectors\IConnectorsRepository $connectorsRepository
	 * @param DevicesModuleModels\Devices\IDevicesRepository $devicesRepository
	 * @param DevicesModuleModels\Devices\IDevicesManager $devicesManager
	 * @param DevicesModuleModels\Devices\Properties\IPropertiesRepository $propertiesRepository
	 * @param DevicesModuleModels\Devices\Properties\IPropertiesManager $propertiesManager
	 * @param DevicesModuleModels\Devices\Attributes\IAttributesRepository $attributesRepository
	 * @param DevicesModuleModels\Devices\Attributes\IAttributesManager $attributesManager
	 * @param DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository
	 * @param DevicesModuleModels\DataStorage\IDevicePropertiesRepository $propertiesDataStorageRepository
	 * @param DevicesModuleModels\DataStorage\IDeviceAttributesRepository $attributesDataStorageRepository
	 * @param Helpers\Database $databaseHelper
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		DevicesModuleModels\Connectors\IConnectorsRepository $connectorsRepository,
		DevicesModuleModels\Devices\IDevicesRepository $devicesRepository,
		DevicesModuleModels\Devices\IDevicesManager $devicesManager,
		DevicesModuleModels\Devices\Properties\IPropertiesRepository $propertiesRepository,
		DevicesModuleModels\Devices\Properties\IPropertiesManager $propertiesManager,
		DevicesModuleModels\Devices\Attributes\IAttributesRepository $attributesRepository,
		DevicesModuleModels\Devices\Attributes\IAttributesManager $attributesManager,
		DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository,
		DevicesModuleModels\DataStorage\IDevicePropertiesRepository $propertiesDataStorageRepository,
		DevicesModuleModels\DataStorage\IDeviceAttributesRepository $attributesDataStorageRepository,
		Helpers\Database $databaseHelper,
		?Log\LoggerInterface $logger = null
	) {
		$this->connectorsRepository = $connectorsRepository;
		$this->devicesRepository = $devicesRepository;
		$this->devicesManager = $devicesManager;
		$this->propertiesRepository = $propertiesRepository;
		$this->propertiesManager = $propertiesManager;
		$this->attributesRepository = $attributesRepository;
		$this->attributesManager = $attributesManager;

		$this->devicesDataStorageRepository = $devicesDataStorageRepository;
		$this->propertiesDataStorageRepository = $propertiesDataStorageRepository;
		$this->attributesDataStorageRepository = $attributesDataStorageRepository;

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
		if (!$entity instanceof Entities\Messages\DeviceFound) {
			return false;
		}

		$deviceItem = $this->devicesDataStorageRepository->findByIdentifier(
			$entity->getConnector(),
			$entity->getIdentifier()
		);

		if ($deviceItem === null) {
			$connectorEntity = $this->databaseHelper->query(
				function () use ($entity): ?DevicesModuleEntities\Connectors\IConnector {
					$findConnectorQuery = new DevicesModuleQueries\FindConnectorsQuery();
					$findConnectorQuery->byId($entity->getConnector());

					return $this->connectorsRepository->findOneBy($findConnectorQuery);
				}
			);

			if ($connectorEntity === null) {
				return true;
			}

			/** @var Entities\ShellyDevice $deviceEntity */
			$deviceEntity = $this->databaseHelper->transaction(
				function () use ($entity, $connectorEntity): Entities\ShellyDevice {
					/** @var Entities\ShellyDevice $deviceEntity */
					$deviceEntity = $this->devicesManager->create(Utils\ArrayHash::from([
						'entity'     => Entities\ShellyDevice::class,
						'connector'  => $connectorEntity,
						'identifier' => $entity->getIdentifier(),
					]));

					return $deviceEntity;
				}
			);

			$this->logger->info(
				'Creating new device',
				[
					'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
					'type'   => 'discovery-message-consumer',
					'device' => [
						'id'         => $deviceEntity->getPlainId(),
						'identifier' => $entity->getIdentifier(),
						'address'    => $entity->getIpAddress(),
					],
				]
			);
		}

		$deviceItem = $this->devicesDataStorageRepository->findByIdentifier(
			$entity->getConnector(),
			$entity->getIdentifier()
		);

		if ($deviceItem === null) {
			$this->logger->error(
				'Newly created device could not be loaded',
				[
					'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
					'type'   => 'discovery-message-consumer',
					'device' => [
						'identifier' => $entity->getIdentifier(),
						'address'    => $entity->getIpAddress(),
					],
				]
			);

			return true;
		}

		$this->setDeviceProperty(
			$deviceItem->getId(),
			$entity->getIpAddress(),
			Types\DevicePropertyIdentifier::IDENTIFIER_IP_ADDRESS
		);
		$this->setDeviceAttribute(
			$deviceItem->getId(),
			$entity->getType(),
			Types\DeviceAttributeIdentifier::IDENTIFIER_MODEL
		);

		$this->logger->debug(
			'Consumed device found message',
			[
				'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type'   => 'discovery-message-consumer',
				'device' => [
					'id' => $deviceItem->getId()->toString(),
				],
				'data'   => $entity->toArray(),
			]
		);

		return true;
	}

}
