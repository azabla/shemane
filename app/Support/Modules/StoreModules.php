<?php

namespace App\Support\Modules;

use App\Models\Store;
use App\Models\StoreModule;
use App\Services\StoreContext;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Liberu\Ecommerce\Contracts\Modules\ModuleDefinition;
use Liberu\Ecommerce\Contracts\Modules\ModuleNotActive;
use Liberu\Ecommerce\Contracts\Modules\ModuleState;
use Liberu\Ecommerce\Contracts\Modules\ReactsToActivation;
use Liberu\Ecommerce\Contracts\Modules\SettingField;

/**
 * Which modules each store has, and their settings. Every read names a store, and the instance is
 * scoped, so nothing carries between requests or jobs under Octane or Horizon. Callers authorize;
 * this enforces only what is true for everyone, such as that an uninstalled module cannot be enabled.
 */
final class StoreModules implements ModuleState
{
    /** @var array<string, ModuleDefinition>|null */
    private ?array $definitions = null;

    /** @var array<int, array<string, StoreModule>> */
    private array $rows = [];

    public function __construct(private readonly Container $container) {}

    /** @return array<string, ModuleDefinition> the modules this deployment booted that stores can use */
    public function definitions(): array
    {
        if ($this->definitions === null) {
            $this->definitions = [];

            foreach ($this->container->tagged(ModuleDefinition::class) as $definition) {
                $this->definitions[$definition->key()] = $definition;
            }
        }

        return $this->definitions;
    }

    public function definition(string $module): ?ModuleDefinition
    {
        return $this->definitions()[$module] ?? null;
    }

    public function currentStoreId(): ?int
    {
        return Store::current()?->id ?? StoreContext::forWrites();
    }

    public function isActive(string $module, ?int $storeId = null): bool
    {
        return $this->status($module, $storeId ?? $this->currentStoreId()) === 'active';
    }

    public function settings(string $module, ?int $storeId = null): array
    {
        $storeId ??= $this->currentStoreId();

        if (! $this->isActive($module, $storeId)) {
            throw ModuleNotActive::for($module, $storeId);
        }

        return $this->row($module, $storeId)?->settings ?? [];
    }

    /** @return 'not-installed'|'unavailable'|'available'|'unconfigured'|'active' */
    public function status(string $module, ?int $storeId): string
    {
        $definition = $this->definition($module);
        $row = $storeId === null ? null : $this->row($module, $storeId);

        return match (true) {
            $definition === null => 'not-installed',
            $row === null || ! $row->available => 'unavailable',
            ! $row->enabled => 'available',
            ! $this->isConfigured($definition, $row->settings ?? []) => 'unconfigured',
            default => 'active',
        };
    }

    /** @return array<string, ModuleDefinition> */
    public function availableTo(int $storeId): array
    {
        return array_filter($this->definitions(), fn (ModuleDefinition $d) => (bool) $this->row($d->key(), $storeId)?->available);
    }

    /** @return list<int> */
    public function storesAllowing(string $module): array
    {
        return StoreModule::withoutGlobalScope('store')->where('module', $module)->where('available', true)
            ->orderBy('store_id')->pluck('store_id')->all();
    }

    public function setAvailable(string $module, int $storeId, bool $available): void
    {
        $definition = $this->definition($module)
            ?? throw new ModuleActivationRefused("Module [{$module}] is not installed in this deployment.");
        $row = $this->row($module, $storeId);

        if (! $available && $row?->enabled) {
            $this->disable($module, $storeId);
        }

        $this->save($definition->key(), $storeId, ['available' => $available]);
    }

    public function enable(string $module, int $storeId): void
    {
        $definition = $this->definition($module);
        $status = $this->status($module, $storeId);

        if ($definition === null || $status === 'unavailable' || $status === 'not-installed') {
            throw new ModuleActivationRefused('This module is not available to this store.');
        }

        if (! $this->isConfigured($definition, $this->row($module, $storeId)?->settings ?? [])) {
            throw new ModuleActivationRefused('Complete this module\'s settings before enabling it.');
        }

        $this->save($module, $storeId, ['enabled' => true]);

        if ($definition instanceof ReactsToActivation) {
            $definition->activated($storeId);
        }
    }

    public function disable(string $module, int $storeId): void
    {
        $this->save($module, $storeId, ['enabled' => false]);

        $definition = $this->definition($module);
        if ($definition instanceof ReactsToActivation) {
            $definition->deactivated($storeId);
        }
    }

    /**
     * @param  array<string, mixed>  $input  a blank secret keeps the stored value
     *
     * @throws ValidationException
     */
    public function configure(string $module, int $storeId, array $input): void
    {
        $definition = $this->definition($module);
        $status = $this->status($module, $storeId);

        if ($definition === null || $status === 'unavailable' || $status === 'not-installed') {
            throw new ModuleActivationRefused('This module is not available to this store.');
        }

        $merged = $this->merge($definition, $this->row($module, $storeId)?->settings ?? [], $input);

        Validator::make($merged, $this->rules($definition))->validate();

        $errors = $definition->validateSettings($merged);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $this->save($module, $storeId, ['settings' => $merged]);
    }

    /** @return array<string, mixed> what a form may show: everything but the secrets */
    public function displayableSettings(string $module, int $storeId): array
    {
        $definition = $this->definition($module);
        $stored = $this->row($module, $storeId)?->settings ?? [];
        $secrets = array_map(fn (SettingField $f) => $f->key, array_filter($definition?->settings() ?? [], fn (SettingField $f) => $f->isSecret()));

        return array_diff_key($stored, array_flip($secrets));
    }

    public function hasStoredValue(string $module, int $storeId, string $key): bool
    {
        return filled($this->row($module, $storeId)?->settings[$key] ?? null);
    }

    /** @return array<string, list<string>> */
    public function rules(ModuleDefinition $definition): array
    {
        $rules = [];

        foreach ($definition->settings() as $field) {
            $rules[$field->key] = [
                $field->required && $field->type !== SettingField::BOOLEAN ? 'required' : 'nullable',
                ...match ($field->type) {
                    SettingField::URL => ['url'],
                    SettingField::BOOLEAN => ['boolean'],
                    SettingField::SELECT => ['in:'.implode(',', array_keys($field->options))],
                    default => ['string'],
                },
                ...$field->rules,
            ];
        }

        return $rules;
    }

    private function isConfigured(ModuleDefinition $definition, array $settings): bool
    {
        foreach ($definition->settings() as $field) {
            if ($field->required && $field->type !== SettingField::BOOLEAN && blank($settings[$field->key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /** Only declared fields are kept, so a crafted form cannot store anything else. */
    private function merge(ModuleDefinition $definition, array $stored, array $input): array
    {
        $merged = [];

        foreach ($definition->settings() as $field) {
            $value = $input[$field->key] ?? null;
            $merged[$field->key] = $field->isSecret() && blank($value) ? ($stored[$field->key] ?? null) : $value;
        }

        return $merged;
    }

    private function row(string $module, int $storeId): ?StoreModule
    {
        $this->rows[$storeId] ??= StoreModule::withoutGlobalScope('store')->where('store_id', $storeId)->get()->keyBy('module')->all();

        return $this->rows[$storeId][$module] ?? null;
    }

    private function save(string $module, int $storeId, array $attributes): void
    {
        StoreModule::withoutGlobalScope('store')->updateOrCreate(['store_id' => $storeId, 'module' => $module], $attributes);
        unset($this->rows[$storeId]);
    }
}
