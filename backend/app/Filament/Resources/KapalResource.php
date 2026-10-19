<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KapalResource\Pages;
use App\Models\Kapal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class KapalResource extends Resource
{
    protected static ?string $model = Kapal::class;
    protected static ?string $navigationIcon = "heroicon-o-truck";
    protected static ?string $navigationLabel = "Kapal";
    protected static ?string $modelLabel = "Kapal";
    protected static ?string $pluralModelLabel = "Kapal";
    protected static ?string $navigationGroup = "Master Data";
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make("Informasi Kapal")->schema([
                Forms\Components\TextInput::make("nama_kapal")->required()->maxLength(255),
                Forms\Components\TextInput::make("call_sign")->required()->maxLength(50),
                Forms\Components\Select::make("jenis_kapal")->required()->options(["kapal-kargo" => "Kapal Kargo","kapal-tongkat" => "Kapal Tongkat","kapal-penumpang" => "Kapal Penumpang","roro" => "Kapal RoRo","tug-boat" => "Tug Boat","lainnya" => "Lainnya"]),
                Forms\Components\TextInput::make("perusahaan_pelayaran")->maxLength(255),
            ])->columns(2),
            Forms\Components\Section::make("Kapasitas")->schema([
                Forms\Components\TextInput::make("kapasitas_dwt")->required()->numeric()->suffix("DWT"),
                Forms\Components\TextInput::make("kapasitas_grt")->required()->numeric()->suffix("GRT"),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make("nama_kapal")->searchable()->sortable(),
            Tables\Columns\TextColumn::make("call_sign")->searchable()->sortable(),
            Tables\Columns\BadgeColumn::make("jenis_kapal")->colors(["primary" => "kapal-kargo","info" => "kapal-tongkat","success" => "roro","warning" => "kapal-penumpang","gray" => "lainnya"])->sortable(),
            Tables\Columns\TextColumn::make("perusahaan_pelayaran")->searchable(),
            Tables\Columns\TextColumn::make("kapasitas_dwt")->numeric()->suffix(" DWT")->sortable(),
            Tables\Columns\TextColumn::make("kapasitas_grt")->numeric()->suffix(" GRT")->sortable(),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])->bulkActions([
            Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
        ])->defaultSort("nama_kapal");
    }

    public static function getPages(): array
    {
        return ["index" => Pages\ListKapals::route("/"),"create" => Pages\CreateKapal::route("/create"),"edit" => Pages\EditKapal::route("/{record}/edit")];
    }
}
