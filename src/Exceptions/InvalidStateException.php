<?php declare(strict_types = 1);

/**
 * InvalidStateException.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Exceptions
 * @since          0.37.0
 *
 * @date           19.07.22
 */

namespace FastyBird\ShellyConnector\Exceptions;

use RuntimeException;

class InvalidStateException extends RuntimeException implements IException
{

}