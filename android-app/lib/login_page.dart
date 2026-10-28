import 'package:flutter/material.dart';

import 'dashboard_page.dart';
import 'api_client.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({
    super.key,
    required this.onThemeChanged,
    this.api,
    this.configurationError,
  });
  final ApiClient? api;
  final String? configurationError;
  final ValueChanged<bool> onThemeChanged;

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final _formKey = GlobalKey<FormState>();
  bool _passwordVisible = false;

  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_busy || !_formKey.currentState!.validate()) return;
    final api = widget.api;
    if (api != null) {
      ScaffoldMessenger.of(context)
        ..clearSnackBars()
        ..removeCurrentSnackBar();
      FocusScope.of(context).unfocus();
      setState(() {
        _busy = true;
        _error = null;
      });
      try {
        await api.login(
          email: _email.text.trim(),
          password: _password.text,
          deviceName: 'Hidroponik DFT Flutter',
        );
        if (!mounted) {
          await api.logout();
          return;
        }
        _password.clear();
        await Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (_) =>
                DashboardPage(api: api, onThemeChanged: widget.onThemeChanged),
          ),
        );
      } on ApiException catch (e) {
        if (mounted) {
          setState(
            () => _error = e.statusCode == 401
                ? 'Email atau kata sandi salah.'
                : e.message,
          );
        }
      } finally {
        if (mounted) {
          setState(() {
            _busy = false;
            _passwordVisible = false;
          });
        }
      }
      return;
    }
    FocusScope.of(context).unfocus();
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        const SnackBar(
          content: Text(
            'Login belum dihubungkan ke API Laravel. Ini pratinjau halaman, bukan sesi masuk.',
          ),
        ),
      );
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Scaffold(
      body: Form(
        key: _formKey,
        child: SafeArea(
          child: LayoutBuilder(
            builder: (context, constraints) {
              final compact = constraints.maxHeight < 680;
              final gap = compact ? 16.0 : 24.0;
              return SingleChildScrollView(
                keyboardDismissBehavior:
                    ScrollViewKeyboardDismissBehavior.onDrag,
                padding: const EdgeInsets.symmetric(
                  horizontal: 24,
                  vertical: 16,
                ),
                child: ConstrainedBox(
                  constraints: BoxConstraints(
                    minHeight: (constraints.maxHeight - 32).clamp(
                      0,
                      double.infinity,
                    ),
                  ),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Flexible(child: HidroponikBrand()),
                          const SizedBox(width: 16),
                          IconButton.outlined(
                            constraints: const BoxConstraints.tightFor(
                              width: 48,
                              height: 48,
                            ),
                            tooltip: colors.brightness == Brightness.dark
                                ? 'Aktifkan mode terang'
                                : 'Aktifkan mode gelap',
                            onPressed: () => widget.onThemeChanged(
                              colors.brightness != Brightness.dark,
                            ),
                            icon: Icon(
                              colors.brightness == Brightness.dark
                                  ? Icons.light_mode_outlined
                                  : Icons.dark_mode_outlined,
                              size: 22,
                            ),
                          ),
                        ],
                      ),
                      Center(
                        child: Container(
                          constraints: const BoxConstraints(maxWidth: 420),
                          padding: EdgeInsets.symmetric(vertical: gap),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              if (!compact) ...[
                                Text(
                                  'HIDROPONIK DFT',
                                  style: TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.w700,
                                    letterSpacing: 1.4,
                                    color: colors.onSurfaceVariant,
                                  ),
                                ),
                                const SizedBox(height: 16),
                              ],
                              Text(
                                'Selamat datang kembali.',
                                style: TextStyle(
                                  fontSize: compact ? 28 : 34,
                                  height: 1.15,
                                  letterSpacing: -1,
                                  fontWeight: FontWeight.w700,
                                  color: colors.onSurface,
                                ),
                              ),
                              const SizedBox(height: 10),
                              Text(
                                'Pantau kebunmu, dalam satu tempat.',
                                style: TextStyle(
                                  fontSize: 14,
                                  height: 1.5,
                                  color: colors.onSurfaceVariant,
                                ),
                              ),
                              SizedBox(height: gap),
                              const Text(
                                'Alamat email',
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              const SizedBox(height: 8),
                              TextFormField(
                                key: const Key('email'),
                                controller: _email,
                                enabled: !_busy,
                                keyboardType: TextInputType.emailAddress,
                                autocorrect: false,
                                validator: (value) {
                                  final email = value?.trim() ?? '';
                                  if (email.isEmpty) {
                                    return 'Alamat email wajib diisi.';
                                  }
                                  if (!RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$')
                                      .hasMatch(email)) {
                                    return 'Masukkan alamat email yang valid.';
                                  }
                                  return null;
                                },
                                textInputAction: TextInputAction.next,
                                autofillHints: const [
                                  AutofillHints.username,
                                  AutofillHints.email,
                                ],
                                decoration: const InputDecoration(
                                  hintText: 'nama@contoh.com',
                                ),
                              ),
                              const SizedBox(height: 18),
                              const Text(
                                'Kata sandi',
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              const SizedBox(height: 8),
                              TextFormField(
                                key: const Key('password'),
                                controller: _password,
                                enabled: !_busy,
                                obscureText: !_passwordVisible,
                                textInputAction: TextInputAction.done,
                                autofillHints: const [AutofillHints.password],
                                validator: (value) =>
                                    value == null || value.isEmpty
                                    ? 'Kata sandi wajib diisi.'
                                    : null,
                                onFieldSubmitted: (_) => _submit(),
                                enableSuggestions: false,
                                autocorrect: false,
                                decoration: InputDecoration(
                                  hintText: 'Masukkan kata sandi',
                                  suffixIcon: IconButton(
                                    tooltip: _passwordVisible
                                        ? 'Sembunyikan kata sandi'
                                        : 'Tampilkan kata sandi',
                                    onPressed: () => setState(
                                      () =>
                                          _passwordVisible = !_passwordVisible,
                                    ),
                                    icon: Icon(
                                      _passwordVisible
                                          ? Icons.visibility_off_outlined
                                          : Icons.visibility_outlined,
                                      size: 21,
                                    ),
                                  ),
                                ),
                              ),
                              const SizedBox(height: 22),
                              if (_error != null ||
                                  widget.configurationError != null)
                                Padding(
                                  padding: const EdgeInsets.only(bottom: 12),
                                  child: Text(
                                    _error ?? widget.configurationError!,
                                    style: TextStyle(color: colors.error),
                                    semanticsLabel:
                                        _error ?? widget.configurationError,
                                  ),
                                ),
                              FilledButton(
                                key: const Key('submit'),
                                style: FilledButton.styleFrom(
                                  minimumSize: const Size.fromHeight(52),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                ),
                                onPressed:
                                    _busy || widget.configurationError != null
                                    ? null
                                    : _submit,
                                child: _busy
                                    ? const SizedBox(
                                        width: 22,
                                        height: 22,
                                        child: CircularProgressIndicator(
                                          strokeWidth: 2,
                                        ),
                                      )
                                    : const Row(
                                        mainAxisAlignment:
                                            MainAxisAlignment.spaceBetween,
                                        children: [
                                          Text(
                                            'Masuk',
                                            style: TextStyle(
                                              fontSize: 15,
                                              fontWeight: FontWeight.w700,
                                            ),
                                          ),
                                          Icon(
                                            Icons.arrow_outward_rounded,
                                            size: 21,
                                          ),
                                        ],
                                      ),
                              ),
                              const SizedBox(height: 16),
                              Row(
                                children: [
                                  Expanded(
                                    child: Divider(
                                      color: colors.outline.withValues(alpha: .35),
                                    ),
                                  ),
                                  Padding(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 10,
                                    ),
                                    child: Text(
                                      'atau',
                                      style: TextStyle(
                                        fontSize: 12,
                                        color: colors.onSurfaceVariant,
                                      ),
                                    ),
                                  ),
                                  Expanded(
                                    child: Divider(
                                      color: colors.outline.withValues(alpha: .35),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 14),
                              OutlinedButton.icon(
                                key: const Key('btn-preview-mode'),
                                style: OutlinedButton.styleFrom(
                                  minimumSize: const Size(double.infinity, 48),
                                  side: BorderSide(
                                    color: colors.outline.withValues(alpha: .5),
                                  ),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(14),
                                  ),
                                ),
                                onPressed: () => Navigator.of(context).push(
                                  MaterialPageRoute<void>(
                                    builder: (_) => DashboardPage(
                                      onThemeChanged: widget.onThemeChanged,
                                    ),
                                  ),
                                ),
                                icon: const Icon(
                                  Icons.visibility_outlined,
                                  size: 20,
                                ),
                                label: const Text(
                                  'Lihat dashboard (pratinjau)',
                                  style: TextStyle(
                                    fontSize: 14,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ),
                              const SizedBox(height: 10),
                              Text(
                                widget.api == null
                                    ? 'Pratinjau · Belum terhubung ke server.'
                                    : 'REST API v1 · Mode pratinjau tersedia',
                                textAlign: TextAlign.center,
                                style: TextStyle(
                                  fontSize: 12,
                                  height: 1.5,
                                  color: colors.onSurfaceVariant,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      Text(
                        'Hidroponik DFT',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: 11,
                          color: colors.onSurfaceVariant,
                        ),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
      ),
    );
  }
}

class HidroponikBrand extends StatelessWidget {
  const HidroponikBrand({super.key});

  @override
  Widget build(BuildContext context) {
    final color = Theme.of(context).colorScheme.onSurface;
    return Semantics(
      label: 'Hidroponik DFT',
      excludeSemantics: true,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          CustomPaint(
            size: const Size(40, 44),
            painter: _HydroponicChannelPainter(color),
          ),
          const SizedBox(width: 10),
          Flexible(
            child: Text(
              'Hidroponik\nDFT',
              style: TextStyle(
                fontSize: 22,
                height: 1.03,
                letterSpacing: -.7,
                fontWeight: FontWeight.w700,
                color: color,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _HydroponicChannelPainter extends CustomPainter {
  const _HydroponicChannelPainter(this.color);
  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2.4
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;
    final channel = RRect.fromRectAndRadius(
      Rect.fromLTWH(3, 22, size.width - 6, 16),
      const Radius.circular(5),
    );
    canvas.drawRRect(channel, paint);
    canvas.drawLine(Offset(8, 30), Offset(size.width - 8, 30), paint);
    for (final x in [10.0, 20.0, 30.0]) {
      canvas.drawCircle(Offset(x, 15), 4, paint);
      canvas.drawLine(Offset(x, 19), Offset(x, 22), paint);
    }
  }

  @override
  bool shouldRepaint(_HydroponicChannelPainter oldDelegate) =>
      oldDelegate.color != color;
}
