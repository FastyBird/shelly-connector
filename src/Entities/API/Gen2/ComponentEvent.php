<?php declare(strict_types = 1);

/**
 * ComponentEvent.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Entities
 * @since          1.0.0
 *
 * @date           14.01.23
 */

namespace FastyBird\Connector\Shelly\Entities\API\Gen2;

use DateTimeInterface;
use Exception;
use FastyBird\Connector\Shelly\Entities;
use Nette\Utils;
use Orisai\ObjectMapper;
use function intval;

/**
 * Generation 2 device component event entity
 *
 * @package        FastyBird:ShellyConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ComponentEvent implements Entities\API\Entity
{

	public function __construct(
		#[ObjectMapper\Rules\StringValue(notEmpty: true)]
		private readonly string $component,
		#[ObjectMapper\Rules\IntValue(unsigned: true)]
		private readonly int $id,
		#[ObjectMapper\Rules\StringValue(notEmpty: true)]
		private readonly string $event,
		#[ObjectMapper\Rules\FloatValue(unsigned: true)]
		private readonly float $timestamp,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\IntValue(),
			new ObjectMapper\Rules\FloatValue(),
			new ObjectMapper\Rules\BoolValue(),
			new ObjectMapper\Rules\StringValue(),
			new ObjectMapper\Rules\NullValue(),
		])]
		private readonly int|float|bool|string|null $data = null,
	)
	{
	}

	public function getComponent(): string
	{
		return $this->component;
	}

	public function getId(): int
	{
		return $this->id;
	}

	public function getEvent(): string
	{
		return $this->event;
	}

	/**
	 * @throws Exception
	 */
	public function getTimestamp(): DateTimeInterface
	{
		return Utils\DateTime::from(intval($this->timestamp));
	}

	public function getData(): float|bool|int|string|null
	{
		return $this->data;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws Exception
	 */
	public function toArray(): array
	{
		return [
			'component' => $this->getComponent(),
			'id' => $this->getId(),
			'event' => $this->getEvent(),
			'timestamp' => $this->getTimestamp()->format(DateTimeInterface::ATOM),
			'data' => $this->getData(),
		];
	}

}
