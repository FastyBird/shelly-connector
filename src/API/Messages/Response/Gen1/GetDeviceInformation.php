<?php declare(strict_types = 1);

/**
 * GetDeviceInformation.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     API
 * @since          1.0.0
 *
 * @date           20.12.22
 */

namespace FastyBird\Connector\Shelly\API\Messages\Response\Gen1;

use FastyBird\Connector\Shelly\API;
use Orisai\ObjectMapper;

/**
 * Generation 1 device information message
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     API
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final readonly class GetDeviceInformation implements API\Messages\Message
{

	public function __construct(
		#[ObjectMapper\Rules\StringValue(notEmpty: true)]
		private string $type,
		#[ObjectMapper\Rules\StringValue(notEmpty: true)]
		private string $mac,
		#[ObjectMapper\Rules\BoolValue(castBoolLike: true)]
		private bool $auth,
		#[ObjectMapper\Rules\StringValue(notEmpty: true)]
		private string $fw,
		#[ObjectMapper\Rules\IntValue(unsigned: true)]
		private int $longid,
	)
	{
	}

	public function getModel(): string
	{
		return $this->type;
	}

	public function getMacAddress(): string
	{
		return $this->mac;
	}

	public function hasAuthentication(): bool
	{
		return $this->auth;
	}

	public function getFirmware(): string
	{
		return $this->fw;
	}

	public function getLongIdentifier(): int
	{
		return $this->longid;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'model' => $this->getModel(),
			'mac_address' => $this->getMacAddress(),
			'has_authentication' => $this->hasAuthentication(),
			'firmware' => $this->getFirmware(),
			'long_identifier' => $this->getLongIdentifier(),
		];
	}

}
