import 'package:flutter/material.dart';

/// Widget Visualisasi 3D Interaktif Rebung Bambu (Nilai Bonus PBL).
/// Memanfaatkan transformasi proyeksi perspektif 3D native Flutter
/// yang dapat dirotasi secara bebas oleh pengguna (drag gesture).
class Bamboo3DViewer extends StatefulWidget {
  const Bamboo3DViewer({
    super.key,
    this.soilMoisturePercent = 65.0,
    this.temperatureC = 27.5,
    this.nodeName = 'Node 1 (Zona A)',
  });

  final double soilMoisturePercent;
  final double temperatureC;
  final String nodeName;

  @override
  State<Bamboo3DViewer> createState() => _Bamboo3DViewerState();
}

class _Bamboo3DViewerState extends State<Bamboo3DViewer> {
  double _rotX = -0.35;
  double _rotY = 0.55;

  Color get _soilColor {
    // Warna tanah bereaksi terhadap kelembapan tanah
    if (widget.soilMoisturePercent < 35) {
      return const Color(0xFFC4A482); // Cokelat pucat kering
    } else if (widget.soilMoisturePercent <= 75) {
      return const Color(0xFF5D4037); // Cokelat subur lembap
    }
    return const Color(0xFF3E2723); // Gelap basah
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return Container(
      margin: const EdgeInsets.symmetric(vertical: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF111F40) : Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: isDark ? const Color(0xFF1E3366) : const Color(0xFFE2E0D8),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Visualisasi 3D Rebung Pintar',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                    ),
                    Text(
                      '${widget.nodeName} · Geser untuk memutar 3D',
                      style: const TextStyle(fontSize: 12, color: Colors.grey),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: const Color(0xFF2E7D32).withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: const Text(
                  'BONUS 3D',
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFF2E7D32),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          GestureDetector(
            onPanUpdate: (details) {
              setState(() {
                _rotY += details.delta.dx * 0.01;
                _rotX -= details.delta.dy * 0.01;
                _rotX = _rotX.clamp(-1.0, 0.2); // Batas rotasi vertikal
              });
            },
            child: SizedBox(
              height: 220,
              child: Center(
                child: Transform(
                  alignment: Alignment.center,
                  transform: Matrix4.identity()
                    ..setEntry(3, 2, 0.0015) // Perspektif kedalaman 3D
                    ..rotateX(_rotX)
                    ..rotateY(_rotY),
                  child: CustomPaint(
                    size: const Size(160, 160),
                    painter: _Bamboo3DPainter(
                      soilColor: _soilColor,
                      isDark: isDark,
                    ),
                  ),
                ),
              ),
            ),
          ),
          Wrap(
            alignment: WrapAlignment.spaceAround,
            spacing: 16,
            runSpacing: 8,
            children: [
              _InfoTag(
                label: 'Kondisi Tanah',
                value: '${widget.soilMoisturePercent.toStringAsFixed(1)}%',
                color: _soilColor,
              ),
              _InfoTag(
                label: 'Suhu Lingkungan',
                value: '${widget.temperatureC.toStringAsFixed(1)} °C',
                color: const Color(0xFF011E60),
              ),
            ],
          ),

        ],
      ),
    );
  }
}

class _InfoTag extends StatelessWidget {
  const _InfoTag({
    required this.label,
    required this.value,
    required this.color,
  });

  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(label, style: const TextStyle(fontSize: 11, color: Colors.grey)),
        const SizedBox(height: 2),
        Text(
          value,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.bold,
            color: color,
          ),
        ),
      ],
    );
  }
}

class _Bamboo3DPainter extends CustomPainter {
  _Bamboo3DPainter({required this.soilColor, required this.isDark});

  final Color soilColor;
  final bool isDark;

  @override
  void paint(Canvas canvas, Size size) {
    final cx = size.width / 2;
    final cy = size.height / 2;

    // 1. Gambar Pot Tanah / Media Kubus 3D
    final potTop = Paint()
      ..color = soilColor
      ..style = PaintingStyle.fill;
    final potSide = Paint()
      ..color = soilColor.withValues(alpha: 0.7)
      ..style = PaintingStyle.fill;
    final border = Paint()
      ..color = Colors.black26
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.5;

    // Permukaan Atas Pot (Isometric Rhombus)
    final topPath = Path()
      ..moveTo(cx, cy + 30)
      ..lineTo(cx + 60, cy + 55)
      ..lineTo(cx, cy + 80)
      ..lineTo(cx - 60, cy + 55)
      ..close();
    canvas.drawPath(topPath, potTop);
    canvas.drawPath(topPath, border);

    // Sisi Kiri Pot
    final leftSidePath = Path()
      ..moveTo(cx - 60, cy + 55)
      ..lineTo(cx, cy + 80)
      ..lineTo(cx, cy + 125)
      ..lineTo(cx - 60, cy + 100)
      ..close();
    canvas.drawPath(leftSidePath, potSide);
    canvas.drawPath(leftSidePath, border);

    // Sisi Kanan Pot
    final rightSidePath = Path()
      ..moveTo(cx, cy + 80)
      ..lineTo(cx + 60, cy + 55)
      ..lineTo(cx + 60, cy + 100)
      ..lineTo(cx, cy + 125)
      ..close();
    canvas.drawPath(rightSidePath, potSide..color = soilColor.withValues(alpha: 0.5));
    canvas.drawPath(rightSidePath, border);


    // 2. Batang Rebung Bambu (Kerucut Silinder Berlapis)
    final bambooBase = Paint()
      ..color = const Color(0xFF558B2F) // Hijau pucat rebung
      ..style = PaintingStyle.fill;
    final bambooTip = Paint()
      ..color = const Color(0xFF7CB342) // Hijau daun tunas
      ..style = PaintingStyle.fill;

    // Ruas 1 (Bawah)
    final segment1 = Path()
      ..moveTo(cx - 18, cy + 50)
      ..lineTo(cx + 18, cy + 50)
      ..lineTo(cx + 14, cy + 15)
      ..lineTo(cx - 14, cy + 15)
      ..close();
    canvas.drawPath(segment1, bambooBase);
    canvas.drawPath(segment1, border);

    // Ruas 2 (Tengah)
    final segment2 = Path()
      ..moveTo(cx - 14, cy + 15)
      ..lineTo(cx + 14, cy + 15)
      ..lineTo(cx + 10, cy - 20)
      ..lineTo(cx - 10, cy - 20)
      ..close();
    canvas.drawPath(segment2, bambooBase..color = const Color(0xFF689F38));
    canvas.drawPath(segment2, border);

    // Ruas 3 (Pucuk Tunas Runcing)
    final segment3 = Path()
      ..moveTo(cx - 10, cy - 20)
      ..lineTo(cx + 10, cy - 20)
      ..lineTo(cx, cy - 65)
      ..close();
    canvas.drawPath(segment3, bambooTip);
    canvas.drawPath(segment3, border);

    // Daun Tunas Rebung (Kiri & Kanan)
    final leafPaint = Paint()
      ..color = const Color(0xFF8BC34A)
      ..style = PaintingStyle.fill;

    final leftLeaf = Path()
      ..moveTo(cx - 5, cy - 30)
      ..quadraticBezierTo(cx - 35, cy - 50, cx - 25, cy - 70)
      ..quadraticBezierTo(cx - 15, cy - 45, cx - 5, cy - 30)
      ..close();
    canvas.drawPath(leftLeaf, leafPaint);
    canvas.drawPath(leftLeaf, border);

    final rightLeaf = Path()
      ..moveTo(cx + 5, cy - 30)
      ..quadraticBezierTo(cx + 35, cy - 50, cx + 25, cy - 70)
      ..quadraticBezierTo(cx + 15, cy - 45, cx + 5, cy - 30)
      ..close();
    canvas.drawPath(rightLeaf, leafPaint);
    canvas.drawPath(rightLeaf, border);
  }

  @override
  bool shouldRepaint(covariant _Bamboo3DPainter oldDelegate) {
    return oldDelegate.soilColor != soilColor || oldDelegate.isDark != isDark;
  }
}
