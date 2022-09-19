<?php declare(strict_types = 1);

/**
 * Connector.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Connector
 * @since          0.37.0
 *
 * @date           14.07.22
 */

namespace FastyBird\ShellyConnector\Connector;

use FastyBird\DevicesModule\Connectors as DevicesModuleConnectors;
use FastyBird\DevicesModule\Exceptions as DevicesModuleExceptions;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\ShellyConnector\Clients;
use FastyBird\ShellyConnector\Consumers;
use FastyBird\ShellyConnector\Helpers;
use FastyBird\ShellyConnector\Types;
use Nette;
use React\EventLoop;
use ReflectionClass;
use function React\Async\async;

/**
 * Connector service executor
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Connector
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Connector implements DevicesModuleConnectors\IConnector
{

	use Nette\SmartObject;

	private const QUEUE_PROCESSING_INTERVAL = 0.01;

	/** @var MetadataEntities\Modules\DevicesModule\IConnectorEntity */
	private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector;

	/** @var Clients\Client|null */
	private ?Clients\Client $client = null;

	/** @var Clients\ClientFactory[] */
	private array $clientsFactories;

	/** @var EventLoop\TimerInterface|null */
	private ?EventLoop\TimerInterface $consumerTimer;

	/** @var Helpers\Connector */
	private Helpers\Connector $connectorHelper;

	/** @var Consumers\Messages */
	private Consumers\Messages $consumer;

	/** @var EventLoop\LoopInterface */
	private EventLoop\LoopInterface $eventLoop;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param Clients\ClientFactory[] $clientsFactories
	 * @param Helpers\Connector $connectorHelper
	 * @param Consumers\Messages $consumer
	 * @param EventLoop\LoopInterface $eventLoop
	 */
	public function __construct(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		array $clientsFactories,
		Helpers\Connector $connectorHelper,
		Consumers\Messages $consumer,
		EventLoop\LoopInterface $eventLoop
	) {
		$this->connector = $connector;

		$this->clientsFactories = $clientsFactories;

		$this->connectorHelper = $connectorHelper;
		$this->consumer = $consumer;

		$this->eventLoop = $eventLoop;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DevicesModuleExceptions\TerminateException
	 */
	public function execute(): void
	{
		$version = $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_CLIENT_VERSION)
		);

		if ($version === null) {
			throw new DevicesModuleExceptions\TerminateException('Connector client version is not configured');
		}

		foreach ($this->clientsFactories as $clientFactory) {
			$rc = new ReflectionClass($clientFactory);

			$constants = $rc->getConstants();

			if (
				array_key_exists(Clients\ClientFactory::VERSION_CONSTANT_NAME, $constants)
				&& $constants[Clients\ClientFactory::VERSION_CONSTANT_NAME] === $version
			) {
				$this->client = $clientFactory->create($this->connector);
			}
		}

		if ($this->client === null) {
			throw new DevicesModuleExceptions\TerminateException('Connector client is not configured');
		}

		$this->client->connect();

		$this->consumerTimer = $this->eventLoop->addPeriodicTimer(
			self::QUEUE_PROCESSING_INTERVAL,
			async(function (): void {
				$this->consumer->consume();
			})
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function terminate(): void
	{
		$this->client?->disconnect();

		if ($this->consumerTimer !== null) {
			$this->eventLoop->cancelTimer($this->consumerTimer);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function hasUnfinishedTasks(): bool
	{
		return !$this->consumer->isEmpty() && $this->consumerTimer !== null;
	}

}
