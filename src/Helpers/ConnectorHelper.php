<?php declare(strict_types = 1);

/**
 * ConnectorHelper.php
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
 * Useful connector helpers
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Helpers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ConnectorHelper
{

	use Nette\SmartObject;

	/** @var DevicesModuleModels\DataStorage\IConnectorPropertiesRepository */
	private DevicesModuleModels\DataStorage\IConnectorPropertiesRepository $propertiesRepository;

	/**
	 * @param DevicesModuleModels\DataStorage\IConnectorPropertiesRepository $propertiesRepository
	 */
	public function __construct(
		DevicesModuleModels\DataStorage\IConnectorPropertiesRepository $propertiesRepository
	) {
		$this->propertiesRepository = $propertiesRepository;
	}

	/**
	 * @param Uuid\UuidInterface $connectorId
	 * @param Types\ConnectorPropertyIdentifierType $type
	 *
	 * @return float|bool|int|string|null
	 */
	public function getConfiguration(
		Uuid\UuidInterface $connectorId,
		Types\ConnectorPropertyIdentifierType $type
	): float|bool|int|string|null {
		$configuration = $this->propertiesRepository->findByIdentifier($connectorId, strval($type->getValue()));

		if ($configuration instanceof MetadataEntities\Modules\DevicesModule\IConnectorStaticPropertyEntity) {
			if ($type->getValue() === Types\ConnectorPropertyIdentifierType::IDENTIFIER_CLIENT_VERSION) {
				return Types\ClientVersionType::isValidValue($type->getValue()) ? $configuration->getValue() : null;
			}

			if ($type->getValue() === Types\ConnectorPropertyIdentifierType::IDENTIFIER_CLIENT_MODE) {
				return Types\ClientModeType::isValidValue($type->getValue()) ? $configuration->getValue() : null;
			}

			return $configuration->getValue();
		}

		return null;
	}

}
