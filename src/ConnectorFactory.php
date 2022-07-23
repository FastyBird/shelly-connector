<?php declare(strict_types = 1);

/**
 * ConnectorFactory.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     common
 * @since          0.37.0
 *
 * @date           14.07.22
 */

namespace FastyBird\ShellyConnector;

use FastyBird\DevicesModule\Connectors as DevicesModuleConnectors;
use FastyBird\DevicesModule\Exceptions as DevicesModuleExceptions;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\Metadata\Entities as MetadataEntities;
use Nette;
use ReflectionClass;

/**
 * Connector service container factory
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     common
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ConnectorFactory implements DevicesModuleConnectors\IConnectorFactory
{

	use Nette\SmartObject;

	/** @var Clients\ClientFactory[] */
	private array $clientsFactories;

	/** @var DevicesModuleModels\DataStorage\IConnectorPropertiesRepository */
	private DevicesModuleModels\DataStorage\IConnectorPropertiesRepository $connectorPropertiesRepository;

	/** @var Connector\ConnectorFactory */
	private Connector\ConnectorFactory $connectorFactory;

	/**
	 * @param Clients\ClientFactory[] $clientsFactories
	 * @param Connector\ConnectorFactory $connectorFactory
	 * @param DevicesModuleModels\DataStorage\IConnectorPropertiesRepository $connectorPropertiesRepository;
	 */
	public function __construct(
		array $clientsFactories,
		Connector\ConnectorFactory $connectorFactory,
		DevicesModuleModels\DataStorage\IConnectorPropertiesRepository $connectorPropertiesRepository
	) {
		$this->clientsFactories = $clientsFactories;
		$this->connectorFactory = $connectorFactory;

		$this->connectorPropertiesRepository = $connectorPropertiesRepository;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getType(): string
	{
		return Entities\ShellyConnectorEntity::CONNECTOR_TYPE;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DevicesModuleExceptions\TerminateException
	 */
	public function create(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	): DevicesModuleConnectors\IConnector {
		$versionProperty = $this->connectorPropertiesRepository->findByIdentifier(
			$connector->getId(),
			Types\ConnectorPropertyIdentifierType::IDENTIFIER_CLIENT_VERSION
		);

		if (
			!$versionProperty instanceof MetadataEntities\Modules\DevicesModule\IConnectorStaticPropertyEntity
			|| !Types\ClientVersionType::isValidValue($versionProperty->getValue())
		) {
			throw new DevicesModuleExceptions\TerminateException('Connector client version is not configured');
		}

		foreach ($this->clientsFactories as $clientFactory) {
			$rc = new ReflectionClass($clientFactory);

			$constants = $rc->getConstants();

			if (
				array_key_exists(Clients\ClientFactory::VERSION_CONSTANT_NAME, $constants)
				&& $constants[Clients\ClientFactory::VERSION_CONSTANT_NAME] === $versionProperty->getValue()
				&& method_exists($clientFactory, 'create')
			) {
				return $this->connectorFactory->create($connector, $clientFactory->create($connector));
			}
		}

		throw new DevicesModuleExceptions\TerminateException('Connector client is not configured');
	}

}
