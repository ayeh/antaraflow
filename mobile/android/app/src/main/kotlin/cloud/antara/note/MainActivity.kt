package cloud.antara.note

import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)

        MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            CHANNEL,
        ).setMethodCallHandler { call, result ->
            when (call.method) {
                "start" -> {
                    RecordingService.start(
                        this,
                        call.argument<String>("title") ?: "Recording",
                        call.argument<String>("note"),
                    )
                    result.success(true)
                }
                "note" -> {
                    RecordingService.note(this, call.argument<String>("note"))
                    result.success(true)
                }
                "stop" -> {
                    RecordingService.stop(this)
                    result.success(true)
                }
                else -> result.notImplemented()
            }
        }
    }

    private companion object {
        const val CHANNEL = "cloud.antara.note/recording_service"
    }
}
