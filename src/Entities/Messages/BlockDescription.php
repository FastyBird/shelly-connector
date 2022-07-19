<?php declare(strict_types = 1);

/**
 * BlockDescription.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Entities
 * @since          0.37.0
 *
 * @date           17.07.22
 */

namespace FastyBird\ShellyConnector\Entities\Messages;

use Nette;

/**
 * Block description entity
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class BlockDescription implements IEntity
{

	use Nette\SmartObject;

	/** @var int */
	private int $identifier;

	/** @var string */
	private string $description;

	/** @var SensorDescription[] */
	private array $sensors;

	/**
	 * @param int $identifier
	 * @param string $description
	 * @param SensorDescription[] $sensors
	 */
	public function __construct(
		int $identifier,
		string $description,
		array $sensors = []
	) {
		$this->identifier = $identifier;
		$this->description = $description;
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
	 * @return string
	 */
	public function getDescription(): string
	{
		return $this->description;
	}

	/**
	 * @return SensorDescription[]
	 */
	public function getSensors(): array
	{
		return $this->sensors;
	}

	/**
	 * @param SensorDescription $sensor
	 *
	 * @return void
	 */
	public function addSensor(SensorDescription $sensor): void
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
			'identifier'  => $this->getIdentifier(),
			'description' => $this->getDescription(),
			'sensors'     => array_map(function (SensorDescription $sensor): array {
				return $sensor->toArray();
			}, $this->getSensors()),
		];
	}

}
