<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class EditRole extends EditRecord
{
    public Collection $permissions;

    protected static string $resource = RoleResource::class;

    protected function getActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $excludedKeys = ['name', 'translated_name', 'guard_name', 'select_all', Utils::getTenantModelForeignKey()];

        $this->permissions = collect($data)
            ->except($excludedKeys)
            ->values()
            ->flatten()
            ->filter()
            ->unique();

        if (Utils::isTenancyEnabled() && Arr::has($data, Utils::getTenantModelForeignKey()) && filled($data[Utils::getTenantModelForeignKey()])) {
            return Arr::only($data, ['translated_name', 'guard_name', Utils::getTenantModelForeignKey()]);
        }

        return Arr::only($data, ['translated_name', 'guard_name']);
    }

    protected function afterSave(): void
    {
        $permissionModels = collect();
        $guardName = (string) ($this->record->guard_name ?? Utils::getFilamentAuthGuard());

        $this->permissions->each(function (string $permission) use ($permissionModels, $guardName): void {
            $permissionModels->push(Utils::getPermissionModel()::firstOrCreate([
                'name' => $permission,
                'guard_name' => $guardName,
            ]));
        });

        // @phpstan-ignore-next-line
        $this->record->syncPermissions($permissionModels);
    }
}
