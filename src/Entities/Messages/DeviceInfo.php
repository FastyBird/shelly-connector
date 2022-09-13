<?php declare(strict_types = 1);

/**
 * DeviceInfo.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Properties
 * @since          0.37.0
 *
 * @date           19.07.22
 */

namespace FastyBird\ShellyConnector\Entities\Messages;

use FastyBird\ShellyConnector\Types;
use Ramsey\Uuid;

/**
 * Device info message entity
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Properties
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DeviceInfo extends Device
{

	/** @var string */
	private string $macAddress;

	/** @var bool */
	private bool $authEnabled;

	/** @var string */
	private string $firmwareVersion;

	/**
	 * @param Types\MessageSource $source
	 * @param Uuid\UuidInterface $connector
	 * @param string $identifier
	 * @param string $ipAddress
	 * @param string $type
	 * @param string $macAddress
	 * @param bool $authEnabled
	 * @param string $firmwareVersion
	 */
	public function __construct(
		Types\MessageSource $source,
		Uuid\UuidInterface $connector,
		string $identifier,
		string $ipAddress,
		string $type,
		string $macAddress,
		bool $authEnabled,
		string $firmwareVersion
	) {
		parent::__construct($source, $connector, $identifier, $type, $ipAddress);

		$this->macAddress = $macAddress;
		$this->authEnabled = $authEnabled;
		$this->firmwareVersion = $firmwareVersion;
	}

	/**
	 * @return string
	 */
	public function getMacAddress(): string
	{
		return $this->macAddress;
	}

	/**
	 * @return bool
	 */
	public function isAuthEnabled(): bool
	{
		return $this->authEnabled;
	}

	/**
	 * @return string
	 */
	public function getFirmwareVersion(): string
	{
		return $this->firmwareVersion;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return array_merge(parent::toArray(), [
			'mac_address'      => $this->getMacAddress(),
			'auth_enabled'     => $this->isAuthEnabled(),
			'firmware_version' => $this->getFirmwareVersion(),
		]);
	}

}