<?php declare(strict_types = 1);

/**
 * IConsumer.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Consumers
 * @since          0.37.0
 *
 * @date           16.07.22
 */

namespace FastyBird\ShellyConnector\Consumers;

use FastyBird\ShellyConnector\Entities;

/**
 * Shelly messages consumer interface
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface IConsumer
{

	/**
	 * @param Entities\Messages\IEntity $entity
	 *
	 * @return void
	 */
	public function consume(Entities\Messages\IEntity $entity): void;

}
