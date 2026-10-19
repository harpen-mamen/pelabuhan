<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = "heroicon-o-user-group";
    protected static ?string $navigationLabel = "Pengguna";
    protected static ?string $modelLabel = "Pengguna";
    protected static ?string $pluralModelLabel = "Pengguna";
    protected static ?string $navigationGroup = "Manajemen User";
    protected static ?int $navigationSort = 1;

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make("Informasi Akun")->schema([
                Forms\Components\TextInput::make("nama")->required()->maxLength(255),
                Forms\Components\TextInput::make("username")->required()->maxLength(255)->unique(ignoreRecord: true),
                Forms\Components\TextInput::make("email")->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                Forms\Components\TextInput::make("kontak")->tel()->maxLength(20),
            ])->columns(2),
            Forms\Components\Section::make("Keamanan & Akses")->schema([
                Forms\Components\TextInput::make("password")->password()->required(fn (string $operation): bool => $operation === "create")->minLength(8)->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null),
                Forms\Components\Select::make("role")->required()->options(["super_admin" => "Admin Utama","admin" => "Admin Operasional","operator" => "Operator","upp" => "Petugas UPP","checker" => "Checker","pelanggan" => "Pelanggan","manajer" => "Manajer","kantor_perhubungan" => "Kantor Perhubungan"]),
                Forms\Components\Toggle::make("is_active")->label("Akun Aktif")->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make("nama")->searchable()->sortable(),
            Tables\Columns\TextColumn::make("username")->searchable()->sortable(),
            Tables\Columns\TextColumn::make("email")->searchable()->sortable(),
            Tables\Columns\BadgeColumn::make("role")->colors(["danger" => "super_admin","warning" => "admin","primary" => "operator","info" => "upp","success" => "checker","gray" => "pelanggan","purple" => "manajer","teal" => "kantor_perhubungan"])->sortable(),
            Tables\Columns\IconColumn::make("is_active")->boolean()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make("role")->options(["super_admin" => "Admin Utama","admin" => "Admin Operasional","operator" => "Operator","upp" => "Petugas UPP","checker" => "Checker","pelanggan" => "Pelanggan","manajer" => "Manajer","kantor_perhubungan" => "Kantor Perhubungan"]),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ])->defaultSort("created_at", "desc");
    }

    public static function getPages(): array
    {
        return ["index" => Pages\ListUsers::route("/"),"create" => Pages\CreateUser::route("/create"),"edit" => Pages\EditUser::route("/{record}/edit")];
    }
}
