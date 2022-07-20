<?php declare(strict_types = 1);

/**
 * Gen1Client.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Clients
 * @since          0.37.0
 *
 * @date           17.07.22
 */

namespace FastyBird\ShellyConnector\Clients;

use DateTimeInterface;
use FastyBird\DateTimeFactory;
use FastyBird\DevicesModule\Exceptions as DevicesModuleExceptions;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\Metadata;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\ShellyConnector\Clients;
use Nette\Utils;
use Psr\Log;
use React\EventLoop;
use React\Http\Message;
use Throwable;

/**
 * Generation 1 devices client
 *
 * When device is in state:
 * STOPPED - is stopped by connected and is no longer processed. This state mark device which is not reachable
 * LOST - is not reachable by connector (disconnected from network, invalid connection, etc.)
 * INIT - connector is trying to load device basic information
 * RUNNING - is ready to handle sensors write requests
 *
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Gen1Client extends Client
{

	private const HTTP_CMD_INFO = 'info';
	private const HTTP_CMD_SETTINGS = 'settings';
	private const HTTP_CMD_DESCRIPTION = 'description';
	private const HTTP_CMD_STATUS = 'status';

	private const SENDING_CMD_DELAY = 60;

	/** @var string[] */
	private array $processedDevices = [];

	/** @var Array<string, Array<string, DateTimeInterface|bool>> */
	private array $processedDevicesCommands = [];

	/** @var Array<string, DateTimeInterface> */
	private array $processedProperties = [];

	/** @var MetadataEntities\Modules\DevicesModule\IConnectorEntity */
	private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector;

	/** @var Clients\Gen1\CoapClient */
	private Clients\Gen1\CoapClient $coapClient;

	/** @var Clients\Gen1\MdnsClient */
	private Clients\Gen1\MdnsClient $mdnsClient;

	/** @var Clients\Gen1\HttpClient */
	private Clients\Gen1\HttpClient $httpClient;

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

	/** @var DateTimeFactory\DateTimeFactory */
	private DateTimeFactory\DateTimeFactory $dateTimeFactory;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param Gen1\CoapClientFactory $coapClientFactory
	 * @param Gen1\MdnsClientFactory $mdnsClientFactory
	 * @param Gen1\HttpClientFactory $httpClientFactory
	 * @param DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository
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
		Clients\Gen1\CoapClientFactory $coapClientFactory,
		Clients\Gen1\MdnsClientFactory $mdnsClientFactory,
		Clients\Gen1\HttpClientFactory $httpClientFactory,
		DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository,
		DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository,
		DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelPropertiesRepository,
		DevicesModuleModels\States\ChannelPropertiesRepository $channelPropertiesStatesRepository,
		DevicesModuleModels\States\ChannelPropertiesManager $channelPropertiesStatesManager,
		DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager,
		DateTimeFactory\DateTimeFactory $dateTimeFactory,
		EventLoop\LoopInterface $eventLoop,
		?Log\LoggerInterface $logger = null
	) {
		parent::__construct($eventLoop);

		$this->connector = $connector;

		$this->coapClient = $coapClientFactory->create();
		$this->mdnsClient = $mdnsClientFactory->create();
		$this->httpClient = $httpClientFactory->create();

		$this->devicesRepository = $devicesRepository;
		$this->channelsRepository = $channelsRepository;
		$this->channelPropertiesRepository = $channelPropertiesRepository;

		$this->channelPropertiesStatesRepository = $channelPropertiesStatesRepository;
		$this->channelPropertiesStatesManager = $channelPropertiesStatesManager;

		$this->deviceConnectionStateManager = $deviceConnectionStateManager;

		$this->dateTimeFactory = $dateTimeFactory;

		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DevicesModuleExceptions\TerminateException
	 */
	public function connect(): void
	{
		try {
			$this->coapClient->connect();
		} catch (Throwable $ex) {
			$this->logger->error('CoAP client could not be started', [
				'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type'   => 'gen1-client',
			]);

			throw new DevicesModuleExceptions\TerminateException(
				'CoAP client could not be started',
				$ex->getCode(),
				$ex
			);
		}

		try {
			$this->mdnsClient->connect();
		} catch (Throwable $ex) {
			$this->logger->error('mDNS client could not be started', [
				'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type'   => 'gen1-client',
			]);

			throw new DevicesModuleExceptions\TerminateException(
				'mDNS client could not be started',
				$ex->getCode(),
				$ex
			);
		}

		try {
			$this->httpClient->connect();
		} catch (Throwable $ex) {
			$this->logger->error('Http api client could not be started', [
				'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type'   => 'gen1-client',
			]);

			throw new DevicesModuleExceptions\TerminateException(
				'Http api client could not be started',
				$ex->getCode(),
				$ex
			);
		}

		parent::connect();
	}

	/**
	 * {@inheritDoc}
	 */
	public function disconnect(): void
	{
		try {
			$this->coapClient->disconnect();
		} catch (Throwable) {
			$this->logger->error('CoAP client could not be disconnected', [
				'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type'   => 'gen1-client',
			]);
		}

		try {
			$this->mdnsClient->disconnect();
		} catch (Throwable) {
			$this->logger->error('mDNS client could not be disconnected', [
				'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type'   => 'gen1-client',
			]);
		}

		parent::disconnect();
	}

	/**
	 * {@inheritDoc}
	 */
	public function isConnected(): bool
	{
		return $this->coapClient->isConnected() || $this->mdnsClient->isConnected() || $this->httpClient->isConnected();
	}

	/**
	 * {@inheritDoc}
	 */
	public function writeDeviceControl(MetadataEntities\Actions\IActionDeviceControlEntity $action): void
	{
		$this->httpClient->writeDeviceControl($action);
	}

	/**
	 * {@inheritDoc}
	 */
	public function writeChannelControl(MetadataEntities\Actions\IActionChannelControlEntity $action): void
	{
		$this->httpClient->writeChannelControl($action);
	}

	/**
	 * @return void
	 *
	 * @throws DevicesModuleExceptions\TerminateException
	 */
	protected function handleCommunication(): void
	{
		foreach ($this->processedProperties as $index => $processedProperty) {
			if (((float) $this->dateTimeFactory->getNow()->format('Uv') - (float) $processedProperty->format('Uv')) >= 500) {
				unset($this->processedProperties[$index]);
			}
		}

		foreach ($this->devicesRepository->findAllByConnector($this->connector->getId()) as $device) {
			if (
				!in_array($device->getId()->toString(), $this->processedDevices, true)
				&& !$this->deviceConnectionStateManager->getState($device)->equalsValue(MetadataTypes\ConnectionStateType::STATE_STOPPED)
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
		$deviceConnectionState = $this->deviceConnectionStateManager->getState($device);

		if ($this->readDeviceData(self::HTTP_CMD_INFO, $device)) {
			return true;
		}

		if ($this->readDeviceData(self::HTTP_CMD_DESCRIPTION, $device)) {
			return true;
		}

		if ($this->readDeviceData(self::HTTP_CMD_SETTINGS, $device)) {
			return true;
		}

		if ($this->readDeviceData(self::HTTP_CMD_STATUS, $device)) {
			return true;
		}

		if ($deviceConnectionState->equalsValue(MetadataTypes\ConnectionStateType::STATE_INIT)) {
			// Initialization is finished, switch device to running mode
			$this->deviceConnectionStateManager->setState(
				$device,
				MetadataTypes\ConnectionStateType::get(MetadataTypes\ConnectionStateType::STATE_RUNNING)
			);
		}

		if ($deviceConnectionState->equalsValue(MetadataTypes\ConnectionStateType::STATE_RUNNING)) {
			return $this->writeChannelsProperty($device);
		}

		return false;
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

		$this->processedDevicesCommands[$device->getId()->toString()][$cmd] = $this->dateTimeFactory->getNow();

		if ($cmd === self::HTTP_CMD_INFO) {
			$result = $this->httpClient->readDeviceInfo($device);

		} elseif ($cmd === self::HTTP_CMD_SETTINGS) {
			$result = $this->httpClient->readDeviceSettings($device);

		} elseif ($cmd === self::HTTP_CMD_DESCRIPTION) {
			$result = $this->httpClient->readDeviceDescription($device);

		} elseif ($cmd === self::HTTP_CMD_STATUS) {
			$result = $this->httpClient->readDeviceStatus($device);
		}

		$result
			?->then(function () use ($cmd, $device): void {
				$this->processedDevicesCommands[$device->getId()->toString()][$cmd] = true;

				$this->deviceConnectionStateManager->setState(
					$device,
					MetadataTypes\ConnectionStateType::get(MetadataTypes\ConnectionStateType::STATE_INIT)
				);
			})
			->otherwise(function (Throwable $ex) use ($cmd, $device): void {
				if ($ex instanceof Message\ResponseException) {
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

						$this->httpClient->writeSensor(
							$device,
							$channel,
							$property,
							$property->getExpectedValue()
						)
							->then(function () use ($property): void {
								$state = $this->channelPropertiesStatesRepository->findOne($property);

								if ($state !== null) {
									$this->channelPropertiesStatesManager->update(
										$property,
										$state,
										Utils\ArrayHash::from([
											'pending' => $this->dateTimeFactory->getNow()->format(DateTimeInterface::ATOM),
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

}
