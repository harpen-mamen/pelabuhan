<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PerusahaanResource\Pages;
use App\Models\Perusahaan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PerusahaanResource extends Resource
{
    protected static ?string $model = Perusahaan::class;
    protected static ?string $navigationIcon = "heroicon-o-building-office";
    protected static ?string $navigationLabel = "Perusahaan";
    protected static ?string $navigationGroup = "Master Data";
    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make("Informasi Perusahaan")->schema([
                Forms\Components\TextInput::make("nama_perusahaan")->required()->maxLength(255),
                Forms\Components\Textarea::make("alamat")->required()->maxLength(500)->rows(3),
                Forms\Components\TextInput::make("npwp")->maxLength(25),
                Forms\Components\TextInput::make("kontak")->tel()->maxLength(20),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make("nama_perusahaan")->searchable()->sortable(),
            Tables\Columns\TextColumn::make("alamat")->searchable()->limit(50),
            Tables\Columns\TextColumn::make("npwp")->searchable()->sortable(),
            Tables\Columns\TextColumn::make("kontak")->searchable(),
        ])->actions([Tables\Actions\EditAction::make()])->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])->defaultSort("nama_perusahaan");
    }

    public static function getPages(): array
    {
        return ["index" => Pages\ListPerusahaans::route("/"),"create" => Pages\CreatePerusahaan::route("/create"),"edit" => Pages\EditPerusahaan::route("/{record}/edit")];
    }
}
