<?php declare(strict_types = 1);

/**
 * DeviceEntity.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Entities
 * @since          0.37.0
 *
 * @date           17.07.22
 */

namespace FastyBird\ShellyConnector\Entities\Messages;

use FastyBird\ShellyConnector\Types;
use Nette;
use Ramsey\Uuid;

/**
 * Base device message entity
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
abstract class DeviceEntity implements IEntity
{

	use Nette\SmartObject;

	/** @var Types\MessageSourceType */
	private Types\MessageSourceType $source;

	/** @var Uuid\UuidInterface */
	private Uuid\UuidInterface $connector;

	/** @var string */
	private string $identifier;

	/** @var string|null */
	private ?string $type;

	/** @var string */
	private string $ipAddress;

	/**
	 * @param Types\MessageSourceType $source
	 * @param Uuid\UuidInterface $connector
	 * @param string $identifier
	 * @param string|null $type
	 * @param string $ipAddress
	 */
	public function __construct(
		Types\MessageSourceType $source,
		Uuid\UuidInterface $connector,
		string $identifier,
		?string $type,
		string $ipAddress
	) {
		$this->source = $source;
		$this->connector = $connector;
		$this->identifier = $identifier;
		$this->type = $type;
		$this->ipAddress = $ipAddress;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSource(): Types\MessageSourceType
	{
		return $this->source;
	}

	/**
	 * @return Uuid\UuidInterface
	 */
	public function getConnector(): Uuid\UuidInterface
	{
		return $this->connector;
	}

	/**
	 * @return string
	 */
	public function getIdentifier(): string
	{
		return $this->identifier;
	}

	/**
	 * @return string|null
	 */
	public function getType(): ?string
	{
		return $this->type;
	}

	/**
	 * @return string
	 */
	public function getIpAddress(): string
	{
		return $this->ipAddress;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'source'     => $this->getSource()->getValue(),
			'identifier' => $this->getIdentifier(),
			'type'       => $this->getType(),
			'ip_address' => $this->getIpAddress(),
		];
	}

}
