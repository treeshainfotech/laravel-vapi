<?php

declare(strict_types=1);

namespace PHPSTORM_META;

override(\app(0), map([
    'vapi' => \TreeshaInfotech\Vapi\VapiManager::class,
]));

override(\Illuminate\Contracts\Container\Container::make(0), map([
    'vapi' => \TreeshaInfotech\Vapi\VapiManager::class,
]));
