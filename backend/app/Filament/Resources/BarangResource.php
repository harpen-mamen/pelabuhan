<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BarangResource\Pages;
use App\Models\Barang;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BarangResource extends Resource
{
    protected static ?string $model = Barang::class;
    protected static ?string $navigationIcon = "heroicon-o-archive-box";
    protected static ?string $navigationLabel = "Barang";
    protected static ?string $navigationGroup = "Operasional";
    protected static ?int $navigationSort = 23;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make("Informasi Barang")->schema([
                Forms\Components\Select::make("id_booking")->required()->relationship("booking", "kode_booking")->searchable()->preload(),
                Forms\Components\TextInput::make("nama_barang")->required()->maxLength(255),
            ])->columns(2),
            Forms\Components\Section::make("Kategori & Ukuran")->schema([
                Forms\Components\Select::make("kategori")->required()->options(["umum" => "Barang Umum","berbahaya" => "Barang Berbahaya","bahan_pokok" => "Bahan Pokok","b3" => "B3","lainnya" => "Lainnya"]),
                Forms\Components\TextInput::make("jumlah_kemasan")->required()->numeric()->suffix("kemasan"),
                Forms\Components\TextInput::make("berat_kg")->required()->numeric()->suffix("Kg"),
                Forms\Components\TextInput::make("volume_m3")->required()->numeric()->suffix("M3"),
            ])->columns(2),
            Forms\Components\Section::make("Status")->schema([
                Forms\Components\Select::make("status_muatan")->required()->options(["belum_dimuat" => "Belum Dimuat","dimuat" => "Sudah Dimuat","dibongkar" => "Sudah Dibongkar"])->default("belum_dimuat"),
                Forms\Components\TextInput::make("dokumen_izin")->maxLength(255),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make("nama_barang")->searchable()->sortable(),
            Tables\Columns\TextColumn::make("booking.kode_booking")->searchable()->sortable(),
            Tables\Columns\BadgeColumn::make("kategori")->colors(["primary" => "umum","danger" => "berbahaya","success" => "bahan_pokok","warning" => "b3","gray" => "lainnya"])->sortable(),
            Tables\Columns\TextColumn::make("jumlah_kemasan")->numeric()->sortable(),
            Tables\Columns\TextColumn::make("berat_kg")->numeric()->suffix(" Kg")->sortable(),
            Tables\Columns\BadgeColumn::make("status_muatan")->colors(["gray" => "belum_dimuat","primary" => "dimuat","success" => "dibongkar"])->sortable(),
        ])->actions([Tables\Actions\EditAction::make()])->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])->defaultSort("created_at", "desc");
    }

    public static function getPages(): array
    {
        return ["index" => Pages\ListBarangs::route("/"),"create" => Pages\CreateBarang::route("/create"),"edit" => Pages\EditBarang::route("/{record}/edit")];
    }
}
