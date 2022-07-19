<?php declare(strict_types = 1);

/**
 * SensorRange.php
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

use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\ShellyConnector\Types;
use Nette;

/**
 * Parsed sensor range entity
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class SensorRange implements IEntity
{

	use Nette\SmartObject;

	/** @var MetadataTypes\DataTypeType */
	private MetadataTypes\DataTypeType $dataType;

	/** @var string[]|int[]|float[]|Array<int, Array<int, string|null>>|Array<int, int|null>|Array<int, float|null>|Array<int, MetadataTypes\SwitchPayloadType|string|Types\RelayPayloadType|null>|null */
	private ?array $format;

	/** @var int|float|string|null */
	private int|float|string|null $invalid;

	/**
	 * @param MetadataTypes\DataTypeType $dataType
	 * @param string[]|int[]|float[]|Array<int, Array<int, string|null>>|Array<int, int|null>|Array<int, float|null>|Array<int, MetadataTypes\SwitchPayloadType|string|Types\RelayPayloadType|null>|null $format
	 * @param int|float|string|null $invalid
	 */
	public function __construct(
		MetadataTypes\DataTypeType $dataType,
		?array $format,
		int|float|string|null $invalid
	) {
		$this->dataType = $dataType;
		$this->format = $format;
		$this->invalid = $invalid;
	}

	/**
	 * @return MetadataTypes\DataTypeType
	 */
	public function getDataType(): MetadataTypes\DataTypeType
	{
		return $this->dataType;
	}

	/**
	 * @return string[]|int[]|float[]|Array<int, Array<int, string|null>>|Array<int, int|null>|Array<int, float|null>|Array<int, MetadataTypes\SwitchPayloadType|string|Types\RelayPayloadType|null>|null
	 */
	public function getFormat(): ?array
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
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'data_type' => $this->dataType->getValue(),
			'format'    => $this->format,
			'invalid'   => $this->invalid,
		];
	}

}
