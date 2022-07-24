<?php declare(strict_types = 1);

/**
 * StatusMessageConsumer.php
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

namespace FastyBird\ShellyConnector\Consumers;

use FastyBird\DevicesModule\Exceptions as DevicesModuleExceptions;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\DevicesModule\Utilities as DevicesModuleUtilities;
use FastyBird\Metadata;
use FastyBird\ShellyConnector\Entities;
use FastyBird\ShellyConnector\Mappers;
use Nette;
use Nette\Utils;
use Psr\Log;

/**
 * Device status message consumer
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class StatusMessageConsumer implements IConsumer
{

	use Nette\SmartObject;

	/** @var DevicesModuleModels\DataStorage\IDevicesRepository */
	private DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository;

	/** @var DevicesModuleModels\States\ChannelPropertiesRepository */
	private DevicesModuleModels\States\ChannelPropertiesRepository $propertyStateRepository;

	/** @var DevicesModuleModels\States\ChannelPropertiesManager */
	private DevicesModuleModels\States\ChannelPropertiesManager $propertiesStatesManager;

	/** @var DevicesModuleModels\States\DeviceConnectionStateManager */
	private DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager;

	/** @var Mappers\ISensorMapper */
	private Mappers\ISensorMapper $sensorMapper;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository
	 * @param DevicesModuleModels\States\ChannelPropertiesRepository $propertyStateRepository
	 * @param DevicesModuleModels\States\ChannelPropertiesManager $propertiesStatesManager
	 * @param DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager
	 * @param Mappers\ISensorMapper $sensorMapper
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository,
		DevicesModuleModels\States\ChannelPropertiesRepository $propertyStateRepository,
		DevicesModuleModels\States\ChannelPropertiesManager $propertiesStatesManager,
		DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager,
		Mappers\ISensorMapper $sensorMapper,
		?Log\LoggerInterface $logger,
	) {
		$this->devicesDataStorageRepository = $devicesDataStorageRepository;

		$this->propertyStateRepository = $propertyStateRepository;
		$this->propertiesStatesManager = $propertiesStatesManager;
		$this->deviceConnectionStateManager = $deviceConnectionStateManager;

		$this->sensorMapper = $sensorMapper;

		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * {@inheritDoc}
	 */
	public function consume(Entities\Messages\IEntity $entity): bool
	{
		if (!$entity instanceof Entities\Messages\DeviceStatusEntity) {
			return false;
		}

		$device = $this->devicesDataStorageRepository->findByIdentifier(
			$entity->getConnector(),
			$entity->getIdentifier()
		);

		if ($device === null) {
			return true;
		}

		// Check device state...
		if (
			!$this->deviceConnectionStateManager->getState($device)->equalsValue(Metadata\Types\ConnectionStateType::STATE_READY)
		) {
			// ... and if it is not ready, set it to ready
			$this->deviceConnectionStateManager->setState(
				$device,
				Metadata\Types\ConnectionStateType::get(Metadata\Types\ConnectionStateType::STATE_READY)
			);
		}

		foreach ($entity->getChannels() as $shellyChannel) {
			foreach ($shellyChannel->getSensors() as $sensor) {
				$property = $this->sensorMapper->findProperty(
					$entity->getConnector(),
					$entity->getIdentifier(),
					$sensor->getIdentifier()
				);

				if ($property !== null) {
					try {
						$propertyState = $this->propertyStateRepository->findOne($property);

					} catch (DevicesModuleExceptions\NotImplementedException) {
						$this->logger->warning(
							'States repository is not configured. State could not be fetched',
							[
								'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
								'type'   => 'status-message-consumer',
								'device' => [
									'id' => $device->getId()->toString(),
								],
							]
						);

						continue;
					}

					$actualValue = DevicesModuleUtilities\ValueHelper::flattenValue(
						DevicesModuleUtilities\ValueHelper::normalizeValue(
							$property->getDataType(),
							$sensor->getValue(),
							$property->getFormat(),
							$property->getInvalid()
						)
					);

					try {
						// In case synchronization failed...
						if ($propertyState === null) {
							// ...create state in storage
							$propertyState = $this->propertiesStatesManager->create(
								$property,
								Utils\ArrayHash::from(array_merge(
									$property->toArray(),
									[
										'actualValue'   => $actualValue,
										'expectedValue' => null,
										'pending'       => false,
										'valid'         => true,
									]
								))
							);

							$this->logger->debug(
								'Channel property state was created',
								[
									'source'   => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
									'type'     => 'status-message-consumer',
									'device'   => [
										'id' => $device->getId()->toString(),
									],
									'channel'  => [
										'id' => $property->getChannel()->toString(),
									],
									'property' => [
										'id'    => $property->getId()->toString(),
										'state' => $propertyState->toArray(),
									],
								]
							);

						} else {
							$propertyState = $this->propertiesStatesManager->update(
								$property,
								$propertyState,
								Utils\ArrayHash::from([
									'actualValue' => $actualValue,
									'valid'       => true,
								])
							);

							$this->logger->debug(
								'Channel property state was updated',
								[
									'source'   => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
									'type'     => 'status-message-consumer',
									'device'   => [
										'id' => $device->getId()->toString(),
									],
									'channel'  => [
										'id' => $property->getChannel()->toString(),
									],
									'property' => [
										'id'    => $property->getId()->toString(),
										'state' => $propertyState->toArray(),
									],
								]
							);
						}
					} catch (DevicesModuleExceptions\NotImplementedException) {
						$this->logger->warning(
							'States manager is not configured. State could not be saved',
							[
								'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
								'type'   => 'status-message-consumer',
								'device' => [
									'id' => $device->getId()->toString(),
								],
							]
						);
					}
				}
			}
		}

		$this->logger->debug(
			'Consumed device status message',
			[
				'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type'   => 'status-message-consumer',
				'device' => [
					'id' => $device->getId()->toString(),
				],
				'data'   => $entity->toArray(),
			]
		);

		return true;
	}

}
