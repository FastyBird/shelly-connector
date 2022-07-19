<?php declare(strict_types = 1);

/**
 * BlockStatus.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Entities
 * @since          0.37.0
 *
 * @date           18.07.22
 */

namespace FastyBird\ShellyConnector\Entities\Messages;

use Nette;

/**
 * Block status entity
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class BlockStatus implements IEntity
{

	use Nette\SmartObject;

	/** @var int */
	private int $identifier;

	/** @var SensorStatus[] */
	private array $sensors;

	/**
	 * @param int $identifier
	 * @param SensorStatus[] $sensors
	 */
	public function __construct(
		int $identifier,
		array $sensors = []
	) {
		$this->identifier = $identifier;
		$this->sensors = array_unique($sensors);
	}

	/**
	 * @return int
	 */
	public function getIdentifier(): int
	{
		return $this->identifier;
	}

	/**
	 * @return SensorStatus[]
	 */
	public function getSensors(): array
	{
		return $this->sensors;
	}

	/**
	 * @param SensorStatus $sensor
	 *
	 * @return void
	 */
	public function addSensor(SensorStatus $sensor): void
	{
		$this->sensors[] = $sensor;

		$this->sensors = array_unique($this->sensors);
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'identifier' => $this->getIdentifier(),
			'sensors'    => array_map(function (SensorStatus $sensor): array {
				return $sensor->toArray();
			}, $this->getSensors()),
		];
	}

}