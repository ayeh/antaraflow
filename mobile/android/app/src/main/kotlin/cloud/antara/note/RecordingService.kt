package cloud.antara.note

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.os.Build
import android.os.IBinder

/**
 * Keeps the microphone alive while the app is not on screen.
 *
 * Without this Android revokes microphone access the moment the app is
 * backgrounded, and it does so silently: the recorder carries on producing
 * perfectly timed chunks, every sample in them a zero. An hour-long sitting
 * with the phone face down would upload an hour of digital silence and nothing
 * anywhere would say so. Measured on an API 36 emulator — 100% zero samples
 * for the whole time the app was backgrounded.
 *
 * The notification is not a nicety either. It is what tells the room that the
 * phone on the table is still listening, which is the same job the Live
 * Activity does on iOS.
 */
class RecordingService : Service() {
    override fun onBind(intent: Intent?): IBinder? = null

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        val title = intent?.getStringExtra(EXTRA_TITLE) ?: "Recording"

        createChannel()
        startForeground(NOTIFICATION_ID, buildNotification(title))

        // Not sticky: if the process dies the session is over as far as this
        // service is concerned, and a restarted service with no recorder
        // behind it would show a notification that lies.
        return START_NOT_STICKY
    }

    private fun createChannel() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return

        val channel = NotificationChannel(
            CHANNEL_ID,
            "Recording",
            NotificationManager.IMPORTANCE_LOW,
        ).apply {
            description = "Shown while a sitting is being recorded."
            setShowBadge(false)
        }

        getSystemService(NotificationManager::class.java).createNotificationChannel(channel)
    }

    private fun buildNotification(title: String): Notification {
        val open = PendingIntent.getActivity(
            this,
            0,
            Intent(this, MainActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_SINGLE_TOP or Intent.FLAG_ACTIVITY_NEW_TASK
            },
            PendingIntent.FLAG_IMMUTABLE,
        )

        return Notification.Builder(this, CHANNEL_ID)
            .setContentTitle(title)
            .setContentText("Recording the sitting")
            .setSmallIcon(android.R.drawable.presence_audio_online)
            .setContentIntent(open)
            .setOngoing(true)
            .setUsesChronometer(true)
            .build()
    }

    companion object {
        private const val CHANNEL_ID = "recording"
        private const val NOTIFICATION_ID = 1

        const val EXTRA_TITLE = "title"

        fun start(context: Context, title: String) {
            val intent = Intent(context, RecordingService::class.java)
                .putExtra(EXTRA_TITLE, title)

            context.startForegroundService(intent)
        }

        fun stop(context: Context) {
            context.stopService(Intent(context, RecordingService::class.java))
        }
    }
}
