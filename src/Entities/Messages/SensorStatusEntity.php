<?php declare(strict_types = 1);

/**
 * SensorStatus.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Entities
 * @since          0.37.0
 *
 * @date           18.07.22
 */

namespace FastyBird\ShellyConnector\Entities\Messages;

use Nette;

/**
 * Sensor status entity
 *
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class SensorStatusEntity implements IEntity
{

	use Nette\SmartObject;

	/** @var int */
	private int $identifier;

	/** @var string|int|float */
	private string|int|float $value;

	/**
	 * @param int $identifier
	 * @param string|int|float $value
	 */
	public function __construct(
		int $identifier,
		string|int|float $value
	) {
		$this->identifier = $identifier;
		$this->value = $value;
	}

	/**
	 * @return int
	 */
	public function getIdentifier(): int
	{
		return $this->identifier;
	}

	/**
	 * @return float|int|string
	 */
	public function getValue(): float|int|string
	{
		return $this->value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'identifier' => $this->getIdentifier(),
			'value'      => $this->getValue(),
		];
	}

}
