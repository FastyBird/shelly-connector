<?php declare(strict_types = 1);

/**
 * DeviceAttributeIdentifier.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Types
 * @since          0.37.0
 *
 * @date           22.07.22
 */

namespace FastyBird\ShellyConnector\Types;

use Consistence;
use FastyBird\Metadata\Types as MetadataTypes;

/**
 * Device attribute identifier types
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class DeviceAttributeIdentifier extends Consistence\Enum\Enum
{

	/**
	 * Define device states
	 */
	public const IDENTIFIER_FIRMWARE_VERSION = MetadataTypes\DeviceAttributeIdentifierType::IDENTIFIER_FIRMWARE_VERSION;
	public const IDENTIFIER_MAC_ADDRESS = MetadataTypes\DeviceAttributeIdentifierType::IDENTIFIER_HARDWARE_MAC_ADDRESS;
	public const IDENTIFIER_MODEL = MetadataTypes\DeviceAttributeIdentifierType::IDENTIFIER_HARDWARE_MODEL;

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return strval(self::getValue());
	}

}