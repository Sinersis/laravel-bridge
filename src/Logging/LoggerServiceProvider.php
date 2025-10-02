<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerLaravel\Logging;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Psr\Log\LoggerInterface;
use RoadRunner\Logger\Logger as AppLogger;
use RoadRunner\PsrLogger\Context\DefaultProcessor;
use RoadRunner\PsrLogger\RpcLogger;
use Spiral\Goridge\RPC\RPC;

final class LoggerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LoggerInterface::class, function (Application $app) {
            /** @var Repository $config */
            $config = $app->make('config');

            /** @var array<string, mixed> $roadrunnerConfig */
            $roadrunnerConfig = $config->get('roadrunner', []);

            /** @var array<string, mixed> $loggerConfig */
            $loggerConfig = $roadrunnerConfig['logger'] ?? [];

            /** @var non-empty-string $relayDsn */
            $relayDsn = $loggerConfig['relay_dsn'] ?? 'tcp://127.0.0.1:6001';

            $rpc = RPC::create($relayDsn);

            $appLogger = new AppLogger($rpc);

            $processor = DefaultProcessor::createDefault();

            return new RpcLogger($appLogger, $processor);
        });

        $this->app->alias(LoggerInterface::class, 'roadrunner.logger');
    }
}
