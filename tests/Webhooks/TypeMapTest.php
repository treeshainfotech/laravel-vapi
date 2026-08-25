<?php

declare(strict_types=1);

use TreeshaInfotech\Vapi\Webhooks\ServerMessageTypeMap;
use Vapi\Types\ServerMessage;

it('maps every server message type the sdk knows', function (): void {
    $union = (new ReflectionProperty(ServerMessage::class, 'message'))->getType();
    expect($union)->toBeInstanceOf(ReflectionUnionType::class);
    assert($union instanceof ReflectionUnionType);

    foreach ($union->getTypes() as $arm) {
        assert($arm instanceof ReflectionNamedType);
        $class = $arm->getName();
        $enum = $class.'Type';

        expect(enum_exists($enum))->toBeTrue("Missing discriminator enum {$enum}.");

        foreach ($enum::cases() as $case) {
            expect(ServerMessageTypeMap::resolve($case->value))->toBe(
                $class,
                "Server message type '{$case->value}' is not mapped to {$class}. Update ServerMessageTypeMap.",
            );
        }
    }
});
