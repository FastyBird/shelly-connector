<?php declare(strict_types = 1);

/**
 * SensorTypeType.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Types
 * @since          0.37.0
 *
 * @date           17.07.22
 */

namespace FastyBird\ShellyConnector\Types;

use Consistence;

/**
 * Sensor type types
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class SensorTypeType extends Consistence\Enum\Enum
{

	/**
	 * Define device states
	 */
	public const TYPE_ALARM = 'A';
	public const TYPE_BATTERY_LEVEL = 'B';
	public const TYPE_CONCENTRATION = 'C';
	public const TYPE_ENERGY = 'E';
	public const TYPE_EVENT = 'EV';
	public const TYPE_EVENT_COUNTER = 'EVC';
	public const TYPE_HUMIDITY = 'H';
	public const TYPE_CURRENT = 'I';
	public const TYPE_LUMINOSITY = 'L';
	public const TYPE_POWER = 'P';
	public const TYPE_STATUS = 'S';
	public const TYPE_TEMPERATURE = 'T';
	public const TYPE_VOLTAGE = 'V';

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
