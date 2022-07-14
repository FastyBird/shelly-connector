<?php declare(strict_types = 1);

/**
 * Constants.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Clients
 * @since          0.37.0
 *
 * @date           14.07.22
 */

namespace FastyBird\ShellyConnector\Clients;

use FastyBird\Metadata\Entities as MetadataEntities;
use React\Datagram;
use React\EventLoop;

/**
 * CoAP client
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class CoapClient implements IClient
{

	/** @var MetadataEntities\Modules\DevicesModule\IConnectorEntity */
	private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector;

	/** @var EventLoop\LoopInterface */
	private EventLoop\LoopInterface $eventLoop;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param EventLoop\LoopInterface $eventLoop
	 */
	public function __construct(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		EventLoop\LoopInterface $eventLoop
	) {
		$this->connector = $connector;
		$this->eventLoop = $eventLoop;
	}

	/**
	 * {@inheritDoc}
	 */
	public function isConnected(): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect(): void
	{
		var_dump('connect');
		// $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		// socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
		// socket_bind($socket, '0.0.0.0', 5683);

		try {
			$factory = new Datagram\Factory($this->eventLoop);

			$factory->createServer('0.0.0.0:5683')->then(function (Datagram\Socket $server) {
				$server->on('message', function ($message, $address, $server): void {
					var_dump('client ' . $address . ': ' . $message);
				});
			});

			$this->eventLoop->addPeriodicTimer(2, function (): void {
				var_dump('period');
			});
		} catch (\Throwable $ex) {
			var_dump($ex->getMessage());
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function disconnect(): void
	{
		// TODO: Implement disconnect() method.
	}

	/**
	 * {@inheritDoc}
	 */
	public function writeDeviceControl(MetadataEntities\Actions\IActionDeviceControlEntity $action): void
	{
		// TODO: Implement writeDeviceControl() method.
	}

	/**
	 * {@inheritDoc}
	 */
	public function writeChannelControl(MetadataEntities\Actions\IActionChannelControlEntity $action): void
	{
		// TODO: Implement writeChannelControl() method.
	}

}
