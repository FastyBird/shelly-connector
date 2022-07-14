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
use FastyBird\ShellyConnector\Clients;
use FastyBird\ShellyConnector\Connector;
use FastyBird\ShellyConnector\Hydrators;
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

		// Connector
		$builder->addDefinition($this->prefix('connector.factory'), new DI\Definitions\ServiceDefinition())
			->setFactory(Connector\ConnectorFactory::class);

		// Clients
		$builder->addFactoryDefinition($this->prefix('client.coap'))
			->setImplement(Clients\CoapClientFactory::class)
			->getResultDefinition()
			->setType(Clients\CoapClient::class);

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
