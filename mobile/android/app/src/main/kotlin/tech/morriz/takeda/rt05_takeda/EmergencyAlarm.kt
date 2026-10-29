package tech.morriz.takeda.rt05_takeda

import android.Manifest
import android.app.Activity
import android.app.KeyguardManager
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.media.AudioAttributes
import android.media.RingtoneManager
import android.graphics.Color
import android.os.Build
import android.os.Bundle
import android.view.Gravity
import android.view.WindowManager
import android.widget.Button
import android.widget.LinearLayout
import android.widget.TextView

/**
 * ADR50: alarm darurat berdering seperti panggilan masuk.
 *
 * Bunyi alarm berulang (FLAG_INSISTENT) sampai notifikasi dibuka/diusap atau 10 menit (umur alarm di server), dengan
 * layar penuh bila Android mengizinkan. Isi di layar kunci tetap generik (tanpa nama/rumah); detail hanya di aplikasi
 * setelah HP dibuka kuncinya. Tag per kejadian sehingga push ganda tidak menumpuk dan pembatalan dapat menghentikannya.
 */
object EmergencyAlarm {
    const val CHANNEL_ID = "rt05_darurat_v2"
    const val EXTRA_ID = "rt05_emergency_id"
    private const val TIMEOUT_MS = 10 * 60 * 1000L
    private val uuid = Regex("[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}")

    fun isId(value: String?): Boolean = value != null && uuid.matches(value)

    private fun tag(id: String) = "rt05-darurat-$id"

    fun ensureChannel(context: Context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        // Kanal v1 (bunyi sekali) dibiarkan agar pengaturan pengguna lama tidak hilang; alarm baru memakai v2.
        val channel = NotificationChannel(CHANNEL_ID, "Alarm darurat RT05", NotificationManager.IMPORTANCE_HIGH).apply {
            description = "Berdering terus seperti panggilan saat ada laporan darurat warga. Bunyi dan izin dapat diatur di HP."
            setSound(RingtoneManager.getDefaultUri(RingtoneManager.TYPE_ALARM),
                AudioAttributes.Builder().setUsage(AudioAttributes.USAGE_ALARM).setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION).build())
            enableVibration(true)
            vibrationPattern = longArrayOf(0, 1000, 500, 1000, 500, 1000)
            lockscreenVisibility = Notification.VISIBILITY_PRIVATE
        }
        manager.createNotificationChannel(channel)
    }

    fun notificationsAllowed(context: Context): Boolean {
        val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        if (Build.VERSION.SDK_INT >= 33 && context.checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) return false
        if (!manager.areNotificationsEnabled()) return false
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            ensureChannel(context)
            val channel = manager.getNotificationChannel(CHANNEL_ID)
            if (channel != null && channel.importance == NotificationManager.IMPORTANCE_NONE) return false
        }
        return true
    }

    /** Android 14+ meminta pengguna mengizinkan "notifikasi layar penuh" untuk aplikasi selain telepon/jam alarm. */
    fun fullScreenAllowed(context: Context): Boolean {
        if (Build.VERSION.SDK_INT < 34) return true
        return (context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager).canUseFullScreenIntent()
    }

    fun show(context: Context, id: String) {
        if (!isId(id)) return
        if (Build.VERSION.SDK_INT >= 33 && context.checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) return
        ensureChannel(context)
        val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        // Push ganda (retry provider, jalur aplikasi terbuka) tidak memulai ulang atau menghentikan dering yang sedang berjalan.
        if (manager.activeNotifications.any { it.tag == tag(id) }) return

        // Ketuk notifikasi → aplikasi (detail darurat). Layar penuh → layar alarm generik yang tetap berdering sampai dijawab.
        val app = Intent(context, MainActivity::class.java).setAction("rt05.emergency.$id").putExtra(EXTRA_ID, id)
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP)
        val ring = Intent(context, EmergencyAlarmActivity::class.java).setAction("rt05.alarm.$id").putExtra(EXTRA_ID, id)
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_NO_USER_ACTION)
        val flags = PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE

        @Suppress("DEPRECATION")
        val builder = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) Notification.Builder(context, CHANNEL_ID) else Notification.Builder(context)
        builder.setSmallIcon(R.drawable.ic_emergency)
            .setContentTitle("DARURAT di RT 05")
            .setContentText("Ada laporan darurat warga. Buka untuk melihat detail.")
            .setCategory(Notification.CATEGORY_ALARM)
            .setVisibility(Notification.VISIBILITY_PRIVATE)
            .setContentIntent(PendingIntent.getActivity(context, id.hashCode(), app, flags))
            .setFullScreenIntent(PendingIntent.getActivity(context, ("alarm" + id).hashCode(), ring, flags), true)
            .setAutoCancel(true)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) builder.setTimeoutAfter(TIMEOUT_MS) else legacySound(builder)
        val notification = builder.build()
        // Berulang seperti dering telepon sampai dibuka, diusap, atau batas waktu.
        notification.flags = notification.flags or Notification.FLAG_INSISTENT
        try { manager.notify(tag(id), 0, notification) } catch (_: SecurityException) { /* Izin dapat dicabut saat mengirim. */ }
    }

    @Suppress("DEPRECATION")
    private fun legacySound(builder: Notification.Builder) {
        builder.setPriority(Notification.PRIORITY_MAX).setSound(RingtoneManager.getDefaultUri(RingtoneManager.TYPE_ALARM))
            .setVibrate(longArrayOf(0, 1000, 500, 1000))
    }

    fun cancel(context: Context, id: String) {
        if (!isId(id)) return
        (context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager).cancel(tag(id), 0)
    }
}

/**
 * Menerima pesan FCM data-only langsung (juga saat aplikasi tertutup), sejajar dengan penerima plugin firebase_messaging.
 * Hanya `alarm=1` + `ref_type=kejadian_darurat` yang membunyikan alarm; `alarm_selesai` menghentikannya.
 */
class EmergencyAlarmReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        val extras = intent.extras ?: return
        val stop = extras.getString("alarm_selesai")
        if (stop != null && EmergencyAlarm.isId(stop)) EmergencyAlarm.cancel(context, stop)
        val id = extras.getString("ref_id")
        if (id != null && extras.getString("alarm") == "1" && extras.getString("ref_type") == "kejadian_darurat" && EmergencyAlarm.isId(id)) {
            EmergencyAlarm.show(context, id)
        }
    }
}

/**
 * Layar alarm seperti panggilan masuk (layar penuh di atas layar kunci). Teks generik saja; detail darurat baru tampil di
 * aplikasi setelah HP dibuka kuncinya. Dering berhenti saat warga memilih Buka atau Tutup.
 */
class EmergencyAlarmActivity : Activity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        val id = intent.getStringExtra(EmergencyAlarm.EXTRA_ID)
        if (id == null || !EmergencyAlarm.isId(id)) {
            finish()
            return
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O_MR1) {
            setShowWhenLocked(true)
            setTurnScreenOn(true)
        } else {
            @Suppress("DEPRECATION")
            window.addFlags(WindowManager.LayoutParams.FLAG_SHOW_WHEN_LOCKED or WindowManager.LayoutParams.FLAG_TURN_SCREEN_ON)
        }
        window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)
        val pad = (24 * resources.displayMetrics.density).toInt()
        fun text(value: String, size: Float) = TextView(this).apply {
            text = value
            textSize = size
            setTextColor(Color.WHITE)
            gravity = Gravity.CENTER
            setPadding(0, pad / 2, 0, pad / 2)
        }
        val open = Button(this).apply {
            text = "Buka detail"
            setOnClickListener { openApp(id) }
        }
        val close = Button(this).apply {
            text = "Matikan bunyi"
            setOnClickListener {
                EmergencyAlarm.cancel(this@EmergencyAlarmActivity, id)
                finish()
            }
        }
        setContentView(LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            gravity = Gravity.CENTER
            setBackgroundColor(Color.rgb(183, 28, 28))
            setPadding(pad, pad, pad, pad)
            addView(text("DARURAT di RT 05", 30f))
            addView(text("Ada laporan darurat warga. Buka detail untuk melihat lokasi dan jenisnya.", 18f))
            addView(open)
            addView(close)
        })
    }

    private fun openApp(id: String) {
        EmergencyAlarm.cancel(this, id)
        val target = Intent(this, MainActivity::class.java).setAction("rt05.emergency.$id").putExtra(EmergencyAlarm.EXTRA_ID, id)
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP)
        val keyguard = getSystemService(KEYGUARD_SERVICE) as KeyguardManager
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O && keyguard.isKeyguardLocked) {
            // Minta buka kunci dulu: detail (nama/rumah pelapor) tidak tampil di atas layar kunci.
            keyguard.requestDismissKeyguard(this, object : KeyguardManager.KeyguardDismissCallback() {
                override fun onDismissSucceeded() {
                    startActivity(target)
                    finish()
                }
            })
        } else {
            startActivity(target)
            finish()
        }
    }
}
