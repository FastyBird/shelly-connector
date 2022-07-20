<?php declare(strict_types = 1);

/**
 * Client.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Clients
 * @since          0.37.0
 *
 * @date           17.07.22
 */

namespace FastyBird\ShellyConnector\Clients;

use Nette;
use React\EventLoop;

/**
 * Base client service
 *
 * @package        FastyBird:ShellyConnectorEntity!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
abstract class Client implements IClient
{

	use Nette\SmartObject;

	private const HANDLER_START_DELAY = 2;
	private const HANDLER_PROCESSING_INTERVAL = 0.01;

	/** @var EventLoop\TimerInterface|null */
	protected ?EventLoop\TimerInterface $handlerTimer;

	/** @var EventLoop\LoopInterface */
	protected EventLoop\LoopInterface $eventLoop;

	/**
	 * @param EventLoop\LoopInterface $eventLoop
	 */
	public function __construct(
		EventLoop\LoopInterface $eventLoop
	) {
		$this->eventLoop = $eventLoop;
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect(): void
	{
		$this->eventLoop->addTimer(self::HANDLER_START_DELAY, function (): void {
			$this->handlerTimer = $this->eventLoop->addPeriodicTimer(self::HANDLER_PROCESSING_INTERVAL, function (): void {
				$this->handleCommunication();
			});
		});
	}

	/**
	 * {@inheritDoc}
	 */
	public function disconnect(): void
	{
		if ($this->handlerTimer !== null) {
			$this->eventLoop->cancelTimer($this->handlerTimer);
		}
	}

	/**
	 * @return void
	 */
	abstract protected function handleCommunication(): void;

}
