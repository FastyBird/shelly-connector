<?php declare(strict_types = 1);

/**
 * Gen1Parser.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnectorEntity!
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
use FastyBird\ShellyConnector\Types;
use Nette;
use Nette\Utils;

/**
 * Generation 1 devices messages parser
 *
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     API
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Gen1Parser
{

	use Nette\SmartObject;

	/** @var Gen1Validator */
	private Gen1Validator $validator;

	/** @var MetadataSchemas\IValidator */
	private MetadataSchemas\IValidator $schemaValidator;

	/**
	 * @param Gen1Validator $validator
	 * @param MetadataSchemas\IValidator $schemaValidator
	 */
	public function __construct(
		Gen1Validator $validator,
		MetadataSchemas\IValidator $schemaValidator
	) {
		$this->validator = $validator;
		$this->schemaValidator = $schemaValidator;
	}

	/**
	 * @param string $address
	 * @param string $type
	 * @param string $identifier
	 * @param string $message
	 *
	 * @return Entities\Messages\DeviceDescriptionEntity
	 */
	public function parseCoapDescriptionMessage(
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

		$blocks = $this->extractBlocksDescription($parsedMessage);

		return new Entities\Messages\DeviceDescriptionEntity(
			$identifier,
			$type,
			$address,
			$blocks,
		);
	}

	/**
	 * @param string $address
	 * @param string $type
	 * @param string $identifier
	 * @param string $message
	 *
	 * @return Entities\Messages\DeviceStatusEntity
	 */
	public function parseCoapStatusMessage(
		string $address,
		string $type,
		string $identifier,
		string $message
	): Entities\Messages\DeviceStatusEntity {
		if (!$this->validator->isValidCoapDescriptionMessage($message)) {
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
			!is_object($parsedMessage)
			|| !property_exists($parsedMessage, 'G')
			|| !is_array($parsedMessage->G)
		) {
			throw new Exceptions\ParseMessageException('Provided message is not valid');
		}

		$blocks = [];

		foreach ($parsedMessage->G as $sensorState) {
			if (count($sensorState) === 3) {
				[$blockIdentifier, $sensorIdentifier, $sensorValue] = $sensorState;

				if (!array_key_exists($blockIdentifier, $blocks)) {
					$blocks[$blockIdentifier] = new Entities\Messages\BlockStatusEntity($blockIdentifier);
				}

				$blocks[$blockIdentifier]->addSensor(
					new Entities\Messages\SensorStatusEntity(
						$sensorIdentifier,
						$sensorValue
					)
				);
			}
		}

		return new Entities\Messages\DeviceStatusEntity(
			$identifier,
			$type,
			$address,
			array_values($blocks)
		);
	}

	/**
	 * @param string $identifier
	 * @param string $address
	 * @param string $message
	 *
	 * @return Entities\Messages\DeviceInfoEntity
	 */
	public function parseHttpShellyMessage(
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
			!is_object($parsedMessage)
			|| !property_exists($parsedMessage, 'type')
			|| !property_exists($parsedMessage, 'mac')
			|| !property_exists($parsedMessage, 'auth')
			|| !property_exists($parsedMessage, 'fw')
		) {
			throw new Exceptions\ParseMessageException('Provided message is not valid');
		}

		return new Entities\Messages\DeviceInfoEntity(
			$identifier,
			$address,
			Utils\Strings::lower($parsedMessage->type),
			$parsedMessage->mac,
			$parsedMessage->auth,
			$parsedMessage->fw
		);
	}

	/**
	 * @param string $identifier
	 * @param string $address
	 * @param string $message
	 *
	 * @return Entities\Messages\DeviceDescriptionEntity
	 */
	public function parseHttpDescriptionMessage(
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

		$blocks = $this->extractBlocksDescription($parsedMessage);

		return new Entities\Messages\DeviceDescriptionEntity(
			$identifier,
			null,
			$address,
			$blocks,
		);
	}

	/**
	 * @param Utils\ArrayHash $description
	 *
	 * @return Entities\Messages\BlockDescriptionEntity[]
	 */
	private function extractBlocksDescription(Utils\ArrayHash $description): array
	{
		if (!$description->offsetExists('blk') || !$description->offsetExists('sen')) {
			return [];
		}

		$blocks = $description->offsetGet('blk');
		$sensors = $description->offsetGet('sen');

		if (!is_array($blocks) || !is_array($sensors)) {
			return [];
		}

		$blocksDescriptions = [];

		foreach ($blocks as $block) {
			if (
				!is_object($block)
				|| !property_exists($block, 'I')
				|| !property_exists($block, 'D')
			) {
				continue;
			}

			$blockDescription = new Entities\Messages\BlockDescriptionEntity(
				intval($block->I),
				$block->D,
			);

			foreach ($sensors as $sensor) {
				if (
					!is_object($sensor)
					|| !property_exists($sensor, 'I')
					|| !property_exists($sensor, 'T')
					|| !property_exists($sensor, 'D')
					|| !property_exists($sensor, 'L')
				) {
					continue;
				}

				if (
					(is_array($sensor->L) && in_array($block->D, $sensor->L, true))
					|| $block->D === (int) $sensor->L
				) {
					$sensorRange = $this->parseSensorRange(
						$block->D,
						$sensor->D,
						property_exists($sensor, 'R') ? $sensor->R : null
					);

					$sensorDescription = new Entities\Messages\SensorDescriptionEntity(
						intval($sensor->I),
						Types\SensorTypeType::get($sensor->T),
						strval($sensor->D),
						$sensorRange->getDataType(),
						property_exists($sensor, 'U') && $sensor->U !== null ? Types\SensorUnitType::get($sensor->U) : null,
						$sensorRange->getFormat(),
						$sensorRange->getInvalid(),
						false,
						Types\WritableSensorTypeType::isValidValue($sensor->D)
					);

					$blockDescription->addSensor($sensorDescription);
				}
			}

			$blocksDescriptions[] = $blockDescription;
		}

		return $blocksDescriptions;
	}

	/**
	 * @param string $block
	 * @param string $description
	 * @param string|string[]|null $rawRange
	 *
	 * @return Entities\Messages\SensorRangeEntity
	 */
    private function parseSensorRange(
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
