<?php

declare(strict_types=1);

namespace TreeshaInfotech\Vapi\Exceptions;

use RuntimeException;

final class MissingApiKey extends RuntimeException
{
    public static function create(): self
    {
        return new self(
            'No Vapi API key configured. Set VAPI_API_KEY in your environment, '
            .'or publish the config file and set vapi.api_key.',
        );
    }
}
