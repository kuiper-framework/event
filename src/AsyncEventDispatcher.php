<?php

/*
 * This file is part of the Kuiper package.
 *
 * (c) Ye Wenbin <wenbinye@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace kuiper\event;

use kuiper\annotations\AnnotationReaderInterface;
use kuiper\event\annotation\Async;
use kuiper\event\async\AsyncEventTask;
use kuiper\swoole\server\ServerInterface;
use kuiper\swoole\task\QueueInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class AsyncEventDispatcher implements AsyncEventDispatcherInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $delegateEventDispatcher;
    /**
     * @var ServerInterface|null
     */
    private $server;
    /**
     * @var AnnotationReaderInterface
     */
    private $annotationReader;
    /**
     * @var QueueInterface|null
     */
    private $taskQueue;

    public function __construct(EventDispatcherInterface $eventDispatcher, AnnotationReaderInterface $annotationReader)
    {
        $this->delegateEventDispatcher = $eventDispatcher;
        $this->annotationReader = $annotationReader;
    }

    /**
     * @param ServerInterface $server
     */
    public function setServer(ServerInterface $server): void
    {
        $this->server = $server;
    }

    /**
     * @param QueueInterface $taskQueue
     */
    public function setTaskQueue(QueueInterface $taskQueue): void
    {
        $this->taskQueue = $taskQueue;
    }

    /**
     * {@inheritDoc}
     */
    public function dispatch(object $event)
    {
        $annotation = $this->annotationReader->getClassAnnotation(new \ReflectionClass($event), Async::class);
        if (null !== $annotation && null !== $this->server && !$this->server->isTaskWorker()) {
            $this->dispatchAsync($event);

            return $event;
        }

        return $this->delegateEventDispatcher->dispatch($event);
    }

    /**
     * @return EventDispatcherInterface
     */
    public function getDelegateEventDispatcher(): EventDispatcherInterface
    {
        return $this->delegateEventDispatcher;
    }

    /**
     * {@inheritDoc}
     */
    public function dispatchAsync(object $event): void
    {
        if (null !== $this->server && !$this->server->isTaskWorker()) {
            $this->taskQueue->put(new AsyncEventTask($event));
        } else {
            $this->delegateEventDispatcher->dispatch($event);
        }
    }
}
