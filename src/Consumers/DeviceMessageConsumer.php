<?php declare(strict_types = 1);

/**
 * DeviceMessageConsumer.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Consumers
 * @since          0.37.0
 *
 * @date           20.07.22
 */

namespace FastyBird\ShellyConnector\Consumers;

use FastyBird\ShellyConnector\Entities;
use Nette;

/**
 * Device message consumer
 *
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DeviceMessageConsumer implements IConsumer
{

	use Nette\SmartObject;

	/**
	 * {@inheritDoc}
	 */
	public function consume(Entities\Messages\IEntity $entity): void
	{
		var_dump($entity->toArray());
	}

}
