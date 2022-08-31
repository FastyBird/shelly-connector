<?php declare(strict_types = 1);

/**
 * MdnsResult.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Properties
 * @since          0.37.0
 *
 * @date           14.07.22
 */

namespace FastyBird\ShellyConnector\Entities\Clients;

use Nette;

/**
 * mDNS search result
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Properties
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class MdnsResult
{

	use Nette\SmartObject;

	/** @var string */
	private string $address;

	/** @var string */
	private string $name;

	/** @var Array<string, string|int|float> */
	private array $data;

	/**
	 * @param string $address
	 * @param string $name
	 * @param Array<string, string|int|float> $data
	 */
	public function __construct(
		string $address,
		string $name,
		array $data
	) {
		$this->address = $address;
		$this->name = $name;
		$this->data = $data;
	}

	/**
	 * @return string
	 */
	public function getAddress(): string
	{
		return $this->address;
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @return Array<string, string|int|float>
	 */
	public function getData(): array
	{
		return $this->data;
	}

	/**
	 * @return Array<string, string|Array<string, string|int|float>>
	 */
	public function __serialize(): array
	{
		return [
			'address' => $this->getAddress(),
			'name'    => $this->getName(),
			'data'    => $this->getData(),
		];
	}

}
