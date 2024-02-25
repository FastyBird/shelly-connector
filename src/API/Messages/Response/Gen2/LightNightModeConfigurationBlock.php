<?php declare(strict_types = 1);

/**
 * LightNightModeConfigurationBlock.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     API
 * @since          1.0.0
 *
 * @date           27.12.22
 */

namespace FastyBird\Connector\Shelly\API\Messages\Response\Gen2;

use FastyBird\Connector\Shelly\API;
use Orisai\ObjectMapper;

/**
 * Generation 2 device light component night mode configuration message
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     API
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final readonly class LightNightModeConfigurationBlock implements API\Messages\Message
{

	/**
	 * @param array<string> $activeBetween
	 */
	public function __construct(
		#[ObjectMapper\Rules\BoolValue()]
		private bool $enable,
		#[ObjectMapper\Rules\IntValue(min: 0, max: 100, unsigned: true)]
		private int $brightness,
		#[ObjectMapper\Rules\ArrayOf(
			new ObjectMapper\Rules\StringValue(notEmpty: true),
			new ObjectMapper\Rules\IntValue(unsigned: true),
		)]
		#[ObjectMapper\Modifiers\FieldName('active_between')]
		private array $activeBetween,
	)
	{
	}

	public function isEnabled(): bool
	{
		return $this->enable;
	}

	public function getBrightness(): int
	{
		return $this->brightness;
	}

	/**
	 * @return array<string>
	 */
	public function getActiveBetween(): array
	{
		return $this->activeBetween;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'enabled' => $this->isEnabled(),
			'brightness' => $this->getBrightness(),
			'active_between' => $this->getActiveBetween(),
		];
	}

}
