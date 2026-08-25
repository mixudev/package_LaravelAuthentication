<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Unit;

use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Vendor\LaravelAuthentication\Contracts\AuthenticationStrategyInterface;
use Vendor\LaravelAuthentication\Exceptions\InvalidStrategyException;
use Vendor\LaravelAuthentication\Support\AuthenticationStrategyRegistry;

class StrategyRegistryTest extends BaseTestCase
{
    public function test_registers_and_retrieves_strategy_instance(): void
    {
        $container = new Container();
        $registry = new AuthenticationStrategyRegistry($container);

        $mockStrategy = $this->createMock(AuthenticationStrategyInterface::class);
        $mockStrategy->method('name')->willReturn('mock_strategy');

        $registry->register('mock', $mockStrategy);

        $this->assertTrue($registry->has('mock'));
        $this->assertSame($mockStrategy, $registry->get('mock'));
        $this->assertContains('mock', $registry->getRegisteredNames());
    }

    public function test_throws_exception_on_unregistered_strategy(): void
    {
        $container = new Container();
        $registry = new AuthenticationStrategyRegistry($container);

        $this->expectException(InvalidStrategyException::class);
        $registry->get('non_existent');
    }
}
