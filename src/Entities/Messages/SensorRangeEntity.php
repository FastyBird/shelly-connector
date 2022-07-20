<?php declare(strict_types = 1);

/**
 * SensorRangeEntity.php
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

use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\ShellyConnector\Types;
use Nette;

/**
 * Parsed sensor range entity
 *
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class SensorRangeEntity implements IEntity
{

	use Nette\SmartObject;

	/** @var Types\MessageSourceType */
	private Types\MessageSourceType $source;

	/** @var MetadataTypes\DataTypeType */
	private MetadataTypes\DataTypeType $dataType;

	/** @var string[]|int[]|float[]|Array<int, Array<int, string|null>>|Array<int, int|null>|Array<int, float|null>|Array<int, MetadataTypes\SwitchPayloadType|string|Types\RelayPayloadType|null>|null */
	private ?array $format;

	/** @var int|float|string|null */
	private int|float|string|null $invalid;

	/**
	 * @param Types\MessageSourceType $source
	 * @param MetadataTypes\DataTypeType $dataType
	 * @param string[]|int[]|float[]|Array<int, Array<int, string|null>>|Array<int, int|null>|Array<int, float|null>|Array<int, MetadataTypes\SwitchPayloadType|string|Types\RelayPayloadType|null>|null $format
	 * @param int|float|string|null $invalid
	 */
	public function __construct(
		Types\MessageSourceType $source,
		MetadataTypes\DataTypeType $dataType,
		?array $format,
		int|float|string|null $invalid
	) {
		$this->source = $source;
		$this->dataType = $dataType;
		$this->format = $format;
		$this->invalid = $invalid;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSource(): Types\MessageSourceType
	{
		return $this->source;
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
			'source'    => $this->getSource()->getValue(),
			'data_type' => $this->dataType->getValue(),
			'format'    => $this->format,
			'invalid'   => $this->invalid,
		];
	}

}
