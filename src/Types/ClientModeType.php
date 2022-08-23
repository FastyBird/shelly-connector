<?php declare(strict_types = 1);

/**
 * ClientModeType.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Types
 * @since          0.37.0
 *
 * @date           04.08.22
 */

namespace FastyBird\ShellyConnector\Types;

use Consistence;

/**
 * Connector client modes types
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ClientModeType extends Consistence\Enum\Enum
{

	/**
	 * Define versions
	 */
	public const TYPE_GEN_1_CLASSIC = 'gen1_classic';
	public const TYPE_GEN_1_MQTT = 'gen1_mqtt';

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
