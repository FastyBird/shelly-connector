<?php declare(strict_types = 1);

/**
 * TConsumeDeviceProperty.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Consumers
 * @since          0.37.0
 *
 * @date           31.08.22
 */

namespace FastyBird\ShellyConnector\Consumers\Messages;

use Doctrine\DBAL;
use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\DevicesModule\Queries as DevicesModuleQueries;
use FastyBird\Metadata;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\ShellyConnector\Entities;
use FastyBird\ShellyConnector\Helpers;
use Nette\Utils;
use Psr\Log;
use Ramsey\Uuid;
use function assert;

/**
 * Device ip address consumer trait
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 *
 * @property-read DevicesModuleModels\Devices\DevicesRepository $devicesRepository
 * @property-read DevicesModuleModels\Devices\Properties\PropertiesRepository $propertiesRepository
 * @property-read DevicesModuleModels\Devices\Properties\PropertiesManager $propertiesManager
 * @property-read DevicesModuleModels\DataStorage\DevicePropertiesRepository $propertiesDataStorageRepository
 * @property-read Helpers\Database $databaseHelper
 * @property-read Log\LoggerInterface $logger
 */
trait TConsumeDeviceProperty
{

	/**
	 * @throws DBAL\Exception
	 * @throws Metadata\Exceptions\FileNotFound
	 */
	private function setDeviceProperty(
		Uuid\UuidInterface $deviceId,
		string|bool|null $value,
		string $identifier,
	): void
	{
		$propertyItem = $this->propertiesDataStorageRepository->findByIdentifier(
			$deviceId,
			$identifier,
		);

		if ($propertyItem !== null && $value === null) {
			/** @var mixed $propertyEntity */
			$propertyEntity = $this->databaseHelper->query(
				function () use ($propertyItem): DevicesModuleEntities\Devices\Properties\Property|null {
					$findPropertyQuery = new DevicesModuleQueries\FindDeviceProperties();
					$findPropertyQuery->byId($propertyItem->getId());

					return $this->propertiesRepository->findOneBy($findPropertyQuery);
				},
			);
			assert(
				$propertyEntity instanceof DevicesModuleEntities\Devices\Properties\Property || $propertyEntity === null,
			);

			if ($propertyEntity !== null) {
				$this->databaseHelper->transaction(
					function () use ($propertyEntity): void {
						$this->propertiesManager->delete($propertyEntity);
					},
				);
			}

			return;
		}

		if ($value === null) {
			return;
		}

		if (
			$propertyItem instanceof MetadataEntities\DevicesModule\DeviceVariableProperty
			&& $propertyItem->getValue() === $value
		) {
			return;
		}

		if (
			$propertyItem !== null
			&& !$propertyItem instanceof MetadataEntities\DevicesModule\DeviceVariableProperty
		) {
			/** @var mixed $propertyEntity */
			$propertyEntity = $this->databaseHelper->query(
				function () use ($propertyItem): DevicesModuleEntities\Devices\Properties\Property|null {
					$findPropertyQuery = new DevicesModuleQueries\FindDeviceProperties();
					$findPropertyQuery->byId($propertyItem->getId());

					return $this->propertiesRepository->findOneBy($findPropertyQuery);
				},
			);
			assert(
				$propertyEntity instanceof DevicesModuleEntities\Devices\Properties\Property || $propertyEntity === null,
			);

			if ($propertyEntity !== null) {
				$this->databaseHelper->transaction(function () use ($propertyEntity): void {
					$this->propertiesManager->delete($propertyEntity);
				});

				$this->logger->warning(
					'Device property is not valid type',
					[
						'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type' => 'message-consumer',
						'device' => [
							'id' => $deviceId->toString(),
						],
						'property' => [
							'id' => $propertyEntity->getPlainId(),
							'identifier' => $identifier,
						],
					],
				);
			}

			$propertyItem = null;
		}

		if ($propertyItem === null) {
			/** @var mixed $deviceEntity */
			$deviceEntity = $this->databaseHelper->query(
				function () use ($deviceId): Entities\ShellyDevice|null {
					$findDeviceQuery = new DevicesModuleQueries\FindDevices();
					$findDeviceQuery->byId($deviceId);

					$deviceEntity = $this->devicesRepository->findOneBy(
						$findDeviceQuery,
						Entities\ShellyDevice::class,
					);
					assert($deviceEntity instanceof Entities\ShellyDevice || $deviceEntity === null);

					return $deviceEntity;
				},
			);
			assert($deviceEntity instanceof Entities\ShellyDevice || $deviceEntity === null);

			if ($deviceEntity === null) {
				return;
			}

			$propertyEntity = $this->databaseHelper->transaction(
				fn (): DevicesModuleEntities\Devices\Properties\Property => $this->propertiesManager->create(
					Utils\ArrayHash::from([
						'entity' => DevicesModuleEntities\Devices\Properties\Variable::class,
						'device' => $deviceEntity,
						'identifier' => $identifier,
						'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
						'settable' => false,
						'queryable' => false,
						'value' => $value,
					]),
				),
			);
			assert($propertyEntity instanceof DevicesModuleEntities\Devices\Properties\Property);

			$this->logger->debug(
				'Device property was created',
				[
					'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
					'type' => 'message-consumer',
					'device' => [
						'id' => $deviceId->toString(),
					],
					'property' => [
						'id' => $propertyEntity->getPlainId(),
						'identifier' => $identifier,
					],
				],
			);

		} else {
			/** @var mixed $propertyEntity */
			$propertyEntity = $this->databaseHelper->query(
				function () use ($propertyItem): DevicesModuleEntities\Devices\Properties\Property|null {
					$findPropertyQuery = new DevicesModuleQueries\FindDeviceProperties();
					$findPropertyQuery->byId($propertyItem->getId());

					return $this->propertiesRepository->findOneBy($findPropertyQuery);
				},
			);
			assert(
				$propertyEntity instanceof DevicesModuleEntities\Devices\Properties\Property || $propertyEntity === null,
			);

			if ($propertyEntity !== null) {
				$propertyEntity = $this->databaseHelper->transaction(
					fn (): DevicesModuleEntities\Devices\Properties\Property => $this->propertiesManager->update(
						$propertyEntity,
						Utils\ArrayHash::from([
							'value' => $value,
						]),
					),
				);
				assert($propertyEntity instanceof DevicesModuleEntities\Devices\Properties\Property);

				$this->logger->debug(
					'Device property was updated',
					[
						'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type' => 'message-consumer',
						'device' => [
							'id' => $deviceId->toString(),
						],
						'property' => [
							'id' => $propertyEntity->getPlainId(),
							'identifier' => $identifier,
						],
					],
				);

			} else {
				$this->logger->error(
					'Device property could not be updated',
					[
						'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type' => 'message-consumer',
						'device' => [
							'id' => $deviceId->toString(),
						],
						'property' => [
							'id' => $propertyItem->getId()->toString(),
							'identifier' => $identifier,
						],
					],
				);
			}
		}
	}

}
