<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerLaravel\Tests\Unit\Grpc\Context;

use PHPUnit\Framework\TestCase;
use Spiral\RoadRunnerLaravel\Grpc\Context\GrpcTarget;

final class GrpcTargetTest extends TestCase
{
    public function testConstructorWithMethodOnly(): void
    {
        $target = new GrpcTarget('TestService.TestMethod');

        $this->assertEquals(['TestService.TestMethod'], $target->getPath());
        $this->assertEquals('TestService.TestMethod', $target->getMethod());
        $this->assertEquals('TestService.TestMethod', (string) $target);
    }

    public function testConstructorWithCustomPath(): void
    {
        $path = ['TestService', 'TestMethod'];
        $target = new GrpcTarget('TestService.TestMethod', $path);

        $this->assertEquals($path, $target->getPath());
        $this->assertEquals('TestService.TestMethod', $target->getMethod());
        $this->assertEquals('TestService::TestMethod', (string) $target);
    }

    public function testConstructorWithCustomDelimiter(): void
    {
        $path = ['TestService', 'TestMethod'];
        $target = new GrpcTarget('TestService.TestMethod', $path, '/');

        $this->assertEquals($path, $target->getPath());
        $this->assertEquals('TestService/TestMethod', (string) $target);
    }

    public function testWithPath(): void
    {
        $target = new GrpcTarget('TestService.TestMethod');
        $newPath = ['NewService', 'NewMethod'];

        $newTarget = $target->withPath($newPath);

        $this->assertNotSame($target, $newTarget);
        $this->assertEquals(['TestService.TestMethod'], $target->getPath());
        $this->assertEquals($newPath, $newTarget->getPath());
        $this->assertEquals('TestService.TestMethod', $target->getMethod());
        $this->assertEquals('TestService.TestMethod', $newTarget->getMethod());
    }

    public function testWithPathAndCustomDelimiter(): void
    {
        $target = new GrpcTarget('TestService.TestMethod');
        $newPath = ['NewService', 'NewMethod'];

        $newTarget = $target->withPath($newPath, '/');

        $this->assertEquals('NewService/NewMethod', (string) $newTarget);
    }

    public function testGetReflectionReturnsNull(): void
    {
        $target = new GrpcTarget('TestService.TestMethod');

        $this->assertNull($target->getReflection());
    }

    public function testGetObjectReturnsNull(): void
    {
        $target = new GrpcTarget('TestService.TestMethod');

        $this->assertNull($target->getObject());
    }

    public function testGetCallableReturnsNull(): void
    {
        $target = new GrpcTarget('TestService.TestMethod');

        $this->assertNull($target->getCallable());
    }

    public function testToStringWithDefaultDelimiter(): void
    {
        $target = new GrpcTarget('TestService.TestMethod', ['TestService', 'TestMethod']);

        $this->assertEquals('TestService::TestMethod', (string) $target);
    }

    public function testEmptyPathDefaultsToMethod(): void
    {
        $target = new GrpcTarget('TestService.TestMethod', []);

        $this->assertEquals(['TestService.TestMethod'], $target->getPath());
    }
}
