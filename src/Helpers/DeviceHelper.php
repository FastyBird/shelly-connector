<?php declare(strict_types = 1);

/**
 * DeviceHelper.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Helpers
 * @since          0.37.0
 *
 * @date           04.08.22
 */

namespace FastyBird\ShellyConnector\Helpers;

use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\ShellyConnector\Types;
use Nette;
use Ramsey\Uuid;

/**
 * Useful device helpers
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Helpers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DeviceHelper
{

	use Nette\SmartObject;

	/** @var DevicesModuleModels\DataStorage\IDevicePropertiesRepository */
	private DevicesModuleModels\DataStorage\IDevicePropertiesRepository $propertiesRepository;

	/**
	 * @param DevicesModuleModels\DataStorage\IDevicePropertiesRepository $propertiesRepository
	 */
	public function __construct(
		DevicesModuleModels\DataStorage\IDevicePropertiesRepository $propertiesRepository
	) {
		$this->propertiesRepository = $propertiesRepository;
	}

	/**
	 * @param Uuid\UuidInterface $deviceId
	 * @param Types\DevicePropertyIdentifierType $type
	 *
	 * @return float|bool|int|string|null
	 */
	public function getConfiguration(
		Uuid\UuidInterface $deviceId,
		Types\DevicePropertyIdentifierType $type
	): float|bool|int|string|null {
		$configuration = $this->propertiesRepository->findByIdentifier($deviceId, strval($type->getValue()));

		if ($configuration instanceof MetadataEntities\Modules\DevicesModule\IDeviceStaticPropertyEntity) {
			if ($type->getValue() === Types\DevicePropertyIdentifierType::IDENTIFIER_IP_ADDRESS) {
				return is_string($configuration->getValue()) ? $configuration->getValue() : null;

			} elseif ($type->getValue() === Types\DevicePropertyIdentifierType::IDENTIFIER_USERNAME) {
				return is_string($configuration->getValue()) ? $configuration->getValue() : null;

			} elseif ($type->getValue() === Types\DevicePropertyIdentifierType::IDENTIFIER_PASSWORD) {
				return is_string($configuration->getValue()) ? $configuration->getValue() : null;

			} elseif ($type->getValue() === Types\DevicePropertyIdentifierType::IDENTIFIER_AUTH_ENABLED) {
				return is_bool($configuration->getValue()) ? $configuration->getValue() : null;
			}

			return $configuration->getValue();
		}

		return null;
	}

}
