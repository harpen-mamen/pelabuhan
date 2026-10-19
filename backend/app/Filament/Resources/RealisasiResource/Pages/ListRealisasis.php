<?php

namespace App\Filament\Resources\RealisasiResource\Pages;

use App\Filament\Resources\RealisasiResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRealisasis extends ListRecords
{
    protected static string $resource = RealisasiResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
