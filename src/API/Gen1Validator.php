<?php declare(strict_types = 1);

/**
 * Gen1Validator.php
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

use FastyBird\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Metadata\Schemas as MetadataSchemas;
use FastyBird\ShellyConnector;
use Nette;
use Nette\Utils;

/**
 * Generation 1 devices messages validator
 *
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     API
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Gen1Validator
{

	use Nette\SmartObject;

	public const COAP_DESCRIPTION_MESSAGE_SCHEMA_FILENAME = 'gen1_coap_description.json';
	public const COAP_STATUS_MESSAGE_SCHEMA_FILENAME = 'gen1_coap_status.json';
	public const HTTP_SHELLY_INFO_MESSAGE_SCHEMA_FILENAME = 'gen1_http_shelly.json';
	public const HTTP_STATUS_MESSAGE_SCHEMA_FILENAME = 'gen1_http_status.json';
	public const HTTP_DESCRIPTION_MESSAGE_SCHEMA_FILENAME = 'gen1_http_description.json';
	public const HTTP_SETTINGS_MESSAGE_SCHEMA_FILENAME = 'gen1_http_settings.json';

	/** @var MetadataSchemas\IValidator */
	private MetadataSchemas\IValidator $schemaValidator;

	/**
	 * @param MetadataSchemas\IValidator $schemaValidator
	 */
	public function __construct(
		MetadataSchemas\IValidator $schemaValidator
	) {
		$this->schemaValidator = $schemaValidator;
	}

	/**
	 * @param string $message
	 *
	 * @return bool
	 */
	public function isValidCoapDescriptionMessage(string $message): bool
	{
		$filePath = ShellyConnector\Constants::RESOURCES_FOLDER . DIRECTORY_SEPARATOR . self::COAP_DESCRIPTION_MESSAGE_SCHEMA_FILENAME;

		try {
			$schema = Utils\FileSystem::read($filePath);

		} catch (Nette\IOException) {
			return false;
		}

		try {
			$this->schemaValidator->validate($message, $schema);
		} catch (MetadataExceptions\MalformedInputException | MetadataExceptions\LogicException | MetadataExceptions\InvalidDataException) {
			return false;
		}

		return true;
	}

	/**
	 * @param string $message
	 *
	 * @return bool
	 */
	public function isValidCoapStatusMessage(string $message): bool
	{
		$filePath = ShellyConnector\Constants::RESOURCES_FOLDER . DIRECTORY_SEPARATOR . self::COAP_STATUS_MESSAGE_SCHEMA_FILENAME;

		try {
			$schema = Utils\FileSystem::read($filePath);

		} catch (Nette\IOException) {
			return false;
		}

		try {
			$this->schemaValidator->validate($message, $schema);
		} catch (MetadataExceptions\MalformedInputException | MetadataExceptions\LogicException | MetadataExceptions\InvalidDataException) {
			return false;
		}

		return true;
	}

	/**
	 * @param string $message
	 *
	 * @return bool
	 */
	public function isValidHttpInfoMessage(string $message): bool
	{
		$filePath = ShellyConnector\Constants::RESOURCES_FOLDER . DIRECTORY_SEPARATOR . self::HTTP_SHELLY_INFO_MESSAGE_SCHEMA_FILENAME;

		try {
			$schema = Utils\FileSystem::read($filePath);

		} catch (Nette\IOException) {
			return false;
		}

		try {
			$this->schemaValidator->validate($message, $schema);
		} catch (MetadataExceptions\MalformedInputException | MetadataExceptions\LogicException | MetadataExceptions\InvalidDataException) {
			return false;
		}

		return true;
	}

	/**
	 * @param string $message
	 *
	 * @return bool
	 */
	public function isValidHttpStatusMessage(string $message): bool
	{
		$filePath = ShellyConnector\Constants::RESOURCES_FOLDER . DIRECTORY_SEPARATOR . self::HTTP_STATUS_MESSAGE_SCHEMA_FILENAME;

		try {
			$schema = Utils\FileSystem::read($filePath);

		} catch (Nette\IOException) {
			return false;
		}

		try {
			$this->schemaValidator->validate($message, $schema);
		} catch (MetadataExceptions\MalformedInputException | MetadataExceptions\LogicException | MetadataExceptions\InvalidDataException) {
			return false;
		}

		return true;
	}

	/**
	 * @param string $message
	 *
	 * @return bool
	 */
	public function isValidHttpSettingsMessage(string $message): bool
	{
		$filePath = ShellyConnector\Constants::RESOURCES_FOLDER . DIRECTORY_SEPARATOR . self::HTTP_SETTINGS_MESSAGE_SCHEMA_FILENAME;

		try {
			$schema = Utils\FileSystem::read($filePath);

		} catch (Nette\IOException) {
			return false;
		}

		try {
			$this->schemaValidator->validate($message, $schema);
		} catch (MetadataExceptions\MalformedInputException | MetadataExceptions\LogicException | MetadataExceptions\InvalidDataException) {
			return false;
		}

		return true;
	}

	/**
	 * @param string $message
	 *
	 * @return bool
	 */
	public function isValidHttpDescriptionMessage(string $message): bool
	{
		$filePath = ShellyConnector\Constants::RESOURCES_FOLDER . DIRECTORY_SEPARATOR . self::HTTP_DESCRIPTION_MESSAGE_SCHEMA_FILENAME;

		try {
			$schema = Utils\FileSystem::read($filePath);

		} catch (Nette\IOException) {
			return false;
		}

		try {
			$this->schemaValidator->validate($message, $schema);
		} catch (MetadataExceptions\MalformedInputException | MetadataExceptions\LogicException | MetadataExceptions\InvalidDataException) {
			return false;
		}

		return true;
	}

}
