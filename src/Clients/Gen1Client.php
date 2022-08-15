<?php declare(strict_types = 1);

/**
 * Gen1Client.php
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
final class Gen1Client extends Client
{

	/** @var MetadataEntities\Modules\DevicesModule\IConnectorEntity */
	private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector;

	/** @var Clients\Gen1\CoapClient|null */
	private ?Clients\Gen1\CoapClient $coapClient = null;

	/** @var Clients\Gen1\MdnsClient|null */
	private ?Clients\Gen1\MdnsClient $mdnsClient = null;

	/** @var Clients\Gen1\HttpClient|null */
	private ?Clients\Gen1\HttpClient $httpClient = null;

	/** @var Gen1\MqttClient|null */
	private ?Clients\Gen1\MqttClient $mqttClient = null;

	/** @var Gen1\CoapClientFactory */
	private Clients\Gen1\CoapClientFactory $coapClientFactory;

	/** @var Gen1\MdnsClientFactory */
	private Clients\Gen1\MdnsClientFactory $mdnsClientFactory;

	/** @var Gen1\HttpClientFactory */
	private Clients\Gen1\HttpClientFactory $httpClientFactory;

	/** @var Gen1\MqttClientFactory */
	private Clients\Gen1\MqttClientFactory $mqttClientFactory;

	/** @var Helpers\ConnectorHelper */
	private Helpers\ConnectorHelper $connectorHelper;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param Gen1\CoapClientFactory $coapClientFactory
	 * @param Gen1\MdnsClientFactory $mdnsClientFactory
	 * @param Gen1\HttpClientFactory $httpClientFactory
	 * @param Gen1\MqttClientFactory $mqttClientFactory
	 * @param Helpers\ConnectorHelper $connectorHelper
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		Clients\Gen1\CoapClientFactory $coapClientFactory,
		Clients\Gen1\MdnsClientFactory $mdnsClientFactory,
		Clients\Gen1\HttpClientFactory $httpClientFactory,
		Clients\Gen1\MqttClientFactory $mqttClientFactory,
		Helpers\ConnectorHelper $connectorHelper,
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
			$this->logger->error('CoAP client could not be started', [
				'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type'   => 'gen1-client',
			]);

			throw new DevicesModuleExceptions\TerminateException(
				'CoAP client could not be started',
				$ex->getCode(),
				$ex
			);
		}

		try {
			$this->mdnsClient->connect();
		} catch (Throwable $ex) {
			$this->logger->error('mDNS client could not be started', [
				'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type'   => 'gen1-client',
			]);

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
			Types\ConnectorPropertyIdentifierType::get(Types\ConnectorPropertyIdentifierType::IDENTIFIER_CLIENT_MODE)
		);

		if ($mode === null) {
			throw new DevicesModuleExceptions\TerminateException('Connector client mode is not configured');
		}

		if ($mode === Types\ClientModeType::TYPE_GEN_1_CLASSIC) {
			$this->coapClient = $this->coapClientFactory->create($this->connector);
			$this->mdnsClient = $this->mdnsClientFactory->create($this->connector);
			$this->httpClient = $this->httpClientFactory->create($this->connector);

			try {
				$this->coapClient->connect();
			} catch (Throwable $ex) {
				$this->logger->error('CoAP client could not be started', [
					'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
					'type'   => 'gen1-client',
				]);

				throw new DevicesModuleExceptions\TerminateException(
					'CoAP client could not be started',
					$ex->getCode(),
					$ex
				);
			}

			try {
				$this->mdnsClient->connect();
			} catch (Throwable $ex) {
				$this->logger->error('mDNS client could not be started', [
					'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
					'type'   => 'gen1-client',
				]);

				throw new DevicesModuleExceptions\TerminateException(
					'mDNS client could not be started',
					$ex->getCode(),
					$ex
				);
			}

			try {
				$this->httpClient->connect();
			} catch (Throwable $ex) {
				$this->logger->error('Http api client could not be started', [
					'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
					'type'   => 'gen1-client',
				]);

				throw new DevicesModuleExceptions\TerminateException(
					'Http api client could not be started',
					$ex->getCode(),
					$ex
				);
			}
		} elseif ($mode === Types\ClientModeType::TYPE_GEN_1_MQTT) {
			$this->mqttClient = $this->mqttClientFactory->create($this->connector);

			try {
				$this->mqttClient->connect();
			} catch (Throwable $ex) {
				$this->logger->error('MQTT client could not be started', [
					'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
					'type'   => 'gen1-client',
				]);

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
		} catch (Throwable) {
			$this->logger->error('CoAP client could not be disconnected', [
				'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type'   => 'gen1-client',
			]);
		}

		try {
			$this->mdnsClient?->disconnect();
		} catch (Throwable) {
			$this->logger->error('mDNS client could not be disconnected', [
				'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type'   => 'gen1-client',
			]);
		}

		try {
			$this->httpClient?->disconnect();
		} catch (Throwable) {
			$this->logger->error('Http api client could not be disconnected', [
				'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type'   => 'gen1-client',
			]);
		}

		try {
			$this->mqttClient?->disconnect();
		} catch (Throwable) {
			$this->logger->error('Http api client could not be disconnected', [
				'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type'   => 'gen1-client',
			]);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function isConnected(): bool
	{
		return $this->coapClient?->isConnected() || $this->mdnsClient?->isConnected() || $this->httpClient?->isConnected() || $this->mqttClient?->isConnected();
	}

	/**
	 * {@inheritDoc}
	 */
	public function writeDeviceControl(MetadataEntities\Actions\IActionDeviceControlEntity $action): void
	{
		if ($this->httpClient !== null) {
			$this->httpClient->writeDeviceControl($action);
		} elseif ($this->mqttClient !== null) {
			$this->mqttClient->writeDeviceControl($action);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function writeChannelControl(MetadataEntities\Actions\IActionChannelControlEntity $action): void
	{
		if ($this->httpClient !== null) {
			$this->httpClient->writeChannelControl($action);
		} elseif ($this->mqttClient !== null) {
			$this->mqttClient->writeChannelControl($action);
		}
	}

}
