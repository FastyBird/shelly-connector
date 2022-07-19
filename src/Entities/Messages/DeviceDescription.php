<?php declare(strict_types = 1);

/**
 * DeviceDescription.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Entities
 * @since          0.37.0
 *
 * @date           18.07.22
 */

namespace FastyBird\ShellyConnector\Entities\Messages;

/**
 * Device description message entity
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DeviceDescription extends Device
{

	/** @var string */
	private string $type;

	/** @var BlockDescription[] */
	private array $blocks;

	/**
	 * @param string $identifier
	 * @param string $type
	 * @param string $ipAddress
	 * @param BlockDescription[] $blocks
	 */
	public function __construct(
		string $identifier,
		string $type,
		string $ipAddress,
		array $blocks
	) {
		parent::__construct($identifier, $ipAddress);

		$this->type = $type;
		$this->blocks = array_unique($blocks);
	}

	/**
	 * @return string
	 */
	public function getType(): string
	{
		return $this->type;
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

		$this->blocks = array_unique($this->blocks);
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return array_merge(parent::toArray(), [
			'type'   => $this->getType(),
			'blocks' => array_map(function (BlockDescription $block): array {
				return $block->toArray();
			}, $this->getBlocks()),
		]);
	}

}
