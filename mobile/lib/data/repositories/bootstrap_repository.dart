import '../../domain/models/bootstrap.dart';
import '../api/api_client.dart';

class BootstrapRepository {
  BootstrapRepository({required ApiClient client}) : _client = client;

  final ApiClient _client;

  /// One call on cold start. Six separate requests over a mobile connection is
  /// most of a second before the first screen can draw.
  Future<BootstrapData> load() async {
    return BootstrapData.fromJson(await _client.get('/bootstrap'));
  }
}
