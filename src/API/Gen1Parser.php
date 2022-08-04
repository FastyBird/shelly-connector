<?php declare(strict_types = 1);

/**
 * Gen1Parser.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     API
 * @since          0.37.0
 *
 * @date           17.07.22
 */

namespace FastyBird\ShellyConnector\API;

use FastyBird\Metadata\Schemas as MetadataSchemas;
use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\ShellyConnector;
use FastyBird\ShellyConnector\Entities;
use FastyBird\ShellyConnector\Exceptions;
use FastyBird\ShellyConnector\Mappers;
use FastyBird\ShellyConnector\Types;
use Nette;
use Nette\Utils;
use Ramsey\Uuid;

/**
 * Generation 1 devices messages parser
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     API
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Gen1Parser
{

	use Nette\SmartObject;

	/** @var Gen1Validator */
	private Gen1Validator $validator;

	/** @var Gen1Transformer */
	private Gen1Transformer $transformer;

	/** @var Mappers\SensorMapper */
	private Mappers\SensorMapper $sensorMapper;

	/** @var MetadataSchemas\IValidator */
	private MetadataSchemas\IValidator $schemaValidator;

	/**
	 * @param Gen1Validator $validator
	 * @param Gen1Transformer $transformer
	 * @param Mappers\SensorMapper $sensorMapper
	 * @param MetadataSchemas\IValidator $schemaValidator
	 */
	public function __construct(
		Gen1Validator $validator,
		Gen1Transformer $transformer,
		Mappers\SensorMapper $sensorMapper,
		MetadataSchemas\IValidator $schemaValidator
	) {
		$this->validator = $validator;
		$this->transformer = $transformer;

		$this->sensorMapper = $sensorMapper;

		$this->schemaValidator = $schemaValidator;
	}

	/**
	 * @param Uuid\UuidInterface $connector
	 * @param string $address
	 * @param string $type
	 * @param string $identifier
	 * @param string $message
	 *
	 * @return Entities\Messages\DeviceDescriptionEntity
	 */
	public function parseCoapDescriptionMessage(
		Uuid\UuidInterface $connector,
		string $address,
		string $type,
		string $identifier,
		string $message
	): Entities\Messages\DeviceDescriptionEntity {
		if (!$this->validator->isValidCoapDescriptionMessage($message)) {
			throw new Exceptions\ParseMessageException('Provided description message is not valid');
		}

		$filePath = ShellyConnector\Constants::RESOURCES_FOLDER . DIRECTORY_SEPARATOR . Gen1Validator::COAP_DESCRIPTION_MESSAGE_SCHEMA_FILENAME;

		try {
			$schema = Utils\FileSystem::read($filePath);

		} catch (Nette\IOException) {
			throw new Exceptions\ParseMessageException('Validation schema for message could not be loaded');
		}

		$parsedMessage = $this->schemaValidator->validate($message, $schema);

		$blocks = $this->extractBlocksDescription(
			Types\MessageSourceType::get(Types\MessageSourceType::SOURCE_GEN_1_COAP),
			$parsedMessage
		);

		return new Entities\Messages\DeviceDescriptionEntity(
			Types\MessageSourceType::get(Types\MessageSourceType::SOURCE_GEN_1_COAP),
			$connector,
			$identifier,
			$type,
			$address,
			$blocks,
		);
	}

	/**
	 * @param Uuid\UuidInterface $connector
	 * @param string $address
	 * @param string $type
	 * @param string $identifier
	 * @param string $message
	 *
	 * @return Entities\Messages\DeviceStatusEntity
	 */
	public function parseCoapStatusMessage(
		Uuid\UuidInterface $connector,
		string $address,
		string $type,
		string $identifier,
		string $message
	): Entities\Messages\DeviceStatusEntity {
		if (!$this->validator->isValidCoapStatusMessage($message)) {
			throw new Exceptions\ParseMessageException('Provided description message is not valid');
		}

		$filePath = ShellyConnector\Constants::RESOURCES_FOLDER . DIRECTORY_SEPARATOR . Gen1Validator::COAP_STATUS_MESSAGE_SCHEMA_FILENAME;

		try {
			$schema = Utils\FileSystem::read($filePath);

		} catch (Nette\IOException) {
			throw new Exceptions\ParseMessageException('Validation schema for message could not be loaded');
		}

		$parsedMessage = $this->schemaValidator->validate($message, $schema);

		if (
			!$parsedMessage->offsetExists('G')
			|| !$parsedMessage['G'] instanceof Utils\ArrayHash
		) {
			throw new Exceptions\ParseMessageException('Provided message is not valid');
		}

		$channels = [];

		foreach ($parsedMessage['G'] as $sensorState) {
			if (is_array($sensorState) && count($sensorState) === 3) {
				[$channel, $sensorIdentifier, $sensorValue] = $sensorState;

				if (!array_key_exists($channel, $channels)) {
					$channels[$channel] = new Entities\Messages\ChannelStatusEntity(
						Types\MessageSourceType::get(Types\MessageSourceType::SOURCE_GEN_1_COAP),
						$channel
					);
				}

				$property = $this->sensorMapper->findProperty(
					$connector,
					strval($identifier),
					intval($sensorIdentifier)
				);

				if ($property !== null) {
					$channels[$channel]->addSensor(
						new Entities\Messages\SensorStatusEntity(
							Types\MessageSourceType::get(Types\MessageSourceType::SOURCE_GEN_1_COAP),
							intval($sensorIdentifier),
							$this->transformer->transformValueFromDevice(
								$property->getDataType(),
								$property->getFormat(),
								strval($sensorValue)
							)
						)
					);
				}
			}
		}

		return new Entities\Messages\DeviceStatusEntity(
			Types\MessageSourceType::get(Types\MessageSourceType::SOURCE_GEN_1_COAP),
			$connector,
			$identifier,
			$type,
			$address,
			array_values($channels)
		);
	}

	/**
	 * @param Uuid\UuidInterface $connector
	 * @param string $identifier
	 * @param string $address
	 * @param string $message
	 *
	 * @return Entities\Messages\DeviceInfoEntity
	 */
	public function parseHttpShellyMessage(
		Uuid\UuidInterface $connector,
		string $identifier,
		string $address,
		string $message
	): Entities\Messages\DeviceInfoEntity {
		if (!$this->validator->isValidHttpShellyMessage($message)) {
			throw new Exceptions\ParseMessageException('Provided description message is not valid');
		}

		$filePath = ShellyConnector\Constants::RESOURCES_FOLDER . DIRECTORY_SEPARATOR . Gen1Validator::HTTP_SHELLY_INFO_MESSAGE_SCHEMA_FILENAME;

		try {
			$schema = Utils\FileSystem::read($filePath);

		} catch (Nette\IOException) {
			throw new Exceptions\ParseMessageException('Validation schema for message could not be loaded');
		}

		$parsedMessage = $this->schemaValidator->validate($message, $schema);

		if (
			!$parsedMessage instanceof Utils\ArrayHash
			|| !$parsedMessage->offsetExists('type')
			|| !$parsedMessage->offsetExists('mac')
			|| !$parsedMessage->offsetExists('auth')
			|| !$parsedMessage->offsetExists('fw')
		) {
			throw new Exceptions\ParseMessageException('Provided message is not valid');
		}

		return new Entities\Messages\DeviceInfoEntity(
			Types\MessageSourceType::get(Types\MessageSourceType::SOURCE_GEN_1_HTTP),
			$connector,
			$identifier,
			$address,
			Utils\Strings::lower(strval($parsedMessage->offsetGet('type'))),
			strval($parsedMessage->offsetGet('mac')),
			boolval($parsedMessage->offsetGet('auth')),
			strval($parsedMessage->offsetGet('fw'))
		);
	}

	/**
	 * @param Uuid\UuidInterface $connector
	 * @param string $identifier
	 * @param string $address
	 * @param string $message
	 *
	 * @return Entities\Messages\DeviceDescriptionEntity
	 */
	public function parseHttpDescriptionMessage(
		Uuid\UuidInterface $connector,
		string $identifier,
		string $address,
		string $message
	): Entities\Messages\DeviceDescriptionEntity {
		if (!$this->validator->isValidHttpDescriptionMessage($message)) {
			throw new Exceptions\ParseMessageException('Provided description message is not valid');
		}

		$filePath = ShellyConnector\Constants::RESOURCES_FOLDER . DIRECTORY_SEPARATOR . Gen1Validator::HTTP_DESCRIPTION_MESSAGE_SCHEMA_FILENAME;

		try {
			$schema = Utils\FileSystem::read($filePath);

		} catch (Nette\IOException) {
			throw new Exceptions\ParseMessageException('Validation schema for message could not be loaded');
		}

		$parsedMessage = $this->schemaValidator->validate($message, $schema);

		$blocks = $this->extractBlocksDescription(
			Types\MessageSourceType::get(Types\MessageSourceType::SOURCE_GEN_1_HTTP),
			$parsedMessage
		);

		return new Entities\Messages\DeviceDescriptionEntity(
			Types\MessageSourceType::get(Types\MessageSourceType::SOURCE_GEN_1_HTTP),
			$connector,
			$identifier,
			null,
			$address,
			$blocks,
		);
	}

	/**
	 * @param Types\MessageSourceType $source
	 * @param Utils\ArrayHash $description
	 *
	 * @return Entities\Messages\BlockDescriptionEntity[]
	 */
	private function extractBlocksDescription(
		Types\MessageSourceType $source,
		Utils\ArrayHash $description
	): array {
		if (!$description->offsetExists('blk') || !$description->offsetExists('sen')) {
			return [];
		}

		$blocks = $description->offsetGet('blk');
		$sensors = $description->offsetGet('sen');

		if (!$blocks instanceof Utils\ArrayHash || !$sensors instanceof Utils\ArrayHash) {
			return [];
		}

		$blocksDescriptions = [];

		foreach ($blocks as $block) {
			if (
				!$block instanceof Utils\ArrayHash
				|| !$block->offsetExists('I')
				|| !$block->offsetExists('D')
			) {
				continue;
			}

			$blockDescription = new Entities\Messages\BlockDescriptionEntity(
				$source,
				intval($block->offsetGet('I')),
				strval($block->offsetGet('D')),
			);

			foreach ($sensors as $sensor) {
				if (
					!$sensor instanceof Utils\ArrayHash
					|| !$sensor->offsetExists('I')
					|| !$sensor->offsetExists('T')
					|| !$sensor->offsetExists('D')
					|| !$sensor->offsetExists('L')
				) {
					continue;
				}

				if (
					($sensor->offsetGet('L') instanceof Utils\ArrayHash && in_array($block->offsetGet('D'), (array) $sensor->offsetGet('L'), true))
					|| intval($block->offsetGet('D')) === intval($sensor->offsetGet('L'))
				) {
					$sensorRange = $this->parseSensorRange(
						$source,
						strval($block->offsetGet('D')),
						strval($sensor->offsetGet('D')),
						$sensor->offsetExists('R') ? (is_array($sensor->offsetGet('R')) || $sensor->offsetGet('R') instanceof Utils\ArrayHash ? (array) $sensor->offsetGet('R') : strval($sensor->offsetGet('R'))) : null
					);

					$sensorDescription = new Entities\Messages\SensorDescriptionEntity(
						$source,
						intval($sensor->offsetGet('I')),
						Types\SensorTypeType::get($sensor->offsetGet('T')),
						strval($sensor->offsetGet('D')),
						$sensorRange->getDataType(),
						$sensor->offsetExists('U') && $sensor->offsetGet('U') !== null ? Types\SensorUnitType::get($sensor->offsetGet('U')) : null,
						$sensorRange->getFormat(),
						$sensorRange->getInvalid(),
						false,
						Types\WritableSensorTypeType::isValidValue($sensor->offsetGet('D'))
					);

					$blockDescription->addSensor($sensorDescription);
				}
			}

			$blocksDescriptions[] = $blockDescription;
		}

		return $blocksDescriptions;
	}

	/**
	 * @param Types\MessageSourceType $source
	 * @param string $block
	 * @param string $description
	 * @param string|string[]|null $rawRange
	 *
	 * @return Entities\Messages\SensorRangeEntity
	 */
    private function parseSensorRange(
		Types\MessageSourceType $source,
		string $block,
		string $description,
        string|array|null $rawRange
    ): Entities\Messages\SensorRangeEntity {
		$invalidValue = null;

		if (is_array($rawRange) && count($rawRange) === 2) {
			$normalValue = $rawRange[0];
			$invalidValue = $rawRange[1] === (string) (int) $rawRange[1] ? intval($rawRange[1]) : ($rawRange[1] === (string) (float) $rawRange[1] ? floatval($rawRange[1]) : $rawRange[1]);

		} elseif (is_string($rawRange)) {
			$normalValue = $rawRange;

		} else {
			return new Entities\Messages\SensorRangeEntity(
				$source,
				$this->adjustSensorDataType(
					$block,
					$description,
					MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_UNKNOWN)
				),
				$this->adjustSensorFormat($block, $description, null),
				null
			);
		}

		if ($normalValue === '0/1') {
			return new Entities\Messages\SensorRangeEntity(
				$source,
				$this->adjustSensorDataType(
					$block,
					$description,
					MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_BOOLEAN)
				),
				$this->adjustSensorFormat($block, $description, null),
				$invalidValue
			);
		}

        if ($normalValue === 'U8') {
			return new Entities\Messages\SensorRangeEntity(
				$source,
				$this->adjustSensorDataType(
					$block,
					$description,
					MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_UCHAR)
				),
				$this->adjustSensorFormat($block, $description, null),
				$invalidValue
			);
		}

        if ($normalValue === 'U16') {
			return new Entities\Messages\SensorRangeEntity(
				$source,
				$this->adjustSensorDataType(
					$block,
					$description,
					MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_USHORT)
				),
				$this->adjustSensorFormat($block, $description, null),
				$invalidValue
			);
		}

        if ($normalValue === 'U32') {
			return new Entities\Messages\SensorRangeEntity(
				$source,
				$this->adjustSensorDataType(
					$block,
					$description,
					MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_UINT)
				),
				$this->adjustSensorFormat($block, $description, null),
				$invalidValue
			);
		}

        if ($normalValue === 'I8') {
			return new Entities\Messages\SensorRangeEntity(
				$source,
				$this->adjustSensorDataType(
					$block,
					$description,
					MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_CHAR)
				),
				$this->adjustSensorFormat($block, $description, null),
				$invalidValue
			);
		}

        if ($normalValue === 'I16') {
			return new Entities\Messages\SensorRangeEntity(
				$source,
				$this->adjustSensorDataType(
					$block,
					$description,
					MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_USHORT)
				),
				$this->adjustSensorFormat($block, $description, null),
				$invalidValue
			);
		}

        if ($normalValue === 'I32') {
			return new Entities\Messages\SensorRangeEntity(
				$source,
				$this->adjustSensorDataType(
					$block,
					$description,
					MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_UINT)
				),
				$this->adjustSensorFormat($block, $description, null),
				$invalidValue
			);
		}

        if (Utils\Strings::contains($normalValue, '/')) {
			$normalValueParts = explode('/', $normalValue);

			if (
				count($normalValueParts) === 2
				&& $normalValueParts[0] === (string) (int) $normalValueParts[0]
				&& $normalValueParts[1] === (string) (int) $normalValueParts[1]
			) {
				return new Entities\Messages\SensorRangeEntity(
					$source,
					$this->adjustSensorDataType(
						$block,
						$description,
						MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_INT)
					),
					$this->adjustSensorFormat(
						$block,
						$description,
						[intval($normalValueParts[0]), intval($normalValueParts[1])]
					),
					$invalidValue
				);
			}

            if (
				count($normalValueParts) === 2
				&& $normalValueParts[0] === (string) (float) $normalValueParts[0]
				&& $normalValueParts[1] === (string) (float) $normalValueParts[1]
			) {
				return new Entities\Messages\SensorRangeEntity(
					$source,
					$this->adjustSensorDataType(
						$block,
						$description,
						MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_FLOAT)
					),
					$this->adjustSensorFormat(
						$block,
						$description,
						[floatval($normalValueParts[0]), floatval($normalValueParts[1])]
					),
					$invalidValue
				);
			}

			return new Entities\Messages\SensorRangeEntity(
				$source,
				$this->adjustSensorDataType(
					$block,
					$description,
					MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_ENUM)
				),
				$this->adjustSensorFormat(
					$block,
					$description,
					array_map(function (string $item): string {
						return Utils\Strings::trim($item);
					}, $normalValueParts)
				),
				$invalidValue
			);
		}

		return new Entities\Messages\SensorRangeEntity(
			$source,
			$this->adjustSensorDataType(
				$block,
				$description,
				MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_UNKNOWN)
			),
			$this->adjustSensorFormat($block, $description, null),
			null,
		);
	}

	/**
	 * @param string $block
	 * @param string $description
	 * @param MetadataTypes\DataTypeType $dataType
	 *
	 * @return MetadataTypes\DataTypeType
	 */
    private function adjustSensorDataType(
		string $block,
		string $description,
		MetadataTypes\DataTypeType $dataType
	): MetadataTypes\DataTypeType {
		if (Utils\Strings::startsWith($block, 'relay') && Utils\Strings::lower($description) === 'output') {
			return MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_SWITCH);
		}

		if (Utils\Strings::startsWith($block, 'light') && Utils\Strings::lower($description) === 'output') {
			return MetadataTypes\DataTypeType::get(MetadataTypes\DataTypeType::DATA_TYPE_SWITCH);
		}

		return $dataType;
	}

	/**
	 * @param string $block
	 * @param string $description
	 * @param string[]|int[]|float[]|null $format
	 *
	 * @return string[]|int[]|float[]|Array<int, Array<int, string|null>>|Array<int, int|null>|Array<int, float|null>|Array<int, MetadataTypes\SwitchPayloadType|string|Types\RelayPayloadType|null>|null
	 */
	private function adjustSensorFormat(
		string $block,
		string $description,
		array|null $format
    ): ?array {
		if (Utils\Strings::startsWith($block, 'relay') && Utils\Strings::lower($description) === 'output') {
			return [
				[MetadataTypes\SwitchPayloadType::PAYLOAD_ON, '1', Types\RelayPayloadType::PAYLOAD_ON],
				[MetadataTypes\SwitchPayloadType::PAYLOAD_OFF, '0', Types\RelayPayloadType::PAYLOAD_OFF],
				[MetadataTypes\SwitchPayloadType::PAYLOAD_TOGGLE, null, Types\RelayPayloadType::PAYLOAD_TOGGLE],
			];
		}

		if (Utils\Strings::startsWith($block, 'light') && Utils\Strings::lower($description) === 'output') {
			return [
				[MetadataTypes\SwitchPayloadType::PAYLOAD_ON, '1', Types\LightSwitchPayloadType::PAYLOAD_ON],
				[MetadataTypes\SwitchPayloadType::PAYLOAD_OFF, '0', Types\LightSwitchPayloadType::PAYLOAD_OFF],
				[MetadataTypes\SwitchPayloadType::PAYLOAD_TOGGLE, null, Types\LightSwitchPayloadType::PAYLOAD_TOGGLE],
			];
		}

		return $format;
	}

}
