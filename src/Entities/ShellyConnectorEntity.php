<?php declare(strict_types = 1);

/**
 * ShellyConnectorEntity.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Entities
 * @since          0.1.0
 *
 * @date           22.01.22
 */

namespace FastyBird\ShellyConnector\Entities;

use Doctrine\ORM\Mapping as ORM;
use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use FastyBird\Metadata\Types as MetadataTypes;

/**
 * @ORM\Entity
 */
class ShellyConnectorEntity extends DevicesModuleEntities\Connectors\Connector implements IShellyConnectorEntity
{

	public const CONNECTOR_TYPE = 'shelly';

	/**
	 * {@inheritDoc}
	 */
	public function getType(): string
	{
		return self::CONNECTOR_TYPE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDiscriminatorName(): string
	{
		return self::CONNECTOR_TYPE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSource(): MetadataTypes\ModuleSourceType|MetadataTypes\PluginSourceType|MetadataTypes\ConnectorSourceType
	{
		return MetadataTypes\ConnectorSourceType::get(MetadataTypes\ConnectorSourceType::SOURCE_CONNECTOR_SHELLY);
	}

}
