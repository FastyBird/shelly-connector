<?php declare(strict_types = 1);

/**
 * Description.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Consumers
 * @since          0.37.0
 *
 * @date           20.07.22
 */

namespace FastyBird\Connector\Shelly\Consumers\Messages;

use Doctrine\DBAL;
use FastyBird\Connector\Shelly\Consumers\Consumer;
use FastyBird\Connector\Shelly\Entities;
use FastyBird\Connector\Shelly\Mappers;
use FastyBird\Connector\Shelly\Types;
use FastyBird\Library\Metadata;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use FastyBird\Module\Devices\Utilities as DevicesUtilities;
use Nette;
use Nette\Utils;
use Psr\Log;
use function assert;
use function sprintf;
use function strval;

/**
 * Device description message consumer
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Description implements Consumer
{

	use Nette\SmartObject;
	use TConsumeDeviceAttribute;
	use TConsumeDeviceProperty;

	private Log\LoggerInterface $logger;

	public function __construct(
		private readonly DevicesModels\Devices\DevicesRepository $devicesRepository,
		private readonly DevicesModels\Devices\DevicesManager $devicesManager,
		private readonly DevicesModels\Devices\Properties\PropertiesRepository $propertiesRepository,
		private readonly DevicesModels\Devices\Properties\PropertiesManager $propertiesManager,
		private readonly DevicesModels\Devices\Attributes\AttributesRepository $attributesRepository,
		private readonly DevicesModels\Devices\Attributes\AttributesManager $attributesManager,
		private readonly DevicesModels\Channels\ChannelsRepository $channelsRepository,
		private readonly DevicesModels\Channels\ChannelsManager $channelsManager,
		private readonly DevicesModels\Channels\Properties\PropertiesRepository $channelsPropertiesRepository,
		private readonly DevicesModels\Channels\Properties\PropertiesManager $channelsPropertiesManager,
		private readonly DevicesModels\DataStorage\DevicesRepository $devicesDataStorageRepository,
		private readonly DevicesModels\DataStorage\DevicePropertiesRepository $propertiesDataStorageRepository,
		private readonly DevicesModels\DataStorage\DeviceAttributesRepository $attributesDataStorageRepository,
		private readonly DevicesModels\DataStorage\ChannelsRepository $channelsDataStorageRepository,
		private readonly Mappers\Sensor $sensorMapper,
		private readonly DevicesUtilities\Database $databaseHelper,
		Log\LoggerInterface|null $logger = null,
	)
	{
		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws DevicesExceptions\Runtime
	 * @throws MetadataExceptions\FileNotFound
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidData
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\Logic
	 * @throws MetadataExceptions\MalformedInput
	 */
	public function consume(Entities\Messages\Entity $entity): bool
	{
		if (!$entity instanceof Entities\Messages\DeviceDescription) {
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
			$deviceEntity = $this->databaseHelper->query(
				function () use ($deviceItem): Entities\ShellyDevice|null {
					$findDeviceQuery = new DevicesQueries\FindDevices();
					$findDeviceQuery->byId($deviceItem->getId());

					$deviceEntity = $this->devicesRepository->findOneBy(
						$findDeviceQuery,
						Entities\ShellyDevice::class,
					);
					assert($deviceEntity instanceof Entities\ShellyDevice || $deviceEntity === null);

					return $deviceEntity;
				},
			);

			if ($deviceEntity === null) {
				return true;
			}

			$this->databaseHelper->transaction(
				function () use ($entity, $deviceEntity): void {
					$this->devicesManager->update($deviceEntity, Utils\ArrayHash::from([
						'name' => $entity->getType(),
					]));
				},
			);
		}

		$this->setDeviceProperty(
			$deviceItem->getId(),
			$entity->getIpAddress(),
			Types\DevicePropertyIdentifier::IDENTIFIER_IP_ADDRESS,
		);
		$this->setDeviceAttribute(
			$deviceItem->getId(),
			$entity->getType(),
			Types\DeviceAttributeIdentifier::IDENTIFIER_MODEL,
		);

		foreach ($entity->getBlocks() as $block) {
			$channelItem = $this->channelsDataStorageRepository->findByIdentifier(
				$deviceItem->getId(),
				$block->getIdentifier() . '_' . $block->getDescription(),
			);

			if ($channelItem === null) {
				$deviceEntity = $this->databaseHelper->query(
					function () use ($deviceItem): Entities\ShellyDevice|null {
						$findDeviceQuery = new DevicesQueries\FindDevices();
						$findDeviceQuery->byId($deviceItem->getId());

						$deviceEntity = $this->devicesRepository->findOneBy($findDeviceQuery);
						assert($deviceEntity instanceof Entities\ShellyDevice || $deviceEntity === null);

						return $deviceEntity;
					},
				);

				if ($deviceEntity === null) {
					return true;
				}

				$this->databaseHelper->transaction(function () use ($block, $deviceEntity): void {
					$this->channelsManager->create(Utils\ArrayHash::from([
						'device' => $deviceEntity,
						'identifier' => sprintf('%d_%s', $block->getIdentifier(), $block->getDescription()),
						'name' => $block->getDescription(),
					]));
				});
			}

			foreach ($block->getSensors() as $sensor) {
				$channelProperty = $this->sensorMapper->findProperty(
					$entity->getConnector(),
					$entity->getIdentifier(),
					$sensor->getIdentifier(),
				);

				if ($channelProperty === null) {
					$channelEntity = $this->databaseHelper->query(
						function () use ($deviceItem, $block): DevicesEntities\Channels\Channel|null {
							$findChannelQuery = new DevicesQueries\FindChannels();
							$findChannelQuery->byDeviceId($deviceItem->getId());
							$findChannelQuery->byIdentifier($block->getIdentifier() . '_' . $block->getDescription());

							return $this->channelsRepository->findOneBy($findChannelQuery);
						},
					);

					if ($channelEntity === null) {
						continue;
					}

					$property = $this->databaseHelper->transaction(
						fn (): DevicesEntities\Channels\Properties\Property => $this->channelsPropertiesManager->create(
							Utils\ArrayHash::from([
								'channel' => $channelEntity,
								'entity' => DevicesEntities\Channels\Properties\Dynamic::class,
								'identifier' => sprintf(
									'%d_%s_%s',
									$sensor->getIdentifier(),
									strval($sensor->getType()->getValue()),
									$sensor->getDescription(),
								),
								'name' => $sensor->getDescription(),
								'unit' => $sensor->getUnit()?->getValue(),
								'dataType' => $sensor->getDataType(),
								'format' => $sensor->getFormat(),
								'invalid' => $sensor->getInvalid(),
								'queryable' => $sensor->isQueryable(),
								'settable' => $sensor->isSettable(),
							]),
						),
					);

					$this->logger->debug(
						'Device sensor was created',
						[
							'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
							'type' => 'description-message-consumer',
							'device' => [
								'id' => $deviceItem->getId()->toString(),
							],
							'channel' => [
								'id' => $property->getChannel()->getPlainId(),
							],
							'property' => [
								'id' => $property->getPlainId(),
							],
						],
					);

				} else {
					$propertyEntity = $this->databaseHelper->query(
						function () use ($channelProperty): DevicesEntities\Channels\Properties\Property|null {
							$findPropertyQuery = new DevicesQueries\FindChannelProperties();
							$findPropertyQuery->byId($channelProperty->getId());

							return $this->channelsPropertiesRepository->findOneBy($findPropertyQuery);
						},
					);

					if ($propertyEntity !== null) {
						$propertyEntity = $this->databaseHelper->transaction(
							fn (): DevicesEntities\Channels\Properties\Property => $this->channelsPropertiesManager->update(
								$propertyEntity,
								Utils\ArrayHash::from([
									'identifier' => sprintf(
										'%d_%s_%s',
										$sensor->getIdentifier(),
										strval($sensor->getType()->getValue()),
										$sensor->getDescription(),
									),
									'name' => $sensor->getDescription(),
									'unit' => $sensor->getUnit()?->getValue(),
									'dataType' => $sensor->getDataType(),
									'format' => $sensor->getFormat(),
									'invalid' => $sensor->getInvalid(),
									'queryable' => $sensor->isQueryable(),
									'settable' => $sensor->isSettable(),
								]),
							),
						);

						$this->logger->debug(
							'Device sensor was updated',
							[
								'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
								'type' => 'description-message-consumer',
								'device' => [
									'id' => $deviceItem->getId()->toString(),
								],
								'channel' => [
									'id' => $propertyEntity->getChannel()->getPlainId(),
								],
								'property' => [
									'id' => $propertyEntity->getPlainId(),
								],
							],
						);

					} else {
						$this->logger->error(
							'Device sensor could not be updated',
							[
								'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
								'type' => 'description-message-consumer',
								'device' => [
									'id' => $deviceItem->getId()->toString(),
								],
								'block' => [
									'identifier' => $block->getIdentifier(),
									'description' => $block->getDescription(),
								],
								'sensor' => [
									'identifier' => $sensor->getIdentifier(),
									'description' => $sensor->getDescription(),
								],
							],
						);
					}
				}
			}
		}

		$this->logger->debug(
			'Consumed device description message',
			[
				'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type' => 'description-message-consumer',
				'device' => [
					'id' => $deviceItem->getId()->toString(),
				],
				'data' => $entity->toArray(),
			],
		);

		return true;
	}

}
