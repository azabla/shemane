<?php

namespace Liberu\Ecommerce\Contracts\Modules;

/** One per-store setting. The core stores all of them encrypted and never re-displays a secret. */
final readonly class SettingField
{
    public const TEXT = 'text';

    public const SECRET = 'secret';

    public const URL = 'url';

    public const BOOLEAN = 'boolean';

    public const SELECT = 'select';

    /**
     * @param  list<string>  $rules  Laravel validation rules, beyond what the type and `required` imply
     * @param  array<string, string>  $options  value => label, for a select
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $type = self::TEXT,
        public bool $required = false,
        public array $rules = [],
        public array $options = [],
        public ?string $help = null,
    ) {}

    public function isSecret(): bool
    {
        return $this->type === self::SECRET;
    }
}
