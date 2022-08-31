<?php declare(strict_types = 1);

/**
 * SensorDescription.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Properties
 * @since          0.37.0
 *
 * @date           17.07.22
 */

namespace FastyBird\ShellyConnector\Entities\Messages;

use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\ShellyConnector\Types;
use Nette;

/**
 * Block sensor description entity
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Properties
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class SensorDescription implements Entity
{

	use Nette\SmartObject;

	/** @var Types\MessageSource */
	private Types\MessageSource $source;

	/** @var int */
	private int $identifier;

	/** @var Types\SensorType */
	private Types\SensorType $type;

	/** @var string */
	private string $description;

	/** @var Types\SensorUnit|null */
	private ?Types\SensorUnit $unit;

	/** @var MetadataTypes\DataTypeType */
	private MetadataTypes\DataTypeType $dataType;

	/** @var string[]|int[]|float[]|Array<int, Array<int, string|null>>|Array<int, int|null>|Array<int, float|null>|Array<int, MetadataTypes\SwitchPayloadType|string|Types\RelayPayload|null>|null */
	private ?array $format;

	/** @var float|int|string|null */
	private float|int|string|null $invalid;

	/** @var bool */
	private bool $queryable;

	/** @var bool */
	private bool $settable;

	/**
	 * @param Types\MessageSource $source
	 * @param int $identifier
	 * @param Types\SensorType $type
	 * @param string $description
	 * @param MetadataTypes\DataTypeType $dataType
	 * @param Types\SensorUnit|null $unit
	 * @param string[]|int[]|float[]|Array<int, Array<int, string|null>>|Array<int, int|null>|Array<int, float|null>|Array<int, MetadataTypes\SwitchPayloadType|string|Types\RelayPayload|null>|null $format
	 * @param float|int|string|null $invalid
	 * @param bool $queryable
	 * @param bool $settable
	 */
	public function __construct(
		Types\MessageSource $source,
		int $identifier,
		Types\SensorType $type,
		string $description,
		MetadataTypes\DataTypeType $dataType,
		?Types\SensorUnit $unit = null,
		?array $format = null,
		float|int|string|null $invalid = null,
		bool $queryable = false,
		bool $settable = false
	) {
		$this->source = $source;
		$this->identifier = $identifier;
		$this->type = $type;
		$this->description = $description;
		$this->dataType = $dataType;
		$this->unit = $unit;
		$this->format = $format;
		$this->invalid = $invalid;
		$this->queryable = $queryable;
		$this->settable = $settable;
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
	public function getIdentifier(): int
	{
		return $this->identifier;
	}

	/**
	 * @return Types\SensorType
	 */
	public function getType(): Types\SensorType
	{
		return $this->type;
	}

	/**
	 * @return string
	 */
	public function getDescription(): string
	{
		return $this->description;
	}

	/**
	 * @return MetadataTypes\DataTypeType
	 */
	public function getDataType(): MetadataTypes\DataTypeType
	{
		return $this->dataType;
	}

	/**
	 * @return Types\SensorUnit|null
	 */
	public function getUnit(): ?Types\SensorUnit
	{
		return $this->unit;
	}

	/**
	 * @return string[]|int[]|float[]|Array<int, Array<int, string|null>>|Array<int, int|null>|Array<int, float|null>|Array<int, MetadataTypes\SwitchPayloadType|string|Types\RelayPayload|null>|null
	 */
	public function getFormat(): mixed
	{
		return $this->format;
	}

	/**
	 * @return float|int|string|null
	 */
	public function getInvalid(): float|int|string|null
	{
		return $this->invalid;
	}

	/**
	 * @return bool
	 */
	public function isQueryable(): bool
	{
		return $this->queryable;
	}

	/**
	 * @return bool
	 */
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
			'source'      => $this->getSource()->getValue(),
			'identifier'  => $this->getIdentifier(),
			'type'        => $this->getType()->getValue(),
			'description' => $this->getDescription(),
			'data_type'   => $this->getDataType()->getValue(),
			'unit'        => $this->getUnit()?->getValue(),
			'format'      => $this->getFormat(),
			'invalid'     => $this->getInvalid(),
			'queryable'   => $this->isQueryable(),
			'settable'    => $this->isQueryable(),
		];
	}

}
