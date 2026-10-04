import 'dart:typed_data';

import 'package:dio/dio.dart';

import '../core/config.dart';

class ApiException implements Exception {
  ApiException(this.message, [this.status]);
  final String message;
  final int? status;
  @override
  String toString() => message;
}

/// Client HTTP du serveur Viratech (jeton Sanctum dans l'en-tête Authorization).
class Api {
  Api._();
  static final Api i = Api._();

  final Dio _dio = Dio(BaseOptions(
    baseUrl: AppConfig.apiBase,
    connectTimeout: const Duration(seconds: 15),
    receiveTimeout: const Duration(seconds: 40),
    headers: {'Accept': 'application/json'},
  ));

  String? token;

  /// Appelé quand le serveur répond 401 (jeton expiré ou révoqué).
  void Function()? onUnauthorized;

  Future<dynamic> get(String path, {Map<String, dynamic>? query}) => _send('GET', path, query: query);
  Future<dynamic> post(String path, {Object? data}) => _send('POST', path, data: data);
  Future<dynamic> delete(String path) => _send('DELETE', path);

  /// Télécharge un fichier protégé (preuve de paiement) en mémoire.
  Future<Uint8List> bytes(String path) async {
    try {
      final r = await _dio.get<List<int>>(path, options: Options(responseType: ResponseType.bytes, headers: token != null ? {'Authorization': 'Bearer $token'} : null));
      return Uint8List.fromList(r.data ?? const []);
    } on DioException catch (e) {
      throw ApiException(_message(e), e.response?.statusCode);
    }
  }

  /// Envoi multipart (preuves de paiement).
  Future<dynamic> upload(String path, Map<String, dynamic> fields, {String? filePath}) async {
    final form = FormData.fromMap({
      ...fields,
      if (filePath != null) 'file': await MultipartFile.fromFile(filePath),
    });
    return _send('POST', path, data: form);
  }

  /// Envoi multipart de plusieurs fichiers (photos d'identité) : $files = {nom du champ: chemin}.
  Future<dynamic> uploadFiles(String path, Map<String, dynamic> fields, Map<String, String> files) async {
    final form = FormData.fromMap({
      ...fields,
      for (final e in files.entries) e.key: await MultipartFile.fromFile(e.value),
    });
    return _send('POST', path, data: form);
  }

  Future<dynamic> _send(String method, String path, {Object? data, Map<String, dynamic>? query}) async {
    try {
      final r = await _dio.request<dynamic>(
        path,
        data: data,
        queryParameters: query,
        options: Options(method: method, headers: token != null ? {'Authorization': 'Bearer $token'} : null),
      );
      return r.data;
    } on DioException catch (e) {
      final status = e.response?.statusCode;
      if (status == 401 && token != null) onUnauthorized?.call();
      throw ApiException(_message(e), status);
    }
  }

  String _message(DioException e) {
    final d = e.response?.data;
    if (d is Map) {
      if (d['message'] is String && (d['message'] as String).isNotEmpty) {
        final errors = d['errors'];
        if (errors is Map && errors.isNotEmpty) {
          final first = errors.values.first;
          if (first is List && first.isNotEmpty) return '${first.first}';
        }
        return d['message'] as String;
      }
    }
    switch (e.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.receiveTimeout:
      case DioExceptionType.connectionError:
        return 'Connexion impossible. Vérifiez votre réseau et réessayez.';
      default:
        return 'Une erreur est survenue (${e.response?.statusCode ?? 'réseau'}).';
    }
  }
}
