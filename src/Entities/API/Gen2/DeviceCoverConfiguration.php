<?php declare(strict_types = 1);

/**
 * DeviceCoverConfiguration.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Entities
 * @since          1.0.0
 *
 * @date           26.12.22
 */

namespace FastyBird\Connector\Shelly\Entities\API\Gen2;

use FastyBird\Connector\Shelly\Entities;
use FastyBird\Connector\Shelly\Types;
use Orisai\ObjectMapper;

/**
 * Generation 2 device input configuration entity
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DeviceCoverConfiguration implements Entities\API\Entity
{

	public function __construct(
		#[ObjectMapper\Rules\IntValue(unsigned: true)]
		private readonly int $id,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\StringValue(notEmpty: true),
			new ObjectMapper\Rules\NullValue(castEmptyString: true),
		])]
		private readonly string|null $name,
		#[ObjectMapper\Rules\ArrayEnumValue(cases: ['single', 'dual', 'detached'])]
		#[ObjectMapper\Modifiers\FieldName('in_mode')]
		private readonly string $mode,
		#[ObjectMapper\Rules\ArrayEnumValue(cases: ['open', 'closed', 'stopped'])]
		#[ObjectMapper\Modifiers\FieldName('initial_state')]
		private readonly string $initialState,
		#[ObjectMapper\Rules\FloatValue()]
		#[ObjectMapper\Modifiers\FieldName('power_limit')]
		private readonly float $powerLimit,
		#[ObjectMapper\Rules\FloatValue()]
		#[ObjectMapper\Modifiers\FieldName('voltage_limit')]
		private readonly float $voltageLimit,
		#[ObjectMapper\Rules\FloatValue()]
		#[ObjectMapper\Modifiers\FieldName('current_limit')]
		private readonly float $currentLimit,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\MappedObjectValue(class: CoverMotorConfigurationBlock::class),
			new ObjectMapper\Rules\NullValue(),
		])]
		private readonly CoverMotorConfigurationBlock|null $motor,
		#[ObjectMapper\Rules\FloatValue()]
		#[ObjectMapper\Modifiers\FieldName('maxtime_open')]
		private readonly float $maximumOpeningTime,
		#[ObjectMapper\Rules\FloatValue()]
		#[ObjectMapper\Modifiers\FieldName('maxtime_close')]
		private readonly float $maximumClosingTime,
		#[ObjectMapper\Rules\BoolValue()]
		#[ObjectMapper\Modifiers\FieldName('swap_inputs')]
		private readonly bool $swappedInput,
		#[ObjectMapper\Rules\BoolValue()]
		#[ObjectMapper\Modifiers\FieldName('invert_directions')]
		private readonly bool $invertedDirections,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\MappedObjectValue(class: CoverObstructionDetectionConfigurationBlock::class),
			new ObjectMapper\Rules\NullValue(),
		])]
		#[ObjectMapper\Modifiers\FieldName('obstruction_detection')]
		private readonly CoverObstructionDetectionConfigurationBlock|null $obstructionDetection,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\MappedObjectValue(class: CoverSafetySwitchConfigurationBlock::class),
			new ObjectMapper\Rules\NullValue(),
		])]
		#[ObjectMapper\Modifiers\FieldName('safety_switch')]
		private readonly CoverSafetySwitchConfigurationBlock|null $safetySwitch,
	)
	{
	}

	public function getId(): int
	{
		return $this->id;
	}

	public function getType(): Types\ComponentType
	{
		return Types\ComponentType::get(Types\ComponentType::COVER);
	}

	public function getName(): string|null
	{
		return $this->name;
	}

	public function getMode(): string
	{
		return $this->mode;
	}

	public function getInitialState(): string
	{
		return $this->initialState;
	}

	public function getPowerLimit(): float
	{
		return $this->powerLimit;
	}

	public function getVoltageLimit(): float
	{
		return $this->voltageLimit;
	}

	public function getCurrentLimit(): float
	{
		return $this->currentLimit;
	}

	public function getMaximumOpeningTime(): float
	{
		return $this->maximumOpeningTime;
	}

	public function getMaximumClosingTime(): float
	{
		return $this->maximumClosingTime;
	}

	public function hasSwappedInputs(): bool
	{
		return $this->swappedInput;
	}

	public function hasInvertedDirection(): bool
	{
		return $this->invertedDirections;
	}

	public function getMotor(): CoverMotorConfigurationBlock|null
	{
		return $this->motor;
	}

	public function getObstructionDetection(): CoverObstructionDetectionConfigurationBlock|null
	{
		return $this->obstructionDetection;
	}

	public function getSafetySwitch(): CoverSafetySwitchConfigurationBlock|null
	{
		return $this->safetySwitch;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'id' => $this->getId(),
			'type' => $this->getType()->getValue(),
			'name' => $this->getName(),
			'mode' => $this->getMode(),
			'initial_state' => $this->getInitialState(),
			'power_limit' => $this->getPowerLimit(),
			'voltage_limit' => $this->getVoltageLimit(),
			'current_limit' => $this->getCurrentLimit(),
			'motor' => $this->getMotor()?->toArray(),
			'maximum_opening_time' => $this->getMaximumOpeningTime(),
			'maximum_closing_time' => $this->getMaximumClosingTime(),
			'swapped_input' => $this->hasSwappedInputs(),
			'inverted_directions' => $this->hasInvertedDirection(),
			'obstruction_detection' => $this->getObstructionDetection()?->toArray(),
			'safety_switch' => $this->getSafetySwitch()?->toArray(),
		];
	}

}
