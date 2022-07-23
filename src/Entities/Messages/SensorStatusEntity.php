<?php declare(strict_types = 1);

/**
 * SensorStatus.php
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
 * Sensor status entity
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class SensorStatusEntity implements IEntity
{

	use Nette\SmartObject;

	/** @var Types\MessageSourceType */
	private Types\MessageSourceType $source;

	/** @var int */
	private int $identifier;

	/** @var float|int|string|bool|MetadataTypes\SwitchPayloadType|null */
	private float|int|string|bool|MetadataTypes\SwitchPayloadType|null $value;

	/**
	 * @param Types\MessageSourceType $source
	 * @param int $identifier
	 * @param float|int|string|bool|MetadataTypes\SwitchPayloadType|null $value
	 */
	public function __construct(
		Types\MessageSourceType $source,
		int $identifier,
		float|int|string|bool|MetadataTypes\SwitchPayloadType|null $value
	) {
		$this->source = $source;
		$this->identifier = $identifier;
		$this->value = $value;
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
	 * @return float|int|string|bool|MetadataTypes\SwitchPayloadType|null
	 */
	public function getValue(): float|int|string|bool|MetadataTypes\SwitchPayloadType|null
	{
		return $this->value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'source'     => $this->getSource()->getValue(),
			'identifier' => $this->getIdentifier(),
			'value'      => is_scalar($this->getValue()) ? $this->getValue() : strval($this->getValue()),
		];
	}

}
