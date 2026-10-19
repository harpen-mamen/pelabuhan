<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PelangganResource\Pages;
use App\Models\Pelanggan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PelangganResource extends Resource
{
    protected static ?string $model = Pelanggan::class;
    protected static ?string $navigationIcon = "heroicon-o-user-group";
    protected static ?string $navigationLabel = "Pelanggan";
    protected static ?string $navigationGroup = "Master Data";
    protected static ?int $navigationSort = 12;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make("Data Pelanggan")->schema([
                Forms\Components\TextInput::make("nama")->required()->maxLength(255),
                Forms\Components\Textarea::make("alamat")->required()->maxLength(500)->rows(3),
                Forms\Components\TextInput::make("kontak")->tel()->maxLength(20),
            ])->columns(2),
            Forms\Components\Section::make("Relasi")->schema([
                Forms\Components\Select::make("id_user")->relationship("user", "nama")->searchable()->preload(),
                Forms\Components\Select::make("id_perusahaan")->relationship("perusahaan", "nama_perusahaan")->searchable()->preload(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make("nama")->searchable()->sortable(),
            Tables\Columns\TextColumn::make("perusahaan.nama_perusahaan")->searchable()->sortable(),
            Tables\Columns\TextColumn::make("alamat")->searchable()->limit(50),
            Tables\Columns\TextColumn::make("kontak")->searchable(),
        ])->actions([Tables\Actions\EditAction::make()])->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])->defaultSort("nama");
    }

    public static function getPages(): array
    {
        return ["index" => Pages\ListPelangans::route("/"),"create" => Pages\CreatePelanggan::route("/create"),"edit" => Pages\EditPelanggan::route("/{record}/edit")];
    }
}
