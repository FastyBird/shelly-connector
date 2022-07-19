<?php declare(strict_types = 1);

/**
 * Connector.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnectorEntity!
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

/**
 * Connector service container
 *
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Connector
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Connector implements DevicesModuleConnectors\IConnector
{

	/** @var Clients\IClient */
	private Clients\IClient $client;

	/** @var MetadataEntities\Modules\DevicesModule\IConnectorEntity */
	private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector;

	/** @var Consumers\Consumer */
	private Consumers\Consumer $consumer;

	/** @var DevicesModuleModels\DataStorage\IDevicesRepository */
	private DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository;

	/** @var DevicesModuleModels\States\DeviceConnectionStateManager */
	private DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param Clients\IClient $client
	 * @param Consumers\Consumer $consumer
	 * @param DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository
	 * @param DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager
	 */
	public function __construct(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		Clients\IClient $client,
		Consumers\Consumer $consumer,
		DevicesModuleModels\DataStorage\IDevicesRepository $devicesRepository,
		DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager
	) {
		$this->connector = $connector;

		$this->client = $client;

		$this->consumer = $consumer;

		$this->devicesRepository = $devicesRepository;
		$this->deviceConnectionStateManager = $deviceConnectionStateManager;
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
		}

		$this->client->connect();
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
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function hasUnfinishedTasks(): bool
	{
		return !$this->consumer->isEmpty();
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
