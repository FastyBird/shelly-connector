<?php declare(strict_types = 1);

/**
 * DeviceSettingsEntity.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Entities
 * @since          0.37.0
 *
 * @date           19.07.22
 */

namespace FastyBird\ShellyConnector\Entities\Messages;

/**
 * Device settings message entity
 *
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DeviceSettingsEntity extends DeviceEntity
{

	/** @var string */
	private string $name;

	/**
	 * @param string $identifier
	 * @param string $ipAddress
	 * @param string $name
	 */
	public function __construct(
		string $identifier,
		string $ipAddress,
		string $name
	) {
		parent::__construct($identifier, $ipAddress);

		$this->name = $name;
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return array_merge(parent::toArray(), [
			'name' => $this->getName(),
		]);
	}

}
