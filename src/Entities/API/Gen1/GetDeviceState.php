<?php declare(strict_types = 1);

/**
 * GetDeviceState.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Entities
 * @since          1.0.0
 *
 * @date           20.12.22
 */

namespace FastyBird\Connector\Shelly\Entities\API\Gen1;

use FastyBird\Connector\Shelly\Entities;
use Orisai\ObjectMapper;
use function array_map;

/**
 * Generation 1 device state entity
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class GetDeviceState implements Entities\API\Entity
{

	/**
	 * @param array<int, DeviceRelayState> $relays
	 * @param array<int, DeviceRollerState> $rollers
	 * @param array<int, DeviceInputState> $inputs
	 * @param array<int, DeviceLightState> $lights
	 * @param array<int, DeviceMeterState> $meters
	 * @param array<int, DeviceEmeterState> $emeters
	 */
	public function __construct(
		#[ObjectMapper\Rules\ArrayOf(
			new ObjectMapper\Rules\MappedObjectValue(DeviceRelayState::class),
		)]
		private readonly array $relays = [],
		#[ObjectMapper\Rules\ArrayOf(
			new ObjectMapper\Rules\MappedObjectValue(DeviceRollerState::class),
		)]
		private readonly array $rollers = [],
		#[ObjectMapper\Rules\ArrayOf(
			new ObjectMapper\Rules\MappedObjectValue(DeviceInputState::class),
		)]
		private readonly array $inputs = [],
		#[ObjectMapper\Rules\ArrayOf(
			new ObjectMapper\Rules\MappedObjectValue(DeviceLightState::class),
		)]
		private readonly array $lights = [],
		#[ObjectMapper\Rules\ArrayOf(
			new ObjectMapper\Rules\MappedObjectValue(DeviceMeterState::class),
		)]
		private readonly array $meters = [],
		#[ObjectMapper\Rules\ArrayOf(
			new ObjectMapper\Rules\MappedObjectValue(DeviceEmeterState::class),
		)]
		private readonly array $emeters = [],
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\MappedObjectValue(WifiStaState::class),
			new ObjectMapper\Rules\NullValue(),
		])]
		private readonly WifiStaState|null $wifi = null,
	)
	{
	}

	/**
	 * @return array<int, DeviceRelayState>
	 */
	public function getRelays(): array
	{
		return $this->relays;
	}

	/**
	 * @return array<int, DeviceRollerState>
	 */
	public function getRollers(): array
	{
		return $this->rollers;
	}

	/**
	 * @return array<int, DeviceInputState>
	 */
	public function getInputs(): array
	{
		return $this->inputs;
	}

	/**
	 * @return array<int, DeviceLightState>
	 */
	public function getLights(): array
	{
		return $this->lights;
	}

	/**
	 * @return array<int, DeviceMeterState>
	 */
	public function getMeters(): array
	{
		return $this->meters;
	}

	/**
	 * @return array<int, DeviceEmeterState>
	 */
	public function getEmeters(): array
	{
		return $this->emeters;
	}

	public function getWifi(): WifiStaState|null
	{
		return $this->wifi;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'relays' => array_map(
				static fn (DeviceRelayState $state): array => $state->toArray(),
				$this->getRelays(),
			),
			'rollers' => array_map(
				static fn (DeviceRollerState $state): array => $state->toArray(),
				$this->getRollers(),
			),
			'inputs' => array_map(
				static fn (DeviceInputState $state): array => $state->toArray(),
				$this->getInputs(),
			),
			'lights' => array_map(
				static fn (DeviceLightState $state): array => $state->toArray(),
				$this->getLights(),
			),
			'meters' => array_map(
				static fn (DeviceMeterState $state): array => $state->toArray(),
				$this->getMeters(),
			),
			'emeters' => array_map(
				static fn (DeviceEmeterState $state): array => $state->toArray(),
				$this->getEmeters(),
			),
			'wifi' => $this->getWifi()?->toArray(),
		];
	}

}
