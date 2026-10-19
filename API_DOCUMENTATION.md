# DOKUMENTASI API PELABUHAN TAHUNA

## Overview
API ini digunakan untuk sistem manajemen pelabuhan dengan role-based access control.

## Authentication
```
POST /api/auth/login
Body: { "username": "xxx", "password": "xxx" }
Response: { "access_token": "...", "token_type": "bearer" }
```

Login mengembalikan token di `data.token` dan profil di `data.user`. Registrasi mandiri hanya membuat akun pelanggan; akun staf dibuat oleh `super_admin`. Hanya akun `super_admin` aktif yang dapat membuka panel web Filament. Role lain memakai aplikasi Flutter dan API.

## Role Definitions

| Role | Description | Color |
|------|-------------|-------|
| super_admin | Admin utama - Panel web Filament dan akses penuh | danger |
| admin | Admin operasional - Akses melalui aplikasi mobile | primary |
| operator | Operator pelabuhan - Membuat & mengelola booking, manifest, voyage | primary |
| upp | Petugas UPP - Verifikasi & validasi manifest | warning |
| checker | Checker dermaga - Input realise bongkar/muat | info |
| pelanggan | Pelanggan - Membuat booking & tracking pengiriman | success |
| manajer | Manajer - View laporan & analytics | secondary |

## Endpoint Permissions

### Booking
| Method | Endpoint | Allowed Roles |
|--------|----------|---------------|
| GET | /api/booking | all |
| GET | /api/booking/{id} | all |
| POST | /api/booking | admin, operator, pelanggan |
| PUT | /api/booking/{id} | admin, operator |
| PUT | /api/booking/{id}/status | admin, operator |
| DELETE | /api/booking/{id} | admin |
| GET | /api/booking/{id}/transitions | admin, operator |
| GET | /api/tracking/{kode} | public |

### Manifest
| Method | Endpoint | Allowed Roles |
|--------|----------|---------------|
| GET | /api/manifest | admin, operator, upp, manajer |
| GET | /api/manifest/{id} | admin, operator, upp, manajer |
| POST | /api/manifest | admin, operator |
| PUT | /api/manifest/{id}/submit | admin, operator |
| PUT | /api/manifest/{id}/verifikasi | admin, upp |

### Realisasi
| Method | Endpoint | Allowed Roles |
|--------|----------|---------------|
| GET | /api/realisasi | all |
| POST | /api/realisasi | admin, checker |
| GET | /api/realisasi/selisih | admin, operator, upp |

### Status Flow

#### Booking Status
```
menunggu -> diproses -> manifest -> siap_dimuat -> dimuat -> dalam_perjalanan -> diterima
      \-> ditolak
      \-> dibatalkan
```

#### Manifest Status
```
draft -> menunggu_verifikasi -> disetujui -> selesai
                          \-> ditolak -> draft
                          \-> perlu_klarifikasi -> selesai
```

## Error Codes

| Code | Description |
|------|-------------|
| AUTH_001 | Unauthenticated |
| AUTH_002 | Akun nonaktif |
| ROLE_001 | Role tidak diizinkan |
| VALIDATION_ERROR | Validasi gagal |
| BOOKING_NOT_FOUND | Booking tidak ditemukan |
| BOOKING_FINAL_STATE | Booking sudah final |
| MANIFEST_EXISTS | Booking sudah punya manifest |
| MANIFEST_NOT_APPROVED | Manifest belum disetujui UPP |
| MANIFEST_INVALID_STATUS | Status manifest tidak valid |
| INVALID_STATUS_TRANSITION | Transisi status tidak valid |
| REASON_REQUIRED | Alasan harus diisi |
