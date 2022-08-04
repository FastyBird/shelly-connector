<?php declare(strict_types = 1);

/**
 * Connector.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Connector
 * @since          0.37.0
 *
 * @date           14.07.22
 */

namespace FastyBird\ShellyConnector\Connector;

use FastyBird\DevicesModule\Connectors as DevicesModuleConnectors;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\ShellyConnector\Clients;
use FastyBird\ShellyConnector\Consumers;
use FastyBird\ShellyConnector\Helpers;
use Nette;
use React\EventLoop;

/**
 * Connector service container
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Connector
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Connector implements DevicesModuleConnectors\IConnector
{

	use Nette\SmartObject;

	private const QUEUE_PROCESSING_INTERVAL = 0.01;

	/** @var EventLoop\TimerInterface|null */
	private ?EventLoop\TimerInterface $consumerTimer;

	/** @var Clients\IClient */
	private Clients\IClient $client;

	/** @var MetadataEntities\Modules\DevicesModule\IConnectorEntity */
	private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector;

	/** @var Helpers\PropertyHelper */
	private Helpers\PropertyHelper $propertyStateHelper;

	/** @var Consumers\Consumer */
	private Consumers\Consumer $consumer;

	/** @var DevicesModuleModels\DataStorage\IDevicesRepository */
	private DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository;

	/** @var DevicesModuleModels\DataStorage\IDevicePropertiesRepository */
	private DevicesModuleModels\DataStorage\IDevicePropertiesRepository $devicePropertiesRepository;

	/** @var DevicesModuleModels\DataStorage\IChannelsRepository */
	private DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository;

	/** @var DevicesModuleModels\DataStorage\IChannelPropertiesRepository */
	private DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelPropertiesRepository;

	/** @var DevicesModuleModels\States\DeviceConnectionStateManager */
	private DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager;

	/** @var EventLoop\LoopInterface */
	private EventLoop\LoopInterface $eventLoop;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param Clients\IClient $client
	 * @param Helpers\PropertyHelper $propertyStateHelper
	 * @param Consumers\Consumer $consumer
	 * @param DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository
	 * @param DevicesModuleModels\DataStorage\IDevicePropertiesRepository $devicePropertiesRepository
	 * @param DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository
	 * @param DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelPropertiesRepository
	 * @param DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager
	 * @param EventLoop\LoopInterface $eventLoop
	 */
	public function __construct(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		Clients\IClient $client,
		Helpers\PropertyHelper $propertyStateHelper,
		Consumers\Consumer $consumer,
		DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository,
		DevicesModuleModels\DataStorage\IDevicePropertiesRepository $devicePropertiesRepository,
		DevicesModuleModels\DataStorage\IChannelsRepository $channelsRepository,
		DevicesModuleModels\DataStorage\IChannelPropertiesRepository $channelPropertiesRepository,
		DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager,
		EventLoop\LoopInterface $eventLoop
	) {
		$this->connector = $connector;

		$this->client = $client;

		$this->propertyStateHelper = $propertyStateHelper;
		$this->consumer = $consumer;

		$this->devicesRepository = $devicesRepository;
		$this->devicePropertiesRepository = $devicePropertiesRepository;
		$this->channelsRepository = $channelsRepository;
		$this->channelPropertiesRepository = $channelPropertiesRepository;
		$this->deviceConnectionStateManager = $deviceConnectionStateManager;

		$this->eventLoop = $eventLoop;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): void
	{
		foreach ($this->devicesRepository->findAllByConnector($this->connector->getId()) as $device) {
			$this->deviceConnectionStateManager->setState(
				$device,
				MetadataTypes\ConnectionStateType::get(MetadataTypes\ConnectionStateType::STATE_UNKNOWN)
			);

			/** @var MetadataEntities\Modules\DevicesModule\IDeviceDynamicPropertyEntity[] $properties */
			$properties = $this->devicePropertiesRepository->findAllByDevice(
				$device->getId(),
				MetadataEntities\Modules\DevicesModule\DeviceDynamicPropertyEntity::class
			);

			$this->propertyStateHelper->setValidState($properties, false);

			foreach ($this->channelsRepository->findAllByDevice($device->getId()) as $channel) {
				/** @var MetadataEntities\Modules\DevicesModule\ChannelDynamicPropertyEntity[] $properties */
				$properties = $this->channelPropertiesRepository->findAllByChannel(
					$channel->getId(),
					MetadataEntities\Modules\DevicesModule\ChannelDynamicPropertyEntity::class
				);

				$this->propertyStateHelper->setValidState($properties, false);
			}
		}

		$this->client->connect();

		$this->consumerTimer = $this->eventLoop->addPeriodicTimer(self::QUEUE_PROCESSING_INTERVAL, function (): void {
			$this->consumer->consume();
		});
	}

	/**
	 * {@inheritDoc}
	 */
	public function terminate(): void
	{
		$this->client->disconnect();

		foreach ($this->devicesRepository->findAllByConnector($this->connector->getId()) as $device) {
			$this->deviceConnectionStateManager->setState(
				$device,
				MetadataTypes\ConnectionStateType::get(MetadataTypes\ConnectionStateType::STATE_DISCONNECTED)
			);

			/** @var MetadataEntities\Modules\DevicesModule\IDeviceDynamicPropertyEntity[] $properties */
			$properties = $this->devicePropertiesRepository->findAllByDevice(
				$device->getId(),
				MetadataEntities\Modules\DevicesModule\DeviceDynamicPropertyEntity::class
			);

			$this->propertyStateHelper->setValidState($properties, false);

			foreach ($this->channelsRepository->findAllByDevice($device->getId()) as $channel) {
				/** @var MetadataEntities\Modules\DevicesModule\ChannelDynamicPropertyEntity[] $properties */
				$properties = $this->channelPropertiesRepository->findAllByChannel(
					$channel->getId(),
					MetadataEntities\Modules\DevicesModule\ChannelDynamicPropertyEntity::class
				);

				$this->propertyStateHelper->setValidState($properties, false);
			}
		}

		if ($this->consumerTimer !== null) {
			$this->eventLoop->cancelTimer($this->consumerTimer);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function hasUnfinishedTasks(): bool
	{
		return !$this->consumer->isEmpty() && $this->consumerTimer !== null;
	}

	/**
	 * @param MetadataEntities\Actions\IActionDeviceControlEntity $action
	 *
	 * @return void
	 */
	public function handleDeviceControlAction(MetadataEntities\Actions\IActionDeviceControlEntity $action): void
	{
		if (!$action->getAction()->equalsValue(MetadataTypes\ControlActionType::ACTION_SET)) {
			return;
		}

		$this->client->writeDeviceControl($action);
	}

	/**
	 * @param MetadataEntities\Actions\IActionChannelControlEntity $action
	 *
	 * @return void
	 */
	public function handleChannelControlAction(MetadataEntities\Actions\IActionChannelControlEntity $action): void
	{
		if (!$action->getAction()->equalsValue(MetadataTypes\ControlActionType::ACTION_SET)) {
			return;
		}

		$this->client->writeChannelControl($action);
	}

}
