<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

class LocationPicker extends Field
{
    protected string $view = 'filament.forms.components.location-picker';

    protected string | \Closure | null $latField = 'lat';
    protected string | \Closure | null $lngField = 'lng';

    public function latField(string | \Closure | null $name): static
    {
        $this->latField = $name;
        return $this;
    }

    public function lngField(string | \Closure | null $name): static
    {
        $this->lngField = $name;
        return $this;
    }

    public function getLatField(): string
    {
        $name = $this->evaluate($this->latField) ?? 'lat';
        $containerPath = $this->getContainer()->getStatePath();
        return $containerPath ? "{$containerPath}.{$name}" : $name;
    }

    public function getLngField(): string
    {
        $name = $this->evaluate($this->lngField) ?? 'lng';
        $containerPath = $this->getContainer()->getStatePath();
        return $containerPath ? "{$containerPath}.{$name}" : $name;
    }
}
