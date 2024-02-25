<?php declare(strict_types = 1);

/**
 * DeviceTemperatureState.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     API
 * @since          1.0.0
 *
 * @date           26.12.22
 */

namespace FastyBird\Connector\Shelly\API\Messages\Response\Gen2;

use FastyBird\Connector\Shelly;
use FastyBird\Connector\Shelly\API;
use FastyBird\Connector\Shelly\Types;
use Orisai\ObjectMapper;
use function array_filter;
use function array_merge;

/**
 * Generation 2 device temperature state message
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     API
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DeviceTemperatureState extends DeviceState implements API\Messages\Message
{

	/**
	 * @param array<string> $errors
	 */
	public function __construct(
		int $id,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\FloatValue(),
			new ObjectMapper\Rules\ArrayEnumValue(cases: [Shelly\Constants::VALUE_NOT_AVAILABLE]),
			new ObjectMapper\Rules\NullValue(),
		])]
		#[ObjectMapper\Modifiers\FieldName('tC')]
		private readonly float|string|null $temperatureCelsius,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\FloatValue(),
			new ObjectMapper\Rules\ArrayEnumValue(cases: [Shelly\Constants::VALUE_NOT_AVAILABLE]),
			new ObjectMapper\Rules\NullValue(),
		])]
		#[ObjectMapper\Modifiers\FieldName('tF')]
		private readonly float|string|null $temperatureFahrenheit,
		array $errors = [],
	)
	{
		parent::__construct($id, $errors);
	}

	public function getType(): Types\ComponentType
	{
		return Types\ComponentType::TEMPERATURE;
	}

	public function getTemperatureCelsius(): float|string|null
	{
		return $this->temperatureCelsius;
	}

	public function getTemperatureFahrenheit(): float|string|null
	{
		return $this->temperatureFahrenheit;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return array_merge(
			parent::toArray(),
			[
				'celsius' => $this->getTemperatureCelsius(),
				'fahrenheit' => $this->getTemperatureFahrenheit(),
			],
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function toState(): array
	{
		return array_filter(
			array_merge(
				parent::toState(),
				[
					'celsius' => $this->getTemperatureCelsius(),
					'fahrenheit' => $this->getTemperatureFahrenheit(),
				],
			),
			static fn ($value): bool => $value !== Shelly\Constants::VALUE_NOT_AVAILABLE,
		);
	}

}
