<?php declare(strict_types = 1);

/**
 * ConnectorControlNameType.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Types
 * @since          0.37.0
 *
 * @date           30.07.22
 */

namespace FastyBird\ShellyConnector\Types;

use Consistence;
use FastyBird\Metadata\Types as MetadataTypes;

/**
 * Connector control name types
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ConnectorControlNameType extends Consistence\Enum\Enum
{

	/**
	 * Define device states
	 */
	public const NAME_DISCOVER = 'version';
	public const NAME_REBOOT = MetadataTypes\ControlNameType::NAME_REBOOT;

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return strval(self::getValue());
	}

}