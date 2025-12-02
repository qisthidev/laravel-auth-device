<?php

namespace Qisthidev\AuthDevice;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Qisthidev\AuthDevice\Guards\DeviceGuard;
use Qisthidev\AuthDevice\Guards\DeviceUserProvider;
use Qisthidev\AuthDevice\Middleware\EnsureDeviceIsValid;
use Qisthidev\AuthDevice\Middleware\EnsureUserCanInvite;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class AuthDeviceServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('auth-device')
            ->hasConfigFile()
            ->hasMigrations([
                'create_auth_devices_table',
                'create_auth_invitations_table',
            ]);
    }

    public function packageBooted(): void
    {
        $this->registerAuthGuard();
        $this->registerMiddleware();
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton('auth-device', function () {
            return new AuthDevice();
        });
    }

    /**
     * Register the device authentication guard.
     */
    protected function registerAuthGuard(): void
    {
        Auth::provider('device', function ($app, array $config) {
            return new DeviceUserProvider();
        });

        Auth::extend('device', function ($app, $name, array $config) {
            $provider = Auth::createUserProvider($config['provider'] ?? null);

            if (! $provider instanceof DeviceUserProvider) {
                $provider = new DeviceUserProvider();
            }

            return new DeviceGuard($provider, $app['request']);
        });
    }

    /**
     * Register middleware aliases.
     */
    protected function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('device.valid', EnsureDeviceIsValid::class);
        $router->aliasMiddleware('can-invite', EnsureUserCanInvite::class);
    }
}
