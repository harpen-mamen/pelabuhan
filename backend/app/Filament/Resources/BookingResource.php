<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingResource\Pages;
use App\Models\Booking;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;
    protected static ?string $navigationIcon = "heroicon-o-document-check";
    protected static ?string $navigationLabel = "Booking";
    protected static ?string $navigationGroup = "Operasional";
    protected static ?int $navigationSort = 21;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make("kode_booking")->searchable()->sortable(),
            Tables\Columns\TextColumn::make("tgl_booking")->date("d M Y")->sortable(),
            Tables\Columns\TextColumn::make("pelanggan.nama")->searchable()->sortable(),
            Tables\Columns\TextColumn::make("asal")->searchable(),
            Tables\Columns\TextColumn::make("tujuan")->searchable(),
            Tables\Columns\BadgeColumn::make("status")->colors(["gray" => "menunggu","warning" => "diproses","primary" => "siap_dimuat","success" => ["dimuat","diterima"],"danger" => ["ditolak","dibatalkan"]])->sortable(),
        ])->defaultSort("created_at", "desc");
    }

    public static function getPages(): array
    {
        return ["index" => Pages\ListBookings::route("/")];
    }
}
