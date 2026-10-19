<?php

namespace App\Filament\Resources\RealisasiResource\Pages;

use App\Filament\Resources\RealisasiResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRealisasi extends CreateRecord
{
    protected static string $resource = RealisasiResource::class;
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data["waktu_input"] = now();
        $data["sync_status"] = true;
        return $data;
    }
}
