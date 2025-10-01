<?php

namespace Spiral\RoadRunnerLaravel\Tests\Support;

use Spiral\RoadRunner\GRPC\ServiceInterface;

interface TestServiceInterface extends ServiceInterface
{
    public const NAME = 'test.TestService';
}