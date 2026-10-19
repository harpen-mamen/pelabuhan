# SIMuatanTahuna Mobile

Flutter app for operational staff and customers. The Laravel + Filament web panel is reserved for `super_admin`.

## Requirements

- Flutter SDK (stable)
- Laravel API running from `backend/`
- MySQL database configured in `backend/.env`

## First run

From this directory, generate the platform folders once, then fetch packages:

```sh
flutter create --platforms=android,ios .
flutter pub get
```

Run on the Android emulator:

```sh
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api
```

For a physical Android device on the same Wi-Fi, run Laravel so it accepts LAN connections:

```sh
cd ../backend
php artisan serve --host=0.0.0.0 --port=8000
```

Then run Flutter with the computer's LAN IPv4 address (find it with `ipconfig` on Windows):

```sh
cd ../mobile
flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8000/api
```

Replace `192.168.1.10` with the actual address of the computer and allow port 8000 through its local firewall. For local HTTP development, enable cleartext traffic in the generated Android manifest and allow local HTTP in the iOS app transport settings. Use HTTPS for deployed environments.

The app stores JWTs with platform secure storage, loads the authenticated user's role, and shows operational modules for that role. Customers can create their own bookings. Admin operasional can manage permitted staff accounts and master data from mobile; the API prevents that role from creating or modifying a `super_admin`. Public registration is restricted to customers.
