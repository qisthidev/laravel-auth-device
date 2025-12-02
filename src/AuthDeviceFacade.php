<?php

namespace Qisthidev\AuthDevice;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Qisthidev\AuthDevice\AuthDevice
 */
class AuthDeviceFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'auth-device';
    }
}
