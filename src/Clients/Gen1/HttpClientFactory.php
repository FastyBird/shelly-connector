<?php declare(strict_types = 1);

/**
 * HttpClientFactory.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Clients
 * @since          0.37.0
 *
 * @date           18.07.22
 */

namespace FastyBird\ShellyConnector\Clients\Gen1;

use FastyBird\Metadata\Entities as MetadataEntities;

/**
 * HTTP api client factory
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface HttpClientFactory
{

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 *
	 * @return HttpClient
	 */
	public function create(MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector): HttpClient;

}
