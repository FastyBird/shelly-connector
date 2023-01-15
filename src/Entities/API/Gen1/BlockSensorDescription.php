<?php declare(strict_types = 1);

/**
 * BlockSensorDescription.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Entities
 * @since          1.0.0
 *
 * @date           17.07.22
 */

namespace FastyBird\Connector\Shelly\Entities\API\Gen1;

use FastyBird\Connector\Shelly\Entities;
use FastyBird\Connector\Shelly\Types;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use Nette;

/**
 * Block sensor description entity
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class BlockSensorDescription implements Entities\API\Entity
{

	use Nette\SmartObject;

	/**
	 * @param array<string>|array<int>|array<float>|array<int, array<int, (string|null)>>|array<int, (int|null)>|array<int, (float|null)>|array<int, (MetadataTypes\SwitchPayload|string|Types\RelayPayload|null)>|null $format
	 */
	public function __construct(
		private readonly int $identifier,
		private readonly Types\SensorType $type,
		private readonly string $description,
		private readonly MetadataTypes\DataType $dataType,
		private readonly string|null $unit = null,
		private readonly array|null $format = null,
		private readonly float|int|string|null $invalid = null,
		private readonly bool $queryable = false,
		private readonly bool $settable = false,
	)
	{
	}

	public function getIdentifier(): int
	{
		return $this->identifier;
	}

	public function getType(): Types\SensorType
	{
		return $this->type;
	}

	public function getDescription(): string
	{
		return $this->description;
	}

	public function getDataType(): MetadataTypes\DataType
	{
		return $this->dataType;
	}

	public function getUnit(): string|null
	{
		return $this->unit;
	}

	/**
	 * @return array<string>|array<int>|array<float>|array<int, array<int, (string|null)>>|array<int, (int|null)>|array<int, (float|null)>|array<int, (MetadataTypes\SwitchPayload|string|Types\RelayPayload|null)>|null
	 */
	public function getFormat(): mixed
	{
		return $this->format;
	}

	public function getInvalid(): float|int|string|null
	{
		return $this->invalid;
	}

	public function isQueryable(): bool
	{
		return $this->queryable;
	}

	public function isSettable(): bool
	{
		return $this->settable;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'identifier' => $this->getIdentifier(),
			'type' => $this->getType()->getValue(),
			'description' => $this->getDescription(),
			'data_type' => $this->getDataType()->getValue(),
			'unit' => $this->getUnit(),
			'format' => $this->getFormat(),
			'invalid' => $this->getInvalid(),
			'queryable' => $this->isQueryable(),
			'settable' => $this->isQueryable(),
		];
	}

}
