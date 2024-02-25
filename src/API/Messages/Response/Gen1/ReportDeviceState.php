<?php declare(strict_types = 1);

/**
 * ReportDeviceState.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     API
 * @since          1.0.0
 *
 * @date           26.08.23
 */

namespace FastyBird\Connector\Shelly\API\Messages\Response\Gen1;

use FastyBird\Connector\Shelly\API;
use Orisai\ObjectMapper;
use function array_map;

/**
 * Generation 1 device reported state message
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     API
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final readonly class ReportDeviceState implements API\Messages\Message
{

	/**
	 * @param array<DeviceBlockState> $states
	 */
	public function __construct(
		#[ObjectMapper\Rules\StringValue(notEmpty: true)]
		private string $identifier,
		#[ObjectMapper\Rules\StringValue(notEmpty: true)]
		#[ObjectMapper\Modifiers\FieldName('ip_address')]
		private string $ipAddress,
		#[ObjectMapper\Rules\ArrayOf(
			new ObjectMapper\Rules\MappedObjectValue(DeviceBlockState::class),
		)]
		private array $states = [],
	)
	{
	}

	public function getIdentifier(): string
	{
		return $this->identifier;
	}

	public function getIpAddress(): string
	{
		return $this->ipAddress;
	}

	/**
	 * @return array<DeviceBlockState>
	 */
	public function getStates(): array
	{
		return $this->states;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'identifier' => $this->getIdentifier(),
			'ip_address' => $this->getIpAddress(),
			'states' => array_map(
				static fn (DeviceBlockState $state): array => $state->toArray(),
				$this->getStates(),
			),
		];
	}

}
