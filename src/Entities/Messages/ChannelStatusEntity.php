<?php declare(strict_types = 1);

/**
 * ChannelStatusEntity.php
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

use FastyBird\ShellyConnector\Types;
use Nette;

/**
 * Block status entity
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ChannelStatusEntity implements IEntity
{

	use Nette\SmartObject;

	/** @var Types\MessageSourceType */
	private Types\MessageSourceType $source;

	/** @var int */
	private int $channel;

	/** @var SensorStatusEntity[] */
	private array $sensors;

	/**
	 * @param Types\MessageSourceType $source
	 * @param int $channel
	 * @param SensorStatusEntity[] $sensors
	 */
	public function __construct(
		Types\MessageSourceType $source,
		int $channel,
		array $sensors = []
	) {
		$this->source = $source;
		$this->channel = $channel;
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
	public function getChannel(): int
	{
		return $this->channel;
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
			'source'  => $this->getSource()->getValue(),
			'channel' => $this->getChannel(),
			'sensors' => array_map(function (SensorStatusEntity $sensor): array {
				return $sensor->toArray();
			}, $this->getSensors()),
		];
	}

}
