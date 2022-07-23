<?php declare(strict_types = 1);

/**
 * DeviceDescriptionEntity.php
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

use FastyBird\ShellyConnector\Types;
use Ramsey\Uuid;

/**
 * Device description message entity
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DeviceDescriptionEntity extends DeviceEntity
{

	/** @var string|null */
	private ?string $type;

	/** @var BlockDescriptionEntity[] */
	private array $blocks;

	/**
	 * @param Types\MessageSourceType $source
	 * @param Uuid\UuidInterface $connector
	 * @param string $identifier
	 * @param string|null $type
	 * @param string $ipAddress
	 * @param BlockDescriptionEntity[] $blocks
	 */
	public function __construct(
		Types\MessageSourceType $source,
		Uuid\UuidInterface $connector,
		string $identifier,
		?string $type,
		string $ipAddress,
		array $blocks
	) {
		parent::__construct($source, $connector, $identifier, $ipAddress);

		$this->type = $type;
		$this->blocks = array_unique($blocks, SORT_REGULAR);
	}

	/**
	 * @return string|null
	 */
	public function getType(): ?string
	{
		return $this->type;
	}

	/**
	 * @return BlockDescriptionEntity[]
	 */
	public function getBlocks(): array
	{
		return $this->blocks;
	}

	/**
	 * @param BlockDescriptionEntity $block
	 *
	 * @return void
	 */
	public function addBlock(BlockDescriptionEntity $block): void
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
			'type'   => $this->getType(),
			'blocks' => array_map(function (BlockDescriptionEntity $block): array {
				return $block->toArray();
			}, $this->getBlocks()),
		]);
	}

}
