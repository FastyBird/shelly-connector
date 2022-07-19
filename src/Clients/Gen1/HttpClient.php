<?php declare(strict_types = 1);

/**
 * HttpClient.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Clients
 * @since          0.37.0
 *
 * @date           18.07.22
 */

namespace FastyBird\ShellyConnector\Clients\Gen1;

use FastyBird\DevicesModule\Exceptions as DevicesModuleExceptions;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\Metadata\Types\DevicePropertyNameType;
use FastyBird\ShellyConnector\Exceptions;
use FastyBird\ShellyConnector\Types\WritableSensorTypeType;
use Nette\Utils;
use Psr\Log;
use React\EventLoop;
use React\Http;

/**
 * HTTP api client
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class HttpClient
{

	private const SHELLY_INFO_ENDPOINT = 'http://{address}/shelly';
	private const STATUS_ENDPOINT = 'http://{address}/status';
	private const SETTINGS_ENDPOINT = 'http://{address}/settings';
	private const DESCRIPTION_ENDPOINT = 'http://{address}/cit/d';
	private const SET_CHANNEL_SENSOR_ENDPOINT = 'http://{address}/{channel}/{index}?{action}={value}';

	private const CHANNEL_BLOCK = '/^(?P<identifier>[0-9]+)_(?P<description>[a-zA-Z0-9_]+)$/';
	private const PROPERTY_SENSOR = '/^(?P<identifier>[0-9]+)_(?P<type>[a-zA-Z]{1,3})_(?P<description>[a-zA-Z0-9]+)$/';

	private const BLOCK_TEST = '/^(?P<channelName>[a-zA-Z]+)_(?P<channelIndex>[0-9_]+)$/';

	/** @var Http\Browser|null */
	private ?Http\Browser $browser = null;

	/** @var DevicesModuleModels\DataStorage\IDevicePropertiesRepository */
	private DevicesModuleModels\DataStorage\IDevicePropertiesRepository $devicePropertiesRepository;

	/** @var EventLoop\LoopInterface */
	private EventLoop\LoopInterface $eventLoop;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	public function __construct(
		DevicesModuleModels\DataStorage\IDevicePropertiesRepository $devicePropertiesRepository,
		EventLoop\LoopInterface $eventLoop,
		?Log\LoggerInterface $logger = null
	) {
		$this->devicePropertiesRepository = $devicePropertiesRepository;

		$this->eventLoop = $eventLoop;

		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * @return bool
	 */
	public function isConnected(): bool
	{
		return $this->browser !== null;
	}

	/**
	 * @return void
	 */
	public function connect(): void
	{
		$this->browser = new Http\Browser($this->eventLoop);
	}

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IDeviceEntity $device
	 * @param MetadataEntities\Modules\DevicesModule\IChannelEntity $channel
	 * @param MetadataEntities\Modules\DevicesModule\IChannelDynamicPropertyEntity|MetadataEntities\Modules\DevicesModule\IChannelMappedPropertyEntity $property
	 * @param float|bool|int|string|null $valueToWrite
	 * @param callable $successCallback
	 * @param callable $errorCallback
	 *
	 * @return void
	 *
	 * @throws DevicesModuleExceptions\TerminateException
	 */
	public function writeSensor(
		MetadataEntities\Modules\DevicesModule\IDeviceEntity $device,
		MetadataEntities\Modules\DevicesModule\IChannelEntity $channel,
		MetadataEntities\Modules\DevicesModule\IChannelDynamicPropertyEntity|MetadataEntities\Modules\DevicesModule\IChannelMappedPropertyEntity $property,
		float|bool|int|string|null $valueToWrite,
		callable $successCallback,
		callable $errorCallback
	): void {
		if ($this->browser === null) {
			$this->connect();
		}

		if ($this->browser === null) {
			throw new DevicesModuleExceptions\TerminateException('HTTP client could not be established');
		}

		try {
			$address = $this->buildDeviceAddress($device);

		} catch (Exceptions\InvalidStateException $ex) {
			$this->logger->error('Device IP address could not be determined', [
				'source' => 'shelly-connector',
				'type'   => 'http-client',
				'device' => [
					'id' => $device->getId()->toString(),
				],
				'channel' => [
					'id' => $channel->getId()->toString(),
				],
				'property' => [
					'id' => $property->getId()->toString(),
				],
				'exception' => [
					'message' => $ex->getMessage(),
					'code'    => $ex->getCode(),
				],
			]);

			return;
		}

		if (
			preg_match(self::CHANNEL_BLOCK, $channel->getIdentifier(), $channelMatches) !== 1
			|| array_key_exists('identifier', $channelMatches)
			|| array_key_exists('description', $channelMatches)
		) {
			$this->logger->error('Channel identifier is not in expected format', [
				'source' => 'shelly-connector',
				'type'   => 'http-client',
				'device' => [
					'id' => $device->getId()->toString(),
				],
				'channel' => [
					'id' => $channel->getId()->toString(),
				],
				'property' => [
					'id' => $property->getId()->toString(),
				],
			]);

			return;
		}

		if (
			preg_match(self::BLOCK_TEST, $channelMatches['description'], $blockMatches) !== 1
			|| array_key_exists('channelName', $blockMatches)
			|| array_key_exists('channelIndex', $blockMatches)
		) {
			$this->logger->error('Channel - block description is not in expected format', [
				'source' => 'shelly-connector',
				'type'   => 'http-client',
				'device' => [
					'id' => $device->getId()->toString(),
				],
				'channel' => [
					'id' => $channel->getId()->toString(),
				],
				'property' => [
					'id' => $property->getId()->toString(),
				],
			]);

			return;
		}

		if ($valueToWrite === null) {
			return;
		}

		try {
			$sensorAction = $this->buildSensorAction($property);

		} catch (Exceptions\InvalidStateException $ex) {
			$this->logger->error('Sensor action could not be created', [
				'source' => 'shelly-connector',
				'type'   => 'http-client',
				'device' => [
					'id' => $device->getId()->toString(),
				],
				'channel' => [
					'id' => $channel->getId()->toString(),
				],
				'property' => [
					'id' => $property->getId()->toString(),
				],
				'exception' => [
					'message' => $ex->getMessage(),
					'code'    => $ex->getCode(),
				],
			]);

			return;
		}

		// @phpstan-ignore-next-line
		$this->browser->get(
			Utils\Strings::replace(
				self::SET_CHANNEL_SENSOR_ENDPOINT,
				[
					'{address}' => $address,
					'{channel}' => $blockMatches['channelName'],
					'{index}'   => $blockMatches['channelIndex'],
					'{action}'  => $sensorAction,
					'{value}'   => $valueToWrite,
				]
			)
		)
			->then(function () use ($property, $successCallback): void {
				$successCallback($property);
			})
			->otherwise(function () use ($property, $errorCallback): void {
				$errorCallback($property);
			});
	}

	/**
	 * @param MetadataEntities\Actions\IActionDeviceControlEntity $action
	 *
	 * @return void
	 */
	public function writeDeviceControl(MetadataEntities\Actions\IActionDeviceControlEntity $action): void
	{
		// TODO: Implement writeDeviceControl() method.
	}

	/**
	 * @param MetadataEntities\Actions\IActionChannelControlEntity $action
	 *
	 * @return void
	 */
	public function writeChannelControl(MetadataEntities\Actions\IActionChannelControlEntity $action): void
	{
		// TODO: Implement writeChannelControl() method.
	}

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IDeviceEntity $device
	 *
	 * @return string
	 */
	private function buildDeviceAddress(MetadataEntities\Modules\DevicesModule\IDeviceEntity $device): string
	{
		$ipAddressProperty = $this->devicePropertiesRepository->findByIdentifier(
			$device->getId(),
			DevicePropertyNameType::NAME_IP_ADDRESS,
		);

		if (
			!$ipAddressProperty instanceof MetadataEntities\Modules\DevicesModule\IDeviceStaticPropertyEntity
			|| is_string($ipAddressProperty->getValue())
		) {
			throw new Exceptions\InvalidStateException('Device IP address could not be determined');
		}

		$usernameProperty = $this->devicePropertiesRepository->findByIdentifier(
			$device->getId(),
			'username',
		);

		$username = null;

		if (
			$usernameProperty instanceof MetadataEntities\Modules\DevicesModule\IDeviceStaticPropertyEntity
			&& is_string($usernameProperty->getValue())
		) {
			$username = $usernameProperty->getValue();
		}

		$passwordProperty = $this->devicePropertiesRepository->findByIdentifier(
			$device->getId(),
			'password',
		);

		$password = null;

		if (
			$passwordProperty instanceof MetadataEntities\Modules\DevicesModule\IDeviceStaticPropertyEntity
			&& is_string($passwordProperty->getValue())
		) {
			$password = $passwordProperty->getValue();
		}

		if ($username !== null && $password !== null) {
			return $username . ':' . $password . '@' . $ipAddressProperty->getValue();
		}

		return strval($ipAddressProperty->getValue());
	}

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IChannelDynamicPropertyEntity|MetadataEntities\Modules\DevicesModule\IChannelMappedPropertyEntity $property
	 *
	 * @return string
	 */
	private function buildSensorAction(
		MetadataEntities\Modules\DevicesModule\IChannelDynamicPropertyEntity|MetadataEntities\Modules\DevicesModule\IChannelMappedPropertyEntity $property
	): string {
		if (preg_match(self::PROPERTY_SENSOR, $property->getIdentifier(), $propertyMatches) !== 1) {
			throw new Exceptions\InvalidStateException('Property identifier is not valid');
		}

		if (
			array_key_exists('identifier', $propertyMatches)
			|| array_key_exists('type', $propertyMatches)
			|| array_key_exists('description', $propertyMatches)
		) {
			throw new Exceptions\InvalidStateException('Property identifier is not valid');
		}

		if ($propertyMatches['description'] === WritableSensorTypeType::TYPE_OUTPUT) {
			return 'turn';
		}

		if ($propertyMatches['description'] === WritableSensorTypeType::TYPE_COLOR_TEMP) {
			return 'temp';
		}

		return $propertyMatches['description'];
	}

}
