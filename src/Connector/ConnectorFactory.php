<?php declare(strict_types = 1);

/**
 * ConnectorFactory.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Connector
 * @since          0.37.0
 *
 * @date           05.07.22
 */

namespace FastyBird\ShellyConnector\Connector;

use FastyBird\ShellyConnector\Clients;

/**
 * Connector service factory
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Connector
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface ConnectorFactory
{

	/**
	 * @param Clients\IClient $client
	 *
	 * @return Connector
	 */
	public function create(
		Clients\IClient $client
	): Connector;

}
