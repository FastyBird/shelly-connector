<?php declare(strict_types = 1);

/**
 * CoapClientFactory.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Clients
 * @since          0.37.0
 *
 * @date           05.07.22
 */

namespace FastyBird\ShellyConnector\Clients\Gen1;

/**
 * CoAP client factory
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface CoapClientFactory
{

	/**
	 * @return CoapClient
	 */
	public function create(): CoapClient;

}
