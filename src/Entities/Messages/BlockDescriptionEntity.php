<?php declare(strict_types = 1);

/**
 * BlockDescriptionEntity.php
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

use FastyBird\ShellyConnector\Types;
use Nette;

/**
 * Block description entity
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class BlockDescriptionEntity implements IEntity
{

	use Nette\SmartObject;

	/** @var Types\MessageSourceType */
	private Types\MessageSourceType $source;

	/** @var int */
	private int $identifier;

	/** @var string */
	private string $description;

	/** @var SensorDescriptionEntity[] */
	private array $sensors;

	/**
	 * @param Types\MessageSourceType $source
	 * @param int $identifier
	 * @param string $description
	 * @param SensorDescriptionEntity[] $sensors
	 */
	public function __construct(
		Types\MessageSourceType $source,
		int $identifier,
		string $description,
		array $sensors = []
	) {
		$this->source = $source;
		$this->identifier = $identifier;
		$this->description = $description;
		$this->sensors = array_unique($sensors, SORT_REGULAR);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSource(): Types\MessageSourceType
	{
		return $this->source;
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
	 * @return SensorDescriptionEntity[]
	 */
	public function getSensors(): array
	{
		return $this->sensors;
	}

	/**
	 * @param SensorDescriptionEntity $sensor
	 *
	 * @return void
	 */
	public function addSensor(SensorDescriptionEntity $sensor): void
	{
		$this->sensors[] = $sensor;

		$this->sensors = array_unique($this->sensors, SORT_REGULAR);
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'source'      => $this->getSource()->getValue(),
			'identifier'  => $this->getIdentifier(),
			'description' => $this->getDescription(),
			'sensors'     => array_map(function (SensorDescriptionEntity $sensor): array {
				return $sensor->toArray();
			}, $this->getSensors()),
		];
	}

}