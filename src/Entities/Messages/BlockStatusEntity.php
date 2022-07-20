<?php declare(strict_types = 1);

/**
 * BlockStatusEntity.php
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

use FastyBird\ShellyConnector\Types;
use Nette;

/**
 * Block status entity
 *
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class BlockStatusEntity implements IEntity
{

	use Nette\SmartObject;

	/** @var Types\MessageSourceType */
	private Types\MessageSourceType $source;

	/** @var int */
	private int $identifier;

	/** @var SensorStatusEntity[] */
	private array $sensors;

	/**
	 * @param Types\MessageSourceType $source
	 * @param int $identifier
	 * @param SensorStatusEntity[] $sensors
	 */
	public function __construct(
		Types\MessageSourceType $source,
		int $identifier,
		array $sensors = []
	) {
		$this->source = $source;
		$this->identifier = $identifier;
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
	 * @return SensorStatusEntity[]
	 */
	public function getSensors(): array
	{
		return $this->sensors;
	}

	/**
	 * @param SensorStatusEntity $sensor
	 *
	 * @return void
	 */
	public function addSensor(SensorStatusEntity $sensor): void
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
			'source'     => $this->getSource()->getValue(),
			'identifier' => $this->getIdentifier(),
			'sensors'    => array_map(function (SensorStatusEntity $sensor): array {
				return $sensor->toArray();
			}, $this->getSensors()),
		];
	}

}
