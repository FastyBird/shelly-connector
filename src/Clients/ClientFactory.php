<?php declare(strict_types = 1);

/**
 * ClientFactory.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Clients
 * @since          1.0.0
 *
 * @date           14.07.22
 */

namespace FastyBird\Connector\Shelly\Clients;

use FastyBird\Library\Metadata\Documents as MetadataDocuments;

/**
 * Base device client factory
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface ClientFactory
{

	public const MODE_CONSTANT_NAME = 'MODE';

	public function create(MetadataDocuments\DevicesModule\Connector $connector): Client;

}
