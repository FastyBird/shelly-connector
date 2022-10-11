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
use function assert;

/**
 * Device type consumer trait
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 *
 * @property-read DevicesModuleModels\Devices\DevicesRepository $devicesRepository
 * @property-read DevicesModuleModels\Devices\Attributes\AttributesRepository $attributesRepository
 * @property-read DevicesModuleModels\Devices\Attributes\AttributesManager $attributesManager
 * @property-read DevicesModuleModels\DataStorage\DeviceAttributesRepository $attributesDataStorageRepository
 * @property-read Helpers\Database $databaseHelper
 * @property-read Log\LoggerInterface $logger
 */
trait TConsumeDeviceAttribute
{

	/**
	 * @throws DBAL\Exception
	 * @throws Metadata\Exceptions\FileNotFound
	 */
	private function setDeviceAttribute(
		Uuid\UuidInterface $deviceId,
		string|null $value,
		string $identifier,
	): void
	{
		$attributeItem = $this->attributesDataStorageRepository->findByIdentifier(
			$deviceId,
			$identifier,
		);

		if ($attributeItem !== null && $value === null) {
			/** @var mixed $attributeEntity */
			$attributeEntity = $this->databaseHelper->query(
				function () use ($attributeItem): DevicesModuleEntities\Devices\Attributes\Attribute|null {
					$findAttributeQuery = new DevicesModuleQueries\FindDeviceAttributes();
					$findAttributeQuery->byId($attributeItem->getId());

					return $this->attributesRepository->findOneBy($findAttributeQuery);
				},
			);
			assert(
				$attributeEntity instanceof DevicesModuleEntities\Devices\Attributes\Attribute || $attributeEntity === null,
			);

			if ($attributeEntity !== null) {
				$this->databaseHelper->transaction(
					function () use ($attributeEntity): void {
						$this->attributesManager->delete($attributeEntity);
					},
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

			/** @var mixed $attributeEntity */
			$attributeEntity = $this->databaseHelper->transaction(
				fn (): DevicesModuleEntities\Devices\Attributes\Attribute => $this->attributesManager->create(
					Utils\ArrayHash::from([
						'device' => $deviceEntity,
						'identifier' => $identifier,
						'content' => $value,
					]),
				),
			);
			assert($attributeEntity instanceof DevicesModuleEntities\Devices\Attributes\Attribute);

			$this->logger->debug(
				'Device attribute was created',
				[
					'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
					'type' => 'message-consumer',
					'device' => [
						'id' => $deviceId->toString(),
					],
					'attribute' => [
						'id' => $attributeEntity->getPlainId(),
						'identifier' => $identifier,
					],
				],
			);

		} else {
			/** @var mixed $attributeEntity */
			$attributeEntity = $this->databaseHelper->query(
				function () use ($attributeItem): DevicesModuleEntities\Devices\Attributes\Attribute|null {
					$findAttributeQuery = new DevicesModuleQueries\FindDeviceAttributes();
					$findAttributeQuery->byId($attributeItem->getId());

					return $this->attributesRepository->findOneBy($findAttributeQuery);
				},
			);
			assert(
				$attributeEntity instanceof DevicesModuleEntities\Devices\Attributes\Attribute || $attributeEntity === null,
			);

			if ($attributeEntity !== null) {
				/** @var mixed $attributeEntity */
				$attributeEntity = $this->databaseHelper->transaction(
					fn (): DevicesModuleEntities\Devices\Attributes\Attribute => $this->attributesManager->update(
						$attributeEntity,
						Utils\ArrayHash::from([
							'content' => $value,
						]),
					),
				);
				assert($attributeEntity instanceof DevicesModuleEntities\Devices\Attributes\Attribute);

				$this->logger->debug(
					'Device attribute was updated',
					[
						'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type' => 'message-consumer',
						'device' => [
							'id' => $deviceId->toString(),
						],
						'attribute' => [
							'id' => $attributeEntity->getPlainId(),
							'identifier' => $identifier,
						],
					],
				);

			} else {
				$this->logger->error(
					'Device attribute could not be updated',
					[
						'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type' => 'message-consumer',
						'device' => [
							'id' => $deviceId->toString(),
						],
						'attribute' => [
							'id' => $attributeItem->getId()->toString(),
							'identifier' => $identifier,
						],
					],
				);
			}
		}
	}

}
