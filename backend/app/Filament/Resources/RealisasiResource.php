<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RealisasiResource\Pages;
use App\Models\RealisasiBongkarMuat;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RealisasiResource extends Resource
{
    protected static ?string $model = RealisasiBongkarMuat::class;
    protected static ?string $navigationIcon = "heroicon-o-clipboard-document-check";
    protected static ?string $navigationLabel = "Realisasi Bongkar Muat";
    protected static ?string $navigationGroup = "Operasional";
    protected static ?int $navigationSort = 24;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make("Detail Realisasi")->schema([
                Forms\Components\Select::make("id_manifest_detail")->required()->relationship("manifestDetail", "id_manifest_detail")->searchable()->preload(),
                Forms\Components\Select::make("id_checker")->required()->default(auth()->id())->relationship("checker", "nama"),
            ])->columns(2),
            Forms\Components\Section::make("Jumlah Realisasi")->schema([
                Forms\Components\TextInput::make("jumlah_realisasi")->required()->numeric()->suffix("unit"),
                Forms\Components\TextInput::make("selisih")->numeric()->suffix("unit")->disabled(),
            ])->columns(2),
            Forms\Components\Section::make("Keterangan")->schema([
                Forms\Components\Textarea::make("keterangan_selisih")->maxLength(500)->rows(3),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make("manifestDetail.manifest.nomor_manifest")->searchable()->sortable()->label("Manifest"),
            Tables\Columns\TextColumn::make("manifestDetail.barang.nama_barang")->searchable()->sortable()->label("Barang"),
            Tables\Columns\TextColumn::make("manifestDetail.jumlah_manifest")->numeric()->suffix(" unit")->label("Jml Manifest"),
            Tables\Columns\TextColumn::make("jumlah_realisasi")->numeric()->suffix(" unit")->sortable(),
            Tables\Columns\TextColumn::make("selisih")->numeric()->suffix(" unit")->sortable(),
            Tables\Columns\TextColumn::make("checker.nama")->sortable()->label("Checker"),
            Tables\Columns\IconColumn::make("sync_status")->boolean()->sortable()->label("Synced"),
            Tables\Columns\TextColumn::make("waktu_input")->dateTime("d M Y, H:i")->sortable(),
        ])->actions([Tables\Actions\EditAction::make()])->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])->defaultSort("waktu_input", "desc");
    }

    public static function getPages(): array
    {
        return ["index" => Pages\ListRealisasis::route("/"),"create" => Pages\CreateRealisasi::route("/create"),"edit" => Pages\EditRealisasi::route("/{record}/edit")];
    }
}
