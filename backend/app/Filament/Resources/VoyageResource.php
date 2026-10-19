<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VoyageResource\Pages;
use App\Models\Voyage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VoyageResource extends Resource
{
    protected static ?string $model = Voyage::class;
    protected static ?string $navigationIcon = "heroicon-o-map";
    protected static ?string $navigationLabel = "Voyage";
    protected static ?string $modelLabel = "Voyage";
    protected static ?string $pluralModelLabel = "Voyage";
    protected static ?string $navigationGroup = "Operasional";
    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make("Informasi Voyage")->schema([
                Forms\Components\Select::make("id_kapal")->required()->relationship("kapal", "nama_kapal")->searchable()->preload()->label("Kapal"),
                Forms\Components\TextInput::make("nomor_voyage")->required()->maxLength(50),
            ])->columns(2),
            Forms\Components\Section::make("Rute & Jadwal")->schema([
                Forms\Components\TextInput::make("rute")->required()->maxLength(255)->placeholder("Contoh: Surabaya - Makassar - Tahuna"),
                Forms\Components\DatePicker::make("tgl_berangkat")->required()->label("Tanggal Berangkat"),
                Forms\Components\DatePicker::make("tgl_tiba")->required()->label("Tanggal Tiba"),
            ])->columns(3),
            Forms\Components\Section::make("Status")->schema([
                Forms\Components\Select::make("status")->required()->options(["direncanakan" => "Direncanakan","berlayar" => "Berlayar","sandar" => "Sandar","selesai" => "Selesai","dibatalkan" => "Dibatalkan"])->default("direncanakan"),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make("nomor_voyage")->searchable()->sortable(),
            Tables\Columns\TextColumn::make("kapal.nama_kapal")->searchable()->sortable()->label("Kapal"),
            Tables\Columns\TextColumn::make("rute")->searchable()->sortable(),
            Tables\Columns\TextColumn::make("tgl_berangkat")->date("d M Y")->sortable(),
            Tables\Columns\TextColumn::make("tgl_tiba")->date("d M Y")->sortable(),
            Tables\Columns\BadgeColumn::make("status")->colors(["gray" => "direncanakan","warning" => "berlayar","success" => "sandar","info" => "selesai","danger" => "dibatalkan"])->sortable(),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])->bulkActions([
            Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
        ])->defaultSort("tgl_berangkat", "desc");
    }

    public static function getPages(): array
    {
        return ["index" => Pages\ListVoyages::route("/"),"create" => Pages\CreateVoyage::route("/create"),"edit" => Pages\EditVoyage::route("/{record}/edit")];
    }
}
