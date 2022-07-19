<?php declare(strict_types = 1);

/**
 * IClient.php
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

use FastyBird\Metadata\Entities as MetadataEntities;

/**
 * Shelly device client interface
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface IClient
{

	/**
	 * @param MetadataEntities\Actions\IActionDeviceControlEntity $action
	 *
	 * @return void
	 */
	public function writeDeviceControl(MetadataEntities\Actions\IActionDeviceControlEntity $action): void;

	/**
	 * @param MetadataEntities\Actions\IActionChannelControlEntity $action
	 *
	 * @return void
	 */
	public function writeChannelControl(MetadataEntities\Actions\IActionChannelControlEntity $action): void;

	/**
	 * @return bool
	 */
	public function isConnected(): bool;

	/**
	 * Connects to a broker
	 *
	 * @return void
	 */
	public function connect(): void;

	/**
	 * Disconnects from a broker
	 *
	 * @return void
	 */
	public function disconnect(): void;

}
