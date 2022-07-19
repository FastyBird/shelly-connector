<?php declare(strict_types = 1);

/**
 * Gen1ClientFactory.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Clients
 * @since          0.37.0
 *
 * @date           17.07.22
 */

namespace FastyBird\ShellyConnector\Clients;

use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\ShellyConnector\Types;

/**
 * Generation 1 devices client factory
 *
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface Gen1ClientFactory extends ClientFactory
{

	public const VERSION = Types\ClientVersionType::GEN_1;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 *
	 * @return Gen1Client
	 */
	public function create(MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector): Gen1Client;

}
