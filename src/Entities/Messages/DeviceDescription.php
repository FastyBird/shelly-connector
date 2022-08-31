<?php declare(strict_types = 1);

/**
 * DeviceDescription.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Properties
 * @since          0.37.0
 *
 * @date           18.07.22
 */

namespace FastyBird\ShellyConnector\Entities\Messages;

use FastyBird\ShellyConnector\Types;
use Ramsey\Uuid;

/**
 * Device description message entity
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Properties
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DeviceDescription extends Device
{

	/** @var BlockDescription[] */
	private array $blocks;

	/**
	 * @param Types\MessageSource $source
	 * @param Uuid\UuidInterface $connector
	 * @param string $identifier
	 * @param string|null $type
	 * @param string $ipAddress
	 * @param BlockDescription[] $blocks
	 */
	public function __construct(
		Types\MessageSource $source,
		Uuid\UuidInterface  $connector,
		string              $identifier,
		?string             $type,
		string              $ipAddress,
		array               $blocks
	) {
		parent::__construct($source, $connector, $identifier, $type, $ipAddress);

		$this->blocks = array_unique($blocks, SORT_REGULAR);
	}

	/**
	 * @return BlockDescription[]
	 */
	public function getBlocks(): array
	{
		return $this->blocks;
	}

	/**
	 * @param BlockDescription $block
	 *
	 * @return void
	 */
	public function addBlock(BlockDescription $block): void
	{
		$this->blocks[] = $block;

		$this->blocks = array_unique($this->blocks, SORT_REGULAR);
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return array_merge(parent::toArray(), [
			'blocks' => array_map(function (BlockDescription $block): array {
				return $block->toArray();
			}, $this->getBlocks()),
		]);
	}

}
