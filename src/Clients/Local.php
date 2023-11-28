<?php declare(strict_types = 1);

/**
 * Local.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Clients
 * @since          1.0.0
 *
 * @date           17.07.22
 */

namespace FastyBird\Connector\Shelly\Clients;

use DateTimeInterface;
use FastyBird\Connector\Shelly;
use FastyBird\Connector\Shelly\API;
use FastyBird\Connector\Shelly\Entities;
use FastyBird\Connector\Shelly\Exceptions;
use FastyBird\Connector\Shelly\Helpers;
use FastyBird\Connector\Shelly\Queue;
use FastyBird\Connector\Shelly\Types;
use FastyBird\DateTimeFactory;
use FastyBird\Library\Bootstrap\Helpers as BootstrapHelpers;
use FastyBird\Library\Metadata\Documents as MetadataDocuments;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Events as DevicesEvents;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use FastyBird\Module\Devices\Utilities as DevicesUtilities;
use Fig\Http\Message\StatusCodeInterface;
use Nette;
use Psr\EventDispatcher as PsrEventDispatcher;
use React\EventLoop;
use RuntimeException;
use Throwable;
use function array_key_exists;
use function array_merge;
use function count;
use function in_array;
use function strval;

/**
 * Local devices client
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Local implements Client
{

	use Nette\SmartObject;

	private const HANDLER_START_DELAY = 2.0;

	private const HANDLER_PROCESSING_INTERVAL = 0.01;

	private const RECONNECT_COOL_DOWN_TIME = 300.0;

	private const CMD_STATE = 'state';

	/** @var array<string, MetadataDocuments\DevicesModule\Device>  */
	private array $devices = [];

	/** @var array<string, API\Gen2WsApi> */
	private array $gen2DevicesWsClients = [];

	/** @var array<string> */
	private array $processedDevices = [];

	/** @var array<string, array<string, DateTimeInterface|bool>> */
	private array $processedDevicesCommands = [];

	private EventLoop\TimerInterface|null $handlerTimer = null;

	public function __construct(
		private readonly MetadataDocuments\DevicesModule\Connector $connector,
		private readonly API\ConnectionManager $connectionManager,
		private readonly Queue\Queue $queue,
		private readonly Helpers\Entity $entityHelper,
		private readonly Helpers\Device $deviceHelper,
		private readonly Shelly\Logger $logger,
		private readonly DevicesModels\Configuration\Devices\Repository $devicesConfigurationRepository,
		private readonly DevicesModels\Configuration\Devices\Properties\Repository $devicesPropertiesConfigurationRepository,
		private readonly DevicesUtilities\DeviceConnection $deviceConnectionManager,
		private readonly DateTimeFactory\Factory $dateTimeFactory,
		private readonly EventLoop\LoopInterface $eventLoop,
		private readonly PsrEventDispatcher\EventDispatcherInterface|null $dispatcher = null,
	)
	{
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	public function connect(): void
	{
		$this->processedDevices = [];
		$this->processedDevicesCommands = [];

		$this->handlerTimer = null;

		try {
			$gen1CoapClient = $this->connectionManager->getGen1CoapApiConnection();

			$gen1CoapClient->connect();

			$gen1CoapClient->on('message', function (Entities\API\Gen1\ReportDeviceState $message): void {
				$this->processGen1DeviceReportedStatus($message);
			});

			$gen1CoapClient->on('error', function (Throwable $ex): void {
				$this->logger->error(
					'An error occur in CoAP connection',
					[
						'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_SHELLY,
						'type' => 'local-client',
						'exception' => BootstrapHelpers\Logger::buildException($ex),
						'connector' => [
							'id' => $this->connector->getId()->toString(),
						],
					],
				);

				if (!$ex instanceof Exceptions\CoapError) {
					$this->dispatcher?->dispatch(
						new DevicesEvents\TerminateConnector(
							MetadataTypes\ConnectorSource::get(MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_SHELLY),
							'CoAP client triggered an error',
						),
					);
				}
			});
		} catch (Throwable $ex) {
			$this->logger->error(
				'CoAP client could not be started',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_SHELLY,
					'type' => 'local-client',
					'exception' => BootstrapHelpers\Logger::buildException($ex),
					'connector' => [
						'id' => $this->connector->getId()->toString(),
					],
				],
			);

			$this->dispatcher?->dispatch(
				new DevicesEvents\TerminateConnector(
					MetadataTypes\ConnectorSource::get(MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_SHELLY),
					'CoAP client could not be started',
				),
			);
		}

		$findDevicesQuery = new DevicesQueries\Configuration\FindDevices();
		$findDevicesQuery->forConnector($this->connector);

		foreach ($this->devicesConfigurationRepository->findAllBy($findDevicesQuery) as $device) {
			$this->devices[$device->getId()->toString()] = $device;

			$findDevicePropertyQuery = new DevicesQueries\Configuration\FindDeviceVariableProperties();
			$findDevicePropertyQuery->forDevice($device);
			$findDevicePropertyQuery->byIdentifier(Types\DevicePropertyIdentifier::GENERATION);

			$generationProperty = $this->devicesPropertiesConfigurationRepository->findOneBy(
				$findDevicePropertyQuery,
				MetadataDocuments\DevicesModule\DeviceVariableProperty::class,
			);

			if (
				$generationProperty !== null
				&& $generationProperty->getValue() === Types\DeviceGeneration::GENERATION_2
			) {
				try {
					$client = $this->createGen2DeviceWsClient($device);

					$client->connect();
				} catch (Throwable $ex) {
					$this->logger->error(
						'Device websocket connection could not be created',
						[
							'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_SHELLY,
							'type' => 'local-client',
							'exception' => BootstrapHelpers\Logger::buildException($ex),
							'connector' => [
								'id' => $this->connector->getId()->toString(),
							],
							'device' => [
								'id' => $device->getId()->toString(),
							],
						],
					);

					$this->dispatcher?->dispatch(
						new DevicesEvents\TerminateConnector(
							MetadataTypes\ConnectorSource::get(MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_SHELLY),
							'Websockets api client could not be started',
						),
					);
				}
			}
		}

		$this->eventLoop->addTimer(
			self::HANDLER_START_DELAY,
			function (): void {
				$this->registerLoopHandler();
			},
		);
	}

	public function disconnect(): void
	{
		$this->connectionManager->getGen1CoapApiConnection()->disconnect();

		foreach ($this->gen2DevicesWsClients as $client) {
			$client->disconnect();
		}

		if ($this->handlerTimer !== null) {
			$this->eventLoop->cancelTimer($this->handlerTimer);

			$this->handlerTimer = null;
		}
	}

	/**
	 * @throws DevicesExceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws RuntimeException
	 */
	private function handleCommunication(): void
	{
		foreach ($this->devices as $device) {
			if (!in_array($device->getId()->toString(), $this->processedDevices, true)) {
				$this->processedDevices[] = $device->getId()->toString();

				if ($this->readDeviceState($device)) {
					$this->registerLoopHandler();

					return;
				}
			}
		}

		$this->processedDevices = [];

		$this->registerLoopHandler();
	}

	/**
	 * @throws DevicesExceptions\InvalidArgument
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws RuntimeException
	 */
	private function readDeviceState(MetadataDocuments\DevicesModule\Device $device): bool
	{
		if (!array_key_exists($device->getId()->toString(), $this->processedDevicesCommands)) {
			$this->processedDevicesCommands[$device->getId()->toString()] = [];
		}

		if (array_key_exists(self::CMD_STATE, $this->processedDevicesCommands[$device->getId()->toString()])) {
			$cmdResult = $this->processedDevicesCommands[$device->getId()->toString()][self::CMD_STATE];

			if (
				$cmdResult instanceof DateTimeInterface
				&& (
					$this->dateTimeFactory->getNow()->getTimestamp() - $cmdResult->getTimestamp()
						< $this->deviceHelper->getStateReadingDelay($device)
				)
			) {
				return false;
			}
		}

		$this->processedDevicesCommands[$device->getId()->toString()][self::CMD_STATE] = $this->dateTimeFactory->getNow();

		$deviceState = $this->deviceConnectionManager->getState($device);

		if ($deviceState->equalsValue(MetadataTypes\ConnectionState::STATE_ALERT)) {
			unset($this->devices[$device->getId()->toString()]);

			return false;
		}

		if ($this->deviceHelper->getGeneration($device)->equalsValue(Types\DeviceGeneration::GENERATION_2)) {
			$client = $this->getGen2DeviceWsClient($device);

			if ($client === null) {
				$client = $this->createGen2DeviceWsClient($device);
			}

			if (!$client->isConnected()) {
				if (!$client->isConnecting()) {
					if (
						$client->getLastConnectAttempt() === null
						|| (
							// phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
							$this->dateTimeFactory->getNow()->getTimestamp() - $client->getLastConnectAttempt()->getTimestamp() >= self::RECONNECT_COOL_DOWN_TIME
						)
					) {
						$client->connect();

					} else {
						$this->queue->append(
							$this->entityHelper->create(
								Entities\Messages\StoreDeviceConnectionState::class,
								[
									'connector' => $device->getConnector()->toString(),
									'identifier' => $device->getIdentifier(),
									'state' => MetadataTypes\ConnectionState::STATE_DISCONNECTED,
								],
							),
						);
					}
				}

				return false;
			}

			$client->readStates()
				->then(function (Entities\API\Gen2\GetDeviceState $response) use ($device): void {
					$this->processedDevicesCommands[$device->getId()->toString()][self::CMD_STATE] = $this->dateTimeFactory->getNow();

					$this->processGen2DeviceGetState($device, $response);
				})
				->catch(function (Throwable $ex) use ($device): void {
					$this->logger->error(
						'Could not read device state',
						[
							'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_SHELLY,
							'type' => 'local-client',
							'exception' => BootstrapHelpers\Logger::buildException($ex),
							'device' => [
								'id' => $device->getId()->toString(),
							],
						],
					);
				});

		} elseif ($this->deviceHelper->getGeneration($device)->equalsValue(Types\DeviceGeneration::GENERATION_1)) {
			$address = $this->deviceHelper->getLocalAddress($device);

			if ($address === null) {
				$this->queue->append(
					$this->entityHelper->create(
						Entities\Messages\StoreDeviceConnectionState::class,
						[
							'connector' => $device->getConnector()->toString(),
							'identifier' => $device->getIdentifier(),
							'state' => MetadataTypes\ConnectionState::STATE_ALERT,
						],
					),
				);

				return true;
			}

			$this->connectionManager->getGen1HttpApiConnection()->getDeviceState(
				$address,
				$this->deviceHelper->getUsername($device),
				$this->deviceHelper->getPassword($device),
			)
				->then(function (Entities\API\Gen1\GetDeviceState $response) use ($device): void {
					$this->processedDevicesCommands[$device->getId()->toString()][self::CMD_STATE] = $this->dateTimeFactory->getNow();

					$this->queue->append(
						$this->entityHelper->create(
							Entities\Messages\StoreDeviceConnectionState::class,
							[
								'connector' => $device->getConnector()->toString(),
								'identifier' => $device->getIdentifier(),
								'state' => MetadataTypes\ConnectionState::STATE_CONNECTED,
							],
						),
					);

					$this->processGen1DeviceGetState($device, $response);
				})
				->catch(function (Throwable $ex) use ($device): void {
					if ($ex instanceof Exceptions\HttpApiError) {
						$this->queue->append(
							$this->entityHelper->create(
								Entities\Messages\StoreDeviceConnectionState::class,
								[
									'connector' => $device->getConnector()->toString(),
									'identifier' => $device->getIdentifier(),
									'state' => MetadataTypes\ConnectionState::STATE_ALERT,
								],
							),
						);
					} elseif ($ex instanceof Exceptions\HttpApiCall) {
						if (
							$ex->getResponse() !== null
							&& $ex->getResponse()->getStatusCode() >= StatusCodeInterface::STATUS_BAD_REQUEST
							&& $ex->getResponse()->getStatusCode() < StatusCodeInterface::STATUS_UNAVAILABLE_FOR_LEGAL_REASONS
						) {
							$this->queue->append(
								$this->entityHelper->create(
									Entities\Messages\StoreDeviceConnectionState::class,
									[
										'connector' => $device->getConnector()->toString(),
										'identifier' => $device->getIdentifier(),
										'state' => MetadataTypes\ConnectionState::STATE_ALERT,
									],
								),
							);

						} elseif (
							$ex->getResponse() !== null
							&& $ex->getResponse()->getStatusCode() >= StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR
							&& $ex->getResponse()->getStatusCode() < StatusCodeInterface::STATUS_NETWORK_AUTHENTICATION_REQUIRED
						) {
							$this->queue->append(
								$this->entityHelper->create(
									Entities\Messages\StoreDeviceConnectionState::class,
									[
										'connector' => $device->getConnector()->toString(),
										'identifier' => $device->getIdentifier(),
										'state' => MetadataTypes\ConnectionState::STATE_LOST,
									],
								),
							);

						} else {
							$this->queue->append(
								$this->entityHelper->create(
									Entities\Messages\StoreDeviceConnectionState::class,
									[
										'connector' => $device->getConnector()->toString(),
										'identifier' => $device->getIdentifier(),
										'state' => MetadataTypes\ConnectionState::STATE_UNKNOWN,
									],
								),
							);
						}
					}

					$this->logger->error(
						'Could not read device state',
						[
							'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_SHELLY,
							'type' => 'local-client',
							'exception' => BootstrapHelpers\Logger::buildException($ex),
							'device' => [
								'id' => $device->getId()->toString(),
							],
						],
					);
				});
		}

		return false;
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function createGen2DeviceWsClient(MetadataDocuments\DevicesModule\Device $device): API\Gen2WsApi
	{
		if (array_key_exists($device->getId()->toString(), $this->gen2DevicesWsClients)) {
			throw new Exceptions\InvalidState('Gen 2 device WS client is already created');
		}

		unset($this->processedDevicesCommands[$device->getId()->toString()]);

		$client = $this->connectionManager->getGen2WsApiConnection($device);

		$client->on(
			'message',
			function (Entities\API\Gen2\GetDeviceState|Entities\API\Gen2\DeviceEvent $message) use ($device): void {
				if ($message instanceof Entities\API\Gen2\GetDeviceState) {
					$this->processGen2DeviceGetState($device, $message);
				}
			},
		);

		$client->on(
			'error',
			function (Throwable $ex) use ($device): void {
				$this->logger->warning(
					'Connection with Gen 2 device failed',
					[
						'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_SHELLY,
						'type' => 'local-client',
						'exception' => BootstrapHelpers\Logger::buildException($ex),
						'device' => [
							'id' => $device->getId()->toString(),
						],
					],
				);

				$this->queue->append(
					$this->entityHelper->create(
						Entities\Messages\StoreDeviceConnectionState::class,
						[
							'connector' => $device->getConnector()->toString(),
							'identifier' => $device->getIdentifier(),
							'state' => MetadataTypes\ConnectionState::STATE_DISCONNECTED,
						],
					),
				);
			},
		);

		$client->on(
			'connected',
			function () use ($client, $device): void {
				$this->logger->debug(
					'Connected to Gen 2 device',
					[
						'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_SHELLY,
						'type' => 'local-client',
						'device' => [
							'id' => $device->getId()->toString(),
						],
					],
				);

				$this->queue->append(
					$this->entityHelper->create(
						Entities\Messages\StoreDeviceConnectionState::class,
						[
							'connector' => $device->getConnector()->toString(),
							'identifier' => $device->getIdentifier(),
							'state' => MetadataTypes\ConnectionState::STATE_CONNECTED,
						],
					),
				);

				$client->readStates()
					->then(function (Entities\API\Gen2\GetDeviceState $state) use ($device): void {
						$this->processGen2DeviceGetState($device, $state);
					})
					->catch(function (Throwable $ex) use ($device): void {
						$this->queue->append(
							$this->entityHelper->create(
								Entities\Messages\StoreDeviceConnectionState::class,
								[
									'connector' => $device->getConnector()->toString(),
									'identifier' => $device->getIdentifier(),
									'state' => MetadataTypes\ConnectionState::STATE_DISCONNECTED,
								],
							),
						);

						$this->logger->error(
							'An error occurred on initial Gen 2 device state reading',
							[
								'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_SHELLY,
								'type' => 'local-client',
								'exception' => BootstrapHelpers\Logger::buildException($ex),
								'device' => [
									'identifier' => $device->getIdentifier(),
								],
							],
						);
					});
			},
		);

		$client->on(
			'disconnected',
			function () use ($device): void {
				$this->logger->debug(
					'Disconnected from Gen 2 device',
					[
						'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_SHELLY,
						'type' => 'local-client',
						'device' => [
							'id' => $device->getId()->toString(),
						],
					],
				);

				$this->queue->append(
					$this->entityHelper->create(
						Entities\Messages\StoreDeviceConnectionState::class,
						[
							'connector' => $device->getConnector()->toString(),
							'identifier' => $device->getIdentifier(),
							'state' => MetadataTypes\ConnectionState::STATE_DISCONNECTED,
						],
					),
				);
			},
		);

		$this->gen2DevicesWsClients[$device->getId()->toString()] = $client;

		return $this->gen2DevicesWsClients[$device->getId()->toString()];
	}

	private function getGen2DeviceWsClient(MetadataDocuments\DevicesModule\Device $device): API\Gen2WsApi|null
	{
		return array_key_exists(
			$device->getId()->toString(),
			$this->gen2DevicesWsClients,
		)
			? $this->gen2DevicesWsClients[$device->getId()->toString()]
			: null;
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function processGen1DeviceGetState(
		MetadataDocuments\DevicesModule\Device $device,
		Entities\API\Gen1\GetDeviceState $state,
	): void
	{
		$states = [];

		if ($state->getInputs() !== []) {
			foreach ($state->getInputs() as $index => $input) {
				$states[] = [
					'identifier' => '_' . Types\BlockDescription::INPUT . '_' . $index,
					'sensors' => [
						[
							'identifier' => '_' . Types\SensorDescription::INPUT,
							'value' => $input->getInput(),
						],
						[
							'identifier' => '_' . Types\SensorDescription::INPUT_EVENT,
							'value' => $input->getEvent(),
						],
						[
							'identifier' => '_' . Types\SensorDescription::INPUT_EVENT_COUNT,
							'value' => $input->getEventCnt(),
						],
					],
				];
			}
		}

		if ($state->getMeters() !== []) {
			foreach ($state->getMeters() as $index => $meter) {
				$states[] = [
					'identifier' => '_' . Types\BlockDescription::METER . '_' . $index,
					'sensors' => [
						[
							'identifier' => '_' . Types\SensorDescription::ACTIVE_POWER,
							'value' => $meter->getPower(),
						],
						[
							'identifier' => '_' . Types\SensorDescription::ROLLER_POWER,
							'value' => $meter->getPower(),
						],
						[
							'identifier' => '_' . Types\SensorDescription::OVERPOWER,
							'value' => $meter->getOverpower(),
						],
						[
							'identifier' => '_' . Types\SensorDescription::OVERPOWER_VALUE,
							'value' => $meter->getOverpower(),
						],
						[
							'identifier' => '_' . Types\SensorDescription::ENERGY,
							'value' => $meter->getTotal(),
						],
						[
							'identifier' => '_' . Types\SensorDescription::ROLLER_ENERGY,
							'value' => $meter->getTotal(),
						],
					],
				];
			}
		}

		if ($state->getRelays() !== []) {
			foreach ($state->getRelays() as $index => $relay) {
				$states[] = [
					'identifier' => '_' . Types\BlockDescription::RELAY . '_' . $index,
					'sensors' => [
						[
							'identifier' => '_' . Types\SensorDescription::OUTPUT,
							'value' => $relay->getState(),
						],
						[
							'identifier' => '_' . Types\SensorDescription::OVERPOWER,
							'value' => $relay->hasOverpower(),
						],
					],
				];

				$states[] = [
					'identifier' => '_' . Types\BlockDescription::DEVICE,
					'sensors' => [
						[
							'identifier' => '_' . Types\SensorDescription::OVERTEMPERATURE,
							'value' => $relay->hasOvertemperature(),
						],
					],
				];
			}
		}

		if ($state->getRollers() !== []) {
			foreach ($state->getRollers() as $index => $roller) {
				$states[] = [
					'identifier' => '_' . Types\BlockDescription::ROLLER . '_' . $index,
					'sensors' => [
						[
							'identifier' => '_' . Types\SensorDescription::ROLLER,
							'value' => $roller->getState(),
						],
						[
							'identifier' => '_' . Types\SensorDescription::ROLLER_POSITION,
							'value' => $roller->getCurrentPosition(),
						],
						[
							'identifier' => '_' . Types\SensorDescription::ROLLER_STOP_REASON,
							'value' => $roller->getStopReason(),
						],
					],
				];

				$states[] = [
					'identifier' => '_' . Types\BlockDescription::DEVICE,
					'sensors' => [
						[
							'identifier' => '_' . Types\SensorDescription::OVERTEMPERATURE,
							'value' => $roller->hasOvertemperature(),
						],
					],
				];
			}
		}

		if ($state->getLights() !== []) {
			foreach ($state->getLights() as $index => $light) {
				$states[] = [
					'identifier' => '_' . Types\BlockDescription::LIGHT . '_' . $index,
					'sensors' => [
						[
							'identifier' => '_' . Types\SensorDescription::RED,
							'value' => $light->getGreen(),
						],
						[
							'identifier' => '_' . Types\SensorDescription::GREEN,
							'value' => $light->getGreen(),
						],
						[
							'identifier' => '_' . Types\SensorDescription::BLUE,
							'value' => $light->getBlue(),
						],
						[
							'identifier' => '_' . Types\SensorDescription::GAIN,
							'value' => $light->getGain(),
						],
						[
							'identifier' => '_' . Types\SensorDescription::WHITE,
							'value' => $light->getWhite(),
						],
						[
							'identifier' => '_' . Types\SensorDescription::WHITE_LEVEL,
							'value' => $light->getWhite(),
						],
						[
							'identifier' => '_' . Types\SensorDescription::EFFECT,
							'value' => $light->getEffect(),
						],
						[
							'identifier' => '_' . Types\SensorDescription::BRIGHTNESS,
							'value' => $light->getBrightness(),
						],
						[
							'identifier' => '_' . Types\SensorDescription::OUTPUT,
							'value' => $light->getState(),
						],
					],
				];
			}
		}

		if ($state->getEmeters() !== []) {
			foreach ($state->getEmeters() as $index => $emeter) {
				$states[] = [
					'identifier' => '_' . Types\BlockDescription::ROLLER . '_' . $index,
					'sensors' => [
						[
							'identifier' => '_' . Types\SensorDescription::ACTIVE_POWER,
							'value' => $emeter->getActivePower(),
						],
						[
							'identifier' => '_' . Types\SensorDescription::REACTIVE_POWER,
							'value' => $emeter->getReactivePower(),
						],
						[
							'identifier' => '_' . Types\SensorDescription::POWER_FACTOR,
							'value' => $emeter->getPowerFactor(),
						],
						[
							'identifier' => '_' . Types\SensorDescription::CURRENT,
							'value' => $emeter->getCurrent(),
						],
						[
							'identifier' => '_' . Types\SensorDescription::VOLTAGE,
							'value' => $emeter->getVoltage(),
						],
						[
							'identifier' => '_' . Types\SensorDescription::ENERGY,
							'value' => $emeter->getTotal(),
						],
						[
							'identifier' => '_' . Types\SensorDescription::ENERGY_RETURNED,
							'value' => $emeter->getTotalReturned(),
						],
					],
				];
			}
		}

		if (count($states) > 0) {
			$this->queue->append(
				$this->entityHelper->create(
					Entities\Messages\StoreDeviceState::class,
					[
						'connector' => $device->getConnector()->toString(),
						'identifier' => $device->getIdentifier(),
						'ip_address' => $state->getWifi()?->getIp() ?? $this->deviceHelper->getIpAddress($device),
						'states' => $states,
					],
				),
			);
		}
	}

	/**
	 * @throws Exceptions\Runtime
	 */
	public function processGen1DeviceReportedStatus(
		Entities\API\Gen1\ReportDeviceState $state,
	): void
	{
		$states = [];

		foreach ($state->getStates() as $blockState) {
			$states[] = [
				'identifier' => $blockState->getSensor() . '_',
				'value' => $blockState->getValue(),
			];
		}

		$this->queue->append(
			$this->entityHelper->create(
				Entities\Messages\StoreDeviceConnectionState::class,
				[
					'connector' => $this->connector->getId()->toString(),
					'identifier' => $state->getIdentifier(),
					'state' => MetadataTypes\ConnectionState::STATE_CONNECTED,
				],
			),
		);

		if (count($states) > 0) {
			$this->queue->append(
				$this->entityHelper->create(
					Entities\Messages\StoreDeviceState::class,
					[
						'connector' => $this->connector->getId()->toString(),
						'identifier' => $state->getIdentifier(),
						'ip_address' => $state->getIpAddress(),
						'states' => $states,
					],
				),
			);
		}
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function processGen2DeviceGetState(
		MetadataDocuments\DevicesModule\Device $device,
		Entities\API\Gen2\GetDeviceState $state,
	): void
	{
		$states = [];

		if ($state->getSwitches() !== []) {
			foreach ($state->getSwitches() as $component) {
				if ($component->getOutput() === null) {
					continue;
				}

				$states[] = [
					'identifier' => (
						$component->getType()->getValue()
						. '_'
						. $component->getId()
						. '_'
						. Types\ComponentAttributeType::ON
					),
					'value' => $component->getOutput(),
				];
			}
		}

		if ($state->getCovers() !== []) {
			foreach ($state->getCovers() as $component) {
				if ($component->getState() !== null) {
					$states[] = [
						'identifier' => (
							$component->getType()->getValue()
							. '_'
							. $component->getId()
							. '_'
							. Types\ComponentAttributeType::STATE
						),
						'value' => $component->getState()->getValue(),
					];
				}

				if ($component->getCurrentPosition() !== null) {
					$states[] = [
						'identifier' => (
							$component->getType()->getValue()
							. '_'
							. $component->getId()
							. '_'
							. Types\ComponentAttributeType::POSITION
						),
						'value' => $component->getCurrentPosition(),
					];
				}
			}
		}

		if ($state->getLights() !== []) {
			foreach ($state->getLights() as $component) {
				if ($component->getOutput() !== null) {
					$states[] = [
						'identifier' => (
							$component->getType()->getValue()
							. '_'
							. $component->getId()
							. '_'
							. Types\ComponentAttributeType::ON
						),
						'value' => $component->getOutput(),
					];
				}

				if ($component->getBrightness() !== Shelly\Constants::VALUE_NOT_AVAILABLE) {
					$states[] = [
						'identifier' => (
							$component->getType()->getValue()
							. '_'
							. $component->getId()
							. '_'
							. Types\ComponentAttributeType::BRIGHTNESS
						),
						'value' => $component->getBrightness(),
					];
				}
			}
		}

		if ($state->getInputs() !== []) {
			foreach ($state->getInputs() as $component) {
				if ($component->getState() instanceof Types\InputPayload) {
					$value = strval($component->getState()->getValue());
				} elseif ($component->getState() !== null) {
					$value = $component->getState();
				} else {
					$value = $component->getPercent();
				}

				$states[] = [
					'identifier' => (
						$component->getType()->getValue()
						. '_'
						. $component->getId()
					),
					'value' => $value,
				];
			}
		}

		if ($state->getTemperature() !== []) {
			foreach ($state->getTemperature() as $component) {
				if ($component->getTemperatureCelsius() !== Shelly\Constants::VALUE_NOT_AVAILABLE) {
					$states[] = [
						'identifier' => (
							$component->getType()->getValue()
							. '_'
							. $component->getId()
							. '_'
							. Types\ComponentAttributeType::CELSIUS
						),
						'value' => $component->getTemperatureCelsius(),
					];
				}

				if ($component->getTemperatureFahrenheit() !== Shelly\Constants::VALUE_NOT_AVAILABLE) {
					$states[] = [
						'identifier' => (
							$component->getType()->getValue()
							. '_'
							. $component->getId()
							. '_'
							. Types\ComponentAttributeType::FAHRENHEIT
						),
						'value' => $component->getTemperatureFahrenheit(),
					];
				}
			}
		}

		if ($state->getHumidity() !== []) {
			foreach ($state->getHumidity() as $component) {
				if ($component->getRelativeHumidity() !== Shelly\Constants::VALUE_NOT_AVAILABLE) {
					$states[] = [
						'identifier' => (
							$component->getType()->getValue()
							. '_'
							. $component->getId()
						),
						'value' => $component->getRelativeHumidity(),
					];
				}
			}
		}

		if ($state->getSwitches() !== [] || $state->getCovers() !== []) {
			foreach (array_merge($state->getSwitches(), $state->getCovers()) as $component) {
				if ($component->getActivePower() !== Shelly\Constants::VALUE_NOT_AVAILABLE) {
					$states[] = [
						'identifier' => (
							$component->getType()->getValue()
							. '_'
							. $component->getId()
							. '_'
							. Types\ComponentAttributeType::ACTIVE_POWER
						),
						'value' => $component->getActivePower(),
					];
				}

				if ($component->getPowerFactor() !== Shelly\Constants::VALUE_NOT_AVAILABLE) {
					$states[] = [
						'identifier' => (
							$component->getType()->getValue()
							. '_'
							. $component->getId()
							. '_'
							. Types\ComponentAttributeType::POWER_FACTOR
						),
						'value' => $component->getPowerFactor(),
					];
				}

				if ($component->getActiveEnergy() instanceof Entities\API\Gen2\ActiveEnergyStateBlock) {
					$states[] = [
						'identifier' => (
							$component->getType()->getValue()
							. '_'
							. $component->getId()
							. '_'
							. Types\ComponentAttributeType::ACTIVE_ENERGY
						),
						'value' => $component->getActiveEnergy()->getTotal(),
					];
				}

				if ($component->getCurrent() !== Shelly\Constants::VALUE_NOT_AVAILABLE) {
					$states[] = [
						'identifier' => (
							$component->getType()->getValue()
							. '_'
							. $component->getId()
							. '_'
							. Types\ComponentAttributeType::CURRENT
						),
						'value' => $component->getCurrent(),
					];
				}

				if ($component->getVoltage() !== Shelly\Constants::VALUE_NOT_AVAILABLE) {
					$states[] = [
						'identifier' => (
							$component->getType()->getValue()
							. '_'
							. $component->getId()
							. '_'
							. Types\ComponentAttributeType::VOLTAGE
						),
						'value' => $component->getVoltage(),
					];
				}

				if ($component->getTemperature() instanceof Entities\API\Gen2\TemperatureBlockState) {
					$states[] = [
						'identifier' => (
							$component->getType()->getValue()
							. '_'
							. $component->getId()
							. '_'
							. Types\ComponentAttributeType::CELSIUS
						),
						'value' => $component->getTemperature()->getTemperatureCelsius(),
					];
				}
			}
		}

		if (count($states) > 0) {
			$this->queue->append(
				$this->entityHelper->create(
					Entities\Messages\StoreDeviceState::class,
					[
						'connector' => $device->getConnector()->toString(),
						'identifier' => $device->getIdentifier(),
						'ip_address' => $state->getEthernet()?->getIp()
							?? ($state->getWifi()?->getStaIp() ?? $this->deviceHelper->getIpAddress($device)),
						'states' => $states,
					],
				),
			);
		}
	}

	private function registerLoopHandler(): void
	{
		$this->handlerTimer = $this->eventLoop->addTimer(
			self::HANDLER_PROCESSING_INTERVAL,
			function (): void {
				$this->handleCommunication();
			},
		);
	}

}
