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
use function assert;

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
	use TConsumeDeviceAttribute;
	use TConsumeDeviceProperty;

	private Log\LoggerInterface $logger;

	public function __construct(
		private readonly DevicesModuleModels\Devices\DevicesRepository $devicesRepository,
		private readonly DevicesModuleModels\Devices\DevicesManager $devicesManager,
		private readonly DevicesModuleModels\Devices\Properties\PropertiesRepository $propertiesRepository,
		private readonly DevicesModuleModels\Devices\Properties\PropertiesManager $propertiesManager,
		private readonly DevicesModuleModels\Devices\Attributes\AttributesRepository $attributesRepository,
		private readonly DevicesModuleModels\Devices\Attributes\AttributesManager $attributesManager,
		private readonly DevicesModuleModels\DataStorage\DevicesRepository $devicesDataStorageRepository,
		private readonly DevicesModuleModels\DataStorage\DevicePropertiesRepository $propertiesDataStorageRepository,
		private readonly DevicesModuleModels\DataStorage\DeviceAttributesRepository $attributesDataStorageRepository,
		private readonly Helpers\Database $databaseHelper,
		Log\LoggerInterface|null $logger = null,
	)
	{
		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * @throws DBAL\Exception
	 * @throws Metadata\Exceptions\FileNotFound
	 */
	public function consume(Entities\Messages\Entity $entity): bool
	{
		if (!$entity instanceof Entities\Messages\DeviceInfo) {
			return false;
		}

		$deviceItem = $this->devicesDataStorageRepository->findByIdentifier(
			$entity->getConnector(),
			$entity->getIdentifier(),
		);

		if ($deviceItem === null) {
			return true;
		}

		if ($deviceItem->getName() === null && $deviceItem->getName() !== $entity->getType()) {
			/** @var mixed $deviceEntity */
			$deviceEntity = $this->databaseHelper->query(
				function () use ($deviceItem): Entities\ShellyDevice|null {
					$findDeviceQuery = new DevicesModuleQueries\FindDevices();
					$findDeviceQuery->byId($deviceItem->getId());

					$deviceEntity = $this->devicesRepository->findOneBy($findDeviceQuery);
					assert($deviceEntity instanceof Entities\ShellyDevice || $deviceEntity === null);

					return $deviceEntity;
				},
			);
			assert($deviceEntity instanceof Entities\ShellyDevice || $deviceEntity === null);

			if ($deviceEntity === null) {
				return true;
			}

			$this->databaseHelper->transaction(function () use ($entity, $deviceEntity): void {
				$this->devicesManager->update($deviceEntity, Utils\ArrayHash::from([
					'name' => $entity->getType(),
				]));
			});
		}

		$this->setDeviceProperty(
			$deviceItem->getId(),
			$entity->getIpAddress(),
			Types\DevicePropertyIdentifier::IDENTIFIER_IP_ADDRESS,
		);
		$this->setDeviceProperty(
			$deviceItem->getId(),
			$entity->isAuthEnabled(),
			Types\DevicePropertyIdentifier::IDENTIFIER_AUTH_ENABLED,
		);
		$this->setDeviceAttribute(
			$deviceItem->getId(),
			$entity->getType(),
			Types\DeviceAttributeIdentifier::IDENTIFIER_MODEL,
		);
		$this->setDeviceAttribute(
			$deviceItem->getId(),
			$entity->getMacAddress(),
			Types\DeviceAttributeIdentifier::IDENTIFIER_MAC_ADDRESS,
		);
		$this->setDeviceAttribute(
			$deviceItem->getId(),
			$entity->getFirmwareVersion(),
			Types\DeviceAttributeIdentifier::IDENTIFIER_FIRMWARE_VERSION,
		);

		$this->logger->debug(
			'Consumed device info message',
			[
				'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type' => 'info-message-consumer',
				'device' => [
					'id' => $deviceItem->getId()->toString(),
				],
				'data' => $entity->toArray(),
			],
		);

		return true;
	}

}
