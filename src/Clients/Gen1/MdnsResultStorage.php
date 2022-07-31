<?php declare(strict_types = 1);

/**
 * MdnsResultStorage.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Clients
 * @since          0.37.0
 *
 * @date           14.07.22
 */

namespace FastyBird\ShellyConnector\Clients\Gen1;

use Nette;
use SplObjectStorage;

/**
 * mDNS search results storage
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 *
 * @extends SplObjectStorage<MdnsResult, null>
 */
class MdnsResultStorage extends SplObjectStorage
{

	use Nette\SmartObject;

	/**
	 * {@inheritDoc}
	 *
	 * @phpstan-param MdnsResult $object
	 *
	 * @return string
	 */
	public function getHash(object $object): string
	{
		return serialize($object);
	}

}
