<?php declare(strict_types = 1);

/**
 * Client.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Clients
 * @since          0.37.0
 *
 * @date           23.02.20
 */

namespace FastyBird\ShellyConnector\Clients;

/**
 * Shelly device client interface
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface Client
{

	/**
	 * Discover new devices
	 *
	 * @return void
	 */
	public function discover(): void;

	/**
	 * Create servers/clients
	 *
	 * @return void
	 */
	public function connect(): void;

	/**
	 * Destroy servers/clients
	 *
	 * @return void
	 */
	public function disconnect(): void;

}
