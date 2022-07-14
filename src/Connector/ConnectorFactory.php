<?php declare(strict_types = 1);

/**
 * Constants.php
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
use FastyBird\DevicesModule\Exceptions as DevicesModuleExceptions;
use FastyBird\ShellyConnector\Clients;
use FastyBird\ShellyConnector\Entities;
use FastyBird\Metadata\Entities as MetadataEntities;

/**
 * Connector service container factory
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Connector
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ConnectorFactory implements DevicesModuleConnectors\IConnectorFactory
{

	/** @var Clients\ClientFactory[] */
	private array $clientsFactories;

	/**
	 * @param Clients\ClientFactory[] $clientsFactories
	 */
	public function __construct(
		array $clientsFactories,
	) {
		$this->clientsFactories = $clientsFactories;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getType(): string
	{
		return Entities\ShellyConnector::CONNECTOR_TYPE;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DevicesModuleExceptions\TerminateException
	 */
	public function create(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	): DevicesModuleConnectors\IConnector {
		$clients = [];

		foreach ($this->clientsFactories as $clientFactory) {
			if (method_exists($clientFactory, 'create')) {
				$clients[] = $clientFactory->create($connector);
			}
		}

		return new Connector($clients);
	}

}
