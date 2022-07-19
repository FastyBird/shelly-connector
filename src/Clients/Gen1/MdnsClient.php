<?php declare(strict_types = 1);

/**
 * MdnsClient.php
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

namespace FastyBird\ShellyConnector\Clients\Gen1;

use Clue\React\Multicast;
use FastyBird\Metadata;
use FastyBird\ShellyConnector\Consumers;
use FastyBird\ShellyConnector\Entities\Messages\DeviceFound;
use InvalidArgumentException;
use Nette\Utils;
use Psr\Log;
use React\Datagram;
use React\Dns;
use React\EventLoop;

/**
 * mDNS client
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class MdnsClient
{

	private const DNS_ADDRESS = '224.0.0.251';
	private const DNS_PORT = 5353;

	private const MATCH_NAME = '/^(?P<devtype>shelly.+)-(?P<id>[0-9A-Fa-f]+)._(http|shelly)._tcp.local$/';

	/** @var MdnsResultStorage */
	private MdnsResultStorage $searchResult;

	/** @var Consumers\Consumer */
	private Consumers\Consumer $consumer;

	/** @var EventLoop\LoopInterface */
	private EventLoop\LoopInterface $eventLoop;

	/** @var Datagram\SocketInterface|null */
	private ?Datagram\SocketInterface $server = null;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param Consumers\Consumer $consumer
	 * @param EventLoop\LoopInterface $eventLoop
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		Consumers\Consumer $consumer,
		EventLoop\LoopInterface $eventLoop,
		?Log\LoggerInterface $logger = null
	) {
		$this->consumer = $consumer;

		$this->eventLoop = $eventLoop;

		$this->logger = $logger ?? new Log\NullLogger();

		$this->searchResult = new MdnsResultStorage();
	}

	/**
	 * @return bool
	 */
	public function isConnected(): bool
	{
		return $this->server !== null;
	}

	/**
	 * @return void
	 */
	public function connect(): void
	{
		$factory = new Multicast\Factory($this->eventLoop);

		$server = $this->server = $factory->createReceiver(self::DNS_ADDRESS . ':' . self::DNS_PORT);

		$parser = new Dns\Protocol\Parser();

		$this->server->on('message', function ($message, $remote) use ($parser): void {
			try {
				$response = $parser->parseMessage($message);

			} catch (InvalidArgumentException) {
				$this->logger->warning('Invalid DNS question response received', [
					'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
					'type'   => 'mdns-client',
				]);

				return;
			}

			if ($response->tc) {
				$this->logger->warning('The server set the truncated bit although we issued a TCP request', [
					'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
					'type'   => 'mdns-client',
				]);

				return;
			}

			$serviceName = null;
			$serviceData = null;

			foreach ($response->answers as $answer) {
				if (
					$answer->type === Dns\Model\Message::TYPE_PTR
					&& is_string($answer->data)
					&& preg_match(self::MATCH_NAME, $answer->data) === 1
					&& $serviceName === null
				) {
					$serviceName = $answer->data;
				}

				if (
					$answer->type === Dns\Model\Message::TYPE_TXT
					&& preg_match(self::MATCH_NAME, $answer->name) === 1
					&& is_array($answer->data)
					&& $serviceData === null
				) {
					$serviceData = $answer->data;
				}
			}

			if ($serviceName !== null && $serviceData !== null) {
				preg_match('/^(\d[\d.]+):(\d+)\b/', $remote, $results);

				if (count($results) === 3) {
					$serviceResult = new MdnsResult($results[1], $serviceName, $serviceData);

					if (!$this->searchResult->contains($serviceResult)) {
						$this->searchResult->attach($serviceResult);

						preg_match(self::MATCH_NAME, $serviceName, $matches);

						$this->consumer->append(new DeviceFound(
							$results[1],
							Utils\Strings::lower($matches['id'])
						));
					}
				}
			}
		});

		$this->eventLoop->futureTick(function () use ($server): void {
			$dumper = new Dns\Protocol\BinaryDumper();

			$query = new Dns\Query\Query(
				'_http._tcp.local',
				Dns\Model\Message::TYPE_PTR,
				Dns\Model\Message::CLASS_IN
			);

			$queryData = $dumper->toBinary(Dns\Model\Message::createRequestForQuery($query));

			$server->send($queryData, self::DNS_ADDRESS . ':' . self::DNS_PORT);

			$query = new Dns\Query\Query(
				'_shelly._tcp.local',
				Dns\Model\Message::TYPE_PTR,
				Dns\Model\Message::CLASS_IN
			);

			$queryData = $dumper->toBinary(Dns\Model\Message::createRequestForQuery($query));

			$server->send($queryData, self::DNS_ADDRESS . ':' . self::DNS_PORT);
		});
	}

	/**
	 * @return void
	 */
	public function disconnect(): void
	{
		$this->server?->close();
	}

}
