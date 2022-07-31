<?php declare(strict_types = 1);

/**
 * IEntity.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Entities
 * @since          0.37.0
 *
 * @date           16.07.22
 */

namespace FastyBird\ShellyConnector\Entities\Messages;

use FastyBird\ShellyConnector\Types;

/**
 * Shelly base message data entity interface
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface IEntity
{

	/**
	 * @return Types\MessageSourceType
	 */
	public function getSource(): Types\MessageSourceType;

	/**
	 * @return Array<string, mixed>
	 */
	public function toArray(): array;

}
