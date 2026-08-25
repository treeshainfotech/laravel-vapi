<?php

declare(strict_types=1);

namespace TreeshaInfotech\Vapi\Builders\Generator;

final readonly class Field
{
    /**
     * @param  string  $name  The DTO property name.
     * @param  'scalar'|'class'|'union'|'enum'|'enumArray'|'classArray'|'scalarArray'  $kind
     * @param  list<string>  $parameterTypes  Builtin names or fully-qualified class names for the setter parameter.
     * @param  ?string  $enum  Fully-qualified enum name for value-of annotations.
     */
    public function __construct(
        public string $name,
        public string $kind,
        public array $parameterTypes,
        public ?string $enum = null,
    ) {}
}
