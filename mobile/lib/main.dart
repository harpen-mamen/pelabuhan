import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;

const _brand = Color(0xFF123047);
const _accent = Color(0xFFE78324);

void main() {
  runApp(const SimuatanApp());
}

class SimuatanApp extends StatelessWidget {
  const SimuatanApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'SIMuatanTahuna',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        useMaterial3: true,
        colorScheme: ColorScheme.fromSeed(seedColor: _brand),
        scaffoldBackgroundColor: const Color(0xFFF4F7FA),
        appBarTheme: const AppBarTheme(
          backgroundColor: Color(0xFFF4F7FA),
          foregroundColor: _brand,
          elevation: 0,
          centerTitle: false,
        ),
        inputDecorationTheme: InputDecorationTheme(
          filled: true,
          fillColor: Colors.white,
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: Color(0xFFDDE5EC)),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: Color(0xFFDDE5EC)),
          ),
        ),
      ),
      home: const SessionShell(),
    );
  }
}

class ApiClient {
  ApiClient({http.Client? client}) : _client = client ?? http.Client();

  static const _baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api',
  );
  static const _storage = FlutterSecureStorage();
  final http.Client _client;

  Future<Map<String, dynamic>> request(
    String path, {
    String method = 'GET',
    Map<String, dynamic>? body,
    bool authenticated = true,
  }) async {
    final token = authenticated ? await _storage.read(key: 'access_token') : null;
    final uri = Uri.parse('$_baseUrl/$path');
    final headers = <String, String>{
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };

    late final http.Response response;
    switch (method) {
      case 'POST':
        response = await _client.post(uri, headers: headers, body: jsonEncode(body ?? {}));
        break;
      case 'PUT':
        response = await _client.put(uri, headers: headers, body: jsonEncode(body ?? {}));
        break;
      case 'DELETE':
        response = await _client.delete(uri, headers: headers);
        break;
      default:
        response = await _client.get(uri, headers: headers);
        break;
    }

    final decoded = response.body.isEmpty
        ? <String, dynamic>{}
        : jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw ApiException(
        decoded['message']?.toString() ?? 'Permintaan gagal (${response.statusCode}).',
        response.statusCode,
      );
    }
    return decoded;
  }

  Future<Map<String, dynamic>> login(String username, String password) async {
    final response = await request(
      'auth/login',
      method: 'POST',
      authenticated: false,
      body: {'username': username, 'password': password},
    );
    final data = response['data'] as Map<String, dynamic>;
    await _storage.write(key: 'access_token', value: data['token'].toString());
    return Map<String, dynamic>.from(data['user'] as Map);
  }

  Future<Map<String, dynamic>> currentUser() async {
    final response = await request('auth/me');
    return Map<String, dynamic>.from(response['data'] as Map);
  }

  Future<void> logout() async {
    try {
      await request('auth/logout', method: 'POST');
    } catch (_) {
      // Logout lokal tetap berjalan jika jaringan/API sedang tidak tersedia.
    } finally {
      await _storage.delete(key: 'access_token');
    }
  }

  Future<void> clearToken() => _storage.delete(key: 'access_token');
}

class ApiException implements Exception {
  const ApiException(this.message, this.statusCode);
  final String message;
  final int statusCode;

  @override
  String toString() => message;
}

class SessionShell extends StatefulWidget {
  const SessionShell({super.key});

  @override
  State<SessionShell> createState() => _SessionShellState();
}

class _SessionShellState extends State<SessionShell> {
  final _api = ApiClient();
  Map<String, dynamic>? _user;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _restoreSession();
  }

  Future<void> _restoreSession() async {
    try {
      final user = await _api.currentUser();
      if (mounted) setState(() => _user = user);
    } catch (_) {
      await _api.clearToken();
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _signedIn(Map<String, dynamic> user) => setState(() => _user = user);

  Future<void> _signOut() async {
    await _api.logout();
    if (mounted) setState(() => _user = null);
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    if (_user == null) return LoginScreen(api: _api, onSignedIn: _signedIn);
    return DashboardScreen(api: _api, user: _user!, onSignOut: _signOut);
  }
}

class LoginScreen extends StatefulWidget {
  const LoginScreen({required this.api, required this.onSignedIn, super.key});
  final ApiClient api;
  final ValueChanged<Map<String, dynamic>> onSignedIn;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _username = TextEditingController();
  final _password = TextEditingController();
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _username.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final user = await widget.api.login(_username.text.trim(), _password.text);
      widget.onSignedIn(user);
    } catch (error) {
      setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _register() async {
    final data = await showDialog<Map<String, String>>(
      context: context,
      builder: (_) => const _RegisterDialog(),
    );
    if (data == null) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final registration = Map<String, dynamic>.from(data)..remove('konfirmasi_password');
      registration.removeWhere((_, value) => value.toString().trim().isEmpty);
      await widget.api.request('auth/register', method: 'POST', authenticated: false, body: registration);
      final user = await widget.api.login(data['username']!, data['password']!);
      widget.onSignedIn(user);
    } catch (error) {
      setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 440),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Container(
                      height: 76,
                      width: 76,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: _brand,
                        borderRadius: BorderRadius.circular(24),
                      ),
                      child: const Icon(Icons.sailing_rounded, color: Colors.white, size: 38),
                    ),
                    const SizedBox(height: 28),
                    const Text(
                      'SIMuatanTahuna',
                      style: TextStyle(color: _brand, fontSize: 29, fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Masuk untuk melanjutkan aktivitas pelabuhan.',
                      style: TextStyle(color: Colors.blueGrey.shade600, fontSize: 15),
                    ),
                    const SizedBox(height: 30),
                    TextFormField(
                      controller: _username,
                      textInputAction: TextInputAction.next,
                      decoration: const InputDecoration(labelText: 'Username', prefixIcon: Icon(Icons.person_outline)),
                      validator: (value) => (value == null || value.trim().isEmpty) ? 'Username wajib diisi' : null,
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _password,
                      obscureText: true,
                      onFieldSubmitted: (_) => _submit(),
                      decoration: const InputDecoration(labelText: 'Kata sandi', prefixIcon: Icon(Icons.lock_outline)),
                      validator: (value) => (value == null || value.isEmpty) ? 'Kata sandi wajib diisi' : null,
                    ),
                    if (_error != null) ...[
                      const SizedBox(height: 14),
                      Text(_error!, style: const TextStyle(color: Colors.red, fontWeight: FontWeight.w600)),
                    ],
                    const SizedBox(height: 22),
                    FilledButton(
                      onPressed: _busy ? null : _submit,
                      style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(52), backgroundColor: _brand),
                      child: _busy
                          ? const SizedBox(height: 22, width: 22, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                          : const Text('Masuk', style: TextStyle(fontWeight: FontWeight.bold)),
                    ),
                    const SizedBox(height: 8),
                    TextButton(
                      onPressed: _busy ? null : _register,
                      child: const Text('Daftar akun pelanggan'),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _RegisterDialog extends StatefulWidget {
  const _RegisterDialog();

  @override
  State<_RegisterDialog> createState() => _RegisterDialogState();
}

class _RegisterDialogState extends State<_RegisterDialog> {
  final _formKey = GlobalKey<FormState>();
  final _fields = <String, TextEditingController>{
    'nama': TextEditingController(),
    'username': TextEditingController(),
    'email': TextEditingController(),
    'kontak': TextEditingController(),
    'password': TextEditingController(),
    'konfirmasi_password': TextEditingController(),
  };

  @override
  void dispose() {
    for (final field in _fields.values) {
      field.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Daftar pelanggan'),
      content: SizedBox(
        width: 420,
        child: SingleChildScrollView(
          child: Form(
            key: _formKey,
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              _field('nama', 'Nama lengkap'),
              _field('username', 'Username'),
              _field('email', 'Email (opsional)', required: false),
              _field('kontak', 'Nomor kontak (opsional)', required: false),
              _field('password', 'Kata sandi (min. 6 karakter)', obscure: true),
              _field('konfirmasi_password', 'Ulangi kata sandi', obscure: true),
            ]),
          ),
        ),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Batal')),
        FilledButton(
          onPressed: () {
            if (!_formKey.currentState!.validate()) return;
            Navigator.pop(context, _fields.map((key, value) => MapEntry(key, value.text.trim())));
          },
          child: const Text('Buat akun'),
        ),
      ],
    );
  }

  Widget _field(String key, String label, {bool required = true, bool obscure = false}) => Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: TextFormField(
          controller: _fields[key],
          obscureText: obscure,
          keyboardType: key == 'email' ? TextInputType.emailAddress : TextInputType.text,
          decoration: InputDecoration(labelText: label),
          validator: (value) {
            final text = value?.trim() ?? '';
            if (required && text.isEmpty) return 'Wajib diisi';
            if (key == 'password' && text.length < 6) return 'Gunakan minimal 6 karakter';
            if (key == 'konfirmasi_password' && text != _fields['password']!.text) return 'Kata sandi tidak sama';
            if (key == 'email' && text.isNotEmpty && !text.contains('@')) return 'Format email tidak valid';
            return null;
          },
        ),
      );
}

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({required this.api, required this.user, required this.onSignOut, super.key});
  final ApiClient api;
  final Map<String, dynamic> user;
  final Future<void> Function() onSignOut;

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  late Future<Map<String, dynamic>> _summary;

  @override
  void initState() {
    super.initState();
    _summary = widget.api.request('dashboard/summary').then((response) {
      final data = response['data'];
      return data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{};
    });
  }

  List<_Module> _modulesFor(String role) {
    final modules = <String, List<_Module>>{
      'admin': const [
        _Module('Booking', 'booking', Icons.inventory_2_outlined),
        _Module('Manifest', 'manifest', Icons.description_outlined),
        _Module('Voyage', 'voyage', Icons.map_outlined),
        _Module('Realisasi', 'realisasi', Icons.fact_check_outlined),
        _Module('Pengguna', 'users', Icons.people_outline),
        _Module('Kapal', 'kapal', Icons.directions_boat_outlined),
        _Module('Pelanggan', 'pelanggan', Icons.groups_outlined),
        _Module('Perusahaan', 'perusahaan', Icons.business_outlined),
        _Module('Tarif', 'tarif', Icons.payments_outlined),
      ],
      'operator': const [
        _Module('Booking', 'booking', Icons.inventory_2_outlined),
        _Module('Manifest', 'manifest', Icons.description_outlined),
        _Module('Voyage', 'voyage', Icons.map_outlined),
        _Module('Kapal', 'kapal', Icons.directions_boat_outlined),
      ],
      'upp': const [
        _Module('Verifikasi manifest', 'manifest', Icons.fact_check_outlined),
        _Module('Laporan arus barang', 'laporan/arus-barang', Icons.bar_chart_rounded),
        _Module('Audit', 'audit-log', Icons.history_rounded),
      ],
      'checker': const [_Module('Manifest untuk dimuat/bongkar', 'manifest', Icons.scale_outlined)],
      'pelanggan': const [_Module('Booking saya', 'booking', Icons.inventory_2_outlined)],
      'manajer': const [
        _Module('Laporan arus barang', 'laporan/arus-barang', Icons.bar_chart_rounded),
        _Module('Laporan manifest', 'laporan/manifest', Icons.description_outlined),
        _Module('Booking', 'booking', Icons.inventory_2_outlined),
        _Module('Voyage', 'voyage', Icons.map_outlined),
        _Module('Audit', 'audit-log', Icons.history_rounded),
      ],
      'kantor_perhubungan': const [
        _Module('Laporan arus barang', 'laporan/arus-barang', Icons.bar_chart_rounded),
        _Module('Laporan manifest', 'laporan/manifest', Icons.description_outlined),
        _Module('Laporan PNBP', 'laporan/pnbp', Icons.payments_outlined),
        _Module('Audit', 'audit-log', Icons.history_rounded),
      ],
    };
    return modules[role] ?? const [];
  }

  @override
  Widget build(BuildContext context) {
    final name = widget.user['nama']?.toString() ?? 'Pengguna';
    final role = widget.user['role']?.toString() ?? '';
    final modules = _modulesFor(role);

    return Scaffold(
      appBar: AppBar(
        title: const Text('SIMuatanTahuna', style: TextStyle(fontWeight: FontWeight.w800)),
        actions: [
          IconButton(
            tooltip: 'Keluar',
            onPressed: widget.onSignOut,
            icon: const Icon(Icons.logout_rounded),
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          setState(() => _summary = widget.api.request('dashboard/summary').then((response) {
                final data = response['data'];
                return data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{};
              }));
          await _summary;
        },
        child: ListView(
          padding: const EdgeInsets.fromLTRB(18, 8, 18, 28),
          children: [
            _WelcomeCard(name: name, role: role),
            const SizedBox(height: 24),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text('Ringkasan', style: TextStyle(fontSize: 19, fontWeight: FontWeight.w800, color: _brand)),
                Icon(Icons.insights_rounded, color: Colors.blueGrey.shade400),
              ],
            ),
            const SizedBox(height: 12),
            FutureBuilder<Map<String, dynamic>>(
              future: _summary,
              builder: (context, snapshot) {
                if (snapshot.connectionState == ConnectionState.waiting) {
                  return const Padding(padding: EdgeInsets.all(24), child: Center(child: CircularProgressIndicator()));
                }
                if (snapshot.hasError) {
                  return _ErrorCard(
                    message: snapshot.error.toString(),
                    onRetry: () => setState(() {
                      _summary = widget.api.request('dashboard/summary').then((response) {
                        final data = response['data'];
                        return data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{};
                      });
                    }),
                  );
                }
                final data = snapshot.data ?? {};
                final stats = _selectStats(data);
                if (stats.isEmpty) {
                  return const _InfoCard(message: 'Ringkasan untuk role ini belum tersedia. Pilih modul di bawah untuk mulai bekerja.');
                }
                return GridView.builder(
                  itemCount: stats.length,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    childAspectRatio: 1.48,
                    crossAxisSpacing: 12,
                    mainAxisSpacing: 12,
                  ),
                  itemBuilder: (context, index) => _StatCard(stat: stats[index]),
                );
              },
            ),
            const SizedBox(height: 24),
            const Text('Menu kerja', style: TextStyle(fontSize: 19, fontWeight: FontWeight.w800, color: _brand)),
            const SizedBox(height: 12),
            if (modules.isEmpty)
              const _InfoCard(message: 'Belum ada menu untuk role ini.')
            else
              ...modules.map((module) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: _ModuleTile(
                      module: module,
                      onTap: () => Navigator.of(context).push(MaterialPageRoute<void>(
                        builder: (_) => ModuleScreen(api: widget.api, module: module, role: role),
                      )),
                    ),
                  )),
          ],
        ),
      ),
    );
  }

  List<_Stat> _selectStats(Map<String, dynamic> data) {
    const candidates = <String, String>{
      'total_booking': 'Total booking',
      'booking_menunggu': 'Menunggu',
      'menunggu_verifikasi': 'Verifikasi',
      'manifest_menunggu_verif': 'Verifikasi',
      'manifest_saya': 'Manifest saya',
      'booking_hari_ini': 'Booking hari ini',
      'total_manifest': 'Total manifest',
      'realisasi_hari_ini': 'Realisasi hari ini',
      'total_voyage': 'Total voyage',
      'total_kapal': 'Kapal',
      'total_pelanggan': 'Pelanggan',
    };
    final selected = <_Stat>[];
    for (final entry in candidates.entries) {
      final value = data[entry.key];
      if (value is num && !selected.any((stat) => stat.label == entry.value)) {
        selected.add(_Stat(entry.value, value.toString(), _iconFor(entry.key)));
      }
    }
    return selected.take(4).toList();
  }

  IconData _iconFor(String key) => switch (key) {
        'total_booking' || 'booking_menunggu' || 'booking_hari_ini' => Icons.inventory_2_outlined,
        'menunggu_verifikasi' || 'manifest_menunggu_verif' || 'manifest_saya' || 'total_manifest' => Icons.description_outlined,
        'realisasi_hari_ini' => Icons.fact_check_outlined,
        'total_voyage' => Icons.map_outlined,
        _ => Icons.directions_boat_outlined,
      };
}

class ModuleScreen extends StatefulWidget {
  const ModuleScreen({required this.api, required this.module, required this.role, super.key});
  final ApiClient api;
  final _Module module;
  final String role;

  @override
  State<ModuleScreen> createState() => _ModuleScreenState();
}

class _ModuleScreenState extends State<ModuleScreen> {
  late Future<List<Map<String, dynamic>>> _records;

  bool get _canCreateBooking => widget.module.path == 'booking' && widget.role == 'pelanggan';
  bool get _canCreateManifest => widget.module.path == 'manifest' && (widget.role == 'operator' || widget.role == 'admin');
  bool get _canCreateVoyage => widget.module.path == 'voyage' && (widget.role == 'operator' || widget.role == 'admin');
  bool get _canManageAdminModule => widget.role == 'admin' && const {'users', 'kapal', 'pelanggan', 'perusahaan', 'tarif'}.contains(widget.module.path);

  bool get _canActOnRecords =>
      (widget.role == 'upp' && widget.module.path == 'manifest') ||
      (widget.role == 'checker' && widget.module.path == 'manifest') ||
      ((widget.role == 'operator' || widget.role == 'admin') && widget.module.path == 'manifest') ||
      ((widget.role == 'operator' || widget.role == 'admin') && widget.module.path == 'booking') ||
      ((widget.role == 'operator' || widget.role == 'admin') && widget.module.path == 'voyage');

  @override
  void initState() {
    super.initState();
    _records = _load();
  }

  Future<List<Map<String, dynamic>>> _load() async {
    var path = widget.module.path;
    if (path == 'laporan/arus-barang' || path == 'laporan/pnbp') {
      final end = DateTime.now();
      final start = end.subtract(const Duration(days: 30));
      String date(DateTime value) => value.toIso8601String().substring(0, 10);
      path = '$path?periode_awal=${date(start)}&periode_akhir=${date(end)}';
    }
    final response = await widget.api.request(path);
    dynamic data = response['data'];
    if (data is Map && data['manifests'] is List) data = data['manifests'];
    if (data is Map && data['rincian'] is List) data = data['rincian'];
    if (data is Map && data['data'] is List) data = data['data'];
    if (data is! List) return const [];
    return data.whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList();
  }

  Future<void> _createBooking() async {
    final form = await showDialog<Map<String, String>>(
      context: context,
      builder: (_) => const _NewBookingDialog(),
    );
    if (form == null) return;
    try {
      await widget.api.request(
        'booking',
        method: 'POST',
        body: {
          'asal': form['asal'],
          'tujuan': form['tujuan'],
          'tgl_booking': DateTime.now().toIso8601String().substring(0, 10),
          'barang': [
            {
              'nama_barang': form['nama_barang'],
              'kategori': form['kategori'],
              'berat': double.parse(form['berat']!),
              'jumlah_kemasan': int.parse(form['jumlah_kemasan']!),
            },
          ],
        },
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Booking berhasil dibuat.')));
      setState(() => _records = _load());
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }

  Future<void> _createManifest() async {
    final data = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => _NewManifestDialog(api: widget.api),
    );
    if (data == null) return;
    try {
      await widget.api.request('manifest', method: 'POST', body: data);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Manifest terkirim untuk verifikasi UPP.')));
      setState(() => _records = _load());
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }

  Future<void> _createVoyage() async {
    final data = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => _NewVoyageDialog(api: widget.api),
    );
    if (data == null) return;
    try {
      await widget.api.request('voyage', method: 'POST', body: data);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Jadwal voyage berhasil dibuat.')));
      setState(() => _records = _load());
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }

  Future<void> _manageAdminRecord([Map<String, dynamic>? record]) async {
    final result = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => _AdminRecordDialog(api: widget.api, path: widget.module.path, record: record),
    );
    if (result == null) return;
    final id = record?['id_user'] ?? record?['id_kapal'] ?? record?['id_pelanggan'] ?? record?['id_perusahaan'] ?? record?['id_tarif'];
    final method = record == null ? 'POST' : 'PUT';
    final path = record == null ? widget.module.path : '${widget.module.path}/$id';
    try {
      await widget.api.request(path, method: method, body: result);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(record == null ? 'Data berhasil ditambahkan.' : 'Perubahan berhasil disimpan.')));
      setState(() => _records = _load());
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }

  Future<void> _toggleUserActive(Map<String, dynamic> record) async {
    final id = record['id_user'];
    if (id == null) return;
    try {
      await widget.api.request('users/$id/toggle-active', method: 'PUT');
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(record['is_active'] == true ? 'Akun dinonaktifkan.' : 'Akun diaktifkan.')));
      setState(() => _records = _load());
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.module.title)),
      floatingActionButton: _canCreateBooking || _canCreateManifest || _canCreateVoyage || _canManageAdminModule
          ? FloatingActionButton.extended(
              onPressed: _canManageAdminModule ? () => _manageAdminRecord() : (_canCreateBooking ? _createBooking : (_canCreateManifest ? _createManifest : _createVoyage)),
              backgroundColor: _accent,
              foregroundColor: Colors.white,
              icon: const Icon(Icons.add),
              label: Text(_canManageAdminModule ? 'Tambah data' : (_canCreateBooking ? 'Buat booking' : (_canCreateManifest ? 'Buat manifest' : 'Buat voyage'))),
            )
          : null,
      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: _records,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) return const Center(child: CircularProgressIndicator());
          if (snapshot.hasError) return Center(child: Padding(padding: const EdgeInsets.all(24), child: Text(snapshot.error.toString(), textAlign: TextAlign.center)));
          final records = snapshot.data ?? [];
          if (records.isEmpty) return const Center(child: _InfoCard(message: 'Belum ada data untuk ditampilkan.'));
          return RefreshIndicator(
            onRefresh: () async => setState(() => _records = _load()),
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: records.length,
              separatorBuilder: (_, __) => const SizedBox(height: 10),
              itemBuilder: (context, index) => _RecordCard(
                record: records[index],
                module: widget.module,
                actionLabel: _recordActionLabel(records[index]),
                onAction: _canActOnRecords ? () => _performRecordAction(records[index]) : null,
                onEdit: _canManageAdminModule ? () => _manageAdminRecord(records[index]) : null,
                onToggleActive: _canManageAdminModule && widget.module.path == 'users' ? () => _toggleUserActive(records[index]) : null,
              ),
            ),
          );
        },
      ),
    );
  }

  String? _recordActionLabel(Map<String, dynamic> record) {
    if ((widget.role == 'operator' || widget.role == 'admin') && widget.module.path == 'booking') return 'Ubah status';
    if (widget.role == 'upp' && record['status'] == 'menunggu_verifikasi') return 'Verifikasi';
    if ((widget.role == 'operator' || widget.role == 'admin') && widget.module.path == 'manifest' &&
        (record['status'] == 'draft' || record['status'] == 'ditolak')) return 'Kirim ulang';
    if (widget.role == 'checker') {
      final status = record['status'];
      if (status == 'disetujui' || status == 'perlu_klarifikasi') return 'Catat muat';
      if (status == 'dalam_bongkar') return 'Catat bongkar';
    }
    if ((widget.role == 'operator' || widget.role == 'admin') && widget.module.path == 'voyage') {
      if (record['status'] == 'dijadwalkan') return 'Berangkatkan';
      if (record['status'] == 'berlayar') return 'Catat tiba';
    }
    return null;
  }

  Future<void> _performRecordAction(Map<String, dynamic> record) async {
    final id = record['id_manifest'] ?? record['id_booking'] ?? record['id_voyage'];
    if (id == null) return;
    try {
      if (widget.module.path == 'booking') {
        final transitionsResponse = await widget.api.request('booking/$id/transitions');
        final data = Map<String, dynamic>.from(transitionsResponse['data'] as Map);
        final transitions = (data['available_transitions'] as List? ?? const [])
            .whereType<Map>()
            .where((item) => item['requires_note'] != true)
            .map((item) => Map<String, String>.from({'status': item['status'].toString(), 'label': item['label'].toString()}))
            .toList();
        if (transitions.isEmpty) throw const ApiException('Tidak ada perubahan status yang tersedia.', 422);
        final status = await showDialog<String>(
          context: context,
          builder: (context) => SimpleDialog(
            title: const Text('Pilih status booking'),
            children: transitions.map((item) => SimpleDialogOption(
              onPressed: () => Navigator.pop(context, item['status']),
              child: Text(item['label']!),
            )).toList(),
          ),
        );
        if (status == null) return;
        await widget.api.request('booking/$id/status', method: 'PUT', body: {'status': status});
      } else if ((widget.role == 'operator' || widget.role == 'admin') && widget.module.path == 'manifest') {
        await widget.api.request('manifest/$id/submit', method: 'PUT');
      } else if (widget.role == 'upp') {
        final result = await showDialog<Map<String, String>>(
          context: context,
          builder: (_) => const _ManifestDecisionDialog(),
        );
        if (result == null) return;
        await widget.api.request('manifest/$id/verifikasi', method: 'PUT', body: result);
      } else if (widget.role == 'checker') {
        final details = (record['detail'] as List? ?? const [])
            .whereType<Map>()
            .map((item) => Map<String, dynamic>.from(item))
            .toList();
        if (details.isEmpty) throw const ApiException('Manifest ini tidak memiliki rincian barang.', 422);
        final result = await showDialog<Map<String, dynamic>>(
          context: context,
          builder: (_) => _RealisasiDialog(
            details: details,
            kind: record['status'] == 'dalam_bongkar' ? 'bongkar' : 'muat',
          ),
        );
        if (result == null) return;
        await widget.api.request('realisasi', method: 'POST', body: result);
      } else if ((widget.role == 'operator' || widget.role == 'admin') && widget.module.path == 'voyage') {
        final action = record['status'] == 'berlayar' ? 'tiba' : 'berangkat';
        final label = action == 'tiba' ? 'tiba' : 'berangkat';
        final confirmed = await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            title: Text('Konfirmasi $label'),
            content: Text('Catat voyage ${record['nomor_voyage'] ?? ''} sebagai $label?'),
            actions: [
              TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Batal')),
              FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Konfirmasi')),
            ],
          ),
        );
        if (confirmed != true) return;
        await widget.api.request('voyage/$id/$action', method: 'POST');
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Perubahan berhasil disimpan.')));
      setState(() => _records = _load());
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }
}

class _AdminRecordDialog extends StatefulWidget {
  const _AdminRecordDialog({required this.api, required this.path, this.record});
  final ApiClient api;
  final String path;
  final Map<String, dynamic>? record;

  @override
  State<_AdminRecordDialog> createState() => _AdminRecordDialogState();
}

class _AdminRecordDialogState extends State<_AdminRecordDialog> {
  final _formKey = GlobalKey<FormState>();
  final Map<String, TextEditingController> _fields = {};
  final List<String> _roles = [];
  String _category = 'umum';
  String _role = 'operator';
  bool _loadingRoles = false;

  static const _categories = ['umum', 'berbahaya', 'bahan_pokok', 'b3', 'lainnya'];

  List<(String, String, bool)> get _schema => switch (widget.path) {
        'users' => [
            ('nama', 'Nama lengkap', true), ('username', 'Username', true), ('email', 'Email', false),
            ('kontak', 'Nomor kontak', false), ('password', 'Kata sandi (kosongkan jika tidak diganti)', widget.record == null),
            ('id_perusahaan', 'ID perusahaan (opsional)', false),
          ],
        'kapal' => [
            ('nama_kapal', 'Nama kapal', true), ('kapasitas_dwt', 'Kapasitas DWT', true),
            ('jenis_kapal', 'Jenis kapal', false), ('nomor_imo', 'Nomor IMO', false), ('pemilik', 'Pemilik', false),
          ],
        'perusahaan' => [
            ('nama_perusahaan', 'Nama perusahaan', true), ('alamat', 'Alamat', false), ('npwp', 'NPWP', false), ('kontak', 'Kontak', false),
          ],
        'pelanggan' => [
            ('nama', 'Nama pelanggan', true), ('id_user', 'ID akun pengguna (opsional)', false),
            ('id_perusahaan', 'ID perusahaan (opsional)', false), ('alamat', 'Alamat', false), ('kontak', 'Kontak', false),
          ],
        'tarif' => [
            ('jenis_layanan', 'Jenis layanan', true), ('rute', 'Rute', true), ('tarif_per_kg', 'Tarif per kg', true),
            ('tarif_per_m3', 'Tarif per m³', false), ('berlaku_dari', 'Berlaku dari (YYYY-MM-DD)', true),
            ('berlaku_hingga', 'Berlaku hingga (opsional)', false),
          ],
        _ => const [],
      };

  @override
  void initState() {
    super.initState();
    for (final field in _schema) {
      final key = field.$1;
      _fields[key] = TextEditingController(text: widget.record?[key]?.toString() ?? '');
    }
    _role = widget.record?['role']?.toString() ?? 'operator';
    _category = widget.record?['kategori_barang']?.toString() ?? 'umum';
    if (widget.path == 'users') _loadRoles();
  }

  Future<void> _loadRoles() async {
    setState(() => _loadingRoles = true);
    try {
      final response = await widget.api.request('users/roles');
      final data = response['data'];
      if (data is Map) _roles.addAll(data.keys.map((key) => key.toString()));
      if (!_roles.contains(_role) && _roles.isNotEmpty) _role = _roles.first;
    } catch (_) {
      // Endpoint create/update tetap memberi validasi; tampilkan role operasional yang dikenal.
      _roles.addAll(['admin', 'operator', 'upp', 'checker', 'pelanggan', 'manajer', 'kantor_perhubungan']);
    } finally {
      if (mounted) setState(() => _loadingRoles = false);
    }
  }

  @override
  void dispose() {
    for (final controller in _fields.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Map<String, dynamic> _payload() {
    final data = <String, dynamic>{};
    for (final field in _schema) {
      final key = field.$1;
      final value = _fields[key]!.text.trim();
      if (value.isEmpty) continue;
      if (const {'id_user', 'id_perusahaan'}.contains(key)) {
        data[key] = int.tryParse(value) ?? value;
      } else if (const {'kapasitas_dwt', 'tarif_per_kg', 'tarif_per_m3'}.contains(key)) {
        data[key] = num.tryParse(value) ?? value;
      } else {
        data[key] = value;
      }
    }
    if (widget.path == 'users') data['role'] = _role;
    if (widget.path == 'tarif') data['kategori_barang'] = _category;
    return data;
  }

  @override
  Widget build(BuildContext context) {
    final title = widget.record == null ? 'Tambah ${_moduleName(widget.path)}' : 'Ubah ${_moduleName(widget.path)}';
    return AlertDialog(
      title: Text(title),
      content: SizedBox(
        width: 440,
        child: SingleChildScrollView(
          child: Form(
            key: _formKey,
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              if (widget.path == 'users') ...[
                DropdownButtonFormField<String>(
                  value: _roles.contains(_role) ? _role : null,
                  decoration: const InputDecoration(labelText: 'Role'),
                  items: _roles.map((role) => DropdownMenuItem(value: role, child: Text(_roleLabel(role)))).toList(),
                  onChanged: _loadingRoles ? null : (value) => setState(() => _role = value ?? _role),
                  validator: (value) => value == null ? 'Pilih role' : null,
                ),
                if (_loadingRoles) const LinearProgressIndicator(),
                const SizedBox(height: 10),
              ],
              if (widget.path == 'tarif') ...[
                DropdownButtonFormField<String>(
                  value: _category,
                  decoration: const InputDecoration(labelText: 'Kategori barang'),
                  items: _categories.map((value) => DropdownMenuItem(value: value, child: Text(value.replaceAll('_', ' ')))).toList(),
                  onChanged: (value) => setState(() => _category = value ?? 'umum'),
                ),
                const SizedBox(height: 10),
              ],
              ..._schema.map((field) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: TextFormField(
                      controller: _fields[field.$1],
                      obscureText: field.$1 == 'password',
                      keyboardType: const {'kapasitas_dwt', 'tarif_per_kg', 'tarif_per_m3', 'id_user', 'id_perusahaan'}.contains(field.$1)
                          ? const TextInputType.numberWithOptions(decimal: true)
                          : TextInputType.text,
                      decoration: InputDecoration(labelText: field.$2),
                      validator: (value) {
                        if (field.$3 && (value == null || value.trim().isEmpty)) return 'Wajib diisi';
                        if (field.$1 == 'password' && value != null && value.isNotEmpty && value.length < 6) return 'Minimal 6 karakter';
                        if (field.$1 == 'username' && (value?.trim().length ?? 0) < 3) return 'Minimal 3 karakter';
                        if (const {'kapasitas_dwt', 'tarif_per_kg', 'tarif_per_m3'}.contains(field.$1) && value != null && value.isNotEmpty && num.tryParse(value) == null) return 'Masukkan angka yang valid';
                        if (const {'id_user', 'id_perusahaan'}.contains(field.$1) && value != null && value.isNotEmpty && int.tryParse(value) == null) return 'Masukkan ID angka';
                        if (field.$1 == 'email' && value != null && value.isNotEmpty && !value.contains('@')) return 'Format email tidak valid';
                        return null;
                      },
                    ),
                  )),
            ]),
          ),
        ),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Batal')),
        FilledButton(
          onPressed: _loadingRoles
              ? null
              : () {
                  if (!_formKey.currentState!.validate()) return;
                  Navigator.pop(context, _payload());
                },
          child: const Text('Simpan'),
        ),
      ],
    );
  }

  String _moduleName(String path) => switch (path) {
        'users' => 'pengguna', 'kapal' => 'kapal', 'pelanggan' => 'pelanggan',
        'perusahaan' => 'perusahaan', 'tarif' => 'tarif', _ => 'data',
      };
}

class _ManifestDecisionDialog extends StatefulWidget {
  const _ManifestDecisionDialog();

  @override
  State<_ManifestDecisionDialog> createState() => _ManifestDecisionDialogState();
}

class _ManifestDecisionDialogState extends State<_ManifestDecisionDialog> {
  final _note = TextEditingController();
  String _decision = 'disetujui';

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
        title: const Text('Verifikasi manifest'),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          DropdownButtonFormField<String>(
            value: _decision,
            decoration: const InputDecoration(labelText: 'Keputusan'),
            items: const [
              DropdownMenuItem(value: 'disetujui', child: Text('Setujui')),
              DropdownMenuItem(value: 'ditolak', child: Text('Tolak')),
              DropdownMenuItem(value: 'revisi', child: Text('Minta revisi')),
            ],
            onChanged: (value) => setState(() => _decision = value ?? 'disetujui'),
          ),
          const SizedBox(height: 12),
          TextField(controller: _note, decoration: const InputDecoration(labelText: 'Catatan (wajib jika ditolak)')),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Batal')),
          FilledButton(
            onPressed: () {
              if (_decision == 'ditolak' && _note.text.trim().isEmpty) return;
              Navigator.pop(context, {'status_verifikasi': _decision, 'catatan': _note.text.trim()});
            },
            child: const Text('Simpan'),
          ),
        ],
      );
}

class _RealisasiDialog extends StatefulWidget {
  const _RealisasiDialog({required this.details, required this.kind});
  final List<Map<String, dynamic>> details;
  final String kind;

  @override
  State<_RealisasiDialog> createState() => _RealisasiDialogState();
}

class _RealisasiDialogState extends State<_RealisasiDialog> {
  final _quantity = TextEditingController();
  int? _detailId;

  @override
  void dispose() {
    _quantity.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
        title: Text('Catat ${widget.kind}'),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          DropdownButtonFormField<int>(
            value: _detailId,
            decoration: const InputDecoration(labelText: 'Barang'),
            items: widget.details.map((detail) {
              final id = int.tryParse(detail['id_manifest_detail'].toString());
              final barang = detail['barang'] is Map ? detail['barang'] as Map : const {};
              return DropdownMenuItem<int>(value: id, child: Text('${barang['nama_barang'] ?? 'Barang'} (${detail['jumlah_manifest']})'));
            }).where((item) => item.value != null).toList(),
            onChanged: (value) => setState(() => _detailId = value),
          ),
          const SizedBox(height: 12),
          TextField(controller: _quantity, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: const InputDecoration(labelText: 'Jumlah terealisasi')),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Batal')),
          FilledButton(
            onPressed: () {
              final quantity = double.tryParse(_quantity.text.trim());
              if (_detailId == null || quantity == null || quantity < 0) return;
              Navigator.pop(context, {'id_manifest_detail': _detailId, 'jumlah_realisasi': quantity, 'jenis': widget.kind});
            },
            child: const Text('Simpan'),
          ),
        ],
      );
}

class _NewVoyageDialog extends StatefulWidget {
  const _NewVoyageDialog({required this.api});
  final ApiClient api;

  @override
  State<_NewVoyageDialog> createState() => _NewVoyageDialogState();
}

class _NewVoyageDialogState extends State<_NewVoyageDialog> {
  final _formKey = GlobalKey<FormState>();
  final _number = TextEditingController();
  final _route = TextEditingController();
  final _eta = TextEditingController(text: '24');
  List<Map<String, dynamic>> _ships = [];
  int? _shipId;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadShips();
  }

  Future<void> _loadShips() async {
    try {
      final response = await widget.api.request('kapal');
      final data = response['data'];
      final list = data is Map ? data['data'] : data;
      if (list is List) _ships = list.whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList();
    } catch (error) {
      _error = error.toString();
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  void dispose() {
    _number.dispose();
    _route.dispose();
    _eta.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
        title: const Text('Jadwalkan voyage'),
        content: SizedBox(
          width: 440,
          child: _loading
              ? const Center(child: CircularProgressIndicator())
              : _error != null
                  ? Text(_error!)
                  : _ships.isEmpty
                      ? const Text('Data kapal belum tersedia.')
                      : Form(
                          key: _formKey,
                          child: SingleChildScrollView(
                            child: Column(mainAxisSize: MainAxisSize.min, children: [
                              DropdownButtonFormField<int>(
                                value: _shipId,
                                decoration: const InputDecoration(labelText: 'Kapal'),
                                items: _ships.map((ship) => DropdownMenuItem<int>(
                                  value: int.tryParse(ship['id_kapal'].toString()),
                                  child: Text(ship['nama_kapal']?.toString() ?? 'Kapal'),
                                )).where((item) => item.value != null).toList(),
                                onChanged: (value) => setState(() => _shipId = value),
                                validator: (value) => value == null ? 'Pilih kapal' : null,
                              ),
                              const SizedBox(height: 12),
                              TextFormField(controller: _number, decoration: const InputDecoration(labelText: 'Nomor voyage'), validator: _required),
                              const SizedBox(height: 12),
                              TextFormField(controller: _route, decoration: const InputDecoration(labelText: 'Rute'), validator: _required),
                              const SizedBox(height: 12),
                              TextFormField(controller: _eta, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Estimasi perjalanan (jam)'), validator: _required),
                            ]),
                          ),
                        ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Batal')),
          FilledButton(
            onPressed: _loading || _error != null || _ships.isEmpty ? null : () {
              if (!_formKey.currentState!.validate()) return;
              final eta = int.tryParse(_eta.text.trim());
              if (eta == null || eta < 1) return;
              Navigator.pop(context, {
                'id_kapal': _shipId,
                'nomor_voyage': _number.text.trim(),
                'rute': _route.text.trim(),
                'tgl_berangkat': DateTime.now().toIso8601String().substring(0, 10),
                'estimasi_jam': eta,
              });
            },
            child: const Text('Simpan jadwal'),
          ),
        ],
      );

  String? _required(String? value) => value == null || value.trim().isEmpty ? 'Wajib diisi' : null;
}

class _NewManifestDialog extends StatefulWidget {
  const _NewManifestDialog({required this.api});
  final ApiClient api;

  @override
  State<_NewManifestDialog> createState() => _NewManifestDialogState();
}

class _NewManifestDialogState extends State<_NewManifestDialog> {
  List<Map<String, dynamic>> _bookings = [];
  List<Map<String, dynamic>> _voyages = [];
  int? _bookingId;
  int? _voyageId;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadChoices();
  }

  Future<void> _loadChoices() async {
    try {
      final responses = await Future.wait([
        widget.api.request('booking?status=diproses'),
        widget.api.request('voyage?aktif=1'),
      ]);
      List<Map<String, dynamic>> rows(dynamic value) {
        final data = value is Map ? value['data'] : value;
        if (data is! List) return [];
        return data.whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList();
      }
      _bookings = rows(responses[0]['data']);
      _voyages = rows(responses[1]['data']);
      _bookings = _bookings.where((booking) => booking['manifest'] == null && (booking['barang'] as List? ?? const []).isNotEmpty).toList();
    } catch (error) {
      _error = error.toString();
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
        title: const Text('Buat manifest'),
        content: SizedBox(
          width: 440,
          child: _loading
              ? const Center(child: CircularProgressIndicator())
              : _error != null
                  ? Text(_error!)
                  : (_bookings.isEmpty || _voyages.isEmpty)
                      ? const Text('Perlu booking berstatus diproses dan voyage terjadwal sebelum membuat manifest.')
                      : Column(mainAxisSize: MainAxisSize.min, children: [
                          DropdownButtonFormField<int>(
                            value: _bookingId,
                            decoration: const InputDecoration(labelText: 'Booking'),
                            items: _bookings.map((booking) => DropdownMenuItem<int>(
                              value: int.tryParse(booking['id_booking'].toString()),
                              child: Text(booking['kode_booking']?.toString() ?? 'Booking'),
                            )).where((item) => item.value != null).toList(),
                            onChanged: (value) => setState(() => _bookingId = value),
                          ),
                          const SizedBox(height: 12),
                          DropdownButtonFormField<int>(
                            value: _voyageId,
                            decoration: const InputDecoration(labelText: 'Voyage'),
                            items: _voyages.map((voyage) => DropdownMenuItem<int>(
                              value: int.tryParse(voyage['id_voyage'].toString()),
                              child: Text(voyage['nomor_voyage']?.toString() ?? 'Voyage'),
                            )).where((item) => item.value != null).toList(),
                            onChanged: (value) => setState(() => _voyageId = value),
                          ),
                        ]),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Batal')),
          FilledButton(
            onPressed: _loading || _error != null || _bookings.isEmpty || _voyages.isEmpty ? null : () {
              if (_bookingId == null || _voyageId == null) return;
              final booking = _bookings.firstWhere((item) => item['id_booking'].toString() == _bookingId.toString());
              final goods = (booking['barang'] as List? ?? const []).whereType<Map>().toList();
              Navigator.pop(context, {
                'id_booking': _bookingId,
                'id_voyage': _voyageId,
                'barang_ids': goods.map((item) => item['id_barang']).toList(),
                'jumlah_manifest': goods.map((item) => item['jumlah_kemasan'] ?? 1).toList(),
              });
            },
            child: const Text('Kirim untuk verifikasi'),
          ),
        ],
      );
}

class _NewBookingDialog extends StatefulWidget {
  const _NewBookingDialog();

  @override
  State<_NewBookingDialog> createState() => _NewBookingDialogState();
}

class _NewBookingDialogState extends State<_NewBookingDialog> {
  final _formKey = GlobalKey<FormState>();
  final _fields = <String, TextEditingController>{
    'asal': TextEditingController(),
    'tujuan': TextEditingController(),
    'nama_barang': TextEditingController(),
    'berat': TextEditingController(),
    'jumlah_kemasan': TextEditingController(text: '1'),
  };
  String _category = 'umum';

  @override
  void dispose() {
    for (final field in _fields.values) {
      field.dispose();
    }
    super.dispose();
  }

  String? _required(String? value) => value == null || value.trim().isEmpty ? 'Wajib diisi' : null;

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Buat booking muatan'),
      content: SizedBox(
        width: 420,
        child: SingleChildScrollView(
          child: Form(
            key: _formKey,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                _dialogField('asal', 'Kota asal'),
                _dialogField('tujuan', 'Kota tujuan'),
                _dialogField('nama_barang', 'Nama barang'),
                DropdownButtonFormField<String>(
                  value: _category,
                  decoration: const InputDecoration(labelText: 'Kategori barang'),
                  items: const [
                    DropdownMenuItem(value: 'umum', child: Text('Umum')),
                    DropdownMenuItem(value: 'bahan_pokok', child: Text('Bahan pokok')),
                    DropdownMenuItem(value: 'berbahaya', child: Text('Berbahaya')),
                    DropdownMenuItem(value: 'lainnya', child: Text('Lainnya')),
                  ],
                  onChanged: (value) => setState(() => _category = value ?? 'umum'),
                ),
                const SizedBox(height: 12),
                _dialogField('berat', 'Berat (kg)', number: true),
                _dialogField('jumlah_kemasan', 'Jumlah kemasan', number: true),
              ],
            ),
          ),
        ),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Batal')),
        FilledButton(
          onPressed: () {
            if (!_formKey.currentState!.validate()) return;
            Navigator.pop(context, {..._fields.map((key, value) => MapEntry(key, value.text)), 'kategori': _category});
          },
          child: const Text('Kirim booking'),
        ),
      ],
    );
  }

  Widget _dialogField(String key, String label, {bool number = false}) => Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: TextFormField(
          controller: _fields[key],
          keyboardType: number ? const TextInputType.numberWithOptions(decimal: true) : TextInputType.text,
          decoration: InputDecoration(labelText: label),
          validator: _required,
        ),
      );
}

class _WelcomeCard extends StatelessWidget {
  const _WelcomeCard({required this.name, required this.role});
  final String name;
  final String role;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [_brand, Color(0xFF245875)], begin: Alignment.topLeft, end: Alignment.bottomRight),
        borderRadius: BorderRadius.circular(24),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('Halo, $name', style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w800)),
              const SizedBox(height: 7),
              Text(_roleLabel(role), style: const TextStyle(color: Color(0xFFD7E7EF), fontSize: 14)),
              const SizedBox(height: 16),
              const Text('Pantau kegiatan muatan dan layanan pelabuhan Tahuna.', style: TextStyle(color: Colors.white, height: 1.4)),
            ]),
          ),
          const SizedBox(width: 8),
          const Icon(Icons.directions_boat_filled_rounded, color: Color(0xFFFFC276), size: 48),
        ],
      ),
    );
  }
}

class _Stat {
  const _Stat(this.label, this.value, this.icon);
  final String label;
  final String value;
  final IconData icon;
}

class _StatCard extends StatelessWidget {
  const _StatCard({required this.stat});
  final _Stat stat;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(18), border: Border.all(color: const Color(0xFFE7EDF2))),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
        Icon(stat.icon, color: _accent),
        Text(stat.value, style: const TextStyle(fontSize: 25, fontWeight: FontWeight.w800, color: _brand)),
        Text(stat.label, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: Colors.blueGrey.shade600, fontSize: 12)),
      ]),
    );
  }
}

class _Module {
  const _Module(this.title, this.path, this.icon);
  final String title;
  final String path;
  final IconData icon;
}

class _ModuleTile extends StatelessWidget {
  const _ModuleTile({required this.module, required this.onTap});
  final _Module module;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: EdgeInsets.zero,
      color: Colors.white,
      elevation: 0,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(17), side: const BorderSide(color: Color(0xFFE7EDF2))),
      child: ListTile(
        onTap: onTap,
        contentPadding: const EdgeInsets.symmetric(horizontal: 15, vertical: 5),
        leading: Container(
          height: 44,
          width: 44,
          decoration: BoxDecoration(color: const Color(0xFFFFF2E5), borderRadius: BorderRadius.circular(13)),
          child: Icon(module.icon, color: _accent),
        ),
        title: Text(module.title, style: const TextStyle(fontWeight: FontWeight.w700, color: _brand)),
        subtitle: const Text('Buka modul'),
        trailing: const Icon(Icons.chevron_right_rounded),
      ),
    );
  }
}

class _RecordCard extends StatelessWidget {
  const _RecordCard({required this.record, required this.module, this.actionLabel, this.onAction, this.onEdit, this.onToggleActive});
  final Map<String, dynamic> record;
  final _Module module;
  final String? actionLabel;
  final VoidCallback? onAction;
  final VoidCallback? onEdit;
  final VoidCallback? onToggleActive;

  String _firstValue(List<String> keys, {String fallback = 'Data'}) {
    for (final key in keys) {
      final value = record[key];
      if (value != null && value.toString().isNotEmpty) return value.toString();
    }
    return fallback;
  }

  @override
  Widget build(BuildContext context) {
    final title = _firstValue(['kode_booking', 'nomor_manifest', 'nomor_voyage', 'nama_kapal', 'nama_perusahaan', 'nama', 'jenis_layanan', 'nomor_realisasi', 'id_booking']);
    final route = [record['asal'], record['tujuan']].where((value) => value != null).join(' → ');
    final status = record['is_active'] is bool
        ? (record['is_active'] == true ? 'aktif' : 'nonaktif')
        : (record['status']?.toString().replaceAll('_', ' ') ?? module.title);
    final date = record['tgl_booking'] ?? record['tgl_manifest'] ?? record['tgl_berangkat'];
    return Card(
      margin: EdgeInsets.zero,
      color: Colors.white,
      elevation: 0,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(17), side: const BorderSide(color: Color(0xFFE7EDF2))),
      child: ListTile(
        leading: CircleAvatar(backgroundColor: const Color(0xFFEAF1F5), child: Icon(module.icon, color: _brand, size: 20)),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
        subtitle: Text([if (route.isNotEmpty) route, if (date != null) date.toString(), status].join(' • ')),
        isThreeLine: route.isNotEmpty,
        trailing: onEdit != null || onToggleActive != null
            ? Row(mainAxisSize: MainAxisSize.min, children: [
                if (onEdit != null) IconButton(tooltip: 'Ubah', onPressed: onEdit, icon: const Icon(Icons.edit_outlined)),
                if (onToggleActive != null)
                  IconButton(
                    tooltip: record['is_active'] == true ? 'Nonaktifkan akun' : 'Aktifkan akun',
                    onPressed: onToggleActive,
                    icon: Icon(record['is_active'] == true ? Icons.person_off_outlined : Icons.person_outline),
                  ),
              ])
            : (actionLabel == null ? null : TextButton(onPressed: onAction, child: Text(actionLabel!))),
      ),
    );
  }
}

class _InfoCard extends StatelessWidget {
  const _InfoCard({required this.message});
  final String message;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16)),
        child: Text(message, style: TextStyle(color: Colors.blueGrey.shade600, height: 1.4)),
      );
}

class _ErrorCard extends StatelessWidget {
  const _ErrorCard({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16)),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(message, style: const TextStyle(color: Colors.red, height: 1.4)),
          const SizedBox(height: 10),
          TextButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh), label: const Text('Coba lagi')),
        ]),
      );
}

String _roleLabel(String role) => switch (role) {
      'admin' => 'Admin operasional',
      'operator' => 'Operator pelabuhan',
      'upp' => 'Petugas UPP',
      'checker' => 'Checker bongkar muat',
      'pelanggan' => 'Pelanggan',
      'manajer' => 'Manajer',
      'kantor_perhubungan' => 'Kantor perhubungan',
      _ => role,
    };
