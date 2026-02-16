<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $guardName = 'admin';

        $this->seedSuperAdminRole($guardName);

        $this->upsertTranslatableRole(
            guardName: $guardName,
            slug: 'reviewer',
            translatedName: [
                'en' => 'Reviewer',
                'ar' => 'مراجع',
            ],
        );

        $this->upsertTranslatableRole(
            guardName: $guardName,
            slug: 'review-supervisor',
            translatedName: [
                'en' => 'Review Supervisor',
                'ar' => 'مشرف المراجعين',
            ],
        );
    }

    protected function seedSuperAdminRole(string $guardName): void
    {
        $translatedName = [
            'en' => 'Admin',
            'ar' => 'ادمن',
        ];

        $existingRole = DB::table('roles')
            ->where('name', 'super_admin')
            ->where('guard_name', $guardName)
            ->first();

        if ($existingRole) {
            $currentTranslatedName = json_decode((string) ($existingRole->translated_name ?? '[]'), true);

            if (! is_array($currentTranslatedName)) {
                $currentTranslatedName = [];
            }

            $currentTranslatedName['en'] = $translatedName['en'];
            $currentTranslatedName['ar'] = $translatedName['ar'];

            DB::table('roles')
                ->where('id', $existingRole->id)
                ->update([
                    'translated_name' => json_encode($currentTranslatedName, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('roles')->insert([
            'name' => 'super_admin',
            'guard_name' => $guardName,
            'translated_name' => json_encode($translatedName, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function upsertTranslatableRole(string $guardName, string $slug, array $translatedName): void
    {
        $role = Role::query()
            ->where('guard_name', $guardName)
            ->where(function (Builder $query) use ($slug, $translatedName): void {
                $query
                    ->where('name', $slug)
                    ->orWhere('translated_name->en', $translatedName['en']);
            })
            ->first();

        if (! $role) {
            Role::query()->create([
                'guard_name' => $guardName,
                'translated_name' => $translatedName,
            ]);

            return;
        }

        $currentTranslatedName = is_array($role->translated_name) ? $role->translated_name : [];

        $role->translated_name = array_merge($currentTranslatedName, [
            'en' => $translatedName['en'],
            'ar' => $translatedName['ar'],
        ]);
        $role->save();
    }
}
