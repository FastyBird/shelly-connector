<?php declare(strict_types = 1);

/**
 * CoapClient.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Clients
 * @since          0.37.0
 *
 * @date           14.07.22
 */

namespace FastyBird\ShellyConnector\Clients\Gen1;

use Clue\React\Multicast;
use FastyBird\Metadata;
use FastyBird\ShellyConnector\API;
use FastyBird\ShellyConnector\Consumers;
use FastyBird\ShellyConnector\Exceptions;
use Nette;
use Psr\Log;
use React\Datagram;
use React\EventLoop;

/**
 * CoAP client
 *
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class CoapClient
{

	use Nette\SmartObject;

	private const COAP_ADDRESS = '224.0.1.187';
	private const COAP_PORT = 5683;

	private const STATUS_MESSAGE_CODE = 30;
	private const DESCRIPTION_MESSAGE_CODE = 69;

	private const AUTOMATIC_DISCOVERY_DELAY = 5;

	/** @var API\Gen1Validator */
	private API\Gen1Validator $validator;

	/** @var API\Gen1Parser */
	private API\Gen1Parser $parser;

	/** @var Consumers\Consumer */
	private Consumers\Consumer $consumer;

	/** @var EventLoop\LoopInterface */
	private EventLoop\LoopInterface $eventLoop;

	/** @var Datagram\SocketInterface|null */
	private ?Datagram\SocketInterface $server = null;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param API\Gen1Validator $validator
	 * @param API\Gen1Parser $parser
	 * @param Consumers\Consumer $consumer
	 * @param EventLoop\LoopInterface $eventLoop
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		API\Gen1Validator $validator,
		API\Gen1Parser $parser,
		Consumers\Consumer $consumer,
		EventLoop\LoopInterface $eventLoop,
		?Log\LoggerInterface $logger = null
	) {
		$this->validator = $validator;
		$this->parser = $parser;
		$this->consumer = $consumer;

		$this->eventLoop = $eventLoop;

		$this->logger = $logger ?? new Log\NullLogger();
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
	public function discover(): void
	{
		if ($this->server === null) {
			$this->logger->warning(
				'CoAP client is not running, discovery process could not be processed',
				[
					'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
					'type'   => 'coap-client',
				]
			);

			return;
		}

		$message = '';

		$buffer = [80, 1, 0, 10, 179, 99, 105, 116, 1, 100, 255];

		foreach ($buffer as $value) {
			$message .= pack('C', $value);
		}

		$this->logger->debug(
			'Sending CoAP discover UDP',
			[
				'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type'   => 'coap-client',
			]
		);

		$this->server->send($message, self::COAP_ADDRESS . ':' . self::COAP_PORT);
	}

	/**
	 * @return void
	 */
	public function connect(): void
	{
		$factory = new Multicast\Factory($this->eventLoop);

		$this->server = $factory->createReceiver(self::COAP_ADDRESS . ':' . self::COAP_PORT);

		$this->server->on('message', function ($message, $remote): void {
			$this->handlePacket($message, $remote);
		});

		$this->eventLoop->addTimer(self::AUTOMATIC_DISCOVERY_DELAY, function (): void {
			$this->discover();
		});
	}

	/**
	 * @return void
	 */
	public function disconnect(): void
	{
		$this->server?->close();
	}

	/**
	 * @param string $packet
	 * @param string $address
	 *
	 * @return void
	 */
	private function handlePacket(string $packet, string $address): void
	{
		$buffer = unpack('C*', $packet);

		if (!is_array($buffer) || count($buffer) < 10) {
			return;
		}

		$pos = 1;

		// Receive messages with ip from proxy
		if ($buffer[1] === 112 && $buffer[2] === 114 && $buffer[3] === 120 && $buffer[4] === 121) {
			$pos = 9;
		}

		$byte = $buffer[$pos];

		$tkl = $byte & 0x0F;

		$code = $buffer[$pos + 1];
		// $messageId = 256 * $buf[3] + $buf[4];

		$pos = $pos + 4 + $tkl;

		if (in_array($code, [self::STATUS_MESSAGE_CODE, self::DESCRIPTION_MESSAGE_CODE])) {
			$byte = $buffer[$pos];
			$totDelta = 0;

			$deviceType = null;
			$deviceIdentifier = null;

			while ($byte !== 0xFF) {
				$delta = $byte >> 4;
				$length = $byte & 0x0F;

				if ($delta === 13) {
					$pos++;
					$delta = $buffer[$pos] + 13;

				} elseif ($delta === 14) {
					$pos += 2;
					$delta = $buffer[$pos - 1] * 256 + $buffer[$pos] + 269;
				}

				$totDelta += $delta;

				if ($length === 13) {
					$pos++;
					$length = $buffer[$pos] + 13;

				} elseif ($length === 14) {
					$pos += 2;
					$length = $buffer[$pos - 1] * 256 + $buffer[$pos] + 269;
				}

				$value = '';
				for ($i = $pos + 1; $i <= $pos + $length; $i++) {
					$value .= pack('C', $buffer[$i]);
				}

				$pos = $pos + $length + 1;

				if ($totDelta === 3332) {
					[
						$deviceType,
						$deviceIdentifier,
					] = explode('#', mb_convert_encoding($value, 'cp1252', 'utf8')) + [null, null];
				}

				$byte = $buffer[$pos];
			}

			$message = '';

			for ($i = $pos + 1; $i <= count($buffer); $i++) {
				$message .= pack('C', $buffer[$i]);
			}

			$message = mb_convert_encoding($message, 'cp1252', 'utf8');

			$this->logger->debug(
				sprintf(
					'CoAP Code: %d, Type: %s, Id: %s, Payload: %s',
					$code,
					$deviceType,
					$deviceIdentifier,
					str_replace(' ', '', $message),
				),
				[
					'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
					'type' => 'coap-client',
				]
			);

			if (
				$code === self::STATUS_MESSAGE_CODE
				&& $this->validator->isValidCoapStatusMessage($message)
				&& $deviceType !== null
				&& $deviceIdentifier !== null
			) {
				try {
					$this->consumer->append(
						$this->parser->parseCoapStatusMessage(
							$address,
							$deviceType,
							$deviceIdentifier,
							$message
						)
					);
				} catch (Exceptions\ParseMessageException $ex) {
					$this->logger->warning(
						'Received message could not be parsed into entity',
						[
							'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
							'type' => 'coap-client',
							'exception' => [
								'message' => $ex->getMessage(),
								'code'    => $ex->getCode(),
							],
						]
					);
				}
			} elseif (
				$code === self::DESCRIPTION_MESSAGE_CODE
				&& $this->validator->isValidCoapDescriptionMessage($message)
				&& $deviceType !== null
				&& $deviceIdentifier !== null
			) {
				try {
					$this->consumer->append(
						$this->parser->parseCoapDescriptionMessage(
							$address,
							$deviceType,
							$deviceIdentifier,
							$message
						)
					);
				} catch (Exceptions\ParseMessageException $ex) {
					$this->logger->warning(
						'Received message could not be parsed into entity',
						[
							'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
							'type' => 'coap-client',
							'exception' => [
								'message' => $ex->getMessage(),
								'code'    => $ex->getCode(),
							],
						]
					);
				}
			}
		}
	}

}
