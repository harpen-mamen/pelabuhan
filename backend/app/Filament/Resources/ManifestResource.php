<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ManifestResource\Pages;
use App\Models\Manifest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ManifestResource extends Resource
{
    protected static ?string $model = Manifest::class;
    protected static ?string $navigationIcon = "heroicon-o-document-text";
    protected static ?string $navigationLabel = "Manifest";
    protected static ?string $navigationGroup = "Operasional";
    protected static ?int $navigationSort = 22;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make("Informasi Manifest")->schema([
                Forms\Components\TextInput::make("nomor_manifest")->disabled(),
                Forms\Components\DatePicker::make("tgl_manifest")->required()->default(now()),
                Forms\Components\Select::make("id_voyage")->required()->relationship("voyage", "nomor_voyage")->searchable()->preload(),
                Forms\Components\Select::make("id_booking")->required()->relationship("booking", "kode_booking")->searchable()->preload(),
            ])->columns(2),
            Forms\Components\Section::make("Total Muatan")->schema([
                Forms\Components\TextInput::make("total_berat")->numeric()->suffix("Kg"),
                Forms\Components\TextInput::make("total_volume")->numeric()->suffix("M3"),
            ])->columns(2),
            Forms\Components\Section::make("Status")->schema([
                Forms\Components\Select::make("status")->required()->options(["draft" => "Draft","menunggu_verifikasi" => "Menunggu Verifikasi","disetujui" => "Disetujui","ditolak" => "Ditolak","revisi" => "Revisi","selesai" => "Selesai"])->default("draft"),
                Forms\Components\Textarea::make("catatan")->maxLength(500),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make("nomor_manifest")->searchable()->sortable(),
            Tables\Columns\TextColumn::make("tgl_manifest")->date("d M Y")->sortable(),
            Tables\Columns\TextColumn::make("voyage.nomor_voyage")->searchable()->sortable(),
            Tables\Columns\TextColumn::make("voyage.kapal.nama_kapal")->sortable(),
            Tables\Columns\TextColumn::make("total_berat")->numeric()->suffix(" Kg")->sortable(),
            Tables\Columns\BadgeColumn::make("status")->colors(["gray" => "draft","warning" => "menunggu_verifikasi","success" => "disetujui","danger" => "ditolak","info" => "revisi","primary" => "selesai"])->sortable(),
        ])->actions([Tables\Actions\EditAction::make()])->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])->defaultSort("created_at", "desc");
    }

    public static function getPages(): array
    {
        return ["index" => Pages\ListManifests::route("/"),"create" => Pages\CreateManifest::route("/create"),"edit" => Pages\EditManifest::route("/{record}/edit")];
    }
}
