<?php declare(strict_types = 1);

/**
 * FbMqttV1Client.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Clients
 * @since          0.25.0
 *
 * @date           05.07.22
 */

namespace FastyBird\ShellyConnector\Clients;

use FastyBird\Metadata\Entities as MetadataEntities;

/**
 * CoAP client factory
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface CoapClientFactory extends ClientFactory
{

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 *
	 * @return CoapClient
	 */
	public function create(MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector): CoapClient;

}
