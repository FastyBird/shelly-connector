<?php declare(strict_types = 1);

/**
 * ShellyConnectorExtension.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     DI
 * @since          0.1.0
 *
 * @date           22.01.22
 */

namespace FastyBird\ShellyConnector\DI;

use Doctrine\Persistence;
use FastyBird\ShellyConnector;
use FastyBird\ShellyConnector\API;
use FastyBird\ShellyConnector\Clients;
use FastyBird\ShellyConnector\Commands;
use FastyBird\ShellyConnector\Connector;
use FastyBird\ShellyConnector\Consumers;
use FastyBird\ShellyConnector\Helpers;
use FastyBird\ShellyConnector\Hydrators;
use FastyBird\ShellyConnector\Mappers;
use FastyBird\ShellyConnector\Schemas;
use Nette;
use Nette\DI;
use Nette\Schema;
use React\EventLoop;
use stdClass;

/**
 * Shelly connector
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     DI
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ShellyConnectorExtension extends DI\CompilerExtension
{

	/**
	 * @param Nette\Configurator $config
	 * @param string $extensionName
	 *
	 * @return void
	 */
	public static function register(
		Nette\Configurator $config,
		string $extensionName = 'fbShellyConnector'
	): void {
		$config->onCompile[] = function (
			Nette\Configurator $config,
			DI\Compiler $compiler
		) use ($extensionName): void {
			$compiler->addExtension($extensionName, new ShellyConnectorExtension());
		};
	}

	/**
	 * {@inheritDoc}
	 */
	public function getConfigSchema(): Schema\Schema
	{
		return Schema\Expect::structure([
			'loop' => Schema\Expect::anyOf(Schema\Expect::string(), Schema\Expect::type(DI\Definitions\Statement::class))
				->nullable(),
		]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		/** @var stdClass $configuration */
		$configuration = $this->getConfig();

		if ($configuration->loop === null && $builder->getByType(EventLoop\LoopInterface::class) === null) {
			$builder->addDefinition($this->prefix('client.loop'), new DI\Definitions\ServiceDefinition())
				->setType(EventLoop\LoopInterface::class)
				->setFactory('React\EventLoop\Factory::create');
		}

		// Service factory
		$builder->addDefinition($this->prefix('service.factory'), new DI\Definitions\ServiceDefinition())
			->setType(ShellyConnector\ConnectorFactory::class);

		// Connector
		$builder->addFactoryDefinition($this->prefix('connector'))
			->setImplement(Connector\ConnectorFactory::class)
			->getResultDefinition()
			->setType(Connector\Connector::class);

		// Clients
		$builder->addFactoryDefinition($this->prefix('client.gen1'))
			->setImplement(Clients\Gen1ClientFactory::class)
			->getResultDefinition()
			->setType(Clients\Gen1Client::class);

		$builder->addFactoryDefinition($this->prefix('client.gen1.coap'))
			->setImplement(Clients\Gen1\CoapClientFactory::class)
			->getResultDefinition()
			->setType(Clients\Gen1\CoapClient::class);

		$builder->addFactoryDefinition($this->prefix('client.gen1.mdns'))
			->setImplement(Clients\Gen1\MdnsClientFactory::class)
			->getResultDefinition()
			->setType(Clients\Gen1\MdnsClient::class);

		$builder->addFactoryDefinition($this->prefix('client.gen1.http'))
			->setImplement(Clients\Gen1\HttpClientFactory::class)
			->getResultDefinition()
			->setType(Clients\Gen1\HttpClient::class);

		$builder->addFactoryDefinition($this->prefix('client.gen1.mqtt'))
			->setImplement(Clients\Gen1\MqttClientFactory::class)
			->getResultDefinition()
			->setType(Clients\Gen1\MqttClient::class);

		// Messages API
		$builder->addDefinition($this->prefix('api.gen1parser'), new DI\Definitions\ServiceDefinition())
			->setType(API\Gen1Parser::class);

		$builder->addDefinition($this->prefix('api.gen1validator'), new DI\Definitions\ServiceDefinition())
			->setType(API\Gen1Validator::class);

		$builder->addDefinition($this->prefix('api.gen1transformer'), new DI\Definitions\ServiceDefinition())
			->setType(API\Gen1Transformer::class);

		// Consumers
		$builder->addDefinition($this->prefix('consumer.proxy'), new DI\Definitions\ServiceDefinition())
			->setType(Consumers\Consumer::class);

		$builder->addDefinition($this->prefix('consumer.device.description.message'), new DI\Definitions\ServiceDefinition())
			->setType(Consumers\DescriptionMessageConsumer::class);

		$builder->addDefinition($this->prefix('consumer.device.status.message'), new DI\Definitions\ServiceDefinition())
			->setType(Consumers\StatusMessageConsumer::class);

		$builder->addDefinition($this->prefix('consumer.device.info.message'), new DI\Definitions\ServiceDefinition())
			->setType(Consumers\InfoMessageConsumer::class);

		$builder->addDefinition($this->prefix('consumer.device.discovery.message'), new DI\Definitions\ServiceDefinition())
			->setType(Consumers\DiscoveryMessageConsumer::class);

		// API schemas
		$builder->addDefinition($this->prefix('schemas.connector.shelly'), new DI\Definitions\ServiceDefinition())
			->setType(Schemas\ShellyConnectorSchema::class);

		$builder->addDefinition($this->prefix('schemas.device.shelly'), new DI\Definitions\ServiceDefinition())
			->setType(Schemas\ShellyDeviceSchema::class);

		// API hydrators
		$builder->addDefinition($this->prefix('hydrators.connector.shelly'), new DI\Definitions\ServiceDefinition())
			->setType(Hydrators\ShellyConnectorHydrator::class);

		$builder->addDefinition($this->prefix('hydrators.device.shelly'), new DI\Definitions\ServiceDefinition())
			->setType(Hydrators\ShellyDeviceHydrator::class);

		// Helpers
		$builder->addDefinition($this->prefix('helpers.database'), new DI\Definitions\ServiceDefinition())
			->setType(Helpers\DatabaseHelper::class);

		// Mappers
		$builder->addDefinition($this->prefix('mappers.sensor'), new DI\Definitions\ServiceDefinition())
			->setType(Mappers\SensorMapper::class);

		// Console commands
		$builder->addDefinition($this->prefix('commands.initialize'), new DI\Definitions\ServiceDefinition())
			->setType(Commands\InitializeCommand::class);

		$builder->addDefinition($this->prefix('commands.discovery'), new DI\Definitions\ServiceDefinition())
			->setType(Commands\DiscoveryCommand::class);

		$builder->addDefinition($this->prefix('commands.execute'), new DI\Definitions\ServiceDefinition())
			->setType(Commands\ExecuteCommand::class);
	}

	/**
	 * {@inheritDoc}
	 */
	public function beforeCompile(): void
	{
		parent::beforeCompile();

		$builder = $this->getContainerBuilder();

		/**
		 * Doctrine entities
		 */

		$ormAnnotationDriverService = $builder->getDefinition('nettrineOrmAnnotations.annotationDriver');

		if ($ormAnnotationDriverService instanceof DI\Definitions\ServiceDefinition) {
			$ormAnnotationDriverService->addSetup('addPaths', [[__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Entities']]);
		}

		$ormAnnotationDriverChainService = $builder->getDefinitionByType(Persistence\Mapping\Driver\MappingDriverChain::class);

		if ($ormAnnotationDriverChainService instanceof DI\Definitions\ServiceDefinition) {
			$ormAnnotationDriverChainService->addSetup('addDriver', [
				$ormAnnotationDriverService,
				'FastyBird\ShellyConnector\Entities',
			]);
		}
	}

}
