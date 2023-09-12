<?php declare(strict_types = 1);

/**
 * GetDeviceInformation.php
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

namespace FastyBird\Connector\Shelly\Entities\API\Gen2;

use FastyBird\Connector\Shelly\Entities;
use Orisai\ObjectMapper;

/**
 * Generation 2 device information entity
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class GetDeviceInformation implements Entities\API\Entity
{

	public function __construct(
		#[ObjectMapper\Rules\StringValue(notEmpty: true)]
		private readonly string $id,
		#[ObjectMapper\Rules\StringValue(notEmpty: true)]
		private readonly string $mac,
		#[ObjectMapper\Rules\StringValue(notEmpty: true)]
		private readonly string $model,
		#[ObjectMapper\Rules\IntValue(unsigned: true)]
		private readonly int $gen,
		#[ObjectMapper\Rules\StringValue(notEmpty: true)]
		#[ObjectMapper\Modifiers\FieldName('fw_id')]
		private readonly string $fwId,
		#[ObjectMapper\Rules\StringValue(notEmpty: true)]
		private readonly string $ver,
		#[ObjectMapper\Rules\StringValue(notEmpty: true)]
		private readonly string $app,
		#[ObjectMapper\Rules\BoolValue()]
		#[ObjectMapper\Modifiers\FieldName('auth_en')]
		private readonly bool $authEn,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\StringValue(notEmpty: true),
			new ObjectMapper\Rules\NullValue(castEmptyString: true),
		])]
		#[ObjectMapper\Modifiers\FieldName('auth_domain')]
		private readonly string|null $authDomain,
	)
	{
	}

	public function getId(): string
	{
		return $this->id;
	}

	public function getMacAddress(): string
	{
		return $this->mac;
	}

	public function getModel(): string
	{
		return $this->model;
	}

	public function getGeneration(): int
	{
		return $this->gen;
	}

	public function getFirmware(): string
	{
		return $this->fwId;
	}

	public function getVersion(): string
	{
		return $this->ver;
	}

	public function getApplication(): string
	{
		return $this->app;
	}

	public function hasAuthentication(): bool
	{
		return $this->authEn;
	}

	public function getAuthenticationDomain(): string|null
	{
		return $this->authDomain;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'id' => $this->getId(),
			'mac_address' => $this->getMacAddress(),
			'model' => $this->getModel(),
			'generation' => $this->getGeneration(),
			'firmware' => $this->getFirmware(),
			'version' => $this->getVersion(),
			'application' => $this->getApplication(),
			'authentication' => $this->hasAuthentication(),
			'authentication_domain' => $this->getAuthenticationDomain(),
		];
	}

}
