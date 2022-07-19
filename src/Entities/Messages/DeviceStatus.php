<?php declare(strict_types = 1);

/**
 * DeviceStatus.php
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
 * Device status message entity
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DeviceStatus extends Device
{

	/** @var string */
	private string $type;

	/** @var BlockStatus[] */
	private array $blocks;

	/**
	 * @param string $identifier
	 * @param string $type
	 * @param string $ipAddress
	 * @param BlockStatus[] $blocks
	 */
	public function __construct(
		string $identifier,
		string $type,
		string $ipAddress,
		array $blocks
	) {
		parent::__construct($identifier, $ipAddress);

		$this->type = $type;
		$this->blocks = $blocks;
	}

	/**
	 * @return string
	 */
	public function getType(): string
	{
		return $this->type;
	}

	/**
	 * @return BlockStatus[]
	 */
	public function getBlocks(): array
	{
		return $this->blocks;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return array_merge(parent::toArray(), [
			'type'   => $this->getType(),
			'blocks' => array_map(function (BlockStatus $block): array {
				return $block->toArray();
			}, $this->getBlocks()),
		]);
	}

}
