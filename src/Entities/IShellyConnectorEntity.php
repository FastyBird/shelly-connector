<?php declare(strict_types = 1);

/**
 * IShellyConnectorEntity.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Entities
 * @since          0.1.0
 *
 * @date           22.01.22
 */

namespace FastyBird\ShellyConnector\Entities;

use FastyBird\DevicesModule\Entities as DevicesModuleEntities;

/**
 * Shelly connector entity interface
 *
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface IShellyConnectorEntity extends DevicesModuleEntities\Connectors\IConnector
{

}
