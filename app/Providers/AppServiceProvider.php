<?php

namespace App\Providers;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Table;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch
                ->locales(['ar', 'en']);
        });

        TranslatableTabs::configureUsing(function (TranslatableTabs $component) {
            $component
                // locales labels
                ->localesLabels([
                    'ar' => __('locales.ar'),
                    'en' => __('locales.en')
                ])
                // default locales
                ->locales(['ar', 'en']);
        });

        // Shared table chrome for every admin table: a friendlier search box,
        // labeled filter/column buttons instead of bare icons, instantly
        // applied filters, and striped rows.
        Table::configureUsing(function (Table $table): void {
            $table
                // Plain numeric dates (23-8-2026 21:31:00) — no month names.
                ->defaultDateDisplayFormat('j-n-Y')
                ->defaultDateTimeDisplayFormat('j-n-Y H:i:s')
                ->striped()
                ->searchPlaceholder(__('Search by name or registration number...'))
                ->deferFilters(false)
                ->filtersFormWidth(Width::Medium)
                ->filtersTriggerAction(fn (Action $action): Action => $action
                    ->button()
                    ->label(__('Filters'))
                    ->color('gray'))
                ->columnManagerTriggerAction(fn (Action $action): Action => $action
                    ->button()
                    ->label(__('Columns'))
                    ->color('gray'));
        });

        // Same numeric date shape on view pages and forms (infolists/schemas).
        Schema::configureUsing(function (Schema $schema): void {
            $schema
                ->defaultDateDisplayFormat('j-n-Y')
                ->defaultDateTimeDisplayFormat('j-n-Y H:i:s');
        });
    }
}
