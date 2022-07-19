<?php declare(strict_types = 1);

/**
 * LightSwitchPayloadType.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Types
 * @since          0.37.0
 *
 * @date           17.07.22
 */

namespace FastyBird\ShellyConnector\Types;

use Consistence;

/**
 * Light switch payload value types
 *
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class LightSwitchPayloadType extends Consistence\Enum\Enum
{

	/**
	 * Define device states
	 */
	public const PAYLOAD_ON = 'on';
	public const PAYLOAD_OFF = 'off';
	public const PAYLOAD_TOGGLE = 'toggle';

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
