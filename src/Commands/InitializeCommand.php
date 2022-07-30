<?php declare(strict_types = 1);

/**
 * InitializeCommand.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Commands
 * @since          0.37.0
 *
 * @date           30.07.22
 */

namespace FastyBird\ShellyConnector\Commands;

use Doctrine\DBAL;
use Doctrine\Persistence;
use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\DevicesModule\Queries as DevicesModuleQueries;
use FastyBird\Metadata;
use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\ShellyConnector\Entities;
use FastyBird\ShellyConnector\Exceptions;
use FastyBird\ShellyConnector\Types;
use Nette\Utils;
use Psr\Log;
use RuntimeException;
use Symfony\Component\Console;
use Symfony\Component\Console\Input;
use Symfony\Component\Console\Output;
use Symfony\Component\Console\Style;
use Throwable;

/**
 * Connector initialize command
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Commands
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class InitializeCommand extends Console\Command\Command
{

	private const CHOICE_QUESTION_CREATE_CONNECTOR = 'Create new connector configuration';
	private const CHOICE_QUESTION_EDIT_CONNECTOR = 'Edit existing connector configuration';

	private const CHOICE_QUESTION_GEN_1_CONNECTOR = 'Original generation 1 devices (based on ESP8266)';
	private const CHOICE_QUESTION_GEN_2_CONNECTOR = 'New generation 2 devices (based on ESP32)';
	private const CHOICE_QUESTION_CLOUD_CONNECTOR = 'Cloud connected devices';

	/** @var DevicesModuleModels\Connectors\IConnectorsRepository */
	private DevicesModuleModels\Connectors\IConnectorsRepository $connectorsRepository;

	/** @var DevicesModuleModels\Connectors\IConnectorsManager */
	private DevicesModuleModels\Connectors\IConnectorsManager $connectorsManager;

	/** @var DevicesModuleModels\Connectors\Properties\IPropertiesRepository */
	private DevicesModuleModels\Connectors\Properties\IPropertiesRepository $propertiesRepository;

	/** @var DevicesModuleModels\Connectors\Properties\IPropertiesManager */
	private DevicesModuleModels\Connectors\Properties\IPropertiesManager $propertiesManager;

	/** @var DevicesModuleModels\Connectors\Controls\IControlsRepository */
	private DevicesModuleModels\Connectors\Controls\IControlsRepository $controlsRepository;

	/** @var DevicesModuleModels\Connectors\Controls\IControlsManager */
	private DevicesModuleModels\Connectors\Controls\IControlsManager $controlsManager;

	/** @var DevicesModuleModels\DataStorage\IConnectorsRepository */
	private DevicesModuleModels\DataStorage\IConnectorsRepository $connectorsDataStorageRepository;

	/** @var Persistence\ManagerRegistry */
	private Persistence\ManagerRegistry $managerRegistry;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param DevicesModuleModels\Connectors\IConnectorsRepository $connectorsRepository
	 * @param DevicesModuleModels\Connectors\IConnectorsManager $connectorsManager
	 * @param DevicesModuleModels\Connectors\Properties\IPropertiesRepository $propertiesRepository
	 * @param DevicesModuleModels\Connectors\Properties\IPropertiesManager $propertiesManager
	 * @param DevicesModuleModels\Connectors\Controls\IControlsRepository $controlsRepository
	 * @param DevicesModuleModels\Connectors\Controls\IControlsManager $controlsManager
	 * @param DevicesModuleModels\DataStorage\IConnectorsRepository $connectorsDataStorageRepository
	 * @param Persistence\ManagerRegistry $managerRegistry
	 * @param Log\LoggerInterface|null $logger
	 * @param string|null $name
	 */
	public function __construct(
		DevicesModuleModels\Connectors\IConnectorsRepository $connectorsRepository,
		DevicesModuleModels\Connectors\IConnectorsManager $connectorsManager,
		DevicesModuleModels\Connectors\Properties\IPropertiesRepository $propertiesRepository,
		DevicesModuleModels\Connectors\Properties\IPropertiesManager $propertiesManager,
		DevicesModuleModels\Connectors\Controls\IControlsRepository $controlsRepository,
		DevicesModuleModels\Connectors\Controls\IControlsManager $controlsManager,
		DevicesModuleModels\DataStorage\IConnectorsRepository $connectorsDataStorageRepository,
		Persistence\ManagerRegistry $managerRegistry,
		?Log\LoggerInterface $logger = null,
		?string $name = null
	) {
		$this->connectorsRepository = $connectorsRepository;
		$this->connectorsManager = $connectorsManager;
		$this->propertiesRepository = $propertiesRepository;
		$this->propertiesManager = $propertiesManager;
		$this->controlsRepository = $controlsRepository;
		$this->controlsManager = $controlsManager;

		$this->connectorsDataStorageRepository = $connectorsDataStorageRepository;

		$this->managerRegistry = $managerRegistry;

		$this->logger = $logger ?? new Log\NullLogger();

		parent::__construct($name);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function configure(): void
	{
		$this
			->setName('fb:shelly-connector:initialize')
			->setDescription('Shelly connector initialization')
			->setDefinition(
				new Input\InputDefinition([
					new Input\InputOption('no-confirm', null, Input\InputOption::VALUE_NONE, 'Do not ask for any confirmation'),
				])
			);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBAL\Exception
	 */
	protected function execute(Input\InputInterface $input, Output\OutputInterface $output): int
	{
		$io = new Style\SymfonyStyle($input, $output);

		$io->title('FB shelly connector - initialization');

		$io->note('This action will create|update connector configuration.');

		if (!$input->getOption('no-confirm')) {
			$question = new Console\Question\ConfirmationQuestion(
				'Would you like to continue?',
				false
			);

			$continue = $io->askQuestion($question);

			if (!$continue) {
				return Console\Command\Command::SUCCESS;
			}
		}

		$question = new Console\Question\ChoiceQuestion(
			'What would you like to do?',
			[
				0 => self::CHOICE_QUESTION_CREATE_CONNECTOR,
				1 => self::CHOICE_QUESTION_EDIT_CONNECTOR,
			]
		);

		$question->setErrorMessage('Selected answer: "%s" is not valid.');

		$whatToDo = $io->askQuestion($question);

		if ($whatToDo === self::CHOICE_QUESTION_CREATE_CONNECTOR) {
			$this->createNewConfiguration($io);

		} elseif ($whatToDo === self::CHOICE_QUESTION_EDIT_CONNECTOR) {
			$this->editExistingConfiguration($io);
		}

		return Console\Command\Command::SUCCESS;
	}

	/**
	 * @param Style\SymfonyStyle $io
	 *
	 * @return void
	 *
	 * @throws DBAL\Exception
	 */
	private function createNewConfiguration(Style\SymfonyStyle $io): void
	{
		$generation = $this->askGeneration($io);

		$question = new Console\Question\Question('Provide connector identifier');

		$question->setValidator(function ($answer) {
			if ($answer !== null && $this->connectorsDataStorageRepository->findByIdentifier($answer) !== null) {
				throw new RuntimeException('This identifier is already used');
			}

			return $answer;
		});

		$identifier = $io->askQuestion($question);

		if ($identifier === '' || $identifier === null) {
			$identifierPattern = 'shelly-%d';

			for ($i = 1; $i <= 100; $i++) {
				$identifier = sprintf($identifierPattern, $i);

				if ($this->connectorsDataStorageRepository->findByIdentifier($identifier) === null) {
					break;
				}
			}
		}

		if ($identifier === '') {
			$io->error('Connector identifier have to provided');

			return;
		}

		$question = new Console\Question\Question('Provide connector name');

		$name = $io->askQuestion($question);

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			$connector = $this->connectorsManager->create(Utils\ArrayHash::from([
				'entity'     => Entities\ShellyConnectorEntity::class,
				'identifier' => $identifier,
				'name'       => $name === '' ? null : $name,
			]));

			$this->propertiesManager->create(Utils\ArrayHash::from([
				'entity'     => DevicesModuleEntities\Connectors\Properties\StaticProperty::class,
				'identifier' => Types\ConnectorPropertyIdentifierType::IDENTIFIER_CLIENT_VERSION,
				'dataType'   => MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_STRING),
				'value'      => $generation->getValue(),
				'connector'  => $connector,
			]));

			$this->controlsManager->create(Utils\ArrayHash::from([
				'name'      => Types\ConnectorControlNameType::NAME_REBOOT,
				'connector' => $connector,
			]));

			$this->controlsManager->create(Utils\ArrayHash::from([
				'name'      => Types\ConnectorControlNameType::NAME_DISCOVER,
				'connector' => $connector,
			]));

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(sprintf(
				'New connector "%s" was successfully created',
				$connector->getName() ?? $connector->getIdentifier()
			));
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error('An unhandled error occurred', [
				'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type'      => 'initialize-cmd',
				'exception' => [
					'message' => $ex->getMessage(),
					'code'    => $ex->getCode(),
				],
			]);

			$io->error('Something went wrong, connector could not be created. Error was logged.');
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}
	}

	/**
	 * @param Style\SymfonyStyle $io
	 *
	 * @return void
	 *
	 * @throws DBAL\Exception
	 */
	private function editExistingConfiguration(Style\SymfonyStyle $io): void
	{
		$io->newLine();

		$connectors = [];

		foreach ($this->connectorsDataStorageRepository as $connector) {
			if ($connector->getType() !== Entities\ShellyConnectorEntity::CONNECTOR_TYPE) {
				continue;
			}

			$connectors[$connector->getIdentifier()] = $connector->getIdentifier() . ($connector->getName() ? ' [' . $connector->getName() . ']' : '');
		}

		if (count($connectors) === 0) {
			$io->warning('No Shelly connectors registered in system');

			$question = new Console\Question\ConfirmationQuestion(
				'Would you like to create new Shelly connector configuration?',
				false
			);

			$continue = $io->askQuestion($question);

			if ($continue) {
				$this->createNewConfiguration($io);
			}

			return;
		}

		$question = new Console\Question\ChoiceQuestion(
			'Please select connector to configure',
			array_values($connectors)
		);

		$question->setErrorMessage('Selected connector: "%s" is not valid.');

		$connectorIdentifierKey = array_search($io->askQuestion($question), $connectors);

		if ($connectorIdentifierKey === false) {
			$io->error('Something went wrong, connector could not be loaded');

			$this->logger->alert('Connector identifier was not able to get from answer', [
				'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type'   => 'initialize-cmd',
			]);

			return;
		}

		$findConnectorQuery = new DevicesModuleQueries\FindConnectorsQuery();
		$findConnectorQuery->byIdentifier($connectorIdentifierKey);

		$connector = $this->connectorsRepository->findOneBy($findConnectorQuery);

		if ($connector === null) {
			$io->error('Something went wrong, connector could not be loaded');

			$this->logger->alert('Connector was not found', [
				'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type'   => 'initialize-cmd',
			]);

			return;
		}

		$findPropertyQuery = new DevicesModuleQueries\FindConnectorPropertiesQuery();
		$findPropertyQuery->forConnector($connector);
		$findPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifierType::IDENTIFIER_CLIENT_VERSION);

		$versionProperty = $this->propertiesRepository->findOneBy($findPropertyQuery);

		if ($versionProperty === null) {
			$changeGeneration = true;

		} else {
			$question = new Console\Question\ConfirmationQuestion(
				'Do you want to change connector devices support?',
				false
			);

			$changeGeneration = $io->askQuestion($question);
		}

		$generation = null;

		if ($changeGeneration) {
			$generation = $this->askGeneration($io);
		}

		$question = new Console\Question\Question('Provide connector name', $connector->getName());

		$name = $io->askQuestion($question);

		$enabled = $connector->isEnabled();

		if ($connector->isEnabled()) {
			$question = new Console\Question\ConfirmationQuestion(
				'Do you want to disable connector?',
				false
			);

			if ($io->askQuestion($question)) {
				$enabled = false;
			}
		} else {
			$question = new Console\Question\ConfirmationQuestion(
				'Do you want to enable connector?',
				false
			);

			if ($io->askQuestion($question)) {
				$enabled = true;
			}
		}

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			$connector = $this->connectorsManager->update($connector, Utils\ArrayHash::from([
				'name'    => $name === '' ? null : $name,
				'enabled' => $enabled,
			]));

			if ($versionProperty === null) {
				if ($generation === null) {
					$generation = $this->askGeneration($io);
				}

				$this->propertiesManager->create(Utils\ArrayHash::from([
					'entity'     => DevicesModuleEntities\Connectors\Properties\StaticProperty::class,
					'identifier' => Types\ConnectorPropertyIdentifierType::IDENTIFIER_CLIENT_VERSION,
					'dataType'   => MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_STRING),
					'value'      => $generation->getValue(),
					'connector'  => $connector,
				]));
			} elseif ($generation !== null) {
				$this->propertiesManager->update($versionProperty, Utils\ArrayHash::from([
					'value' => $generation->getValue(),
				]));
			}

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(sprintf(
				'Connector "%s" was successfully updated',
				$connector->getName() ?? $connector->getIdentifier()
			));
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error('An unhandled error occurred', [
				'source'    => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
				'type'      => 'initialize-cmd',
				'exception' => [
					'message' => $ex->getMessage(),
					'code'    => $ex->getCode(),
				],
			]);

			$io->error('Something went wrong, connector could not be created. Error was logged.');
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}
	}

	/**
	 * @param Style\SymfonyStyle $io
	 *
	 * @return Types\ClientVersionType
	 */
	private function askGeneration(Style\SymfonyStyle $io): Types\ClientVersionType
	{
		$question = new Console\Question\ChoiceQuestion(
			'What generation of Shelly devices should this connector handle?',
			[
				self::CHOICE_QUESTION_GEN_1_CONNECTOR,
				self::CHOICE_QUESTION_GEN_2_CONNECTOR,
				self::CHOICE_QUESTION_CLOUD_CONNECTOR,
			],
			0
		);

		$question->setErrorMessage('Selected answer: "%s" is not valid.');

		$generation = $io->askQuestion($question);

		if ($generation === self::CHOICE_QUESTION_GEN_1_CONNECTOR) {
			return Types\ClientVersionType::get(Types\ClientVersionType::TYPE_GEN_1);
		}

		if ($generation === self::CHOICE_QUESTION_GEN_2_CONNECTOR) {
			return Types\ClientVersionType::get(Types\ClientVersionType::TYPE_GEN_2);
		}

		if ($generation === self::CHOICE_QUESTION_CLOUD_CONNECTOR) {
			return Types\ClientVersionType::get(Types\ClientVersionType::TYPE_CLOUD);
		}

		throw new Exceptions\InvalidStateException('Unknown connector version selected');
	}

	/**
	 * @return DBAL\Connection
	 */
	private function getOrmConnection(): DBAL\Connection
	{
		$connection = $this->managerRegistry->getConnection();

		if ($connection instanceof DBAL\Connection) {
			return $connection;
		}

		throw new Exceptions\RuntimeException('Entity manager could not be loaded');
	}

}
