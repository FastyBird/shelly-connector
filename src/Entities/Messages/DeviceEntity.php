<?php declare(strict_types = 1);

/**
 * DeviceEntity.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Entities
 * @since          0.37.0
 *
 * @date           17.07.22
 */

namespace FastyBird\ShellyConnector\Entities\Messages;

use Nette;

/**
 * Base device message entity
 *
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
abstract class DeviceEntity implements IEntity
{

	use Nette\SmartObject;

	/** @var string */
	private string $identifier;

	/** @var string */
	private string $ipAddress;

	/**
	 * @param string $identifier
	 * @param string $ipAddress
	 */
	public function __construct(
		string $identifier,
		string $ipAddress
	) {
		$this->identifier = $identifier;
		$this->ipAddress = $ipAddress;
	}

	/**
	 * @return string
	 */
	public function getIdentifier(): string
	{
		return $this->identifier;
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
			'identifier' => $this->getIdentifier(),
			'ip_address' => $this->getIpAddress(),
		];
	}

}
