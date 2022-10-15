<?php declare(strict_types = 1);

/**
 * Discovery.php
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

use DateTimeInterface;
use FastyBird\DateTimeFactory;
use FastyBird\DevicesModule\Exceptions as DevicesModuleExceptions;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\DevicesModule\Queries as DevicesModuleQueries;
use FastyBird\Metadata;
use FastyBird\ShellyConnector\Clients;
use FastyBird\ShellyConnector\Consumers;
use FastyBird\ShellyConnector\Entities;
use FastyBird\ShellyConnector\Helpers;
use FastyBird\ShellyConnector\Types;
use Psr\Log;
use Ramsey\Uuid;
use React\EventLoop;
use ReflectionClass;
use Symfony\Component\Console;
use Symfony\Component\Console\Input;
use Symfony\Component\Console\Output;
use Symfony\Component\Console\Style;
use Throwable;
use function array_key_exists;
use function array_key_first;
use function array_search;
use function array_values;
use function count;
use function intval;
use function is_string;
use function React\Async\async;
use function sprintf;
use const SIGINT;

/**
 * Connector devices discovery command
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Commands
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Discovery extends Console\Command\Command
{

	private const DISCOVERY_WAITING_INTERVAL = 5.0;

	private const DISCOVERY_MAX_PROCESSING_INTERVAL = 30.0;

	private const QUEUE_PROCESSING_INTERVAL = 0.01;

	private DateTimeInterface|null $executedTime = null;

	private EventLoop\TimerInterface|null $consumerTimer;

	private EventLoop\TimerInterface|null $progressBarTimer;

	private Log\LoggerInterface $logger;

	/**
	 * @param Array<Clients\ClientFactory> $clientsFactories
	 */
	public function __construct(
		private readonly array $clientsFactories,
		private readonly Helpers\Connector $connectorHelper,
		private readonly Helpers\Device $deviceHelper,
		private readonly Consumers\Messages $consumer,
		private readonly DevicesModuleModels\DataStorage\ConnectorsRepository $connectorsRepository,
		private readonly DevicesModuleModels\Devices\DevicesRepository $devicesRepository,
		private readonly DateTimeFactory\Factory $dateTimeFactory,
		private readonly EventLoop\LoopInterface $eventLoop,
		Log\LoggerInterface|null $logger = null,
		string|null $name = null,
	)
	{
		$this->logger = $logger ?? new Log\NullLogger();

		parent::__construct($name);
	}

	/**
	 * @throws Console\Exception\InvalidArgumentException
	 */
	protected function configure(): void
	{
		$this
			->setName('fb:shelly-connector:discover')
			->setDescription('Shelly connector devices discovery')
			->setDefinition(
				new Input\InputDefinition([
					new Input\InputOption(
						'connector',
						'c',
						Input\InputOption::VALUE_OPTIONAL,
						'Run devices module connector',
						true,
					),
					new Input\InputOption(
						'no-confirm',
						null,
						Input\InputOption::VALUE_NONE,
						'Do not ask for any confirmation',
					),
				]),
			);
	}

	/**
	 * @throws Console\Exception\InvalidArgumentException
	 * @throws DevicesModuleExceptions\InvalidState
	 * @throws Metadata\Exceptions\FileNotFound
	 */
	protected function execute(Input\InputInterface $input, Output\OutputInterface $output): int
	{
		$io = new Style\SymfonyStyle($input, $output);

		$io->title('Shelly connector - discovery');

		$io->note('This action will run connector devices discovery.');

		if ($input->getOption('no-confirm') === false) {
			$question = new Console\Question\ConfirmationQuestion(
				'Would you like to continue?',
				false,
			);

			$continue = (bool) $io->askQuestion($question);

			if (!$continue) {
				return Console\Command\Command::SUCCESS;
			}
		}

		if (
			$input->hasOption('connector')
			&& is_string($input->getOption('connector'))
			&& $input->getOption('connector') !== ''
		) {
			$connectorId = $input->getOption('connector');

			$connector = Uuid\Uuid::isValid($connectorId)
				? $this->connectorsRepository->findById(Uuid\Uuid::fromString($connectorId))
				: $this->connectorsRepository->findByIdentifier($connectorId);

			if ($connector === null) {
				$io->warning('Connector was not found in system');

				return Console\Command\Command::FAILURE;
			}
		} else {
			$connectors = [];

			foreach ($this->connectorsRepository as $connector) {
				if ($connector->getType() !== Entities\ShellyConnector::CONNECTOR_TYPE) {
					continue;
				}

				$connectors[$connector->getIdentifier()] = $connector->getIdentifier()
					. ($connector->getName() !== null ? ' [' . $connector->getName() . ']' : '');
			}

			if (count($connectors) === 0) {
				$io->warning('No connectors registered in system');

				return Console\Command\Command::FAILURE;
			}

			if (count($connectors) === 1) {
				$connectorIdentifier = array_key_first($connectors);

				$connector = $this->connectorsRepository->findByIdentifier($connectorIdentifier);

				if ($connector === null) {
					$io->warning('Connector was not found in system');

					return Console\Command\Command::FAILURE;
				}

				if ($input->getOption('no-confirm') === false) {
					$question = new Console\Question\ConfirmationQuestion(
						sprintf(
							'Would you like to discover devices with "%s" connector',
							$connector->getName() ?? $connector->getIdentifier(),
						),
						false,
					);

					if ($io->askQuestion($question) === false) {
						return Console\Command\Command::SUCCESS;
					}
				}
			} else {
				$question = new Console\Question\ChoiceQuestion(
					'Please select connector to execute',
					array_values($connectors),
				);

				$question->setErrorMessage('Selected connector: %s is not valid.');

				$connectorIdentifier = array_search($io->askQuestion($question), $connectors, true);

				if ($connectorIdentifier === false) {
					$io->error('Something went wrong, connector could not be loaded');

					$this->logger->alert(
						'Connector identifier was not able to get from answer',
						[
							'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
							'type' => 'discovery-cmd',
						],
					);

					return Console\Command\Command::FAILURE;
				}

				$connector = $this->connectorsRepository->findByIdentifier($connectorIdentifier);
			}

			if ($connector === null) {
				$io->error('Something went wrong, connector could not be loaded');

				$this->logger->alert(
					'Connector was not found',
					[
						'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type' => 'discovery-cmd',
					],
				);

				return Console\Command\Command::FAILURE;
			}
		}

		if (!$connector->isEnabled()) {
			$io->warning('Connector is disabled. Disabled connector could not be executed');

			return Console\Command\Command::SUCCESS;
		}

		$version = $this->connectorHelper->getConfiguration(
			$connector->getId(),
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_CLIENT_VERSION),
		);

		if ($version === null) {
			$io->error('Connector client version is not configured');

			return Console\Command\Command::FAILURE;
		}

		foreach ($this->clientsFactories as $clientFactory) {
			$rc = new ReflectionClass($clientFactory);

			$constants = $rc->getConstants();

			if (
				array_key_exists(Clients\ClientFactory::VERSION_CONSTANT_NAME, $constants)
				&& $constants[Clients\ClientFactory::VERSION_CONSTANT_NAME] === $version
			) {
				$client = $clientFactory->create($connector);

				$progressBar = new Console\Helper\ProgressBar(
					$output,
					intval(self::DISCOVERY_MAX_PROCESSING_INTERVAL * 60),
				);

				$progressBar->setFormat('[%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %');

				try {
					$this->eventLoop->addSignal(SIGINT, function () use ($client, $io): void {
						$this->logger->info(
							'Stopping Shelly connector discovery...',
							[
								'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
								'type' => 'discovery-cmd',
							],
						);

						$io->info('Stopping Shelly connector discovery...');

						$client->disconnect();

						$this->checkAndTerminate($io);
					});

					$this->eventLoop->futureTick(function () use ($client, $io, $progressBar): void {
						$this->logger->info(
							'Starting Shelly connector discovery...',
							[
								'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
								'type' => 'discovery-cmd',
							],
						);

						$io->info('Starting Shelly connector discovery...');

						$progressBar->start();

						$this->executedTime = $this->dateTimeFactory->getNow();

						$client->discover();
					});

					$this->consumerTimer = $this->eventLoop->addPeriodicTimer(
						self::QUEUE_PROCESSING_INTERVAL,
						async(function (): void {
							$this->consumer->consume();
						}),
					);

					$this->progressBarTimer = $this->eventLoop->addPeriodicTimer(
						0.1,
						async(static function () use ($progressBar): void {
							$progressBar->advance();
						}),
					);

					$this->eventLoop->addTimer(
						self::DISCOVERY_MAX_PROCESSING_INTERVAL,
						async(function () use ($client, $io): void {
							$client->disconnect();

							$this->checkAndTerminate($io);
						}),
					);

					$this->eventLoop->run();

					$progressBar->finish();

					$io->newLine();

					$findDevicesQuery = new DevicesModuleQueries\FindDevices();
					$findDevicesQuery->byConnectorId($connector->getId());

					$devices = $this->devicesRepository->findAllBy($findDevicesQuery);

					$table = new Console\Helper\Table($output);
					$table->setHeaders([
						'#',
						'ID',
						'Name',
						'Type',
						'IP address',
					]);

					$foundDevices = 0;

					foreach ($devices as $device) {
						$createdAt = $device->getCreatedAt();

						if (
							$createdAt !== null
							&& $this->executedTime !== null
							&& $createdAt->getTimestamp() > $this->executedTime->getTimestamp()
						) {
							$foundDevices++;

							$ipAddress = $this->deviceHelper->getConfiguration(
								$device->getId(),
								Types\DevicePropertyIdentifier::get(
									Types\DevicePropertyIdentifier::IDENTIFIER_IP_ADDRESS,
								),
							);

							$hardwareModelAttribute = $device->findAttribute(
								Types\DeviceAttributeIdentifier::IDENTIFIER_MODEL,
							);

							$table->addRow([
								$foundDevices,
								$device->getPlainId(),
								$device->getName() ?? $device->getIdentifier(),
								$hardwareModelAttribute?->getContent(true) ?? 'N/A',
								is_string($ipAddress) ? $ipAddress : 'N/A',
							]);
						}
					}

					if ($foundDevices > 0) {
						$io->newLine();

						$io->info(sprintf('Found %d new devices', $foundDevices));

						$table->render();

						$io->newLine();

					} else {
						$io->info('No devices were found');
					}

					$io->success('Devices discovery was successfully finished');

					return Console\Command\Command::SUCCESS;
				} catch (DevicesModuleExceptions\Terminate $ex) {
					$this->logger->error(
						'An error occurred',
						[
							'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
							'type' => 'discovery-cmd',
							'exception' => [
								'message' => $ex->getMessage(),
								'code' => $ex->getCode(),
							],
						],
					);

					$io->error('Something went wrong, discovery could not be finished. Error was logged.');

					$client->disconnect();

					$this->eventLoop->stop();

					return Console\Command\Command::FAILURE;
				} catch (Throwable $ex) {
					$this->logger->error(
						'An unhandled error occurred',
						[
							'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
							'type' => 'discovery-cmd',
							'exception' => [
								'message' => $ex->getMessage(),
								'code' => $ex->getCode(),
							],
						],
					);

					$io->error('Something went wrong, discovery could not be finished. Error was logged.');

					$client->disconnect();

					$this->eventLoop->stop();

					return Console\Command\Command::FAILURE;
				}
			}
		}

		$io->error('Connector client is not configured');

		return Console\Command\Command::FAILURE;
	}

	private function checkAndTerminate(Style\SymfonyStyle $io): void
	{
		if ($this->consumer->isEmpty()) {
			if ($this->consumerTimer !== null) {
				$this->eventLoop->cancelTimer($this->consumerTimer);
			}

			if ($this->progressBarTimer !== null) {
				$this->eventLoop->cancelTimer($this->progressBarTimer);
			}

			$this->eventLoop->stop();

		} else {
			if (
				$this->executedTime !== null
				&& $this->dateTimeFactory->getNow()->getTimestamp() - $this->executedTime->getTimestamp() > self::DISCOVERY_MAX_PROCESSING_INTERVAL
			) {
				$this->logger->error(
					'Discovery exceeded reserved time and have been terminated',
					[
						'source' => Metadata\Constants::CONNECTOR_SHELLY_SOURCE,
						'type' => 'discovery-cmd',
					],
				);

				if ($this->consumerTimer !== null) {
					$this->eventLoop->cancelTimer($this->consumerTimer);
				}

				if ($this->progressBarTimer !== null) {
					$this->eventLoop->cancelTimer($this->progressBarTimer);
				}

				$this->eventLoop->stop();

				return;
			}

			$this->eventLoop->addTimer(
				self::DISCOVERY_WAITING_INTERVAL,
				async(function () use ($io): void {
					$this->checkAndTerminate($io);
				}),
			);
		}
	}

}
