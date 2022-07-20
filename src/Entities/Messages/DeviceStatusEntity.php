<?php declare(strict_types = 1);

/**
 * DeviceStatusEntity.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Entities
 * @since          0.37.0
 *
 * @date           18.07.22
 */

namespace FastyBird\ShellyConnector\Entities\Messages;

use FastyBird\ShellyConnector\Types;

/**
 * Device status message entity
 *
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DeviceStatusEntity extends DeviceEntity
{

	/** @var string */
	private string $type;

	/** @var BlockStatusEntity[] */
	private array $blocks;

	/**
	 * @param Types\MessageSourceType $source,
	 * @param string $identifier
	 * @param string $type
	 * @param string $ipAddress
	 * @param BlockStatusEntity[] $blocks
	 */
	public function __construct(
		Types\MessageSourceType $source,
		string $identifier,
		string $type,
		string $ipAddress,
		array $blocks
	) {
		parent::__construct($source, $identifier, $ipAddress);

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
	 * @return BlockStatusEntity[]
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
			'blocks' => array_map(function (BlockStatusEntity $block): array {
				return $block->toArray();
			}, $this->getBlocks()),
		]);
	}

}
