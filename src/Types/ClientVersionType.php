<?php declare(strict_types = 1);

/**
 * ClientVersionType.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Types
 * @since          0.37.0
 *
 * @date           19.07.22
 */

namespace FastyBird\ShellyConnector\Types;

use Consistence;

/**
 * Connector client versions types
 *
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ClientVersionType extends Consistence\Enum\Enum
{

	/**
	 * Define versions
	 */
	public const GEN_1 = 'gen1';

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
