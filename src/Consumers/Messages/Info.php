<?php declare(strict_types = 1);

/**
 * Info.php
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
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\ShellyConnector\Consumers\Consumer;
use FastyBird\ShellyConnector\Entities;
use FastyBird\ShellyConnector\Helpers;
use FastyBird\ShellyConnector\Types;
use Nette;
use Nette\Utils;
use Psr\Log;
use Ramsey\Uuid;

/**
 * Device description message consumer
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Info implements Consumer
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
		if (!$entity instanceof Entities\Messages\DeviceInfo) {
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

			$this->databaseHelper->transaction(function () use ($entity, $device): void {
				$this->devicesManager->update($device, Utils\ArrayHash::from([
					'name' => $entity->getType(),
				]));
			});
		}

		$this->setDeviceIpAddress($deviceItem->getId(), $entity->getIpAddress());
		$this->setDeviceHardwareModel($deviceItem->getId(), $entity->getType());
		$this->setDeviceMacAddress($deviceItem->getId(), $entity->getMacAddress());
		$this->setDeviceFirmwareVersion($deviceItem->getId(), $entity->getFirmwareVersion());
		$this->setDeviceAuthEnabled($deviceItem->getId(), $entity->isAuthEnabled());

		$this->logger->debug(
			'Consumed device info message',
			[
				'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type'   => 'info-message-consumer',
				'device' => [
					'id' => $deviceItem->getId()->toString(),
				],
				'data'   => $entity->toArray(),
			]
		);

		return true;
	}

	/**
	 * @param Uuid\UuidInterface $deviceId
	 * @param string $macAddress
	 *
	 * @return void
	 *
	 * @throws DBAL\Exception
	 */
	private function setDeviceMacAddress(Uuid\UuidInterface $deviceId, string $macAddress): void
	{
		$macAddressAttributeItem = $this->attributesDataStorageRepository->findByIdentifier(
			$deviceId,
			Types\DeviceAttributeIdentifier::IDENTIFIER_MAC_ADDRESS
		);

		if ($macAddressAttributeItem !== null && $macAddressAttributeItem->getContent() === $macAddress) {
			return;
		}

		if ($macAddressAttributeItem === null) {
			/** @var DevicesModuleEntities\Devices\IDevice|null $device */
			$device = $this->databaseHelper->query(function () use ($deviceId): ?DevicesModuleEntities\Devices\IDevice {
				$findDeviceQuery = new DevicesModuleQueries\FindDevicesQuery();
				$findDeviceQuery->byId($deviceId);

				return $this->devicesRepository->findOneBy($findDeviceQuery);
			});

			if ($device === null) {
				return;
			}

			/** @var DevicesModuleEntities\Devices\Attributes\IAttribute $attribute */
			$attribute = $this->databaseHelper->transaction(
				function () use ($device, $macAddress): DevicesModuleEntities\Devices\Attributes\IAttribute {
					return $this->attributesManager->create(Utils\ArrayHash::from([
						'device'     => $device,
						'identifier' => Types\DeviceAttributeIdentifier::IDENTIFIER_MAC_ADDRESS,
						'content'    => $macAddress,
					]));
				}
			);

			$this->logger->debug(
				'Device mac address attribute was created',
				[
					'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
					'type'      => 'discovery-message-consumer',
					'device'    => [
						'id' => $deviceId->toString(),
					],
					'attribute' => [
						'id' => $attribute->getPlainId(),
					],
				]
			);

		} else {
			/** @var DevicesModuleEntities\Devices\Attributes\IAttribute|null $attribute */
			$attribute = $this->databaseHelper->query(
				function () use ($macAddressAttributeItem): ?DevicesModuleEntities\Devices\Attributes\IAttribute {
					$findAttributeQuery = new DevicesModuleQueries\FindDeviceAttributesQuery();
					$findAttributeQuery->byId($macAddressAttributeItem->getId());

					return $this->attributesRepository->findOneBy($findAttributeQuery);
				}
			);

			if ($attribute !== null) {
				/** @var DevicesModuleEntities\Devices\Attributes\IAttribute $attribute */
				$attribute = $this->databaseHelper->transaction(
					function () use ($macAddress, $attribute): DevicesModuleEntities\Devices\Attributes\IAttribute {
						return $this->attributesManager->update($attribute, Utils\ArrayHash::from([
							'content' => $macAddress,
						]));
					}
				);

				$this->logger->debug(
					'Device mac address attribute was updated',
					[
						'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type'      => 'discovery-message-consumer',
						'device'    => [
							'id' => $deviceId->toString(),
						],
						'attribute' => [
							'id' => $attribute->getPlainId(),
						],
					]
				);

			} else {
				$this->logger->error(
					'Device mac address attribute could not be updated',
					[
						'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type'      => 'discovery-message-consumer',
						'device'    => [
							'id' => $deviceId->toString(),
						],
						'attribute' => [
							'id' => $macAddressAttributeItem->getId()->toString(),
						],
					]
				);
			}
		}
	}

	/**
	 * @param Uuid\UuidInterface $deviceId
	 * @param string $firmwareVersion
	 *
	 * @return void
	 *
	 * @throws DBAL\Exception
	 */
	private function setDeviceFirmwareVersion(Uuid\UuidInterface $deviceId, string $firmwareVersion): void
	{
		$firmwareVersionAttributeItem = $this->attributesDataStorageRepository->findByIdentifier(
			$deviceId,
			Types\DeviceAttributeIdentifier::IDENTIFIER_FIRMWARE_VERSION
		);

		if ($firmwareVersionAttributeItem !== null && $firmwareVersionAttributeItem->getContent() === $firmwareVersion) {
			return;
		}

		if ($firmwareVersionAttributeItem === null) {
			/** @var DevicesModuleEntities\Devices\IDevice|null $device */
			$device = $this->databaseHelper->query(function () use ($deviceId): ?DevicesModuleEntities\Devices\IDevice {
				$findDeviceQuery = new DevicesModuleQueries\FindDevicesQuery();
				$findDeviceQuery->byId($deviceId);

				return $this->devicesRepository->findOneBy($findDeviceQuery);
			});

			if ($device === null) {
				return;
			}

			/** @var DevicesModuleEntities\Devices\Attributes\IAttribute $attribute */
			$attribute = $this->databaseHelper->transaction(
				function () use ($device, $firmwareVersion): DevicesModuleEntities\Devices\Attributes\IAttribute {
					return $this->attributesManager->create(Utils\ArrayHash::from([
						'device'     => $device,
						'identifier' => Types\DeviceAttributeIdentifier::IDENTIFIER_FIRMWARE_VERSION,
						'content'    => $firmwareVersion,
					]));
				}
			);

			$this->logger->debug(
				'Device firmware version attribute was created',
				[
					'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
					'type'      => 'discovery-message-consumer',
					'device'    => [
						'id' => $deviceId->toString(),
					],
					'attribute' => [
						'id' => $attribute->getPlainId(),
					],
				]
			);

		} else {
			/** @var DevicesModuleEntities\Devices\Attributes\IAttribute|null $attribute */
			$attribute = $this->databaseHelper->query(
				function () use ($firmwareVersionAttributeItem): ?DevicesModuleEntities\Devices\Attributes\IAttribute {
					$findAttributeQuery = new DevicesModuleQueries\FindDeviceAttributesQuery();
					$findAttributeQuery->byId($firmwareVersionAttributeItem->getId());

					return $this->attributesRepository->findOneBy($findAttributeQuery);
				}
			);

			if ($attribute !== null) {
				/** @var DevicesModuleEntities\Devices\Attributes\IAttribute $attribute */
				$attribute = $this->databaseHelper->transaction(
					function () use ($firmwareVersion, $attribute): DevicesModuleEntities\Devices\Attributes\IAttribute {
						return $this->attributesManager->update($attribute, Utils\ArrayHash::from([
							'content' => $firmwareVersion,
						]));
					}
				);

				$this->logger->debug(
					'Device firmware version attribute was updated',
					[
						'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type'      => 'discovery-message-consumer',
						'device'    => [
							'id' => $deviceId->toString(),
						],
						'attribute' => [
							'id' => $attribute->getPlainId(),
						],
					]
				);

			} else {
				$this->logger->error(
					'Device firmware version attribute could not be updated',
					[
						'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type'      => 'discovery-message-consumer',
						'device'    => [
							'id' => $deviceId->toString(),
						],
						'attribute' => [
							'id' => $firmwareVersionAttributeItem->getId()->toString(),
						],
					]
				);
			}
		}
	}

	/**
	 * @param Uuid\UuidInterface $deviceId
	 * @param bool $authEnabled
	 *
	 * @return void
	 *
	 * @throws DBAL\Exception
	 */
	private function setDeviceAuthEnabled(Uuid\UuidInterface $deviceId, bool $authEnabled): void
	{
		$authEnabledPropertyItem = $this->propertiesDataStorageRepository->findByIdentifier(
			$deviceId,
			Types\DevicePropertyIdentifier::IDENTIFIER_AUTH_ENABLED
		);

		if (
			$authEnabledPropertyItem instanceof MetadataEntities\Modules\DevicesModule\IDeviceStaticPropertyEntity
			&& $authEnabledPropertyItem->getValue() === $authEnabled
		) {
			return;
		}

		if (
			$authEnabledPropertyItem !== null
			&& !$authEnabledPropertyItem instanceof MetadataEntities\Modules\DevicesModule\IDeviceStaticPropertyEntity
		) {
			/** @var DevicesModuleEntities\Devices\Properties\IProperty|null $property */
			$property = $this->databaseHelper->query(
				function () use ($authEnabledPropertyItem): ?DevicesModuleEntities\Devices\Properties\IProperty {
					$findPropertyQuery = new DevicesModuleQueries\FindDevicePropertiesQuery();
					$findPropertyQuery->byId($authEnabledPropertyItem->getId());

					return $this->propertiesRepository->findOneBy($findPropertyQuery);
				}
			);

			if ($property !== null) {
				$this->databaseHelper->transaction(function () use ($property): void {
					$this->propertiesManager->delete($property);
				});

				$this->logger->warning(
					'Device auth enabled property is not valid type',
					[
						'source'   => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type'     => 'discovery-message-consumer',
						'device'   => [
							'id' => $deviceId->toString(),
						],
						'property' => [
							'id' => $property->getPlainId(),
						],
					]
				);
			}

			$authEnabledPropertyItem = null;
		}

		if ($authEnabledPropertyItem === null) {
			/** @var DevicesModuleEntities\Devices\IDevice|null $device */
			$device = $this->databaseHelper->query(function () use ($deviceId): ?DevicesModuleEntities\Devices\IDevice {
				$findDeviceQuery = new DevicesModuleQueries\FindDevicesQuery();
				$findDeviceQuery->byId($deviceId);

				return $this->devicesRepository->findOneBy($findDeviceQuery);
			});

			if ($device === null) {
				return;
			}

			/** @var DevicesModuleEntities\Devices\Properties\IProperty $property */
			$property = $this->databaseHelper->transaction(
				function () use ($device, $authEnabled): DevicesModuleEntities\Devices\Properties\IProperty {
					return $this->propertiesManager->create(Utils\ArrayHash::from([
						'entity'     => DevicesModuleEntities\Devices\Properties\StaticProperty::class,
						'device'     => $device,
						'identifier' => Types\DevicePropertyIdentifier::IDENTIFIER_AUTH_ENABLED,
						'dataType'   => MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_BOOLEAN),
						'settable'   => false,
						'queryable'  => false,
						'value'      => $authEnabled,
					]));
				}
			);

			$this->logger->debug(
				'Device auth enabled property was created',
				[
					'source'   => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
					'type'     => 'discovery-message-consumer',
					'device'   => [
						'id' => $deviceId->toString(),
					],
					'property' => [
						'id' => $property->getPlainId(),
					],
				]
			);

		} else {
			/** @var DevicesModuleEntities\Devices\Properties\IProperty|null $property */
			$property = $this->databaseHelper->query(
				function () use ($authEnabledPropertyItem): ?DevicesModuleEntities\Devices\Properties\IProperty {
					$findPropertyQuery = new DevicesModuleQueries\FindDevicePropertiesQuery();
					$findPropertyQuery->byId($authEnabledPropertyItem->getId());

					return $this->propertiesRepository->findOneBy($findPropertyQuery);
				}
			);

			if ($property !== null) {
				/** @var DevicesModuleEntities\Devices\Properties\IProperty $property */
				$property = $this->databaseHelper->transaction(
					function () use ($authEnabled, $property): DevicesModuleEntities\Devices\Properties\IProperty {
						return $this->propertiesManager->update($property, Utils\ArrayHash::from([
							'value' => $authEnabled,
						]));
					}
				);

				$this->logger->debug(
					'Device auth enabled property was updated',
					[
						'source'   => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type'     => 'discovery-message-consumer',
						'device'   => [
							'id' => $deviceId->toString(),
						],
						'property' => [
							'id' => $property->getPlainId(),
						],
					]
				);

			} else {
				$this->logger->error(
					'Device auth enabled property could not be updated',
					[
						'source'   => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type'     => 'discovery-message-consumer',
						'device'   => [
							'id' => $deviceId->toString(),
						],
						'property' => [
							'id' => $authEnabledPropertyItem->getId()->toString(),
						],
					]
				);
			}
		}
	}

}
