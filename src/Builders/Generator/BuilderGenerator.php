<?php

declare(strict_types=1);

namespace TreeshaInfotech\Vapi\Builders\Generator;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;
use RuntimeException;
use Vapi\Analytics\AnalyticsClient;
use Vapi\Assistants\AssistantsClient;
use Vapi\Calls\CallsClient;
use Vapi\Campaigns\CampaignsClient;
use Vapi\Chats\ChatsClient;
use Vapi\Core\Json\JsonSerializableType;
use Vapi\Eval\EvalClient;
use Vapi\Files\FilesClient;
use Vapi\Insight\InsightClient;
use Vapi\ObservabilityScorecard\ObservabilityScorecardClient;
use Vapi\PhoneNumbers\PhoneNumbersClient;
use Vapi\ProviderResources\ProviderResourcesClient;
use Vapi\Sessions\SessionsClient;
use Vapi\Squads\SquadsClient;
use Vapi\StructuredOutputs\StructuredOutputsClient;
use Vapi\Tools\ToolsClient;

/**
 * Emits fluent builder classes for every request DTO the SDK's resource
 * clients accept by reflecting over their properties and docblocks.
 * Enum-annotated string fields (`value-of<SomeEnum>`) become setters that
 * accept the native enum; required fields become `make()` parameters.
 *
 * DTOs that cannot be typed soundly (private constructors, or required
 * fields with unclassifiable types) are skipped.
 *
 * Run `composer build:builders` after upgrading vapi/vapi.
 */
final class BuilderGenerator
{
    private const array CLIENTS = [
        AnalyticsClient::class,
        AssistantsClient::class,
        CallsClient::class,
        CampaignsClient::class,
        ChatsClient::class,
        EvalClient::class,
        FilesClient::class,
        InsightClient::class,
        ObservabilityScorecardClient::class,
        PhoneNumbersClient::class,
        ProviderResourcesClient::class,
        SessionsClient::class,
        SquadsClient::class,
        StructuredOutputsClient::class,
        ToolsClient::class,
    ];

    private const array SCALARS = ['string', 'int', 'float', 'bool'];

    /**
     * Every DTO accepted as a parameter by a public method of a resource
     * client, sorted for deterministic output.
     *
     * @return list<class-string<JsonSerializableType>>
     *
     * @throws ReflectionException
     */
    public function targets(): array
    {
        $targets = [];
        foreach (self::CLIENTS as $client) {
            $reflection = new ReflectionClass($client);
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isConstructor()) {
                    continue;
                }
                foreach ($method->getParameters() as $parameter) {
                    $type = $parameter->getType();
                    if (
                        $type instanceof ReflectionNamedType
                        && ! $type->isBuiltin()
                        && is_subclass_of($type->getName(), JsonSerializableType::class)
                    ) {
                        $targets[$type->getName()] = true;
                    }
                }
            }
        }

        ksort($targets, SORT_STRING);

        return array_keys($targets);
    }

    /**
     * Generates sources for every supported target.
     *
     * @return array<string, string> Builder class name => source.
     *
     * @throws ReflectionException
     * @throws RuntimeException On a builder name collision.
     */
    public function generateAll(): array
    {
        $sources = [];
        foreach ($this->targets() as $target) {
            try {
                $source = $this->generate($target);
            } catch (RuntimeException) {
                continue;
            }
            if ($source !== null) {
                $builder = $this->builderClassName($target);
                if (isset($sources[$builder])) {
                    throw new RuntimeException('Builder name collision: '.$builder.'.');
                }
                $sources[$builder] = $source;
            }
        }

        ksort($sources, SORT_STRING);

        return $sources;
    }

    /**
     * @param  class-string  $dtoClass
     */
    public function builderClassName(string $dtoClass): string
    {
        $short = $this->shortName($dtoClass);
        if (str_ends_with($short, 'Dto')) {
            $short = substr($short, 0, -3);
        }

        return $short.'Builder';
    }

    /**
     * Returns the builder source or null when the DTO cannot be typed
     * soundly.
     *
     * @param  class-string  $dtoClass
     *
     * @throws ReflectionException
     * @throws RuntimeException When a DTO cannot be rendered.
     */
    public function generate(string $dtoClass): ?string
    {
        $reflection = new ReflectionClass($dtoClass);
        if ($reflection->getConstructor()?->isPublic() !== true) {
            return null;
        }

        $namespace = $reflection->getNamespaceName();
        $uses = $this->usesOf($reflection);

        $required = [];
        $optional = [];
        $skipped = [];
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $field = $this->classify($property, $namespace, $uses);
            $type = $property->getType();
            $isRequired = $type !== null && ! $type->allowsNull();

            if ($field instanceof Field) {
                if ($isRequired) {
                    $required[] = $field;
                } else {
                    $optional[] = $field;
                }
            } elseif ($isRequired) {
                return null;
            } else {
                $skipped[] = $property->getName();
            }
        }

        return $this->render($dtoClass, $required, $optional, $skipped);
    }

    /**
     * @param  array<string, string>  $uses
     */
    private function classify(ReflectionProperty $property, string $namespace, array $uses): ?Field
    {
        $name = $property->getName();
        $doc = $property->getDocComment();
        $doc = $doc === false ? '' : $doc;
        $type = $property->getType();

        if (preg_match('/@var \??value-of<([A-Za-z0-9_\\\\]+)>[\s*]/', $doc, $matches) === 1) {
            $enum = $this->resolveName($matches[1], $namespace, $uses);
            if ($enum !== null && enum_exists($enum)) {
                return new Field($name, 'enum', [$enum], $enum);
            }
        }

        if (preg_match('/@var \??array<value-of<([A-Za-z0-9_\\\\]+)>>[\s*]/', $doc, $matches) === 1) {
            $enum = $this->resolveName($matches[1], $namespace, $uses);
            if ($enum !== null && enum_exists($enum)) {
                return new Field($name, 'enumArray', [$enum], $enum);
            }
        }

        if (preg_match('/@var \??array<([A-Za-z0-9_\\\\]+)>[\s*]/', $doc, $matches) === 1) {
            $item = $matches[1];
            if (in_array($item, self::SCALARS, true)) {
                return new Field($name, 'scalarArray', [$item]);
            }
            $class = $this->resolveName($item, $namespace, $uses);
            if ($class !== null) {
                return new Field($name, 'classArray', [$class]);
            }
        }

        if ($type instanceof ReflectionUnionType) {
            $parts = [];
            foreach ($type->getTypes() as $part) {
                if (! $part instanceof ReflectionNamedType) {
                    return null;
                }
                if ($part->getName() === 'null') {
                    continue;
                }
                if ($part->isBuiltin()) {
                    return null;
                }
                $parts[] = $part->getName();
            }

            return $parts === [] ? null : new Field($name, 'union', $parts);
        }

        if ($type instanceof ReflectionNamedType) {
            if (! $type->isBuiltin()) {
                return new Field($name, 'class', [$type->getName()]);
            }
            if (in_array($type->getName(), self::SCALARS, true)) {
                return new Field($name, 'scalar', [$type->getName()]);
            }
        }

        return null;
    }

    /**
     * @param  class-string  $dtoClass
     * @param  list<Field>  $required
     * @param  list<Field>  $optional
     * @param  list<string>  $skipped
     */
    private function render(string $dtoClass, array $required, array $optional, array $skipped): string
    {
        $builderName = $this->builderClassName($dtoClass);
        $dtoShort = $this->shortName($dtoClass);
        $imports = $this->imports($dtoClass, array_merge($required, $optional));
        $isReadonly = $required !== [] && $optional === [];

        $lines = [];
        $lines[] = '<?php';
        $lines[] = '';
        $lines[] = 'declare(strict_types=1);';
        $lines[] = '';
        $lines[] = 'namespace TreeshaInfotech\Vapi\Builders;';
        $lines[] = '';
        foreach ($imports as $import) {
            $lines[] = 'use '.$import.';';
        }
        $lines[] = '';
        $lines[] = '/**';
        $lines[] = ' * Fluent builder for '.$dtoShort.'.';
        $lines[] = ' *';
        $lines[] = ' * Generated by `composer build:builders`. Do not edit; regenerate after';
        $lines[] = ' * upgrading vapi/vapi.';
        if ($skipped !== []) {
            $lines[] = ' *';
            $lines[] = ' * Fields without a setter (pass them to the '.$dtoShort.' constructor';
            $lines[] = ' * instead): '.implode(', ', $skipped).'.';
        }
        $lines[] = ' */';
        $lines[] = ($isReadonly ? 'final readonly class ' : 'final class ').$builderName;
        $lines[] = '{';

        foreach ($optional as $field) {
            foreach ($this->propertyLines($field) as $line) {
                $lines[] = $line;
            }
            $lines[] = '';
        }

        if ($required !== []) {
            foreach ($this->constructorLines($required, $isReadonly) as $line) {
                $lines[] = $line;
            }
            $lines[] = '';
        }

        foreach ($this->makeLines($required) as $line) {
            $lines[] = $line;
        }
        $lines[] = '';

        foreach ($optional as $field) {
            foreach ($this->setterLines($field) as $line) {
                $lines[] = $line;
            }
            $lines[] = '';
        }

        $lines[] = '    public function build(): '.$dtoShort;
        $lines[] = '    {';
        $fields = array_merge($required, $optional);
        if ($fields === []) {
            $lines[] = '        return new '.$dtoShort.'([]);';
        } elseif ($optional === []) {
            // Required fields are never null, so there is nothing to filter.
            $lines[] = '        return new '.$dtoShort.'([';
            foreach ($fields as $field) {
                $lines[] = "            '".$field->name."' => \$this->".$field->name.',';
            }
            $lines[] = '        ]);';
        } else {
            $lines[] = '        $values = array_filter([';
            foreach ($fields as $field) {
                $lines[] = "            '".$field->name."' => \$this->".$field->name.',';
            }
            $lines[] = '        ], static fn (mixed $value): bool => $value !== null);';
            $lines[] = '';
            $lines[] = '        return new '.$dtoShort.'($values);';
        }
        $lines[] = '    }';
        $lines[] = '}';

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  list<Field>  $required
     * @param  bool  $readonlyClass  Whether the emitted class is itself readonly.
     * @return list<string>
     */
    private function constructorLines(array $required, bool $readonlyClass): array
    {
        $docblock = [];
        foreach ($required as $field) {
            $annotation = $this->requiredDocType($field);
            if ($annotation !== null) {
                $docblock[] = '     * @param  '.$annotation.'  $'.$field->name;
            }
        }

        $lines = [];
        if ($docblock !== []) {
            $lines[] = '    /**';
            foreach ($docblock as $line) {
                $lines[] = $line;
            }
            $lines[] = '     */';
        }
        $lines[] = '    private function __construct(';
        foreach ($required as $field) {
            $modifier = $readonlyClass ? 'private ' : 'private readonly ';
            $lines[] = '        '.$modifier.$this->requiredNativeType($field).' $'.$field->name.',';
        }
        $lines[] = '    ) {}';

        return $lines;
    }

    /**
     * @param  list<Field>  $required
     * @return list<string>
     */
    private function makeLines(array $required): array
    {
        if ($required === []) {
            return [
                '    public static function make(): self',
                '    {',
                '        return new self;',
                '    }',
            ];
        }

        $docblock = [];
        $parameters = [];
        $arguments = [];
        foreach ($required as $field) {
            $annotation = $this->makeDocType($field);
            if ($annotation !== null) {
                $docblock[] = '     * @param  '.$annotation.'  $'.$field->name;
            }
            $parameters[] = $this->makeParameterType($field).' $'.$field->name;
            $arguments[] = $field->kind === 'enum'
                ? '$'.$field->name.'->value'
                : '$'.$field->name;
        }

        $lines = [];
        if ($docblock !== []) {
            $lines[] = '    /**';
            foreach ($docblock as $line) {
                $lines[] = $line;
            }
            $lines[] = '     */';
        }
        $lines[] = '    public static function make('.implode(', ', $parameters).'): self';
        $lines[] = '    {';
        $lines[] = '        return new self('.implode(', ', $arguments).');';
        $lines[] = '    }';

        return $lines;
    }

    private function requiredNativeType(Field $field): string
    {
        return match ($field->kind) {
            'scalar' => $field->parameterTypes[0],
            'class' => $this->shortName($field->parameterTypes[0]),
            'union' => $this->unionSignature($field),
            'enum' => 'string',
            'enumArray', 'classArray', 'scalarArray' => 'array',
        };
    }

    private function requiredDocType(Field $field): ?string
    {
        return match ($field->kind) {
            'enum' => 'value-of<'.$this->shortName((string) $field->enum).'>',
            'enumArray' => 'list<value-of<'.$this->shortName((string) $field->enum).'>>',
            'classArray' => 'list<'.$this->shortName($field->parameterTypes[0]).'>',
            'scalarArray' => 'list<'.$field->parameterTypes[0].'>',
            default => null,
        };
    }

    private function makeParameterType(Field $field): string
    {
        return match ($field->kind) {
            'scalar' => $field->parameterTypes[0],
            'class' => $this->shortName($field->parameterTypes[0]),
            'union' => $this->unionSignature($field),
            'enum' => $this->shortName((string) $field->enum),
            'enumArray', 'classArray', 'scalarArray' => 'array',
        };
    }

    private function makeDocType(Field $field): ?string
    {
        return match ($field->kind) {
            'enumArray' => 'list<value-of<'.$this->shortName((string) $field->enum).'>>',
            'classArray' => 'list<'.$this->shortName($field->parameterTypes[0]).'>',
            'scalarArray' => 'list<'.$field->parameterTypes[0].'>',
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private function propertyLines(Field $field): array
    {
        $name = $field->name;

        return match ($field->kind) {
            'scalar' => ['    private ?'.$field->parameterTypes[0].' $'.$name.' = null;'],
            'class' => ['    private ?'.$this->shortName($field->parameterTypes[0]).' $'.$name.' = null;'],
            'union' => ['    private '.$this->unionSignature($field).'|null $'.$name.' = null;'],
            'enum' => [
                '    /** @var ?value-of<'.$this->shortName((string) $field->enum).'> */',
                '    private ?string $'.$name.' = null;',
            ],
            'enumArray' => [
                '    /** @var ?list<value-of<'.$this->shortName((string) $field->enum).'>> */',
                '    private ?array $'.$name.' = null;',
            ],
            'classArray' => [
                '    /** @var ?list<'.$this->shortName($field->parameterTypes[0]).'> */',
                '    private ?array $'.$name.' = null;',
            ],
            'scalarArray' => [
                '    /** @var ?list<'.$field->parameterTypes[0].'> */',
                '    private ?array $'.$name.' = null;',
            ],
        };
    }

    /**
     * @return list<string>
     */
    private function setterLines(Field $field): array
    {
        $name = $field->name;

        return match ($field->kind) {
            'scalar' => $this->simpleSetter($name, $field->parameterTypes[0]),
            'class' => $this->simpleSetter($name, $this->shortName($field->parameterTypes[0])),
            'union' => $this->simpleSetter($name, $this->unionSignature($field)),
            'enum' => [
                '    public function '.$name.'('.$this->shortName((string) $field->enum).' $'.$name.'): self',
                '    {',
                '        $this->'.$name.' = $'.$name.'->value;',
                '',
                '        return $this;',
                '    }',
            ],
            'enumArray' => [
                '    public function '.$name.'('.$this->shortName((string) $field->enum).' ...$'.$name.'): self',
                '    {',
                '        $values = [];',
                '        foreach ($'.$name.' as $item) {',
                '            $values[] = $item->value;',
                '        }',
                '        $this->'.$name.' = $values;',
                '',
                '        return $this;',
                '    }',
            ],
            'classArray' => $this->variadicSetter($name, $this->shortName($field->parameterTypes[0])),
            'scalarArray' => $this->variadicSetter($name, $field->parameterTypes[0]),
        };
    }

    /**
     * @return list<string>
     */
    private function simpleSetter(string $name, string $signature): array
    {
        return [
            '    public function '.$name.'('.$signature.' $'.$name.'): self',
            '    {',
            '        $this->'.$name.' = $'.$name.';',
            '',
            '        return $this;',
            '    }',
        ];
    }

    /**
     * @return list<string>
     */
    private function variadicSetter(string $name, string $signature): array
    {
        return [
            '    public function '.$name.'('.$signature.' ...$'.$name.'): self',
            '    {',
            '        $this->'.$name.' = array_values($'.$name.');',
            '',
            '        return $this;',
            '    }',
        ];
    }

    private function unionSignature(Field $field): string
    {
        $parts = [];
        foreach ($field->parameterTypes as $type) {
            $parts[] = $this->shortName($type);
        }

        return implode('|', $parts);
    }

    /**
     * @param  class-string  $dtoClass
     * @param  list<Field>  $fields
     * @return list<string>
     */
    private function imports(string $dtoClass, array $fields): array
    {
        $classes = [$dtoClass];
        foreach ($fields as $field) {
            if ($field->enum !== null) {
                $classes[] = $field->enum;
            }
            foreach ($field->parameterTypes as $type) {
                if (! in_array($type, self::SCALARS, true)) {
                    $classes[] = $type;
                }
            }
        }

        $classes = array_values(array_unique($classes));

        $shortNames = [];
        foreach ($classes as $class) {
            $short = $this->shortName($class);
            if (isset($shortNames[$short]) && $shortNames[$short] !== $class) {
                throw new RuntimeException(sprintf(
                    'Import conflict: %s and %s share the short name %s.',
                    $shortNames[$short],
                    $class,
                    $short,
                ));
            }
            $shortNames[$short] = $class;
        }

        sort($classes, SORT_STRING);

        return $classes;
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     * @return array<string, string>
     */
    private function usesOf(ReflectionClass $reflection): array
    {
        $file = $reflection->getFileName();
        if ($file === false) {
            throw new RuntimeException('Cannot read the source file of '.$reflection->getName().'.');
        }

        $source = file_get_contents($file);
        if ($source === false) {
            throw new RuntimeException('Cannot read '.$file.'.');
        }

        $uses = [];
        if (preg_match_all('/^use\s+([A-Za-z0-9_\\\\]+?)(?:\s+as\s+([A-Za-z0-9_]+))?;/m', $source, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as $match) {
                $alias = $match[2] ?? $this->shortName($match[1]);
                $uses[$alias] = $match[1];
            }
        }

        return $uses;
    }

    /**
     * @param  array<string, string>  $uses
     */
    private function resolveName(string $name, string $namespace, array $uses): ?string
    {
        $name = ltrim($name, '\\');

        if (str_contains($name, '\\')) {
            return class_exists($name) || enum_exists($name) ? $name : null;
        }

        $candidates = [
            $uses[$name] ?? null,
            $namespace.'\\'.$name,
            'Vapi\\Types\\'.$name,
        ];
        foreach ($candidates as $candidate) {
            if ($candidate !== null && (class_exists($candidate) || enum_exists($candidate))) {
                return $candidate;
            }
        }

        return null;
    }

    private function shortName(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }
}
