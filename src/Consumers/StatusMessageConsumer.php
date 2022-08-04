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

use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\DevicesModule\Utilities as DevicesModuleUtilities;
use FastyBird\Metadata;
use FastyBird\ShellyConnector\Entities;
use FastyBird\ShellyConnector\Helpers;
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

	/** @var DevicesModuleModels\States\DeviceConnectionStateManager */
	private DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager;

	/** @var Mappers\ISensorMapper */
	private Mappers\ISensorMapper $sensorMapper;

	/** @var Helpers\PropertyHelper */
	private Helpers\PropertyHelper $propertyStateHelper;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository
	 * @param DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager
	 * @param Mappers\ISensorMapper $sensorMapper
	 * @param Helpers\PropertyHelper $propertyStateHelper
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		DevicesModuleModels\DataStorage\IDevicesRepository $devicesDataStorageRepository,
		DevicesModuleModels\States\DeviceConnectionStateManager $deviceConnectionStateManager,
		Mappers\ISensorMapper $sensorMapper,
		Helpers\PropertyHelper $propertyStateHelper,
		?Log\LoggerInterface $logger
	) {
		$this->devicesDataStorageRepository = $devicesDataStorageRepository;

		$this->deviceConnectionStateManager = $deviceConnectionStateManager;

		$this->sensorMapper = $sensorMapper;
		$this->propertyStateHelper = $propertyStateHelper;

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

		$deviceItem = $this->devicesDataStorageRepository->findByIdentifier(
			$entity->getConnector(),
			$entity->getIdentifier()
		);

		if ($deviceItem === null) {
			return true;
		}

		// Check device state...
		if (
			!$this->deviceConnectionStateManager->getState($deviceItem)->equalsValue(Metadata\Types\ConnectionStateType::STATE_READY)
		) {
			// ... and if it is not ready, set it to ready
			$this->deviceConnectionStateManager->setState(
				$deviceItem,
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
					$actualValue = DevicesModuleUtilities\ValueHelper::flattenValue(
						DevicesModuleUtilities\ValueHelper::normalizeValue(
							$property->getDataType(),
							$sensor->getValue(),
							$property->getFormat(),
							$property->getInvalid()
						)
					);

					$propertyState = $this->propertyStateHelper->setValue($property, Utils\ArrayHash::from([
						'actualValue' => $actualValue,
						'valid'       => true,
					]));

					if ($propertyState !== null) {
						$this->logger->debug(
							'Channel property state was updated',
							[
								'source'   => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
								'type'     => 'status-message-consumer',
								'device'   => [
									'id' => $deviceItem->getId()->toString(),
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
						$this->logger->warning(
							'Channel property state could not be updated',
							[
								'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
								'type'   => 'status-message-consumer',
								'device' => [
									'id' => $deviceItem->getId()->toString(),
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
					'id' => $deviceItem->getId()->toString(),
				],
				'data'   => $entity->toArray(),
			]
		);

		return true;
	}

}
