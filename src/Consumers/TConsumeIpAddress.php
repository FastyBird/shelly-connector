<?php declare(strict_types = 1);

/**
 * TConsumeIpAddress.php
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
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\ShellyConnector\Helpers;
use FastyBird\ShellyConnector\Types;
use Nette\Utils;
use Psr\Log;
use Ramsey\Uuid;

/**
 * Device ip address consumer trait
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 *
 * @property-read DevicesModuleModels\Devices\Properties\IPropertiesRepository $propertiesRepository
 * @property-read DevicesModuleModels\Devices\Properties\IPropertiesManager $propertiesManager
 * @property-read DevicesModuleModels\DataStorage\IDevicePropertiesRepository $propertiesDataStorageRepository
 * @property-read Helpers\DatabaseHelper $databaseHelper
 * @property-read Log\LoggerInterface $logger
 */
trait TConsumeIpAddress
{

	/**
	 * @param Uuid\UuidInterface $device
	 * @param string $ipAddress
	 *
	 * @return void
	 *
	 * @throws DBAL\Exception
	 */
	protected function setDeviceIpAddress(Uuid\UuidInterface $device, string $ipAddress): void
	{
		$ipAddressProperty = $this->propertiesDataStorageRepository->findByIdentifier(
			$device,
			Types\DevicePropertyIdentifierType::IDENTIFIER_IP_ADDRESS
		);

		if (
			$ipAddressProperty instanceof MetadataEntities\Modules\DevicesModule\IDeviceStaticPropertyEntity
			&& $ipAddressProperty->getValue() === $ipAddress
		) {
			return;
		}

		if (
			$ipAddressProperty !== null
			&& !$ipAddressProperty instanceof MetadataEntities\Modules\DevicesModule\IDeviceStaticPropertyEntity
		) {
			/** @var DevicesModuleEntities\Devices\Properties\IProperty|null $property */
			$property = $this->databaseHelper->query(
				function () use ($ipAddressProperty): ?DevicesModuleEntities\Devices\Properties\IProperty {
					$findPropertyQuery = new DevicesModuleQueries\FindDevicePropertiesQuery();
					$findPropertyQuery->byId($ipAddressProperty->getId());

					return $this->propertiesRepository->findOneBy($findPropertyQuery);
				}
			);

			if ($property !== null) {
				$this->databaseHelper->transaction(function () use ($property): void {
					$this->propertiesManager->delete($property);
				});

				$this->logger->warning(
					'Device ip address property is not valid type',
					[
						'source'   => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type'     => 'message-consumer',
						'device'   => [
							'id' => $device->toString(),
						],
						'property' => [
							'id' => $property->getPlainId(),
						],
					]
				);
			}

			$ipAddressProperty = null;
		}

		if ($ipAddressProperty === null) {
			/** @var DevicesModuleEntities\Devices\Properties\IProperty $property */
			$property = $this->databaseHelper->transaction(function () use ($device, $ipAddress): DevicesModuleEntities\Devices\Properties\IProperty {
				return $this->propertiesManager->create(Utils\ArrayHash::from([
					'entity'     => DevicesModuleEntities\Devices\Properties\StaticProperty::class,
					'device'     => $device,
					'identifier' => Types\DevicePropertyIdentifierType::IDENTIFIER_IP_ADDRESS,
					'dataType'   => MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_STRING),
					'settable'   => false,
					'queryable'  => false,
					'value'      => $ipAddress,
				]));
			});

			$this->logger->debug(
				'Device ip address property was created',
				[
					'source'   => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
					'type'     => 'message-consumer',
					'device'   => [
						'id' => $device->toString(),
					],
					'property' => [
						'id' => $property->getPlainId(),
					],
				]
			);

		} else {
			$findPropertyQuery = new DevicesModuleQueries\FindDevicePropertiesQuery();
			$findPropertyQuery->byId($ipAddressProperty->getId());

			$property = $this->propertiesRepository->findOneBy($findPropertyQuery);

			if ($property !== null) {
				/** @var DevicesModuleEntities\Devices\Properties\IProperty $property */
				$property = $this->databaseHelper->transaction(
					function () use ($ipAddress, $property): DevicesModuleEntities\Devices\Properties\IProperty {
						return $this->propertiesManager->update($property, Utils\ArrayHash::from([
							'value' => $ipAddress,
						]));
					}
				);

				$this->logger->debug(
					'Device ip address property was updated',
					[
						'source'   => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type'     => 'message-consumer',
						'device'   => [
							'id' => $device->toString(),
						],
						'property' => [
							'id' => $property->getPlainId(),
						],
					]
				);

			} else {
				$this->logger->error(
					'Device ip address property could not be updated',
					[
						'source'   => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type'     => 'message-consumer',
						'device'   => [
							'id' => $device->toString(),
						],
						'property' => [
							'id' => $ipAddressProperty->getId()->toString(),
						],
					]
				);
			}
		}
	}

}
