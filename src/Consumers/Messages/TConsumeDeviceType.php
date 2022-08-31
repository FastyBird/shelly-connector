<?php declare(strict_types = 1);

/**
 * TConsumeDeviceType.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Consumers
 * @since          0.37.0
 *
 * @date           30.07.22
 */

namespace FastyBird\ShellyConnector\Consumers\Messages;

use Doctrine\DBAL;
use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\DevicesModule\Queries as DevicesModuleQueries;
use FastyBird\Metadata;
use FastyBird\ShellyConnector\Helpers;
use FastyBird\ShellyConnector\Types;
use Nette\Utils;
use Psr\Log;
use Ramsey\Uuid;

/**
 * Device type consumer trait
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 *
 * @property-read DevicesModuleModels\Devices\IDevicesRepository $devicesRepository
 * @property-read DevicesModuleModels\Devices\Attributes\IAttributesRepository $attributesRepository
 * @property-read DevicesModuleModels\Devices\Attributes\IAttributesManager $attributesManager
 * @property-read DevicesModuleModels\DataStorage\IDeviceAttributesRepository $attributesDataStorageRepository
 * @property-read Helpers\Database $databaseHelper
 * @property-read Log\LoggerInterface $logger
 */
trait TConsumeDeviceType
{

	/**
	 * @param Uuid\UuidInterface $deviceId
	 * @param string|null $model
	 *
	 * @return void
	 *
	 * @throws DBAL\Exception
	 */
	private function setDeviceHardwareModel(Uuid\UuidInterface $deviceId, ?string $model): void
	{
		if ($model === null) {
			return;
		}

		$modelAttributeItem = $this->attributesDataStorageRepository->findByIdentifier(
			$deviceId,
			Types\DeviceAttributeIdentifier::IDENTIFIER_MODEL
		);

		if ($modelAttributeItem !== null && $modelAttributeItem->getContent() === $model) {
			return;
		}

		if ($modelAttributeItem === null) {
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
				function () use ($device, $model): DevicesModuleEntities\Devices\Attributes\IAttribute {
					return $this->attributesManager->create(Utils\ArrayHash::from([
						'device'     => $device,
						'identifier' => Types\DeviceAttributeIdentifier::IDENTIFIER_MODEL,
						'content'    => $model,
					]));
				}
			);

			$this->logger->debug(
				'Device model attribute was created',
				[
					'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
					'type'      => 'message-consumer',
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
				function () use ($modelAttributeItem): ?DevicesModuleEntities\Devices\Attributes\IAttribute {
					$findAttributeQuery = new DevicesModuleQueries\FindDeviceAttributesQuery();
					$findAttributeQuery->byId($modelAttributeItem->getId());

					return $this->attributesRepository->findOneBy($findAttributeQuery);
				}
			);

			if ($attribute !== null) {
				/** @var DevicesModuleEntities\Devices\Attributes\IAttribute $attribute */
				$attribute = $this->databaseHelper->transaction(
					function () use ($model, $attribute): DevicesModuleEntities\Devices\Attributes\IAttribute {
						return $this->attributesManager->update($attribute, Utils\ArrayHash::from([
							'content' => $model,
						]));
					}
				);

				$this->logger->debug(
					'Device model attribute was updated',
					[
						'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type'      => 'message-consumer',
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
					'Device model attribute could not be updated',
					[
						'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type'      => 'message-consumer',
						'device'    => [
							'id' => $deviceId->toString(),
						],
						'attribute' => [
							'id' => $modelAttributeItem->getId()->toString(),
						],
					]
				);
			}
		}
	}

}
