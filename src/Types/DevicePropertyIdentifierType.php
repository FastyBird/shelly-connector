<?php declare(strict_types = 1);

/**
 * DevicePropertyIdentifierType.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Types
 * @since          0.37.0
 *
 * @date           19.07.22
 */

namespace FastyBird\ShellyConnector\Types;

use Consistence;
use FastyBird\Metadata\Types as MetadataTypes;

/**
 * Device property identifier types
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class DevicePropertyIdentifierType extends Consistence\Enum\Enum
{

	/**
	 * Define device states
	 */
	public const IDENTIFIER_IP_ADDRESS = MetadataTypes\DevicePropertyNameType::NAME_IP_ADDRESS;
	public const IDENTIFIER_STATE = MetadataTypes\DevicePropertyNameType::NAME_STATE;
	public const IDENTIFIER_USERNAME = 'username';
	public const IDENTIFIER_PASSWORD = 'password';
	public const IDENTIFIER_AUTH_ENABLED = 'auth-enabled';

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
