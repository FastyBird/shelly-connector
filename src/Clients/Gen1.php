<?php declare(strict_types = 1);

/**
 * Gen1.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Clients
 * @since          0.37.0
 *
 * @date           17.07.22
 */

namespace FastyBird\ShellyConnector\Clients;

use FastyBird\DevicesModule\Exceptions as DevicesModuleExceptions;
use FastyBird\Metadata;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\ShellyConnector\Clients;
use FastyBird\ShellyConnector\Helpers;
use FastyBird\ShellyConnector\Types;
use Nette;
use Psr\Log;
use Throwable;

/**
 * Generation 1 devices client
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Gen1 implements Client
{

	use Nette\SmartObject;

	/** @var MetadataEntities\Modules\DevicesModule\IConnectorEntity */
	private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector;

	/** @var Clients\Gen1\Coap|null */
	private ?Clients\Gen1\Coap $coapClient = null;

	/** @var Clients\Gen1\Mdns|null */
	private ?Clients\Gen1\Mdns $mdnsClient = null;

	/** @var Clients\Gen1\Http|null */
	private ?Clients\Gen1\Http $httpClient = null;

	/** @var Gen1\Mqtt|null */
	private ?Clients\Gen1\Mqtt $mqttClient = null;

	/** @var Gen1\CoapFactory */
	private Clients\Gen1\CoapFactory $coapClientFactory;

	/** @var Gen1\MdnsFactory */
	private Clients\Gen1\MdnsFactory $mdnsClientFactory;

	/** @var Gen1\HttpFactory */
	private Clients\Gen1\HttpFactory $httpClientFactory;

	/** @var Gen1\MqttFactory */
	private Clients\Gen1\MqttFactory $mqttClientFactory;

	/** @var Helpers\Connector */
	private Helpers\Connector $connectorHelper;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param Gen1\CoapFactory $coapClientFactory
	 * @param Gen1\MdnsFactory $mdnsClientFactory
	 * @param Gen1\HttpFactory $httpClientFactory
	 * @param Gen1\MqttFactory $mqttClientFactory
	 * @param Helpers\Connector $connectorHelper
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		Clients\Gen1\CoapFactory $coapClientFactory,
		Clients\Gen1\MdnsFactory $mdnsClientFactory,
		Clients\Gen1\HttpFactory $httpClientFactory,
		Clients\Gen1\MqttFactory $mqttClientFactory,
		Helpers\Connector $connectorHelper,
		?Log\LoggerInterface $logger = null
	) {
		$this->connector = $connector;

		$this->connectorHelper = $connectorHelper;

		$this->coapClientFactory = $coapClientFactory;
		$this->mdnsClientFactory = $mdnsClientFactory;
		$this->httpClientFactory = $httpClientFactory;
		$this->mqttClientFactory = $mqttClientFactory;

		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * @return void
	 *
	 * @throws DevicesModuleExceptions\TerminateException
	 */
	public function discover(): void
	{
		$this->coapClient = $this->coapClientFactory->create($this->connector);
		$this->mdnsClient = $this->mdnsClientFactory->create($this->connector);

		try {
			$this->coapClient->connect(true);
		} catch (Throwable $ex) {
			$this->logger->error(
				'CoAP client could not be started',
				[
					'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
					'type'      => 'gen1-client',
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
					'connector' => [
						'id' => $this->connector->getId()->toString(),
					],
				]
			);

			throw new DevicesModuleExceptions\TerminateException(
				'CoAP client could not be started',
				$ex->getCode(),
				$ex
			);
		}

		try {
			$this->mdnsClient->connect();
		} catch (Throwable $ex) {
			$this->logger->error(
				'mDNS client could not be started',
				[
					'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
					'type'      => 'gen1-client',
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
					'connector' => [
						'id' => $this->connector->getId()->toString(),
					],
				]
			);

			throw new DevicesModuleExceptions\TerminateException(
				'mDNS client could not be started',
				$ex->getCode(),
				$ex
			);
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DevicesModuleExceptions\TerminateException
	 */
	public function connect(): void
	{
		$mode = $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_CLIENT_MODE)
		);

		if ($mode === null) {
			throw new DevicesModuleExceptions\TerminateException('Connector client mode is not configured');
		}

		if ($mode === Types\ClientMode::TYPE_GEN_1_CLASSIC) {
			$this->coapClient = $this->coapClientFactory->create($this->connector);
			$this->mdnsClient = $this->mdnsClientFactory->create($this->connector);
			$this->httpClient = $this->httpClientFactory->create($this->connector);

			try {
				$this->coapClient->connect();
			} catch (Throwable $ex) {
				$this->logger->error(
					'CoAP client could not be started',
					[
						'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type'      => 'gen1-client',
						'exception' => [
							'message' => $ex->getMessage(),
							'code'    => $ex->getCode(),
						],
						'connector' => [
							'id' => $this->connector->getId()->toString(),
						],
					]
				);

				throw new DevicesModuleExceptions\TerminateException(
					'CoAP client could not be started',
					$ex->getCode(),
					$ex
				);
			}

			try {
				$this->mdnsClient->connect();
			} catch (Throwable $ex) {
				$this->logger->error(
					'mDNS client could not be started',
					[
						'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type'      => 'gen1-client',
						'exception' => [
							'message' => $ex->getMessage(),
							'code'    => $ex->getCode(),
						],
						'connector' => [
							'id' => $this->connector->getId()->toString(),
						],
					]
				);

				throw new DevicesModuleExceptions\TerminateException(
					'mDNS client could not be started',
					$ex->getCode(),
					$ex
				);
			}

			try {
				$this->httpClient->connect();
			} catch (Throwable $ex) {
				$this->logger->error(
					'Http api client could not be started',
					[
						'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type'      => 'gen1-client',
						'exception' => [
							'message' => $ex->getMessage(),
							'code'    => $ex->getCode(),
						],
						'connector' => [
							'id' => $this->connector->getId()->toString(),
						],
					]
				);

				throw new DevicesModuleExceptions\TerminateException(
					'Http api client could not be started',
					$ex->getCode(),
					$ex
				);
			}
		} elseif ($mode === Types\ClientMode::TYPE_GEN_1_MQTT) {
			$this->mqttClient = $this->mqttClientFactory->create($this->connector);

			try {
				$this->mqttClient->connect();
			} catch (Throwable $ex) {
				$this->logger->error(
					'MQTT client could not be started',
					[
						'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type'      => 'gen1-client',
						'exception' => [
							'message' => $ex->getMessage(),
							'code'    => $ex->getCode(),
						],
						'connector' => [
							'id' => $this->connector->getId()->toString(),
						],
					]
				);

				throw new DevicesModuleExceptions\TerminateException(
					'MQTT client could not be started',
					$ex->getCode(),
					$ex
				);
			}
		} else {
			throw new DevicesModuleExceptions\TerminateException('Client mode is not configured');
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function disconnect(): void
	{
		try {
			$this->coapClient?->disconnect();
		} catch (Throwable $ex) {
			$this->logger->error(
				'CoAP client could not be disconnected',
				[
					'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
					'type'      => 'gen1-client',
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
					'connector' => [
						'id' => $this->connector->getId()->toString(),
					],
				]
			);
		}

		try {
			$this->mdnsClient?->disconnect();
		} catch (Throwable $ex) {
			$this->logger->error(
				'mDNS client could not be disconnected',
				[
					'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
					'type'      => 'gen1-client',
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
					'connector' => [
						'id' => $this->connector->getId()->toString(),
					],
				]
			);
		}

		try {
			$this->httpClient?->disconnect();
		} catch (Throwable $ex) {
			$this->logger->error(
				'Http api client could not be disconnected',
				[
					'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
					'type'      => 'gen1-client',
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
					'connector' => [
						'id' => $this->connector->getId()->toString(),
					],
				]
			);
		}

		try {
			$this->mqttClient?->disconnect();
		} catch (Throwable $ex) {
			$this->logger->error(
				'MQTT client could not be disconnected',
				[
					'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
					'type'      => 'gen1-client',
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
					'connector' => [
						'id' => $this->connector->getId()->toString(),
					],
				]
			);
		}
	}

}
