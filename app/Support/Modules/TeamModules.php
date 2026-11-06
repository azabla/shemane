<?php

namespace App\Support\Modules;

use App\Models\ModuleAvailability;
use App\Models\Store;
use App\Models\TeamModule;
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
 * The platform offers installed modules; a team enables and configures them; its stores inherit
 * that. Modules still ask by store through ModuleState. Scoped, so nothing carries between requests
 * or jobs under Octane or Horizon. Callers authorize; this enforces only what is true for everyone.
 */
final class TeamModules implements ModuleState
{
    /** @var array<string, ModuleDefinition>|null */
    private ?array $definitions = null;

    /** @var array<string, bool>|null */
    private ?array $availability = null;

    /** @var array<int, array<string, TeamModule>> */
    private array $rows = [];

    /** @var array<int, int|null> */
    private array $teams = [];

    public function __construct(private readonly Container $container) {}

    /** @return array<string, ModuleDefinition> the booted modules a team can use */
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

    public function teamOfStore(int $storeId): ?int
    {
        if (! array_key_exists($storeId, $this->teams)) {
            $team = Store::query()->whereKey($storeId)->value('team_id');
            $this->teams[$storeId] = $team === null ? null : (int) $team;
        }

        return $this->teams[$storeId];
    }

    public function isActive(string $module, ?int $storeId = null): bool
    {
        $teamId = $this->teamFor($storeId);

        return $teamId !== null && $this->status($module, $teamId) === 'active';
    }

    public function settings(string $module, ?int $storeId = null): array
    {
        $storeId ??= $this->currentStoreId();
        $teamId = $this->teamFor($storeId);

        if ($teamId === null || ! $this->isActive($module, $storeId)) {
            throw ModuleNotActive::for($module, $storeId);
        }

        return $this->row($module, $teamId)?->settings ?? [];
    }

    public function isAvailable(string $module): bool
    {
        $this->availability ??= ModuleAvailability::query()->pluck('available', 'module')->map(fn ($v) => (bool) $v)->all();

        return $this->definition($module) !== null && ($this->availability[$module] ?? false);
    }

    /** @return array<string, ModuleDefinition> */
    public function available(): array
    {
        return array_filter($this->definitions(), fn (ModuleDefinition $d) => $this->isAvailable($d->key()));
    }

    /** @return 'not-installed'|'unavailable'|'available'|'unconfigured'|'active' */
    public function status(string $module, int $teamId): string
    {
        $definition = $this->definition($module);
        $row = $this->row($module, $teamId);

        return match (true) {
            $definition === null => 'not-installed',
            ! $this->isAvailable($module) => 'unavailable',
            $row === null || ! $row->enabled => 'available',
            ! $this->isConfigured($definition, $row->settings ?? []) => 'unconfigured',
            default => 'active',
        };
    }

    /** @return list<int> */
    public function teamsEnabling(string $module): array
    {
        return TeamModule::query()->where('module', $module)->where('enabled', true)
            ->orderBy('team_id')->pluck('team_id')->map(fn ($id) => (int) $id)->all();
    }

    /** Withdrawing a module turns it off for every team, which keeps its settings. */
    public function setAvailable(string $module, bool $available): void
    {
        $definition = $this->definition($module)
            ?? throw new ModuleActivationRefused("Module [{$module}] is not installed in this deployment.");

        if (! $available) {
            foreach ($this->teamsEnabling($module) as $teamId) {
                $this->disable($module, $teamId);
            }
        }

        ModuleAvailability::query()->updateOrCreate(['module' => $definition->key()], ['available' => $available]);
        $this->availability = null;
    }

    public function enable(string $module, int $teamId): void
    {
        $definition = $this->offered($module);

        if (! $this->isConfigured($definition, $this->row($module, $teamId)?->settings ?? [])) {
            throw new ModuleActivationRefused('Complete this module\'s settings before enabling it.');
        }

        $this->save($module, $teamId, ['enabled' => true]);
        $this->notifyStores($definition, $teamId, activated: true);
    }

    public function disable(string $module, int $teamId): void
    {
        $wasEnabled = (bool) $this->row($module, $teamId)?->enabled;
        $this->save($module, $teamId, ['enabled' => false]);

        $definition = $this->definition($module);
        if ($wasEnabled && $definition !== null) {
            $this->notifyStores($definition, $teamId, activated: false);
        }
    }

    /** A store created in a team that already uses a module gets it, and the module hears about it. */
    public function storeJoined(Store $store): void
    {
        $this->teams[$store->id] = (int) $store->team_id;

        foreach ($this->definitions() as $key => $definition) {
            if ($definition instanceof ReactsToActivation && $this->status($key, (int) $store->team_id) === 'active') {
                $definition->activated($store->id);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $input  a blank secret keeps the stored value
     *
     * @throws ValidationException
     */
    public function configure(string $module, int $teamId, array $input): void
    {
        $definition = $this->offered($module);
        $merged = $this->merge($definition, $this->row($module, $teamId)?->settings ?? [], $input);

        Validator::make($merged, $this->rules($definition))->validate();

        $errors = $definition->validateSettings($merged);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $this->save($module, $teamId, ['settings' => $merged]);
    }

    /** @return array<string, mixed> what a form may show: everything but the secrets */
    public function displayableSettings(string $module, int $teamId): array
    {
        $definition = $this->definition($module);
        $stored = $this->row($module, $teamId)?->settings ?? [];
        $secrets = array_map(fn (SettingField $f) => $f->key, array_filter($definition?->settings() ?? [], fn (SettingField $f) => $f->isSecret()));

        return array_diff_key($stored, array_flip($secrets));
    }

    public function hasStoredValue(string $module, int $teamId, string $key): bool
    {
        return filled($this->row($module, $teamId)?->settings[$key] ?? null);
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

    private function teamFor(?int $storeId): ?int
    {
        $storeId ??= $this->currentStoreId();

        return $storeId === null ? null : $this->teamOfStore($storeId);
    }

    private function offered(string $module): ModuleDefinition
    {
        $definition = $this->definition($module);

        if ($definition === null || ! $this->isAvailable($module)) {
            throw new ModuleActivationRefused('This module is not available to your team.');
        }

        return $definition;
    }

    private function notifyStores(ModuleDefinition $definition, int $teamId, bool $activated): void
    {
        if (! $definition instanceof ReactsToActivation) {
            return;
        }

        foreach (Store::query()->where('team_id', $teamId)->orderBy('id')->pluck('id') as $storeId) {
            $activated ? $definition->activated((int) $storeId) : $definition->deactivated((int) $storeId);
        }
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

    private function row(string $module, int $teamId): ?TeamModule
    {
        $this->rows[$teamId] ??= TeamModule::query()->where('team_id', $teamId)->get()->keyBy('module')->all();

        return $this->rows[$teamId][$module] ?? null;
    }

    private function save(string $module, int $teamId, array $attributes): void
    {
        TeamModule::query()->updateOrCreate(['team_id' => $teamId, 'module' => $module], $attributes);
        unset($this->rows[$teamId]);
    }
}
