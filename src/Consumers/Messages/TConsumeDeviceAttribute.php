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
 * @date           31.08.22
 */

namespace FastyBird\ShellyConnector\Consumers\Messages;

use Doctrine\DBAL;
use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\DevicesModule\Queries as DevicesModuleQueries;
use FastyBird\Metadata;
use FastyBird\ShellyConnector\Entities;
use FastyBird\ShellyConnector\Helpers;
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
trait TConsumeDeviceAttribute
{

	/**
	 * @param Uuid\UuidInterface $deviceId
	 * @param string|null $value
	 * @param string $identifier
	 *
	 * @return void
	 *
	 * @throws DBAL\Exception
	 */
	private function setDeviceAttribute(
		Uuid\UuidInterface $deviceId,
		?string $value,
		string $identifier
	): void {
		$attributeItem = $this->attributesDataStorageRepository->findByIdentifier(
			$deviceId,
			$identifier
		);

		if ($attributeItem !== null && $value === null) {
			/** @var DevicesModuleEntities\Devices\Attributes\IAttribute|null $attributeEntity */
			$attributeEntity = $this->databaseHelper->query(
				function () use ($attributeItem): ?DevicesModuleEntities\Devices\Attributes\IAttribute {
					$findAttributeQuery = new DevicesModuleQueries\FindDeviceAttributesQuery();
					$findAttributeQuery->byId($attributeItem->getId());

					return $this->attributesRepository->findOneBy($findAttributeQuery);
				}
			);

			if ($attributeEntity !== null) {
				$this->databaseHelper->transaction(
					function () use ($attributeEntity): void {
						$this->attributesManager->delete($attributeEntity);
					}
				);
			}

			return;
		}

		if ($value === null) {
			return;
		}

		if ($attributeItem !== null && $attributeItem->getContent() === $value) {
			return;
		}

		if ($attributeItem === null) {
			/** @var Entities\ShellyDevice|null $deviceEntity */
			$deviceEntity = $this->databaseHelper->query(
				function () use ($deviceId): ?Entities\ShellyDevice {
					$findDeviceQuery = new DevicesModuleQueries\FindDevicesQuery();
					$findDeviceQuery->byId($deviceId);

					/** @var Entities\ShellyDevice|null $deviceEntity */
					$deviceEntity = $this->devicesRepository->findOneBy(
						$findDeviceQuery,
						Entities\ShellyDevice::class
					);

					return $deviceEntity;
				}
			);

			if ($deviceEntity === null) {
				return;
			}

			/** @var DevicesModuleEntities\Devices\Attributes\IAttribute $attributeEntity */
			$attributeEntity = $this->databaseHelper->transaction(
				function () use (
					$deviceEntity,
					$value,
					$identifier
				): DevicesModuleEntities\Devices\Attributes\IAttribute {
					return $this->attributesManager->create(Utils\ArrayHash::from([
						'device'     => $deviceEntity,
						'identifier' => $identifier,
						'content'    => $value,
					]));
				}
			);

			$this->logger->debug(
				'Device attribute was created',
				[
					'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
					'type'      => 'message-consumer',
					'device'    => [
						'id' => $deviceId->toString(),
					],
					'attribute' => [
						'id'         => $attributeEntity->getPlainId(),
						'identifier' => $identifier,
					],
				]
			);

		} else {
			/** @var DevicesModuleEntities\Devices\Attributes\IAttribute|null $attributeEntity */
			$attributeEntity = $this->databaseHelper->query(
				function () use ($attributeItem): ?DevicesModuleEntities\Devices\Attributes\IAttribute {
					$findAttributeQuery = new DevicesModuleQueries\FindDeviceAttributesQuery();
					$findAttributeQuery->byId($attributeItem->getId());

					return $this->attributesRepository->findOneBy($findAttributeQuery);
				}
			);

			if ($attributeEntity !== null) {
				/** @var DevicesModuleEntities\Devices\Attributes\IAttribute $attributeEntity */
				$attributeEntity = $this->databaseHelper->transaction(
					function () use ($value, $attributeEntity): DevicesModuleEntities\Devices\Attributes\IAttribute {
						return $this->attributesManager->update($attributeEntity, Utils\ArrayHash::from([
							'content' => $value,
						]));
					}
				);

				$this->logger->debug(
					'Device attribute was updated',
					[
						'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type'      => 'message-consumer',
						'device'    => [
							'id' => $deviceId->toString(),
						],
						'attribute' => [
							'id'         => $attributeEntity->getPlainId(),
							'identifier' => $identifier,
						],
					]
				);

			} else {
				$this->logger->error(
					'Device attribute could not be updated',
					[
						'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type'      => 'message-consumer',
						'device'    => [
							'id' => $deviceId->toString(),
						],
						'attribute' => [
							'id'         => $attributeItem->getId()->toString(),
							'identifier' => $identifier,
						],
					]
				);
			}
		}
	}

}