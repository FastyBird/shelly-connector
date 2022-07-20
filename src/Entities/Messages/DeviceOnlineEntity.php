<?php declare(strict_types = 1);

/**
 * DeviceOnlineEntity.php
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
 * Device online status message entity
 *
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DeviceOnlineEntity extends DeviceEntity
{

	/** @var string */
	private string $time;

	/** @var string */
	private string $unixtime;

	/**
	 * @param string $identifier
	 * @param string $ipAddress
	 * @param string $time
	 * @param string $unixtime
	 */
	public function __construct(
		string $identifier,
		string $ipAddress,
		string $time,
		string $unixtime
	) {
		parent::__construct($identifier, $ipAddress);

		$this->time = $time;
		$this->unixtime = $unixtime;
	}

	/**
	 * @return string
	 */
	public function getTime(): string
	{
		return $this->time;
	}

	/**
	 * @return string
	 */
	public function getUnixtime(): string
	{
		return $this->unixtime;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return array_merge(parent::toArray(), [
			'time'     => $this->getTime(),
			'unixtime' => $this->getUnixtime(),
		]);
	}

}
