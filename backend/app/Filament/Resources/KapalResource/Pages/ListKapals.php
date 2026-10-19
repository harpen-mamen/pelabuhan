<?php

namespace App\Filament\Resources\KapalResource\Pages;

use App\Filament\Resources\KapalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKapals extends ListRecords
{
    protected static string $resource = KapalResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
