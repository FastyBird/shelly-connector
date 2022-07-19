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

use DateTimeInterface;
use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\ShellyConnector\Types;
use Nette;
use Nette\Utils;

/**
 * Generation 1 devices data transformers
 *
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     API
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Gen1Transformer
{

	use Nette\SmartObject;

	/**
	 * @param MetadataTypes\DataTypeType $dataType
	 * @param string[]|int[]|float[]|Array<int, Array<int, string|null>>|Array<int, int|null>|Array<int, float|null>|Array<int, MetadataTypes\SwitchPayloadType|string|Types\RelayPayloadType|null>|null $format
	 * @param string|int|float|bool|null $value
	 *
	 * @return float|int|string|bool|MetadataTypes\SwitchPayloadType|null
	 */
	public function transformValueFromDevice(
		MetadataTypes\DataTypeType $dataType,
		?array $format,
		string|int|float|bool|null $value
	): float|int|string|bool|MetadataTypes\SwitchPayloadType|null {
		if ($value === null) {
			return null;
		}

		if ($dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_FLOAT)) {
			$floatValue = floatval($value);

			if (is_array($format) && count($format) === 2) {
				[$minValue, $maxValue] = $format + [null, null];

				if ($minValue !== null && floatval($minValue) >= $floatValue) {
					return null;
				}

                if ($maxValue !== null && floatval($maxValue) <= $floatValue) {
					return null;
				}
			}

			return $floatValue;
		}

		if (
			$dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_UCHAR)
			|| $dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_CHAR)
			|| $dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_USHORT)
			|| $dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_SHORT)
			|| $dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_UINT)
			|| $dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_INT)
		) {
			$intValue = intval($value);

			if (is_array($format) && count($format) === 2) {
				[$minValue, $maxValue] = $format + [null, null];

				if ($minValue !== null && intval($minValue) >= $intValue) {
					return null;
				}

				if ($maxValue !== null && intval($maxValue) <= $intValue) {
					return null;
				}
			}

			return $intValue;
		}

		if ($dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_BOOLEAN)) {
			return is_bool($value) ? $value : boolval($value);
		}

		if ($dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_ENUM)) {
			if (is_array($format)) {
				$filteredFormat = array_filter($format, function ($item) use ($value): bool {
					return ((is_array($item) || is_string($item))) && $this->filterEnumFormat($item, $value);
				});

				if (count($filteredFormat) === 1) {
					if (is_array($filteredFormat[0])) {
						if (count($filteredFormat[0]) === 3) {
							return strval($filteredFormat[0][0]) ? Utils\Strings::lower(strval($filteredFormat[0][1])) === Utils\Strings::lower(strval($value)) : null;
						}
					} else {
						return strval($filteredFormat[0]);
					}
				}
			}
		}

		if ($dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_SWITCH)) {
			if (is_array($format)) {
				$filteredFormat = array_filter($format, function ($item) use ($value): bool {
					return ((is_array($item) || is_string($item))) && $this->filterEnumFormat($item, $value);
				});

				if (
					count($filteredFormat) === 1
					&& is_array($filteredFormat[0])
					&& count($filteredFormat[0]) === 3
					&& Utils\Strings::lower(strval($filteredFormat[0][1])) === Utils\Strings::lower(strval($value))
					&& MetadataTypes\SwitchPayloadType::isValidValue(strval($value))
				) {
					return MetadataTypes\SwitchPayloadType::get(strval($value));
				}
			}
		}

		return null;
	}

	/**
	 * @param MetadataTypes\DataTypeType $dataType
	 * @param Array<int, string>|Array<int, Array<int, string|null>>|Array<int, int|null>|Array<int, float|null>|null $format
	 * @param bool|float|int|string|DateTimeInterface|MetadataTypes\ButtonPayloadType|MetadataTypes\SwitchPayloadType|null $value
	 *
	 * @return string|int|float|bool|null
	 */
	public function transformValueToDevice(
		MetadataTypes\DataTypeType $dataType,
		?array $format,
		bool|float|int|string|DateTimeInterface|MetadataTypes\ButtonPayloadType|MetadataTypes\SwitchPayloadType|null $value
	): string|int|float|bool|null {
		if ($value === null) {
			return null;
		}

		if ($dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_BOOLEAN)) {
			if (is_bool($value)) {
				return $value ? 1 : 0;
			}

            return null;
		}

		if ($dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_ENUM)) {
			if (is_array($format)) {
				$filteredFormat = array_filter($format, function ($item) use ($value): bool {
					return ((is_array($item) || is_string($item))) && $this->filterEnumFormat($item, $value);
				});

				if (
					count($filteredFormat) === 1
					&& is_array($filteredFormat[0])
					&& count($filteredFormat[0]) === 3
					&& Utils\Strings::lower(strval($filteredFormat[0][0])) === Utils\Strings::lower(strval($value))
				) {
					return strval($filteredFormat[0][2]);
				}

				if (
					count($filteredFormat) === 1
					&& !is_array($filteredFormat[0])
				) {
					return strval($filteredFormat[0]);
				}

                return null;
			}
		}

		if ($dataType->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_SWITCH)) {
			if (is_array($format)) {
				$filteredFormat = array_filter($format, function ($item) use ($value): bool {
					return ((is_array($item) || is_string($item))) && $this->filterEnumFormat($item, $value);
				});

				if (
					count($filteredFormat) === 1
					&& is_array($filteredFormat[0])
					&& count($filteredFormat[0]) === 3
					&& Utils\Strings::lower(strval($filteredFormat[0][0])) === Utils\Strings::lower(strval($value))
				) {
					return strval($filteredFormat[0][2]);
				}
			}
		}

		return is_scalar($value) ? $value : strval($value);
	}

	/**
	 * @param string|Array<int, string|null> $item
	 * @param int|float|string|bool|DateTimeInterface|MetadataTypes\ButtonPayloadType|MetadataTypes\SwitchPayloadType $value
	 *
	 * @return bool
	 */
	private function filterEnumFormat(
		string|array $item,
		int|float|string|bool|DateTimeInterface|MetadataTypes\ButtonPayloadType|MetadataTypes\SwitchPayloadType $value
	): bool {
		if (is_array($item)) {
			if (count($item) !== 3) {
				return false;
			}

			return Utils\Strings::lower(strval($value)) === Utils\Strings::lower(strval($item[0]))
				|| Utils\Strings::lower(strval($value)) === Utils\Strings::lower(strval($item[1]))
				|| Utils\Strings::lower(strval($value)) === Utils\Strings::lower(strval($item[2]));
		}

		return Utils\Strings::lower(strval($value)) === Utils\Strings::lower($item);
	}

}
