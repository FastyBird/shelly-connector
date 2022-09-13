<?php declare(strict_types = 1);

/**
 * Gen1Factory.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
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
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface Gen1Factory extends ClientFactory
{

	public const VERSION = Types\ClientVersion::TYPE_GEN_1;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 *
	 * @return Gen1
	 */
	public function create(MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector): Gen1;

}