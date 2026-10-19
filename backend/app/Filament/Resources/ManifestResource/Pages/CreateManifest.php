<?php

namespace App\Filament\Resources\ManifestResource\Pages;

use App\Filament\Resources\ManifestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateManifest extends CreateRecord
{
    protected static string $resource = ManifestResource::class;
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data["nomor_manifest"] = "MNF-" . date("Ymd") . "-" . strtoupper(substr(uniqid(), -6));
        return $data;
    }
}
