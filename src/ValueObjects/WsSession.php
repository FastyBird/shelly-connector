<?php declare(strict_types = 1);

/**
 * WsSession.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     ValueObjects
 * @since          1.0.0
 *
 * @date           11.01.23
 */

namespace FastyBird\Connector\Shelly\ValueObjects;

use Orisai\ObjectMapper;

/**
 * Websocket session
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     ValueObjects
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final readonly class WsSession implements ObjectMapper\MappedObject
{

	public function __construct(
		#[ObjectMapper\Rules\StringValue(notEmpty: true)]
		private string $realm,
		#[ObjectMapper\Rules\StringValue(notEmpty: true)]
		private string $username,
		#[ObjectMapper\Rules\IntValue()]
		private int $nonce,
		#[ObjectMapper\Rules\IntValue()]
		private int $cnonce,
		#[ObjectMapper\Rules\StringValue(notEmpty: true)]
		private string $response,
		#[ObjectMapper\Rules\IntValue()]
		private int $nc,
		#[ObjectMapper\Rules\StringValue(notEmpty: true)]
		private string $algorithm,
	)
	{
	}

	public function getRealm(): string
	{
		return $this->realm;
	}

	public function getUsername(): string
	{
		return $this->username;
	}

	public function getNonce(): int
	{
		return $this->nonce;
	}

	public function getCnonce(): int
	{
		return $this->cnonce;
	}

	public function getResponse(): string
	{
		return $this->response;
	}

	public function getNc(): int
	{
		return $this->nc;
	}

	public function getAlgorithm(): string
	{
		return $this->algorithm;
	}

	/**
	 * @return array<string, string|int>
	 */
	public function toArray(): array
	{
		return [
			'realm' => $this->getRealm(),
			'username' => $this->getUsername(),
			'nonce' => $this->getNonce(),
			'cnonce' => $this->getCnonce(),
			'response' => $this->getResponse(),
			'nc' => $this->getNc(),
			'algorithm' => $this->getAlgorithm(),
		];
	}

}
