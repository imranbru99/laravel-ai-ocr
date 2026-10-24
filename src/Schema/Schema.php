<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Schema;

use ImranDevBd\AiOcr\Normalizers\Normalizer;

final class Schema
{
    /** @var array<string, mixed> */
    private array $definition = ['type' => 'object', 'properties' => [], 'additionalProperties' => false];

    /** @var list<string> */
    private array $current = [];

    /** @var array<string, callable> */
    private array $casts = [];

    /** @var array<string,callable> */
    private array $validators = [];

    /** @var array<string,array{schema:self,array:bool}> */
    private array $children = [];

    public function __construct(public readonly string $name = 'document') {}

    public static function make(string $name = 'document'): self
    {
        return new self($name);
    }

    /** @param array<string, mixed> $schema */
    public static function fromJson(array $schema): self
    {
        $self = new self;
        $self->definition = $self->validateDefinition($schema);

        return $self;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function validateDefinition(array $node, int $depth = 0): array
    {
        if (isset($node['properties']) && ! is_array($node['properties'])) {
            throw new \InvalidArgumentException('Schema properties must be an object.');
        }
        if (isset($node['required']) && (! is_array($node['required']) || count(array_filter($node['required'], 'is_string')) !== count($node['required']))) {
            throw new \InvalidArgumentException('Schema required must contain field names.');
        }
        if (isset($node['enum']) && ! is_array($node['enum'])) {
            throw new \InvalidArgumentException('Schema enum must be an array.');
        }
        if ($node['nullable'] ?? false) {
            $node['type'] = array_values(array_unique([...(array) ($node['type'] ?? 'object'), 'null']));
            unset($node['nullable']);
        }
        if ($depth > 16 || count($node['properties'] ?? []) > 200) {
            throw new \InvalidArgumentException('Schema is too deeply nested or has too many fields.');
        }
        $supported = ['type', 'properties', 'required', 'additionalProperties', 'items', 'enum', 'nullable', 'format', 'pattern', 'description', 'title', '$schema', '$id', 'minimum', 'maximum', 'minLength', 'maxLength', 'minItems', 'maxItems', 'x-cast'];
        foreach (array_keys($node) as $key) {
            if (! in_array($key, $supported, true)) {
                throw new \InvalidArgumentException('Unsupported JSON Schema keyword: '.$key);
            }
        }
        foreach ((array) ($node['type'] ?? 'object') as $type) {
            if (! in_array($type, ['object', 'array', 'string', 'integer', 'number', 'boolean', 'null'], true)) {
                throw new \InvalidArgumentException('Unsupported schema type.');
            }
        }
        if (in_array('object', (array) ($node['type'] ?? 'object'), true)) {
            $node['type'] ??= 'object';
            $node['additionalProperties'] = false;
            foreach ($node['properties'] ?? [] as $key => $child) {
                if (! is_array($child)) {
                    throw new \InvalidArgumentException('Schema properties must be objects.');
                } $node['properties'][$key] = $this->validateDefinition($child, $depth + 1);
            }
        }
        if (isset($node['items'])) {
            if (! is_array($node['items'])) {
                throw new \InvalidArgumentException('Schema items must be an object.');
            } $node['items'] = $this->validateDefinition($node['items'], $depth + 1);
        }

        return $node;
    }

    private function add(string $name, string $type, ?string $format = null): self
    {
        $segments = explode('.', $name);
        $node = &$this->definition;
        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $node['properties'][$segment] = array_filter(['type' => $type, 'format' => $format]);
            } else {
                $node['properties'][$segment] ??= ['type' => 'object', 'properties' => [], 'additionalProperties' => false];
                $node = &$node['properties'][$segment];
            }
        }
        $this->current = $segments;

        return $this;
    }

    public function string(string $name): self
    {
        return $this->add($name, 'string');
    }

    public function number(string $name): self
    {
        return $this->add($name, 'number');
    }

    public function integer(string $name): self
    {
        return $this->add($name, 'integer');
    }

    public function boolean(string $name): self
    {
        return $this->add($name, 'boolean');
    }

    public function money(string $name): self
    {
        $this->add($name, 'integer');
        $this->node()['x-cast'] = 'money';

        return $this;
    }

    public function date(string $name): self
    {
        return $this->add($name, 'string', 'date');
    }

    public function array(string $name, callable $builder): self
    {
        $child = new self($name);
        $builder($child);
        $this->add($name, 'array');
        $this->node()['items'] = $child->toJsonSchema();
        $this->children[$name] = ['schema' => $child, 'array' => true];

        return $this;
    }

    public function object(string $name, callable $builder): self
    {
        $child = new self($name);
        $builder($child);
        $this->add($name, 'object');
        $node = &$this->node();
        $node = $child->toJsonSchema();
        $this->children[$name] = ['schema' => $child, 'array' => false];

        return $this;
    }

    /** @return array<string, mixed> */
    private function &node(): array
    {
        $node = &$this->definition;
        foreach ($this->current as $segment) {
            $node = &$node['properties'][$segment];
        }

        return $node;
    }

    public function required(): self
    {
        $node = &$this->definition;
        foreach (array_slice($this->current, 0, -1) as $segment) {
            $node = &$node['properties'][$segment];
        } $name = end($this->current);
        $node['required'] = array_values(array_unique([...($node['required'] ?? []), $name]));

        return $this;
    }

    public function nullable(): self
    {
        $node = &$this->node();
        $node['type'] = [(string) $node['type'], 'null'];

        return $this;
    }

    /** @param list<string|int|float|bool|null> $values */
    public function enum(array $values): self
    {
        $this->node()['enum'] = $values;

        return $this;
    }

    public function description(string $text): self
    {
        $this->node()['description'] = $text;

        return $this;
    }

    public function regex(string $pattern): self
    {
        $this->node()['pattern'] = $pattern;

        return $this;
    }

    public function validator(callable $validator): self
    {
        $this->validators[implode('.', $this->current)] = $validator;

        return $this;
    }

    public function cast(callable $cast): self
    {
        $this->casts[implode('.', $this->current)] = $cast;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function cacheDefinition(): ?array
    {
        foreach ($this->children as $child) {
            if ($child['schema']->cacheDefinition() === null) {
                return null;
            }
        }

        return $this->casts === [] && $this->validators === [] ? ['name' => $this->name, 'schema' => $this->definition] : null;
    }

    /** @return array<string, mixed> */
    public function toJsonSchema(): array
    {
        return $this->definition;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalize(array $data): array
    {
        foreach ($this->children as $path => $child) {
            $value = data_get($data, $path);
            if (is_array($value)) {
                data_set($data, $path, $child['array'] ? array_map(fn ($item) => is_array($item) ? $child['schema']->normalize($item) : $item, $value) : $child['schema']->normalize($value));
            }
        }
        foreach ($this->casts as $key => $cast) {
            if (data_get($data, $key) !== null) {
                data_set($data, $key, $cast(data_get($data, $key)));
            }
        }

        return $data;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return $this->compileRules($this->definition);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, list<string>>
     */
    private function compileRules(array $node, string $prefix = ''): array
    {
        $rules = [];
        foreach ($node['properties'] ?? [] as $key => $field) {
            $path = $prefix.$key;
            $type = is_array($field['type'] ?? null) ? $field['type'][0] : ($field['type'] ?? 'string');
            $rules[$path] = [in_array($key, $node['required'] ?? [], true) ? 'required' : 'sometimes', $type === 'number' ? 'numeric' : ($type === 'object' ? 'array' : $type)];
            if (is_array($field['type'] ?? null) && in_array('null', $field['type'], true)) {
                $rules[$path] = array_map(fn ($rule) => $rule === 'required' ? 'present' : $rule, $rules[$path]);
                $rules[$path][] = 'nullable';
            } if (($field['format'] ?? null) === 'date') {
                $rules[$path][] = 'date_format:Y-m-d';
            } if ($type === 'object') {
                $rules = array_merge($rules, $this->compileRules($field, $path.'.'));
            } if ($type === 'array') {
                $rules = array_merge($rules, $this->compileRules($field['items'] ?? [], $path.'.*.'));
            }
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public function errors(array $data): array
    {
        $errors = $this->check($data, $this->definition, '');
        foreach ($this->children as $path => $child) {
            $value = data_get($data, $path);
            if (! is_array($value)) {
                continue;
            }
            $items = $child['array'] ? $value : [$value];
            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                } foreach ($child['schema']->errors($item) as $error) {
                    $errors[] = $path.'.'.($child['array'] ? $index.'.' : '').$error;
                }
            }
        }
        foreach ($this->validators as $path => $validator) {
            if (! $validator(data_get($data, $path))) {
                $errors[] = $path;
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<string>
     */
    private function check(mixed $value, array $schema, string $path): array
    {
        $errors = [];
        $types = (array) ($schema['type'] ?? 'object');
        $valid = false;
        foreach ($types as $type) {
            $valid = $valid || match ($type) {
                'null' => $value === null, 'object' => is_array($value) && ($value === [] || ! array_is_list($value)), 'array' => is_array($value) && array_is_list($value), 'integer' => is_int($value), 'number' => is_int($value) || is_float($value), 'boolean' => is_bool($value), 'string' => is_string($value), default => false
            };
        }
        if (! $valid) {
            return [$path ?: '$'];
        }
        if (is_int($value) || is_float($value)) {
            if ((isset($schema['minimum']) && $value < $schema['minimum']) || (isset($schema['maximum']) && $value > $schema['maximum'])) {
                $errors[] = $path;
            }
        }
        if (is_string($value)) {
            if ((isset($schema['minLength']) && mb_strlen($value) < $schema['minLength']) || (isset($schema['maxLength']) && mb_strlen($value) > $schema['maxLength'])) {
                $errors[] = $path;
            }
        }
        if (is_array($value)) {
            if ((isset($schema['minItems']) && count($value) < $schema['minItems']) || (isset($schema['maxItems']) && count($value) > $schema['maxItems'])) {
                $errors[] = $path;
            }
        }
        if (($schema['format'] ?? null) === 'email' && is_string($value) && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $errors[] = $path;
        }
        if (($schema['format'] ?? null) === 'uri' && is_string($value) && ! filter_var($value, FILTER_VALIDATE_URL)) {
            $errors[] = $path;
        }
        if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            $errors[] = $path;
        }
        if (is_string($value) && isset($schema['pattern']) && @preg_match('~'.str_replace('~', '\\~', $schema['pattern']).'~u', $value) !== 1) {
            $errors[] = $path;
        }
        if (($schema['format'] ?? null) === 'date' && is_string($value) && Normalizer::date($value) !== $value) {
            $errors[] = $path;
        }
        if (is_array($value) && in_array('object', $types, true)) {
            foreach ($schema['required'] ?? [] as $key) {
                if (! array_key_exists($key, $value)) {
                    $errors[] = ltrim($path.'.'.$key, '.');
                }
            } foreach ($value as $key => $item) {
                $child = ltrim($path.'.'.$key, '.');
                if (isset($schema['properties'][$key])) {
                    $errors = array_merge($errors, $this->check($item, $schema['properties'][$key], $child));
                } elseif (($schema['additionalProperties'] ?? true) === false) {
                    $errors[] = $child;
                }
            }
        }
        if (is_array($value) && in_array('array', $types, true)) {
            foreach ($value as $i => $item) {
                $errors = array_merge($errors, $this->check($item, $schema['items'] ?? [], $path.'.'.$i));
            }
        }

        return array_values(array_unique($errors));
    }
}
