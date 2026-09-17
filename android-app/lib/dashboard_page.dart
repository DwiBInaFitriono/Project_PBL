import 'dart:async';

import 'package:flutter/material.dart';

import 'api_client.dart';

import 'app_sidebar.dart';
import 'monitoring_page.dart';
import 'history_page.dart';
import 'settings_pages.dart';

class DashboardPage extends StatefulWidget {
  const DashboardPage({super.key, required this.onThemeChanged, this.api});
  final ApiClient? api;
  final ValueChanged<bool> onThemeChanged;

  @override
  State<DashboardPage> createState() => _DashboardPageState();
}

class _DashboardPageState extends State<DashboardPage> {
  final _scaffold = GlobalKey<ScaffoldState>();
  final _scroll = ScrollController();
  String _destination = 'dashboard';
  String _sensor = 'temperature';
  final _historyDrafts = <String, HistoryDraft>{};
  int _hours = 24;
  int? _expandedNode;
  bool _collapsed = false;
  String? _activeMenuSensor;
  bool _closing = false;
  bool _exitScheduled = false;
  final _backStack =
      <({String destination, String sensor, int hours, String? menuSensor})>[];

  Map<String, dynamic>? _snapshot;
  String? _error;
  bool _loading = false;
  Timer? _poll;

  @override
  void initState() {
    super.initState();
    if (widget.api != null) _load();
  }

  Future<void> _load() async {
    final api = widget.api;
    if (api == null || _loading || _closing) return;
    _poll?.cancel();
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final snapshot = await api.monitoring();
      if (mounted && !_closing) setState(() => _snapshot = snapshot);
    } on ApiException catch (e) {
      if (e.statusCode == 401) {
        _sessionExpired();
        return;
      }
      if (mounted && !_closing) setState(() => _error = e.message);
    } finally {
      if (mounted && !_closing) {
        setState(() => _loading = false);
        final seconds = (_snapshot?['pollIntervalSeconds'] as int? ?? 15).clamp(
          5,
          300,
        );
        _poll = Timer(Duration(seconds: seconds), _load);
      }
    }
  }

  void _sessionExpired() {
    if (!mounted || _closing) return;
    _poll?.cancel();
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Sesi berakhir. Silakan masuk kembali.')),
    );
    _closePreview();
  }

  Future<void> _logout() async {
    if (_closing) return;
    _poll?.cancel();
    setState(() {
      _closing = true;
      _snapshot = null;
      _historyDrafts.clear();
    });
    final navigator = Navigator.of(context);
    final route = ModalRoute.of(context);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted && route != null && route.isActive) {
        navigator.popUntil((candidate) => identical(candidate, route));
      }
    });
    String? error;
    try {
      await widget.api?.logout();
    } on ApiException {
      error =
          'Sesi lokal ditutup. Pencabutan token server belum terkonfirmasi.';
    }
    if (!mounted) return;
    if (error != null) {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(error)));
    }
    _closePreview();
  }

  @override
  void dispose() {
    _poll?.cancel();
    _scroll.dispose();
    super.dispose();
  }

  void _navigate(String target, String? sensor) {
    if (_closing) return;
    if (target == 'esp' &&
        widget.api != null &&
        widget.api?.user?['role'] != 'operator') {
      return;
    }
    _scaffold.currentState?.closeDrawer();
    setState(() {
      if (target != _destination || sensor != _activeMenuSensor) {
        _backStack.add((
          destination: _destination,
          sensor: _sensor,
          hours: _hours,
          menuSensor: _activeMenuSensor,
        ));
      }
      _destination = target;
      _activeMenuSensor = sensor;
      if (sensor != null) _sensor = sensor;
    });
    if (_scroll.hasClients) _scroll.jumpTo(0);
  }

  void _back() {
    if (_backStack.isEmpty) return;
    final previous = _backStack.removeLast();
    setState(() {
      _destination = previous.destination;
      _sensor = previous.sensor;
      _hours = previous.hours;
      _activeMenuSensor = previous.menuSensor;
    });
    if (_scroll.hasClients) _scroll.jumpTo(0);
  }

  void _closePreview() {
    if (_exitScheduled || !mounted) return;
    _poll?.cancel();
    setState(() {
      _closing = true;
      _exitScheduled = true;
      _snapshot = null;
      _historyDrafts.clear();
    });
    final navigator = Navigator.of(context);
    final route = ModalRoute.of(context);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted || route == null || !route.isActive) return;
      navigator.popUntil((candidate) => identical(candidate, route));
      navigator.removeRoute(route);
    });
  }

  void _refresh() {
    if (widget.api != null) {
      _load();
      return;
    }
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text(
          'Belum terhubung ke API Laravel. Tidak ada data baru yang diambil.',
        ),
      ),
    );
  }

  Widget _sidebar(bool mobile) => AppSidebar(
    showSettings: true,
    operatorPreview:
        widget.api == null || widget.api?.user?['role'] == 'operator',
    preview: widget.api == null,
    compact: !mobile && _collapsed,
    destination: _destination,
    activeSensor: _activeMenuSensor,
    expandedNode: _expandedNode,
    onClose: mobile ? () => _scaffold.currentState?.closeDrawer() : null,
    onToggleNode: (node) => setState(() {
      final wasCompact = !mobile && _collapsed;
      _collapsed = false;
      _expandedNode = wasCompact ? node : (_expandedNode == node ? null : node);
    }),
    onNavigate: _navigate,
  );

  @override
  Widget build(BuildContext context) {
    if (_closing) {
      return const PopScope<void>(
        canPop: false,
        child: Scaffold(
          body: Center(
            child: CircularProgressIndicator(semanticsLabel: 'Menutup sesi…'),
          ),
        ),
      );
    }
    final desktop = MediaQuery.sizeOf(context).width > 1024;
    final node = _destination.startsWith('node-')
        ? int.parse(_destination.split('-').last)
        : null;
    final dark = Theme.of(context).brightness == Brightness.dark;
    return PopScope<void>(
      canPop: widget.api == null && _backStack.isEmpty,
      onPopInvokedWithResult: (didPop, result) {
        if (!didPop) {
          if (_backStack.isNotEmpty) {
            _back();
          } else if (widget.api != null) {
            _logout();
          }
        }
      },
      child: Scaffold(
        key: _scaffold,
        drawer: desktop
            ? null
            : Drawer(
                width: MediaQuery.sizeOf(context).width.clamp(0, 340) - 32,
                child: _sidebar(true),
              ),
        body: Row(
          children: [
            if (desktop)
              SizedBox(width: _collapsed ? 80 : 250, child: _sidebar(false)),
            Expanded(
              child: Column(
                children: [
                  Material(
                    color: Theme.of(context).colorScheme.surface,
                    child: SafeArea(
                      bottom: false,
                      child: Padding(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 10,
                        ),
                        child: Row(
                          children: [
                            IconButton(
                              tooltip: desktop
                                  ? (_collapsed
                                        ? 'Perluas sidebar'
                                        : 'Ringkas sidebar')
                                  : 'Buka navigasi',
                              onPressed: () {
                                if (desktop) {
                                  setState(() => _collapsed = !_collapsed);
                                } else {
                                  _scaffold.currentState?.openDrawer();
                                }
                              },
                              icon: Icon(
                                desktop
                                    ? Icons.menu_open_rounded
                                    : Icons.menu_rounded,
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                _destination == 'account'
                                    ? 'Setting Akun'
                                    : _destination == 'esp'
                                    ? 'Setting ESP'
                                    : _destination.startsWith('history')
                                    ? 'Riwayat data'
                                    : node == null
                                    ? 'Dashboard'
                                    : 'Node $node',
                                style: const TextStyle(
                                  fontSize: 20,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                            IconButton(
                              tooltip: dark
                                  ? 'Aktifkan mode terang'
                                  : 'Aktifkan mode gelap',
                              onPressed: () => widget.onThemeChanged(!dark),
                              icon: Icon(
                                dark
                                    ? Icons.light_mode_outlined
                                    : Icons.dark_mode_outlined,
                              ),
                            ),
                            IconButton(
                              tooltip: widget.api == null
                                  ? 'Tutup pratinjau'
                                  : 'Keluar',
                              onPressed: _closing
                                  ? null
                                  : (widget.api == null
                                        ? _closePreview
                                        : _logout),
                              icon: const Icon(Icons.logout_rounded),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  const Divider(height: 1),
                  Expanded(
                    child: SafeArea(
                      top: false,
                      child: SingleChildScrollView(
                        controller: _scroll,
                        padding: EdgeInsets.all(desktop ? 28 : 20),
                        child: Center(
                          child: ConstrainedBox(
                            constraints: const BoxConstraints(maxWidth: 1120),
                            child: _destination.startsWith('history')
                                ? HistoryPage(
                                    api: widget.api,
                                    onSessionExpired: _sessionExpired,
                                    key: ValueKey(_destination),
                                    initialNode: _destination.contains('-')
                                        ? _destination.split('-').last
                                        : '',
                                    draft: _historyDrafts.putIfAbsent(
                                      _destination,
                                      () => HistoryDraft(
                                        node: _destination.contains('-')
                                            ? _destination.split('-').last
                                            : '',
                                      ),
                                    ),
                                  )
                                : _destination == 'esp'
                                ? EspSettingsPage(
                                    api: widget.api,
                                    onSessionExpired: _sessionExpired,
                                  )
                                : _destination == 'account'
                                ? AccountSettingsPage(
                                    api: widget.api,
                                    onSessionExpired: _sessionExpired,
                                  )
                                : MonitoringPage(
                                    snapshot: _snapshot,
                                    connected: widget.api != null,
                                    loading: _loading,
                                    error: _error,
                                    node: node,
                                    sensorId: _sensor,
                                    hours: _hours,
                                    onSensorChanged: (value) => setState(() {
                                      _sensor = value;
                                      if (node != null) {
                                        _activeMenuSensor = value;
                                      }
                                    }),
                                    onHoursChanged: (value) =>
                                        setState(() => _hours = value),
                                    onHistory: () {
                                      _historyDrafts[node == null
                                          ? 'history'
                                          : 'history-$node'] = HistoryDraft(
                                        node: node?.toString() ?? '',
                                        sensor: _sensor,
                                      );
                                      _navigate(
                                        node == null
                                            ? 'history'
                                            : 'history-$node',
                                        null,
                                      );
                                    },
                                    onRefresh: _refresh,
                                  ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
