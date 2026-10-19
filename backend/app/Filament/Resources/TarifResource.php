<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TarifResource\Pages;
use App\Models\Tarif;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TarifResource extends Resource
{
    protected static ?string $model = Tarif::class;
    protected static ?string $navigationIcon = "heroicon-o-currency-dollar";
    protected static ?string $navigationLabel = "Tarif Bongkar Muat";
    protected static ?string $navigationGroup = "Master Data";
    protected static ?int $navigationSort = 13;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make("Jenis Layanan")->schema([
                Forms\Components\TextInput::make("jenis_layanan")->required()->maxLength(100),
                Forms\Components\Select::make("kategori_barang")->required()->options(["umum" => "Barang Umum","berbahaya" => "Barang Berbahaya","bahan_pokok" => "Bahan Pokok","b3" => "B3","lainnya" => "Lainnya"]),
                Forms\Components\TextInput::make("rute")->required()->maxLength(255),
            ])->columns(3),
            Forms\Components\Section::make("Besaran Tarif")->schema([
                Forms\Components\TextInput::make("tarif_per_kg")->required()->numeric()->prefix("Rp "),
                Forms\Components\TextInput::make("tarif_per_m3")->required()->numeric()->prefix("Rp "),
            ])->columns(2),
            Forms\Components\Section::make("Periode Berlaku")->schema([
                Forms\Components\DatePicker::make("berlaku_dari")->required()->default(now()),
                Forms\Components\DatePicker::make("berlaku_hingga"),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make("jenis_layanan")->searchable()->sortable(),
            Tables\Columns\BadgeColumn::make("kategori_barang")->colors(["primary" => "umum","danger" => "berbahaya","success" => "bahan_pokok","warning" => "b3","gray" => "lainnya"])->sortable(),
            Tables\Columns\TextColumn::make("rute")->searchable()->sortable(),
            Tables\Columns\TextColumn::make("tarif_per_kg")->money("IDR")->sortable(),
            Tables\Columns\TextColumn::make("tarif_per_m3")->money("IDR")->sortable(),
            Tables\Columns\TextColumn::make("berlaku_dari")->date("d M Y")->sortable(),
            Tables\Columns\TextColumn::make("berlaku_hingga")->date("d M Y")->sortable(),
        ])->actions([Tables\Actions\EditAction::make()])->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])->defaultSort("jenis_layanan");
    }

    public static function getPages(): array
    {
        return ["index" => Pages\ListTarifs::route("/"),"create" => Pages\CreateTarif::route("/create"),"edit" => Pages\EditTarif::route("/{record}/edit")];
    }
}
