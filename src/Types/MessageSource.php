<?php declare(strict_types = 1);

/**
 * MessageSource.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Types
 * @since          0.37.0
 *
 * @date           20.07.22
 */

namespace FastyBird\ShellyConnector\Types;

use Consistence;

/**
 * Message client source types
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class MessageSource extends Consistence\Enum\Enum
{

	/**
	 * Define versions
	 */
	public const SOURCE_GEN_1_COAP = 'gen_1_coap';
	public const SOURCE_GEN_1_HTTP = 'gen_1_http';
	public const SOURCE_GEN_1_MDNS = 'gen_1_mdns';
	public const SOURCE_GEN_1_MQTT = 'gen_1_mqtt';

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return strval(self::getValue());
	}

}