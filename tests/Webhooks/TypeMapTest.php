<?php

declare(strict_types=1);

use TreeshaInfotech\Vapi\Webhooks\ServerMessageTypeMap;
use Vapi\Types\ServerMessage;
use Vapi\Types\ServerMessageEndOfCallReport;
use Vapi\Types\ServerMessageStatusUpdate;
use Vapi\Types\ServerMessageToolCalls;
use Vapi\Types\ServerMessageTranscript;
use Vapi\Types\ServerMessageTranscriptType;

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

it('resolves every publicly exposed type constant', function (): void {
    $constants = [
        ServerMessageTypeMap::END_OF_CALL_REPORT => ServerMessageEndOfCallReport::class,
        ServerMessageTypeMap::STATUS_UPDATE => ServerMessageStatusUpdate::class,
        ServerMessageTypeMap::TOOL_CALLS => ServerMessageToolCalls::class,
        ServerMessageTypeMap::TRANSCRIPT => ServerMessageTranscript::class,
        ServerMessageTypeMap::TRANSCRIPT_FINAL => ServerMessageTranscript::class,
    ];

    foreach ($constants as $type => $class) {
        expect(ServerMessageTypeMap::resolve($type))->toBe(
            $class,
            "Constant for '{$type}' no longer resolves to {$class}.",
        );
    }
});

it('exposes a constant for every transcript discriminator', function (): void {
    $covered = [ServerMessageTypeMap::TRANSCRIPT, ServerMessageTypeMap::TRANSCRIPT_FINAL];

    foreach (ServerMessageTranscriptType::cases() as $case) {
        expect(in_array($case->value, $covered, true))->toBeTrue(
            "Transcript discriminator '{$case->value}' has no constant. Listeners comparing "
            .'message.type against the constants would silently miss it.',
        );
    }
});
