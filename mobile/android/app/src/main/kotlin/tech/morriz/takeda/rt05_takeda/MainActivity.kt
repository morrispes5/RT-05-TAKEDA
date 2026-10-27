package tech.morriz.takeda.rt05_takeda

import android.Manifest
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Intent
import android.content.pm.PackageManager
import android.content.pm.ShortcutInfo
import android.content.pm.ShortcutManager
import android.graphics.drawable.Icon
import android.media.AudioAttributes
import android.media.RingtoneManager
import android.os.Build
import android.os.Bundle
import android.provider.Settings
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    private var channel: MethodChannel? = null
    private var pendingLaunch: Map<String, String>? = null
    private val channelId = "rt05_darurat_v1"
    private val shortcutAction = "tech.morriz.takeda.PANIC"

    private fun panicShortcut(): ShortcutInfo = ShortcutInfo.Builder(this, "rt05_panic")
        .setShortLabel("Tombol panik")
        .setLongLabel("Buka laporan darurat RT05")
        .setIcon(Icon.createWithResource(this, applicationInfo.icon))
        .setIntent(Intent(this, MainActivity::class.java).setAction(shortcutAction))
        .build()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        capture(intent)
        val manager = getSystemService(NOTIFICATION_SERVICE) as NotificationManager
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val alarms = NotificationChannel(channelId, "Darurat RT05", NotificationManager.IMPORTANCE_HIGH).apply {
                description = "Pemberitahuan kejadian darurat warga. Izin dan bunyi dapat diatur di HP."
                setSound(RingtoneManager.getDefaultUri(RingtoneManager.TYPE_ALARM), AudioAttributes.Builder().setUsage(AudioAttributes.USAGE_ALARM).setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION).build())
                enableVibration(true)
                lockscreenVisibility = Notification.VISIBILITY_PRIVATE
            }
            manager.createNotificationChannel(alarms)
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N_MR1) {
            getSystemService(ShortcutManager::class.java).addDynamicShortcuts(listOf(panicShortcut()))
        }
    }

    private fun capture(action: Intent?) {
        if (action?.action == shortcutAction) pendingLaunch = mapOf("type" to "shortcut")
        val id = action?.getStringExtra("rt05_emergency_id")
        if (id != null && id.matches(Regex("[0-9a-fA-F-]{36}"))) pendingLaunch = mapOf("type" to "emergency", "id" to id)
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        capture(intent)
        if (pendingLaunch != null) channel?.invokeMethod("launch", null)
    }

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        channel = MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "rt05/emergency").apply {
            setMethodCallHandler { call, result ->
                when (call.method) {
                    "takeLaunch" -> { val action = pendingLaunch; pendingLaunch = null; result.success(action) }
                    "pinShortcut" -> {
                        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                            val manager = getSystemService(ShortcutManager::class.java)
                            try { result.success(manager.isRequestPinShortcutSupported && manager.requestPinShortcut(panicShortcut(), null)) }
                            catch (_: RuntimeException) { result.success(false) }
                        } else result.success(false)
                    }
                    "alarm" -> {
                        val id = call.argument<String>("id")
                        if (id == null || !id.matches(Regex("[0-9a-fA-F-]{36}"))) { result.error("INVALID_ID", "Invalid reference", null) }
                        else { showAlarm(id, call.argument<String>("notification_id")); result.success(null) }
                    }
                    "settings" -> {
                        val settings = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) Intent(Settings.ACTION_CHANNEL_NOTIFICATION_SETTINGS).putExtra(Settings.EXTRA_APP_PACKAGE, packageName).putExtra(Settings.EXTRA_CHANNEL_ID, channelId)
                            else Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS, android.net.Uri.parse("package:$packageName"))
                        try { startActivity(settings); result.success(null) } catch (_: Exception) { result.error("UNAVAILABLE", "Settings unavailable", null) }
                    }
                    else -> result.notImplemented()
                }
            }
        }
    }

    private fun showAlarm(id: String, notificationId: String?) {
        if (Build.VERSION.SDK_INT >= 33 && checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) return
        val target = Intent(this, MainActivity::class.java).setAction("rt05.emergency.$id").putExtra("rt05_emergency_id", id)
            .addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP)
        val tap = PendingIntent.getActivity(this, id.hashCode(), target, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
        val builder = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) Notification.Builder(this, channelId) else Notification.Builder(this)
        builder.setSmallIcon(R.drawable.ic_emergency).setContentTitle("Darurat RT05 TAKEDA")
            .setContentText("Ada laporan darurat warga. Buka aplikasi untuk melihat detail.")
            .setCategory(Notification.CATEGORY_ALARM).setVisibility(Notification.VISIBILITY_PRIVATE)
            .setContentIntent(tap).setAutoCancel(true).setOnlyAlertOnce(true)
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) builder.setPriority(Notification.PRIORITY_MAX)
            .setSound(RingtoneManager.getDefaultUri(RingtoneManager.TYPE_ALARM))
        try { (getSystemService(NOTIFICATION_SERVICE) as NotificationManager).notify("rt05-${notificationId ?: id}", 0, builder.build()) } catch (_: SecurityException) { /* Permission can change while sending. */ }
    }
}
