<?php declare(strict_types = 1);

/**
 * DeviceBlockDescription.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     API
 * @since          1.0.0
 *
 * @date           17.07.22
 */

namespace FastyBird\Connector\Shelly\API\Messages\Response\Gen1;

use FastyBird\Connector\Shelly\API;
use Orisai\ObjectMapper;
use function array_map;
use function array_unique;
use const SORT_REGULAR;

/**
 * Block description message
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     API
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final readonly class DeviceBlockDescription implements API\Messages\Message
{

	/**
	 * @param array<BlockSensorDescription> $sensors
	 */
	public function __construct(
		#[ObjectMapper\Rules\IntValue()]
		private int $identifier,
		#[ObjectMapper\Rules\StringValue(notEmpty: true)]
		private string $description,
		#[ObjectMapper\Rules\ArrayOf(
			new ObjectMapper\Rules\MappedObjectValue(BlockSensorDescription::class),
		)]
		private array $sensors = [],
	)
	{
	}

	public function getIdentifier(): int
	{
		return $this->identifier;
	}

	public function getDescription(): string
	{
		return $this->description;
	}

	/**
	 * @return array<BlockSensorDescription>
	 */
	public function getSensors(): array
	{
		return array_unique($this->sensors, SORT_REGULAR);
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'identifier' => $this->getIdentifier(),
			'description' => $this->getDescription(),
			'sensors' => array_map(
				static fn (BlockSensorDescription $sensor): array => $sensor->toArray(),
				$this->getSensors(),
			),
		];
	}

}
