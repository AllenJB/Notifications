<?php
declare(strict_types=1);

namespace AllenJB\Notifications\Tests;

use AllenJB\Notifications\ErrorHandler;
use AllenJB\Notifications\NotificationFactory;
use AllenJB\Notifications\Notifications;
use AllenJB\Notifications\Services\MemoryStore;
use PHPUnit\Framework\TestCase;

class SoftMemoryLimitTest extends TestCase
{
    public function testBytesLimitExceeded(): void
    {
        $notificationStore = new MemoryStore();
        $notifications = new Notifications([$notificationStore]);
        $notificationFactory = new NotificationFactory();
        ErrorHandler::setup(__DIR__ . '../', $notifications, $notificationFactory);
        ErrorHandler::setSoftMemoryLimitBytes('15');
        ErrorHandler::handleShutdown();

        $events = $notificationStore->retrieve();
        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertStringContainsStringIgnoringCase('soft memory limit reached', ($event->getMessage() ?? ''));

        $context = $event->getContext();
        $this->assertEquals('15', $context['Additional Data']['soft_limit_bytes']);
    }


    public function testBytesLimitNotExceeded(): void
    {
        $notificationStore = new MemoryStore();
        $notifications = new Notifications([$notificationStore]);
        $notificationFactory = new NotificationFactory();
        ErrorHandler::setup(__DIR__ . '../', $notifications, $notificationFactory);
        ErrorHandler::setSoftMemoryLimitBytes('128m');
        ErrorHandler::handleShutdown();

        $events = $notificationStore->retrieve();
        $this->assertCount(0, $events);
    }


    public function testPercentLimitExceeded(): void
    {
        $memoryLimit = ini_set('memory_limit', '10m');
        $notificationStore = new MemoryStore();
        $notifications = new Notifications([$notificationStore]);
        $notificationFactory = new NotificationFactory();
        ErrorHandler::setup(__DIR__ . '../', $notifications, $notificationFactory);
        ErrorHandler::setSoftMemoryLimitPercentage(1);
        ErrorHandler::handleShutdown();

        $events = $notificationStore->retrieve();
        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertStringContainsStringIgnoringCase('soft memory limit reached', ($event->getMessage() ?? ''));

        $context = $event->getContext();
        $this->assertEquals('104,858', $context['Additional Data']['soft_limit_bytes']);
        ini_set('memory_limit', $memoryLimit);
    }


    public function testPercentLimitNotExceeded(): void
    {
        $memoryLimit = ini_set('memory_limit', '1G');
        $notificationStore = new MemoryStore();
        $notifications = new Notifications([$notificationStore]);
        $notificationFactory = new NotificationFactory();
        ErrorHandler::setup(__DIR__ . '../', $notifications, $notificationFactory);
        ErrorHandler::setSoftMemoryLimitPercentage(50);
        ErrorHandler::handleShutdown();

        $events = $notificationStore->retrieve();
        $this->assertCount(0, $events);
        ini_set('memory_limit', $memoryLimit);
    }
}
