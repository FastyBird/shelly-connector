<?php declare(strict_types = 1);

/**
 * ChannelStatus.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Properties
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
 * @subpackage     Properties
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ChannelStatus implements Entity
{

	use Nette\SmartObject;

	/** @var Types\MessageSource */
	private Types\MessageSource $source;

	/** @var int */
	private int $channel;

	/** @var SensorStatus[] */
	private array $sensors;

	/**
	 * @param Types\MessageSource $source
	 * @param int $channel
	 * @param SensorStatus[] $sensors
	 */
	public function __construct(
		Types\MessageSource $source,
		int                 $channel,
		array               $sensors = []
	) {
		$this->source = $source;
		$this->channel = $channel;
		$this->sensors = array_unique($sensors, SORT_REGULAR);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSource(): Types\MessageSource
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
			'sensors' => array_map(function (SensorStatus $sensor): array {
				return $sensor->toArray();
			}, $this->getSensors()),
		];
	}

}
