<?php declare(strict_types = 1);

/**
 * HttpClient.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Clients
 * @since          0.37.0
 *
 * @date           18.07.22
 */

namespace FastyBird\ShellyConnector\Clients\Gen1;

use DateTimeInterface;
use FastyBird\DateTimeFactory;
use FastyBird\DevicesModule\Exceptions as DevicesModuleExceptions;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\Metadata;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\ShellyConnector\API;
use FastyBird\ShellyConnector\Consumers;
use FastyBird\ShellyConnector\Exceptions;
use FastyBird\ShellyConnector\Types;
use Nette;
use Nette\Utils;
use Psr\Http\Message;
use Psr\Log;
use React\EventLoop;
use React\Http;
use React\Promise;
use RuntimeException;
use Throwable;

/**
 * HTTP api client
 *
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class HttpClient
{

	use Nette\SmartObject;

	private const SHELLY_ENDPOINT = 'http://ADDRESS/shelly';
	private const STATUS_ENDPOINT = 'http://ADDRESS/status';
	private const SETTINGS_ENDPOINT = 'http://ADDRESS/settings';
	private const DESCRIPTION_ENDPOINT = 'http://ADDRESS/cit/d';

	private const SET_CHANNEL_SENSOR_ENDPOINT = 'http://ADDRESS/CHANNEL/INDEX?ACTION=VALUE';

	private const CHANNEL_BLOCK = '/^(?P<identifier>[0-9]+)_(?P<description>[a-zA-Z0-9_]+)$/';
	private const PROPERTY_SENSOR = '/^(?P<identifier>[0-9]+)_(?P<type>[a-zA-Z]{1,3})_(?P<description>[a-zA-Z0-9]+)$/';

	private const BLOCK_PARTS = '/^(?P<channelName>[a-zA-Z]+)_(?P<channelIndex>[0-9_]+)$/';

	private const CMD_SHELLY = 'shelly';
	private const CMD_SETTINGS = 'settings';
	private const CMD_DESCRIPTION = 'description';
	private const CMD_STATUS = 'status';

	private const SENDING_CMD_DELAY = 60;

	private const HANDLER_START_DELAY = 2;
	private const HANDLER_PROCESSING_INTERVAL = 0.01;

	/** @var string[] */
	private array $processedDevices = [];

	/** @var Array<string, Array<string, DateTimeInterface|bool>> */
	private array $processedDevicesCommands = [];

	/** @var Array<string, DateTimeInterface> */
	private array $processedProperties = [];

	/** @var EventLoop\TimerInterface|null */
	protected ?EventLoop\TimerInterface $handlerTimer;

	/** @var MetadataEntities\Modules\DevicesModule\IConnectorEntity */
	private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector;

	/** @var API\Gen1Validator */
	private API\Gen1Validator $validator;

	/** @var API\Gen1Parser */
	private API\Gen1Parser $parser;

	/** @var API\Gen1Transformer */
	private API\Gen1Transformer $transformer;

	/** @var Consumers\Consumer */
	private Consumers\Consumer $consumer;

	/** @var Http\Browser|null */
	private ?Http\Browser $browser = null;

	/** @var DevicesModuleModels\DataStorage\IDevicesRepository */
	private DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository;

	/** @var DevicesModuleModels\DataStorage\IChannelsRepository */
	private DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository;

	/** @var DevicesModuleModels\DataStorage\IChannelPropertiesRepository */
	private DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelPropertiesRepository;

	/** @var DevicesModuleModels\States\ChannelPropertiesRepository */
	private DevicesModuleModels\States\ChannelPropertiesRepository $channelPropertiesStatesRepository;

	/** @var DevicesModuleModels\States\ChannelPropertiesManager */
	private DevicesModuleModels\States\ChannelPropertiesManager $channelPropertiesStatesManager;

	/** @var DevicesModuleModels\States\DeviceConnectionStateManager */
	private DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager;

	/** @var DevicesModuleModels\DataStorage\IDevicePropertiesRepository */
	private DevicesModuleModels\DataStorage\IDevicePropertiesRepository $devicePropertiesRepository;

	/** @var DateTimeFactory\DateTimeFactory */
	private DateTimeFactory\DateTimeFactory $dateTimeFactory;

	/** @var EventLoop\LoopInterface */
	private EventLoop\LoopInterface $eventLoop;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param API\Gen1Validator $validator
	 * @param API\Gen1Parser $parser
	 * @param API\Gen1Transformer $transformer
	 * @param Consumers\Consumer $consumer
	 * @param DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository
	 * @param DevicesModuleModels\DataStorage\IDevicePropertiesRepository $devicePropertiesRepository
	 * @param DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository
	 * @param DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelPropertiesRepository
	 * @param DevicesModuleModels\States\ChannelPropertiesRepository $channelPropertiesStatesRepository
	 * @param DevicesModuleModels\States\ChannelPropertiesManager $channelPropertiesStatesManager
	 * @param DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager
	 * @param DateTimeFactory\DateTimeFactory $dateTimeFactory
	 * @param EventLoop\LoopInterface $eventLoop
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		API\Gen1Validator $validator,
		API\Gen1Parser $parser,
		API\Gen1Transformer $transformer,
		Consumers\Consumer $consumer,
		DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository,
		DevicesModuleModels\DataStorage\IDevicePropertiesRepository $devicePropertiesRepository,
		DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository,
		DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelPropertiesRepository,
		DevicesModuleModels\States\ChannelPropertiesRepository $channelPropertiesStatesRepository,
		DevicesModuleModels\States\ChannelPropertiesManager $channelPropertiesStatesManager,
		DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager,
		DateTimeFactory\DateTimeFactory $dateTimeFactory,
		EventLoop\LoopInterface $eventLoop,
		?Log\LoggerInterface $logger = null
	) {
		$this->connector = $connector;

		$this->validator = $validator;
		$this->parser = $parser;
		$this->transformer = $transformer;

		$this->consumer = $consumer;

		$this->devicesRepository = $devicesRepository;
		$this->devicePropertiesRepository = $devicePropertiesRepository;

		$this->channelsRepository = $channelsRepository;
		$this->channelPropertiesRepository = $channelPropertiesRepository;

		$this->channelPropertiesStatesRepository = $channelPropertiesStatesRepository;
		$this->channelPropertiesStatesManager = $channelPropertiesStatesManager;

		$this->deviceConnectionStateManager = $deviceConnectionStateManager;

		$this->dateTimeFactory = $dateTimeFactory;

		$this->eventLoop = $eventLoop;

		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * @return bool
	 */
	public function isConnected(): bool
	{
		return $this->browser !== null;
	}

	/**
	 * @return void
	 */
	public function connect(): void
	{
		$this->browser = new Http\Browser($this->eventLoop);

		$this->eventLoop->addTimer(
			self::HANDLER_START_DELAY,
			function (): void {
				$this->handlerTimer = $this->eventLoop->addPeriodicTimer(
					self::HANDLER_PROCESSING_INTERVAL,
					function (): void {
						$this->handleCommunication();
					}
				);
			}
		);
	}

	/**
	 * @return void
	 */
	public function disconnect(): void
	{
		if ($this->handlerTimer !== null) {
			$this->eventLoop->cancelTimer($this->handlerTimer);
		}
	}

	/**
	 * @param MetadataEntities\Actions\IActionDeviceControlEntity $action
	 *
	 * @return void
	 */
	public function writeDeviceControl(MetadataEntities\Actions\IActionDeviceControlEntity $action): void
	{
		// TODO: Implement writeDeviceControl() method.
	}

	/**
	 * @param MetadataEntities\Actions\IActionChannelControlEntity $action
	 *
	 * @return void
	 */
	public function writeChannelControl(MetadataEntities\Actions\IActionChannelControlEntity $action): void
	{
		// TODO: Implement writeChannelControl() method.
	}

	/**
	 * @return void
	 *
	 * @throws DevicesModuleExceptions\TerminateException
	 */
	private function handleCommunication(): void
	{
		foreach ($this->processedProperties as $index => $processedProperty) {
			if (((float) $this->dateTimeFactory->getNow()->format('Uv') - (float) $processedProperty->format('Uv')) >= 500) {
				unset($this->processedProperties[$index]);
			}
		}

		foreach ($this->devicesRepository->findAllByConnector($this->connector->getId()) as $device) {
			if (
				!in_array($device->getId()->toString(), $this->processedDevices, true)
				&& $this->getDeviceAddress($device) !== null
				&& !$this->deviceConnectionStateManager->getState($device)
					->equalsValue(MetadataTypes\ConnectionStateType::STATE_STOPPED)
			) {
				$this->processedDevices[] = $device->getId()->toString();

				if ($this->processDevice($device)) {
					return;
				}
			}
		}

		$this->processedDevices = [];
	}

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IDeviceEntity $device
	 *
	 * @return bool
	 *
	 * @throws DevicesModuleExceptions\TerminateException
	 */
	private function processDevice(MetadataEntities\Modules\DevicesModule\IDeviceEntity $device): bool
	{
		if ($this->readDeviceData(self::CMD_SHELLY, $device)) {
			return true;
		}

		if ($this->readDeviceData(self::CMD_DESCRIPTION, $device)) {
			return true;
		}

		return $this->writeChannelsProperty($device);
	}

	/**
	 * @param string $cmd
	 * @param MetadataEntities\Modules\DevicesModule\IDeviceEntity $device
	 *
	 * @return bool
	 *
	 * @throws DevicesModuleExceptions\TerminateException
	 */
	private function readDeviceData(string $cmd, MetadataEntities\Modules\DevicesModule\IDeviceEntity $device): bool
	{
		$httpCmdResult = null;

		if (!array_key_exists($device->getId()->toString(), $this->processedDevicesCommands)) {
			$this->processedDevicesCommands[$device->getId()->toString()] = [];
		}

		if (array_key_exists($cmd, $this->processedDevicesCommands[$device->getId()->toString()])) {
			$httpCmdResult = $this->processedDevicesCommands[$device->getId()->toString()][$cmd];
		}

		if ($httpCmdResult === true) {
			return true;
		}

		if (
			$httpCmdResult instanceof DateTimeInterface
			&& ($this->dateTimeFactory->getNow()->getTimestamp() - $httpCmdResult->getTimestamp()) < self::SENDING_CMD_DELAY
		) {
			return false;
		}

		$result = null;

		if ($cmd === self::CMD_SHELLY) {
			$result = $this->readDeviceInfo($device);

		} elseif ($cmd === self::CMD_DESCRIPTION) {
			$result = $this->readDeviceDescription($device);
		}

		$this->processedDevicesCommands[$device->getId()->toString()][$cmd] = $this->dateTimeFactory->getNow();

		$result
			?->then(function () use ($cmd, $device): void {
				$this->processedDevicesCommands[$device->getId()->toString()][$cmd] = true;
			})
			->otherwise(function (Throwable $ex) use ($cmd, $device): void {
				if ($ex instanceof Http\Message\ResponseException) {
					if ($ex->getCode() >= 400 && $ex->getCode() < 499) {
						$this->deviceConnectionStateManager->setState(
							$device,
							MetadataTypes\ConnectionStateType::get(MetadataTypes\ConnectionStateType::STATE_STOPPED)
						);

					} elseif ($ex->getCode() >= 500 && $ex->getCode() < 599) {
						$this->deviceConnectionStateManager->setState(
							$device,
							MetadataTypes\ConnectionStateType::get(MetadataTypes\ConnectionStateType::STATE_LOST)
						);

					} else {
						$this->deviceConnectionStateManager->setState(
							$device,
							MetadataTypes\ConnectionStateType::get(MetadataTypes\ConnectionStateType::STATE_UNKNOWN)
						);
					}
				}

				if ($ex instanceof RuntimeException) {
					$this->deviceConnectionStateManager->setState(
						$device,
						MetadataTypes\ConnectionStateType::get(MetadataTypes\ConnectionStateType::STATE_LOST)
					);
				}

				$this->processedDevicesCommands[$device->getId()->toString()][$cmd] = $this->dateTimeFactory->getNow();
			});

		return false;
	}

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IDeviceEntity $device
	 *
	 * @return bool
	 *
	 * @throws DevicesModuleExceptions\TerminateException
	 */
	private function writeChannelsProperty(MetadataEntities\Modules\DevicesModule\IDeviceEntity $device): bool
	{
		$now = $this->dateTimeFactory->getNow();

		foreach ($this->channelsRepository->findAllByDevice($device->getId()) as $channel) {
			foreach ($this->channelPropertiesRepository->findAllByChannel($channel->getId()) as $property) {
				if (
					(
						$property instanceof MetadataEntities\Modules\DevicesModule\IChannelDynamicPropertyEntity
						|| $property instanceof MetadataEntities\Modules\DevicesModule\IChannelMappedPropertyEntity
					)
					&& $property->getExpectedValue() !== null
					&& $property->isPending()
				) {
					$pending = is_string($property->getPending()) ? Utils\DateTime::createFromFormat(DateTimeInterface::ATOM, $property->getPending()) : true;
					$debounce = array_key_exists($property->getId()->toString(), $this->processedProperties) ? $this->processedProperties[$property->getId()->toString()] : false;

					if (
						$debounce !== false
						&& (float) $now->format('Uv') - (float) $debounce->format('Uv') < 500
					) {
						continue;
					}

					unset($this->processedProperties[$property->getId()->toString()]);

					if (
						$pending === true
						|| (
							$pending !== false
							&& (float) $now->format('Uv') - (float) $pending->format('Uv') > 2000
						)
					) {
						$this->processedProperties[$property->getId()->toString()] = $now;

						$valueToWrite = $this->transformer->transformValueToDevice(
							$property->getDataType(),
							$property->getFormat(),
							$property->getExpectedValue()
						);

						if ($valueToWrite === null) {
							return true;
						}

						$this->writeSensor(
							$device,
							$channel,
							$property,
							$valueToWrite
						)
							->then(function () use ($property): void {
								$state = $this->channelPropertiesStatesRepository->findOne($property);

								if ($state !== null) {
									$this->channelPropertiesStatesManager->update(
										$property,
										$state,
										Utils\ArrayHash::from([
											'pending' => $this->dateTimeFactory->getNow()
												->format(DateTimeInterface::ATOM),
										])
									);
								}
							})
							->otherwise(function () use ($property): void {
								unset($this->processedProperties[$property->getId()->toString()]);
							});

						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IDeviceEntity $device
	 *
	 * @return Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	 *
	 * @throws DevicesModuleExceptions\TerminateException
	 */
	private function readDeviceInfo(
		MetadataEntities\Modules\DevicesModule\IDeviceEntity $device
	): Promise\ExtendedPromiseInterface|Promise\PromiseInterface {
		$address = $this->buildDeviceAddress($device);

		if ($address === null) {
			return Promise\reject(new Exceptions\InvalidStateException('Device address could not be determined'));
		}

		return $this->getBrowser()->get(
			Utils\Strings::replace(
				self::SHELLY_ENDPOINT,
				[
					'/ADDRESS/' => $address,
				]
			)
		)
			->then(function (Message\ResponseInterface $response) use ($device, $address): void {
				$message = $response->getBody()->getContents();

				if ($this->validator->isValidHttpShellyMessage($message)) {
					try {
						$this->consumer->append(
							$this->parser->parseHttpShellyMessage(
								$device->getIdentifier(),
								$address,
								$message
							)
						);
					} catch (Exceptions\ParseMessageException $ex) {
						$this->logger->warning(
							'Received message could not be parsed into entity',
							[
								'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
								'type'      => 'http-client',
								'exception' => [
									'message' => $ex->getMessage(),
									'code'    => $ex->getCode(),
								],
							]
						);
					}
				}
			})
			->otherwise(function (Throwable $ex) use ($address, $device): void {
				$this->logger->error(
					'Failed to call device http api',
					[
						'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type'      => 'http-client',
						'endpoint'  => Utils\Strings::replace(
							self::SHELLY_ENDPOINT,
							[
								'/ADDRESS/' => $address,
							]
						),
						'device'    => [
							'id' => $device->getId()->toString(),
						],
						'exception' => [
							'message' => $ex->getMessage(),
							'code'    => $ex->getCode(),
						],
					]
				);
			});
	}

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IDeviceEntity $device
	 *
	 * @return Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	 *
	 * @throws DevicesModuleExceptions\TerminateException
	 */
	private function readDeviceDescription(
		MetadataEntities\Modules\DevicesModule\IDeviceEntity $device
	): Promise\ExtendedPromiseInterface|Promise\PromiseInterface {
		$address = $this->buildDeviceAddress($device);

		if ($address === null) {
			return Promise\reject(new Exceptions\InvalidStateException('Device address could not be determined'));
		}

		return $this->getBrowser()->get(
			Utils\Strings::replace(
				self::DESCRIPTION_ENDPOINT,
				[
					'/ADDRESS/' => $address,
				]
			)
		)
			->then(function (Message\ResponseInterface $response) use ($device, $address): void {
				$message = $response->getBody()->getContents();

				if ($this->validator->isValidHttpDescriptionMessage($message)) {
					try {
						$this->consumer->append(
							$this->parser->parseHttpDescriptionMessage(
								$device->getIdentifier(),
								$address,
								$message
							)
						);
					} catch (Exceptions\ParseMessageException $ex) {
						$this->logger->warning(
							'Received message could not be parsed into entity',
							[
								'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
								'type'      => 'http-client',
								'exception' => [
									'message' => $ex->getMessage(),
									'code'    => $ex->getCode(),
								],
							]
						);
					}
				}
			})
			->otherwise(function (Throwable $ex) use ($address, $device): void {
				$this->logger->error(
					'Failed to call device http api',
					[
						'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type'      => 'http-client',
						'endpoint'  => Utils\Strings::replace(
							self::DESCRIPTION_ENDPOINT,
							[
								'/ADDRESS/' => $address,
							]
						),
						'device'    => [
							'id' => $device->getId()->toString(),
						],
						'exception' => [
							'message' => $ex->getMessage(),
							'code'    => $ex->getCode(),
						],
					]
				);
			});
	}

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IDeviceEntity $device
	 * @param MetadataEntities\Modules\DevicesModule\IChannelEntity $channel
	 * @param MetadataEntities\Modules\DevicesModule\IChannelDynamicPropertyEntity|MetadataEntities\Modules\DevicesModule\IChannelMappedPropertyEntity $property
	 * @param float|bool|int|string $valueToWrite
	 *
	 * @return Promise\ExtendedPromiseInterface|Promise\PromiseInterface
	 *
	 * @throws DevicesModuleExceptions\TerminateException
	 */
	private function writeSensor(
		MetadataEntities\Modules\DevicesModule\IDeviceEntity $device,
		MetadataEntities\Modules\DevicesModule\IChannelEntity $channel,
		MetadataEntities\Modules\DevicesModule\IChannelDynamicPropertyEntity|MetadataEntities\Modules\DevicesModule\IChannelMappedPropertyEntity $property,
		float|bool|int|string $valueToWrite
	): Promise\ExtendedPromiseInterface|Promise\PromiseInterface {
		$address = $this->buildDeviceAddress($device);

		if ($address === null) {
			return Promise\reject(new Exceptions\InvalidStateException('Device address could not be determined'));
		}

		if (
			preg_match(self::CHANNEL_BLOCK, $channel->getIdentifier(), $channelMatches) !== 1
			|| array_key_exists('identifier', $channelMatches)
			|| array_key_exists('description', $channelMatches)
		) {
			$this->logger->error('Channel identifier is not in expected format', [
				'source'   => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type'     => 'http-client',
				'device'   => [
					'id' => $device->getId()->toString(),
				],
				'channel'  => [
					'id' => $channel->getId()->toString(),
				],
				'property' => [
					'id' => $property->getId()->toString(),
				],
			]);

			return Promise\reject(new Exceptions\InvalidStateException('Channel identifier is not in expected format'));
		}

		if (
			preg_match(self::BLOCK_PARTS, $channelMatches['description'], $blockMatches) !== 1
			|| array_key_exists('channelName', $blockMatches)
			|| array_key_exists('channelIndex', $blockMatches)
		) {
			$this->logger->error('Channel - block description is not in expected format', [
				'source'   => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type'     => 'http-client',
				'device'   => [
					'id' => $device->getId()->toString(),
				],
				'channel'  => [
					'id' => $channel->getId()->toString(),
				],
				'property' => [
					'id' => $property->getId()->toString(),
				],
			]);

			return Promise\reject(new Exceptions\InvalidStateException('Channel - block description is not in expected format'));
		}

		try {
			$sensorAction = $this->buildSensorAction($property);

		} catch (Exceptions\InvalidStateException $ex) {
			$this->logger->error('Sensor action could not be created', [
				'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type'      => 'http-client',
				'device'    => [
					'id' => $device->getId()->toString(),
				],
				'channel'   => [
					'id' => $channel->getId()->toString(),
				],
				'property'  => [
					'id' => $property->getId()->toString(),
				],
				'exception' => [
					'message' => $ex->getMessage(),
					'code'    => $ex->getCode(),
				],
			]);

			return Promise\reject(new Exceptions\InvalidStateException('Sensor action could not be created'));
		}

		// @phpstan-ignore-next-line
		return $this->getBrowser()->get(
			Utils\Strings::replace(
				self::SET_CHANNEL_SENSOR_ENDPOINT,
				[
					'/ADDRESS/' => $address,
					'/CHANNEL/' => $blockMatches['channelName'],
					'/INDEX/'   => $blockMatches['channelIndex'],
					'/ACTION/'  => $sensorAction,
					'/VALUE/'   => $valueToWrite,
				]
			)
		)
			->otherwise(function (Throwable $ex) use (
				$address,
				$blockMatches,
				$sensorAction,
				$valueToWrite,
				$device,
				$channel,
				$property
			): void {
				$this->logger->error(
					'Failed to call device http api',
					[
						'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type'      => 'http-client',
						'endpoint'  => Utils\Strings::replace(
							self::SET_CHANNEL_SENSOR_ENDPOINT,
							[
								'/ADDRESS/' => $address,
								'/CHANNEL/' => $blockMatches['channelName'],
								'/INDEX/'   => $blockMatches['channelIndex'],
								'/ACTION/'  => $sensorAction,
								'/VALUE/'   => $valueToWrite,
							]
						),
						'device'    => [
							'id' => $device->getId()->toString(),
						],
						'channel'   => [
							'id' => $channel->getId()->toString(),
						],
						'property'  => [
							'id' => $property->getId()->toString(),
						],
						'exception' => [
							'message' => $ex->getMessage(),
							'code'    => $ex->getCode(),
						],
					]
				);
			});
	}

	/**
	 * @return Http\Browser
	 *
	 * @throws DevicesModuleExceptions\TerminateException
	 */
	private function getBrowser(): Http\Browser
	{
		if ($this->browser === null) {
			$this->connect();
		}

		if ($this->browser === null) {
			throw new DevicesModuleExceptions\TerminateException('HTTP client could not be established');
		}

		return $this->browser;
	}

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IDeviceEntity $device
	 *
	 * @return string|null
	 */
	private function getDeviceAddress(MetadataEntities\Modules\DevicesModule\IDeviceEntity $device): ?string
	{
		$ipAddressProperty = $this->devicePropertiesRepository->findByIdentifier(
			$device->getId(),
			Types\DevicePropertyIdentifierType::IDENTIFIER_IP_ADDRESS
		);

		if (
			$ipAddressProperty instanceof MetadataEntities\Modules\DevicesModule\IDeviceStaticPropertyEntity
			&& is_string($ipAddressProperty->getValue())
		) {
			return $ipAddressProperty->getValue();
		}

		return null;
	}

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IDeviceEntity $device
	 *
	 * @return string|null
	 */
	private function buildDeviceAddress(MetadataEntities\Modules\DevicesModule\IDeviceEntity $device): ?string
	{
		$ipAddress = $this->getDeviceAddress($device);

		if ($ipAddress === null) {
			$this->logger->error('Device IP address could not be determined', [
				'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type'   => 'http-client',
				'device' => [
					'id' => $device->getId()->toString(),
				],
			]);

			return null;
		}

		$usernameProperty = $this->devicePropertiesRepository->findByIdentifier(
			$device->getId(),
			Types\DevicePropertyIdentifierType::IDENTIFIER_USERNAME,
		);

		$username = null;

		if (
			$usernameProperty instanceof MetadataEntities\Modules\DevicesModule\IDeviceStaticPropertyEntity
			&& is_string($usernameProperty->getValue())
		) {
			$username = $usernameProperty->getValue();
		}

		$passwordProperty = $this->devicePropertiesRepository->findByIdentifier(
			$device->getId(),
			Types\DevicePropertyIdentifierType::IDENTIFIER_PASSWORD,
		);

		$password = null;

		if (
			$passwordProperty instanceof MetadataEntities\Modules\DevicesModule\IDeviceStaticPropertyEntity
			&& is_string($passwordProperty->getValue())
		) {
			$password = $passwordProperty->getValue();
		}

		if ($username !== null && $password !== null) {
			return $username . ':' . $password . '@' . $ipAddress;
		}

		return $ipAddress;
	}

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IChannelDynamicPropertyEntity|MetadataEntities\Modules\DevicesModule\IChannelMappedPropertyEntity $property
	 *
	 * @return string
	 */
	private function buildSensorAction(
		MetadataEntities\Modules\DevicesModule\IChannelDynamicPropertyEntity|MetadataEntities\Modules\DevicesModule\IChannelMappedPropertyEntity $property
	): string {
		if (preg_match(self::PROPERTY_SENSOR, $property->getIdentifier(), $propertyMatches) !== 1) {
			throw new Exceptions\InvalidStateException('Property identifier is not valid');
		}

		if (
			array_key_exists('identifier', $propertyMatches)
			|| array_key_exists('type', $propertyMatches)
			|| array_key_exists('description', $propertyMatches)
		) {
			throw new Exceptions\InvalidStateException('Property identifier is not valid');
		}

		if ($propertyMatches['description'] === Types\WritableSensorTypeType::TYPE_OUTPUT) {
			return 'turn';
		}

		if ($propertyMatches['description'] === Types\WritableSensorTypeType::TYPE_COLOR_TEMP) {
			return 'temp';
		}

		return $propertyMatches['description'];
	}

}
