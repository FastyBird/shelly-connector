<?php declare(strict_types = 1);

/**
 * Consumer.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Consumers
 * @since          0.37.0
 *
 * @date           16.07.22
 */

namespace FastyBird\ShellyConnector\Consumers;

use FastyBird\ShellyConnector\Entities;
use Nette;
use SplObjectStorage;
use SplQueue;

/**
 * Clients message consumer proxy
 *
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Consumer
{

	use Nette\SmartObject;

	/** @var SplObjectStorage<IConsumer, null> */
	private SplObjectStorage $consumers;

	/** @var SplQueue<Entities\Messages\IEntity> */
	private SplQueue $queue;

	/**
	 * @param IConsumer[] $consumers
	 */
	public function __construct(array $consumers)
	{
		$this->consumers = new SplObjectStorage();
		$this->queue = new SplQueue();

		foreach ($consumers as $consumer) {
			$this->consumers->attach($consumer);
		}
	}

	/**
	 * @param Entities\Messages\IEntity $entity
	 *
	 * @return void
	 */
	public function append(Entities\Messages\IEntity $entity): void
	{
		$this->queue->enqueue($entity);
	}

	/**
	 * @return void
	 */
	public function consume(): void
	{
		$this->queue->rewind();

		if ($this->queue->isEmpty()) {
			return;
		}

		$entity = $this->queue->dequeue();

		$this->consumers->rewind();

		/** @var IConsumer $consumer */
		foreach ($this->consumers as $consumer) {
			$consumer->consume($entity);
		}
	}

	/**
	 * @return bool
	 */
	public function isEmpty(): bool
	{
		return $this->queue->isEmpty();
	}

}
