<?php declare(strict_types = 1);

/**
 * StoreDeviceState.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Queue
 * @since          1.0.0
 *
 * @date           20.07.22
 */

namespace FastyBird\Connector\Shelly\Queue\Consumers;

use Doctrine\DBAL;
use FastyBird\Connector\Shelly;
use FastyBird\Connector\Shelly\Documents;
use FastyBird\Connector\Shelly\Exceptions;
use FastyBird\Connector\Shelly\Queries;
use FastyBird\Connector\Shelly\Queue;
use FastyBird\Connector\Shelly\Types;
use FastyBird\Library\Application\Exceptions as ApplicationExceptions;
use FastyBird\Library\Application\Helpers as ApplicationHelpers;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Library\Tools\Exceptions as ToolsExceptions;
use FastyBird\Module\Devices\Documents as DevicesDocuments;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use FastyBird\Module\Devices\States as DevicesStates;
use Nette;
use Nette\Utils;
use TypeError;
use ValueError;
use function assert;
use function React\Async\await;

/**
 * Store device state message consumer
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Queue
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class StoreDeviceState implements Queue\Consumer
{

	use Nette\SmartObject;
	use DeviceProperty;

	public function __construct(
		protected readonly Shelly\Logger $logger,
		protected readonly DevicesModels\Entities\Devices\DevicesRepository $devicesRepository,
		protected readonly DevicesModels\Entities\Devices\Properties\PropertiesRepository $devicesPropertiesRepository,
		protected readonly DevicesModels\Entities\Devices\Properties\PropertiesManager $devicesPropertiesManager,
		protected readonly ApplicationHelpers\Database $databaseHelper,
		private readonly DevicesModels\Entities\Channels\Properties\PropertiesRepository $channelsPropertiesRepository,
		private readonly DevicesModels\Entities\Channels\Properties\PropertiesManager $channelsPropertiesManager,
		private readonly DevicesModels\Configuration\Devices\Repository $devicesConfigurationRepository,
		private readonly DevicesModels\Configuration\Devices\Properties\Repository $devicesPropertiesConfigurationRepository,
		private readonly DevicesModels\Configuration\Channels\Repository $channelsConfigurationRepository,
		private readonly DevicesModels\Configuration\Channels\Properties\Repository $channelsPropertiesConfigurationRepository,
		private readonly DevicesModels\States\Async\DevicePropertiesManager $devicePropertiesStatesManager,
		private readonly DevicesModels\States\Async\ChannelPropertiesManager $channelPropertiesStatesManager,
	)
	{
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws ApplicationExceptions\Runtime
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidArgument
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws ToolsExceptions\InvalidArgument
	 * @throws TypeError
	 * @throws Utils\JsonException
	 * @throws ValueError
	 */
	public function consume(Queue\Messages\Message $message): bool
	{
		if (!$message instanceof Queue\Messages\StoreDeviceState) {
			return false;
		}

		$findDeviceQuery = new Queries\Configuration\FindDevices();
		$findDeviceQuery->byConnectorId($message->getConnector());
		$findDeviceQuery->startWithIdentifier($message->getIdentifier());

		$device = $this->devicesConfigurationRepository->findOneBy(
			$findDeviceQuery,
			Documents\Devices\Device::class,
		);

		if ($device === null) {
			return true;
		}

		if ($message->getIpAddress() !== null) {
			$this->setDeviceProperty(
				$device->getId(),
				$message->getIpAddress(),
				MetadataTypes\DataType::STRING,
				Types\DevicePropertyIdentifier::IP_ADDRESS,
			);
		}

		foreach ($message->getStates() as $state) {
			if ($state instanceof Queue\Messages\PropertyState) {
				$findDevicePropertyQuery = new DevicesQueries\Configuration\FindDeviceProperties();
				$findDevicePropertyQuery->forDevice($device);

				if (Utils\Strings::startsWith($state->getIdentifier(), '_')) {
					$findDevicePropertyQuery->endWithIdentifier($state->getIdentifier());
				} elseif (Utils\Strings::endsWith($state->getIdentifier(), '_')) {
					$findDevicePropertyQuery->startWithIdentifier($state->getIdentifier());
				} else {
					$findDevicePropertyQuery->byIdentifier($state->getIdentifier());
				}

				$property = $this->devicesPropertiesConfigurationRepository->findOneBy($findDevicePropertyQuery);

				if ($property !== null) {
					if ($property instanceof DevicesDocuments\Devices\Properties\Dynamic) {
						await($this->devicePropertiesStatesManager->set(
							$property,
							Utils\ArrayHash::from([
								DevicesStates\Property::ACTUAL_VALUE_FIELD => $state->getValue(),
							]),
							MetadataTypes\Sources\Connector::SHELLY,
						));
					} elseif ($property instanceof DevicesDocuments\Devices\Properties\Variable) {
						$this->databaseHelper->transaction(
							function () use ($property, $state): void {
								$property = $this->devicesPropertiesRepository->find(
									$property->getId(),
									DevicesEntities\Devices\Properties\Variable::class,
								);
								assert($property instanceof DevicesEntities\Devices\Properties\Variable);

								$this->devicesPropertiesManager->update(
									$property,
									Utils\ArrayHash::from([
										'value' => $state->getValue(),
									]),
								);
							},
						);
					}
				} else {
					$findChannelsQuery = new Queries\Configuration\FindChannels();
					$findChannelsQuery->forDevice($device);

					$channels = $this->channelsConfigurationRepository->findAllBy(
						$findChannelsQuery,
						Documents\Channels\Channel::class,
					);

					foreach ($channels as $channel) {
						$findChannelPropertyQuery = new DevicesQueries\Configuration\FindChannelProperties();
						$findChannelPropertyQuery->forChannel($channel);

						if (Utils\Strings::startsWith($state->getIdentifier(), '_')) {
							$findChannelPropertyQuery->endWithIdentifier($state->getIdentifier());
						} elseif (Utils\Strings::endsWith($state->getIdentifier(), '_')) {
							$findChannelPropertyQuery->startWithIdentifier($state->getIdentifier());
						} else {
							$findChannelPropertyQuery->byIdentifier($state->getIdentifier());
						}

						$property = $this->channelsPropertiesConfigurationRepository->findOneBy(
							$findChannelPropertyQuery,
						);

						if ($property !== null) {
							if ($property instanceof DevicesDocuments\Channels\Properties\Dynamic) {
								await($this->channelPropertiesStatesManager->set(
									$property,
									Utils\ArrayHash::from([
										DevicesStates\Property::ACTUAL_VALUE_FIELD => $state->getValue(),
									]),
									MetadataTypes\Sources\Connector::SHELLY,
								));
							} elseif ($property instanceof DevicesDocuments\Channels\Properties\Variable) {
								$this->databaseHelper->transaction(
									function () use ($property, $state): void {
										$property = $this->channelsPropertiesRepository->find(
											$property->getId(),
											DevicesEntities\Channels\Properties\Variable::class,
										);
										assert($property instanceof DevicesEntities\Channels\Properties\Variable);

										$this->channelsPropertiesManager->update(
											$property,
											Utils\ArrayHash::from([
												'value' => $state->getValue(),
											]),
										);
									},
								);
							}

							break;
						}
					}
				}
			} else {
				$findChannelQuery = new Queries\Configuration\FindChannels();
				$findChannelQuery->forDevice($device);

				if (Utils\Strings::startsWith($state->getIdentifier(), '_')) {
					$findChannelQuery->endWithIdentifier($state->getIdentifier());
				} elseif (Utils\Strings::endsWith($state->getIdentifier(), '_')) {
					$findChannelQuery->startWithIdentifier($state->getIdentifier());
				} else {
					$findChannelQuery->byIdentifier($state->getIdentifier());
				}

				$channel = $this->channelsConfigurationRepository->findOneBy(
					$findChannelQuery,
					Documents\Channels\Channel::class,
				);

				if ($channel !== null) {
					foreach ($state->getSensors() as $sensor) {
						$findChannelPropertyQuery = new DevicesQueries\Configuration\FindChannelProperties();
						$findChannelPropertyQuery->forChannel($channel);

						if (Utils\Strings::startsWith($sensor->getIdentifier(), '_')) {
							$findChannelPropertyQuery->endWithIdentifier($sensor->getIdentifier());
						} elseif (Utils\Strings::endsWith($sensor->getIdentifier(), '_')) {
							$findChannelPropertyQuery->startWithIdentifier($sensor->getIdentifier());
						} else {
							$findChannelPropertyQuery->byIdentifier($sensor->getIdentifier());
						}

						$property = $this->channelsPropertiesConfigurationRepository->findOneBy(
							$findChannelPropertyQuery,
						);

						if ($property instanceof DevicesDocuments\Channels\Properties\Dynamic) {
							await($this->channelPropertiesStatesManager->set(
								$property,
								Utils\ArrayHash::from([
									DevicesStates\Property::ACTUAL_VALUE_FIELD => $sensor->getValue(),
								]),
								MetadataTypes\Sources\Connector::SHELLY,
							));
						} elseif ($property instanceof DevicesDocuments\Channels\Properties\Variable) {
							$this->databaseHelper->transaction(
								function () use ($property, $sensor): void {
									$property = $this->channelsPropertiesRepository->find(
										$property->getId(),
										DevicesEntities\Channels\Properties\Variable::class,
									);
									assert($property instanceof DevicesEntities\Channels\Properties\Variable);

									$this->channelsPropertiesManager->update(
										$property,
										Utils\ArrayHash::from([
											'value' => $sensor->getValue(),
										]),
									);
								},
							);
						}
					}
				}
			}
		}

		$this->logger->debug(
			'Consumed store device state message',
			[
				'source' => MetadataTypes\Sources\Connector::SHELLY->value,
				'type' => 'store-device-state-message-consumer',
				'connector' => [
					'id' => $message->getConnector()->toString(),
				],
				'device' => [
					'id' => $device->getId()->toString(),
				],
				'data' => $message->toArray(),
			],
		);

		return true;
	}

}
