<?php declare(strict_types = 1);

/**
 * Exchange.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Writers
 * @since          1.0.0
 *
 * @date           14.12.22
 */

namespace FastyBird\Connector\Shelly\Writers;

use DateTimeInterface;
use FastyBird\Connector\Shelly\Clients;
use FastyBird\Connector\Shelly\Entities;
use FastyBird\Connector\Shelly\Helpers;
use FastyBird\DateTimeFactory;
use FastyBird\Library\Exchange\Consumers as ExchangeConsumers;
use FastyBird\Library\Metadata\Entities as MetadataEntities;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use FastyBird\Module\Devices\States as DevicesStates;
use Nette;
use Nette\Utils;
use Psr\Log;
use Throwable;
use function assert;

/**
 * Exchange based properties writer
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Writers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Exchange implements Writer, ExchangeConsumers\Consumer
{

	use Nette\SmartObject;

	public const NAME = 'exchange';

	private Entities\ShellyConnector|null $connector = null;

	private Clients\Client|null $client = null;

	private Log\LoggerInterface $logger;

	public function __construct(
		private readonly Helpers\Property $propertyStateHelper,
		private readonly DateTimeFactory\Factory $dateTimeFactory,
		private readonly DevicesModels\Channels\Properties\PropertiesRepository $propertiesRepository,
		private readonly ExchangeConsumers\Container $consumer,
		Log\LoggerInterface|null $logger = null,
	)
	{
		$this->logger = $logger ?? new Log\NullLogger();
	}

	public function connect(
		Entities\ShellyConnector $connector,
		Clients\Client $client,
	): void
	{
		$this->connector = $connector;
		$this->client = $client;

		$this->consumer->enable(self::class);
	}

	public function disconnect(): void
	{
		$this->consumer->disable(self::class);
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 */
	public function consume(
		MetadataTypes\ModuleSource|MetadataTypes\PluginSource|MetadataTypes\ConnectorSource|MetadataTypes\AutomatorSource $source,
		MetadataTypes\RoutingKey $routingKey,
		MetadataEntities\Entity|null $entity,
	): void
	{
		assert($this->connector instanceof Entities\ShellyConnector);

		if ($entity instanceof MetadataEntities\DevicesModule\ChannelDynamicProperty) {
			$findPropertyQuery = new DevicesQueries\FindChannelProperties();
			$findPropertyQuery->byId($entity->getId());

			$property = $this->propertiesRepository->findOneBy($findPropertyQuery);

			if ($property === null) {
				return;
			}

			assert($property instanceof DevicesEntities\Channels\Properties\Dynamic);

			if (!$property->getChannel()->getDevice()->getConnector()->getId()->equals($this->connector->getId())) {
				return;
			}

			$device = $property->getChannel()->getDevice();
			$channel = $property->getChannel();

			assert($device instanceof Entities\ShellyDevice);

			$this->client?->writeChannelProperty($device, $channel, $property)
				->then(function () use ($property): void {
					$this->propertyStateHelper->setValue(
						$property,
						Utils\ArrayHash::from([
							DevicesStates\Property::PENDING_KEY => $this->dateTimeFactory->getNow()->format(
								DateTimeInterface::ATOM,
							),
						]),
					);
				})
				->otherwise(function (Throwable $ex) use ($device, $channel, $property): void {
					$this->logger->error(
						'Could write new property state',
						[
							'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_SHELLY,
							'type' => 'exchange-writer',
							'group' => 'writer',
							'exception' => [
								'message' => $ex->getMessage(),
								'code' => $ex->getCode(),
							],
							'connector' => [
								'id' => $this->connector?->getPlainId(),
							],
							'device' => [
								'id' => $device->getPlainId(),
							],
							'channel' => [
								'id' => $channel->getPlainId(),
							],
							'property' => [
								'id' => $property->getPlainId(),
							],
						],
					);

					$this->propertyStateHelper->setValue(
						$property,
						Utils\ArrayHash::from([
							DevicesStates\Property::EXPECTED_VALUE_KEY => null,
							DevicesStates\Property::PENDING_KEY => false,
						]),
					);
				});
		}
	}

}
