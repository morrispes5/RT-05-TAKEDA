import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../app/config.dart';
import 'health.dart';
import 'session.dart';

/// Error API v1 terstruktur: {"error": {code, message, fields?, request_id}}.
class ApiException implements Exception {
  ApiException(this.status, this.code, this.message, {this.fields = const {}, this.requestId});

  final int status;
  final String code;
  final String message;
  final Map<String, List<String>> fields;
  final String? requestId;

  bool get isConflict => status == 409;
  bool get isUnknownOutcome => code == 'NETWORK_TIMEOUT';

  /// Pesan pertama yang layak ditampilkan (validasi per field atau pesan umum).
  String get display => fields.values.expand((e) => e).firstOrNull ?? message;

  @override
  String toString() => display;

  static ApiException from(DioException e) {
    final data = e.response?.data;
    if (data is Map && data['error'] is Map) {
      final err = data['error'] as Map;
      final fields = <String, List<String>>{};
      (err['fields'] as Map?)?.forEach((k, v) => fields['$k'] = (v as List).map((x) => '$x').toList());
      return ApiException(e.response?.statusCode ?? 0, '${err['code']}', '${err['message']}', fields: fields, requestId: err['request_id'] as String?);
    }
    return switch (e.type) {
      DioExceptionType.connectionTimeout ||
      DioExceptionType.receiveTimeout ||
      DioExceptionType.sendTimeout => ApiException(0, 'NETWORK_TIMEOUT', 'Koneksi lambat. Hasil belum diketahui; periksa data sebelum mencoba lagi.'),
      DioExceptionType.connectionError => ApiException(0, 'NETWORK_ERROR', 'Tidak dapat terhubung ke server. Periksa koneksi internet.'),
      // 5xx tanpa envelope (mis. proxy/gateway saat deploy): layanan belum tersedia, bukan sesi tidak sah.
      _ when (e.response?.statusCode ?? 0) >= 500 => ApiException(
        e.response!.statusCode!,
        'SERVICE_UNAVAILABLE',
        'Layanan sementara tidak tersedia. Coba lagi nanti.',
      ),
      _ => ApiException(e.response?.statusCode ?? 0, 'UNKNOWN', 'Terjadi kesalahan. Coba lagi.'),
    };
  }
}

/// Klien HTTP API RT05 TAKEDA. Token bearer dari secure storage; 401 → sesi dihapus.
/// Tidak ada auto-retry untuk POST (keuangan memakai Idempotency-Key yang sama saat retry manual).
class Api {
  Api(this._ref) {
    _base = _ref.read(appConfigProvider).apiBaseUrl;
    _dio = Dio(
      BaseOptions(
        baseUrl: '$_base/api/v1',
        connectTimeout: const Duration(seconds: 15),
        receiveTimeout: const Duration(seconds: 30),
        headers: {'Accept': 'application/json'},
      ),
    );
    final adapter = _ref.read(httpAdapterProvider);
    if (adapter != null) _dio.httpClientAdapter = adapter;
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          final token = _ref.read(sessionProvider).token;
          // Nilai yang dipasang pemanggil saat request dibuat tidak ditimpa sesi yang lebih baru.
          // Berkas publik situs (mis. /unduh/versi.json) tidak menerima token.
          if (token != null && options.extra['public'] != true) options.headers.putIfAbsent('Authorization', () => 'Bearer $token');
          options.extra.putIfAbsent('sessionEpoch', () => _ref.read(sessionProvider.notifier).epoch);
          handler.next(options);
        },
        onError: (e, handler) {
          final auth = e.requestOptions.headers['Authorization'];
          final epoch = e.requestOptions.extra['sessionEpoch'];
          if (e.response?.statusCode == 401 && e.requestOptions.extra['ignoreUnauthorized'] != true && auth is String && auth.startsWith('Bearer ')) {
            _ref.read(sessionProvider.notifier).expire(token: auth.substring(7), epoch: epoch is int ? epoch : null);
          }
          handler.next(e);
        },
      ),
    );
  }

  final Ref _ref;
  late final String _base;
  late final Dio _dio;

  String absolute(String path) => path.startsWith('http') ? path : '$_base$path';
  Map<String, String> get authHeaders => {if (_ref.read(sessionProvider).token != null) 'Authorization': 'Bearer ${_ref.read(sessionProvider).token}'};

  Future<T> _wrap<T>(Future<Response<dynamic>> Function() call, T Function(dynamic body) parse) async {
    try {
      final res = await call();
      return parse(res.data);
    } on DioException catch (e) {
      throw ApiException.from(e);
    }
  }

  /// M16: JSON publik di origin yang sama di luar /api/v1, tanpa token dan tanpa envelope.
  Future<dynamic> publicJson(String path) => _wrap(
    () => _dio.get(
      '$_base$path',
      options: Options(extra: {'public': true}, responseType: ResponseType.json),
    ),
    (b) => b,
  );

  /// ADR46: unduh APK pembaruan dari /unduh situs resmi ke [savePath], tanpa token.
  Future<void> downloadPublic(String path, String savePath, {ProgressCallback? onProgress, CancelToken? cancel}) => _wrap(
    () => _dio.download(
      '$_base$path',
      savePath,
      onReceiveProgress: onProgress,
      cancelToken: cancel,
      options: Options(extra: {'public': true}, receiveTimeout: const Duration(seconds: 60)),
    ),
    (_) {},
  );

  /// Mengembalikan `data` dari envelope.
  /// [bearer] memeriksa token tertentu tanpa mengganti token sesi yang sedang aktif.
  /// [ignoreUnauthorized] menahan interceptor agar 401 token itu tidak mengakhiri sesi HP.
  Future<dynamic> get(String path, {Map<String, dynamic>? query, String? bearer, bool ignoreUnauthorized = false}) => _wrap(
    () => _dio.get(
      path,
      queryParameters: query,
      options: Options(headers: {if (bearer != null) 'Authorization': 'Bearer $bearer'}, extra: {if (ignoreUnauthorized) 'ignoreUnauthorized': true}),
    ),
    (b) => (b as Map)['data'],
  );

  /// Mengembalikan envelope lengkap (data + meta) untuk daftar berhalaman.
  Future<Map<String, dynamic>> page(String path, {Map<String, dynamic>? query}) =>
      _wrap(() => _dio.get(path, queryParameters: query), (b) => Map<String, dynamic>.from(b as Map));

  /// [bearer] dan [sessionEpoch] dipasang sekarang, sebelum interceptor Dio berjalan.
  Future<dynamic> post(String path, {Object? data, String? idempotencyKey, String? bearer, int? sessionEpoch}) => _wrap(
    () => _dio.post(
      path,
      data: _financeData(path, data),
      options: Options(
        headers: {'Idempotency-Key': ?idempotencyKey, if (bearer != null) 'Authorization': 'Bearer $bearer'},
        extra: {'sessionEpoch': ?sessionEpoch},
      ),
    ),
    (b) => b is Map ? b['data'] : null,
  );

  Object? _financeData(String path, Object? data) {
    if (_ref.read(sessionProvider).akun?.keuanganFleksibel != true || data is! Map) return data;
    if (!RegExp(r'^/admin/(dues|deposit|cash|old-book|houses/[^/]+/dues-rates)').hasMatch(path)) return data;
    return {...data, 'versi_keuangan': 2};
  }

  Future<dynamic> patch(String path, {Object? data}) => _wrap(() => _dio.patch(path, data: data), (b) => b is Map ? b['data'] : null);

  Future<dynamic> put(String path, {Object? data}) => _wrap(() => _dio.put(path, data: data), (b) => b is Map ? b['data'] : null);

  /// Unduhan berkas (mis. ekspor CSV). Respons error JSON tetap diubah menjadi [ApiException].
  Future<Uint8List> download(String path, {Map<String, dynamic>? query}) async {
    try {
      final res = await _dio.get<List<int>>(
        path,
        queryParameters: query,
        options: Options(
          responseType: ResponseType.bytes,
          headers: {'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, text/csv, application/json'},
        ),
      );
      return Uint8List.fromList(res.data ?? const []);
    } on DioException catch (e) {
      final data = e.response?.data;
      if (data is List<int>) {
        try {
          e.response!.data = jsonDecode(utf8.decode(data));
        } catch (_) {}
      }
      throw ApiException.from(e);
    }
  }

  /// Mengembalikan `data` bila ada (mis. hapus foto pengaduan); 204 → null.
  Future<dynamic> delete(String path, {Object? data}) => _wrap(() => _dio.delete(path, data: data), (b) => b is Map ? b['data'] : null);
}

final apiProvider = Provider<Api>((ref) => Api(ref));

/// Api untuk provider data milik akun. Provider ikut dimuat ulang saat akun aktif berganti (ADR51),
/// supaya iuran, keluarga, dan laporan rumah lain tidak tampil dari cache autoDispose yang
/// masih dipegang tab baru pada frame peralihan.
Api accountApi(Ref ref) {
  ref.watch(sessionProvider.select((s) => s.akun?.id));
  return ref.read(apiProvider);
}
