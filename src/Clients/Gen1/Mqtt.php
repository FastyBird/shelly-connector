<?php declare(strict_types = 1);

/**
 * Mqtt.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Clients
 * @since          0.37.0
 *
 * @date           20.07.22
 */

namespace FastyBird\ShellyConnector\Clients\Gen1;

use FastyBird\DevicesModule\Exceptions as DevicesModuleExceptions;
use FastyBird\Metadata\Entities as MetadataEntities;
use Nette;

/**
 * MQTT client
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Mqtt
{

	use Nette\SmartObject;

	/** @var MetadataEntities\Modules\DevicesModule\IConnectorEntity */
	private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 */
	public function __construct(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	) {
		$this->connector = $connector;
	}

	/**
	 * @return void
	 *
	 * @throws DevicesModuleExceptions\TerminateException
	 */
	public function connect(): void
	{
		throw new DevicesModuleExceptions\TerminateException(
			sprintf('MQTT client is not implemented for connector %s', $this->connector->getIdentifier())
		);
	}

	/**
	 * @return void
	 *
	 * @throws DevicesModuleExceptions\TerminateException
	 */
	public function disconnect(): void
	{
		throw new DevicesModuleExceptions\TerminateException(
			sprintf('MQTT client is not implemented for connector %s', $this->connector->getIdentifier())
		);
	}

}
