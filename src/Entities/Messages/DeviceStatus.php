<?php declare(strict_types = 1);

/**
 * DeviceStatus.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Properties
 * @since          0.37.0
 *
 * @date           18.07.22
 */

namespace FastyBird\ShellyConnector\Entities\Messages;

use FastyBird\ShellyConnector\Types;
use Ramsey\Uuid;

/**
 * Device status message entity
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Properties
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DeviceStatus extends Device
{

	/** @var ChannelStatus[] */
	private array $channels;

	/**
	 * @param Types\MessageSource $source
	 * @param Uuid\UuidInterface $connector
	 * @param string $identifier
	 * @param string $type
	 * @param string $ipAddress
	 * @param ChannelStatus[] $channels
	 */
	public function __construct(
		Types\MessageSource $source,
		Uuid\UuidInterface  $connector,
		string              $identifier,
		string              $type,
		string              $ipAddress,
		array               $channels
	) {
		parent::__construct($source, $connector, $identifier, $type, $ipAddress);

		$this->channels = $channels;
	}

	/**
	 * @return ChannelStatus[]
	 */
	public function getChannels(): array
	{
		return $this->channels;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return array_merge(parent::toArray(), [
			'channels' => array_map(function (ChannelStatus $channel): array {
				return $channel->toArray();
			}, $this->getChannels()),
		]);
	}

}
