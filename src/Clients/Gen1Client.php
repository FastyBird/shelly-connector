<?php declare(strict_types = 1);

/**
 * Gen1Client.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnectorEntity!
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
use Psr\Log;
use Throwable;

/**
 * Generation 1 devices client
 *
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Gen1Client extends Client
{

	/** @var Clients\Gen1\CoapClient */
	private Clients\Gen1\CoapClient $coapClient;

	/** @var Clients\Gen1\MdnsClient */
	private Clients\Gen1\MdnsClient $mdnsClient;

	/** @var Clients\Gen1\HttpClient */
	private Clients\Gen1\HttpClient $httpClient;

	/** @var Gen1\MqttClient */
	private Clients\Gen1\MqttClient $mqttClient;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param Gen1\CoapClientFactory $coapClientFactory
	 * @param Gen1\MdnsClientFactory $mdnsClientFactory
	 * @param Gen1\HttpClientFactory $httpClientFactory
	 * @param Clients\Gen1\MqttClientFactory $mqttClientFactory
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		Clients\Gen1\CoapClientFactory $coapClientFactory,
		Clients\Gen1\MdnsClientFactory $mdnsClientFactory,
		Clients\Gen1\HttpClientFactory $httpClientFactory,
		Clients\Gen1\MqttClientFactory $mqttClientFactory,
		?Log\LoggerInterface $logger = null
	) {
		$this->coapClient = $coapClientFactory->create();
		$this->mdnsClient = $mdnsClientFactory->create();
		$this->httpClient = $httpClientFactory->create($connector);
		$this->mqttClient = $mqttClientFactory->create($connector);

		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DevicesModuleExceptions\TerminateException
	 */
	public function connect(): void
	{
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
	}

	/**
	 * {@inheritDoc}
	 */
	public function disconnect(): void
	{
		try {
			$this->coapClient->disconnect();
		} catch (Throwable) {
			$this->logger->error('CoAP client could not be disconnected', [
				'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type'   => 'gen1-client',
			]);
		}

		try {
			$this->mdnsClient->disconnect();
		} catch (Throwable) {
			$this->logger->error('mDNS client could not be disconnected', [
				'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type'   => 'gen1-client',
			]);
		}

		try {
			$this->httpClient->disconnect();
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
		return $this->coapClient->isConnected() || $this->mdnsClient->isConnected() || $this->httpClient->isConnected();
	}

	/**
	 * {@inheritDoc}
	 */
	public function writeDeviceControl(MetadataEntities\Actions\IActionDeviceControlEntity $action): void
	{
		$this->httpClient->writeDeviceControl($action);
	}

	/**
	 * {@inheritDoc}
	 */
	public function writeChannelControl(MetadataEntities\Actions\IActionChannelControlEntity $action): void
	{
		$this->httpClient->writeChannelControl($action);
	}

}
