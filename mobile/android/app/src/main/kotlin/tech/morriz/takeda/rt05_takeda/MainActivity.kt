package tech.morriz.takeda.rt05_takeda

import android.app.PendingIntent
import android.content.Intent
import android.content.pm.PackageInfo
import android.content.pm.PackageInstaller
import android.content.pm.PackageManager
import android.content.pm.ShortcutInfo
import android.content.pm.ShortcutManager
import android.graphics.drawable.Icon
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.provider.Settings
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import java.io.File
import java.security.MessageDigest

class MainActivity : FlutterActivity() {
    private var channel: MethodChannel? = null
    private var updateChannel: MethodChannel? = null
    private var pendingLaunch: Map<String, String>? = null
    private val shortcutAction = "tech.morriz.takeda.PANIC"
    private val installAction = "tech.morriz.takeda.INSTALL_STATUS"

    private fun panicShortcut(): ShortcutInfo = ShortcutInfo.Builder(this, "rt05_panic")
        .setShortLabel("Tombol panik")
        .setLongLabel("Buka laporan darurat RT05")
        .setIcon(Icon.createWithResource(this, applicationInfo.icon))
        .setIntent(Intent(this, MainActivity::class.java).setAction(shortcutAction))
        .build()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        // Activity bisa dibuat ulang setelah sesi pemasangan di-commit: status PackageInstaller lalu tiba di sini,
        // bukan onNewIntent. Kanal rt05/update sudah dibuat oleh super.onCreate (configureFlutterEngine).
        if (savedInstanceState == null && intent?.action == installAction) installStatus(intent)
        capture(intent)
        EmergencyAlarm.ensureChannel(this)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N_MR1) {
            getSystemService(ShortcutManager::class.java).addDynamicShortcuts(listOf(panicShortcut()))
        }
        // ADR46: APK yang sudah terpasang (atau tidak lebih baru) tidak disimpan di cache HP.
        Thread { updateDir().listFiles()?.forEach { if (!isNewerApk(it)) it.delete() } }.start()
    }

    private fun capture(action: Intent?) {
        if (action?.action == shortcutAction) pendingLaunch = mapOf("type" to "shortcut")
        val id = action?.getStringExtra(EmergencyAlarm.EXTRA_ID)
        if (id != null && EmergencyAlarm.isId(id)) {
            pendingLaunch = mapOf("type" to "emergency", "id" to id)
            // Warga sudah membuka detail: dering alarm kejadian ini berhenti.
            EmergencyAlarm.cancel(this, id)
        }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        if (intent.action == installAction) {
            installStatus(intent)
            return
        }
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
                        else { EmergencyAlarm.show(this@MainActivity, id); result.success(null) }
                    }
                    "settings" -> {
                        val settings = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) Intent(Settings.ACTION_CHANNEL_NOTIFICATION_SETTINGS).putExtra(Settings.EXTRA_APP_PACKAGE, packageName).putExtra(Settings.EXTRA_CHANNEL_ID, EmergencyAlarm.CHANNEL_ID)
                            else Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS, android.net.Uri.parse("package:$packageName"))
                        try { startActivity(settings); result.success(null) } catch (_: Exception) { result.error("UNAVAILABLE", "Settings unavailable", null) }
                    }
                    "readiness" -> result.success(mapOf("notifikasi" to EmergencyAlarm.notificationsAllowed(this@MainActivity),
                        "layar_penuh" to EmergencyAlarm.fullScreenAllowed(this@MainActivity)))
                    "fullScreenSettings" -> {
                        if (Build.VERSION.SDK_INT >= 34) {
                            try { startActivity(Intent(Settings.ACTION_MANAGE_APP_USE_FULL_SCREEN_INTENT, Uri.parse("package:$packageName"))); result.success(null) }
                            catch (_: Exception) { result.error("UNAVAILABLE", "Settings unavailable", null) }
                        } else result.success(null)
                    }
                    else -> result.notImplemented()
                }
            }
        }
        updateChannel = MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "rt05/update").apply {
            setMethodCallHandler { call, result ->
                when (call.method) {
                    "abis" -> result.success(Build.SUPPORTED_ABIS.toList())
                    "directory" -> result.success(updateDir().absolutePath)
                    "permission" -> {
                        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                            try { startActivity(Intent(Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES, Uri.parse("package:$packageName"))); result.success(null) }
                            catch (_: Exception) { result.error("UNAVAILABLE", "Settings unavailable", null) }
                        } else result.success(null)
                    }
                    "install" -> install(call.argument<String>("path"), call.argument<String>("sha256"), result)
                    else -> result.notImplemented()
                }
            }
        }
    }

    // ADR46: pembaruan dari dalam aplikasi. Berkas hanya di cache aplikasi; dipasang lewat PackageInstaller sehingga
    // Android tetap menampilkan konfirmasi dan menolak APK bertanda tangan lain.
    private fun updateDir(): File = File(cacheDir, "pembaruan").apply { mkdirs() }

    @Suppress("DEPRECATION")
    private fun versionOf(info: PackageInfo): Long =
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) info.longVersionCode else info.versionCode.toLong()

    /** Hanya paket aplikasi ini dengan versionCode lebih tinggi dari yang terpasang. */
    @Suppress("DEPRECATION")
    private fun isNewerApk(file: File): Boolean {
        if (!file.isFile || !file.name.endsWith(".apk")) return false
        val archive = packageManager.getPackageArchiveInfo(file.path, 0) ?: return false
        return archive.packageName == packageName && versionOf(archive) > versionOf(packageManager.getPackageInfo(packageName, 0))
    }

    private fun digestOf(file: File): String {
        val digest = MessageDigest.getInstance("SHA-256")
        file.inputStream().use { input ->
            val buffer = ByteArray(64 * 1024)
            while (true) {
                val n = input.read(buffer)
                if (n < 0) break
                digest.update(buffer, 0, n)
            }
        }
        return digest.digest().joinToString("") { "%02x".format(it) }
    }

    private fun install(path: String?, sha256: String?, result: MethodChannel.Result) {
        val file = path?.let { File(it).canonicalFile }
        if (file == null || file.parentFile != updateDir().canonicalFile || sha256 == null || !sha256.matches(Regex("[0-9a-f]{64}"))) {
            result.error("INVALID", "Invalid update file", null)
            return
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O && !packageManager.canRequestPackageInstalls()) {
            result.success("permission")
            return
        }
        Thread {
            val rejected = try {
                when {
                    digestOf(file) != sha256 -> { file.delete(); "HASH" }
                    !isNewerApk(file) -> { file.delete(); "INVALID" }
                    else -> { commit(file); null }
                }
            } catch (_: Exception) { "FAILED" }
            runOnUiThread { if (rejected == null) result.success("started") else result.error(rejected, "Update rejected", null) }
        }.start()
    }

    private fun commit(file: File) {
        val installer = packageManager.packageInstaller
        val id = installer.createSession(PackageInstaller.SessionParams(PackageInstaller.SessionParams.MODE_FULL_INSTALL))
        installer.openSession(id).use { session ->
            file.inputStream().use { input ->
                session.openWrite("rt05.apk", 0, file.length()).use { output ->
                    input.copyTo(output)
                    session.fsync(output)
                }
            }
            val status = Intent(this, MainActivity::class.java).setAction(installAction).addFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP)
            // Sistem mengisi EXTRA_STATUS ke intent ini, jadi PendingIntent harus mutable (Android 12+).
            val flags = PendingIntent.FLAG_UPDATE_CURRENT or (if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) PendingIntent.FLAG_MUTABLE else 0)
            session.commit(PendingIntent.getActivity(this, id, status, flags).intentSender)
        }
    }

    @Suppress("DEPRECATION")
    private fun installStatus(intent: Intent) {
        val code = when (intent.getIntExtra(PackageInstaller.EXTRA_STATUS, PackageInstaller.STATUS_FAILURE)) {
            PackageInstaller.STATUS_PENDING_USER_ACTION -> {
                val confirm = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) intent.getParcelableExtra(Intent.EXTRA_INTENT, Intent::class.java)
                    else intent.getParcelableExtra<Intent>(Intent.EXTRA_INTENT)
                // Konfirmasi Install terbuka: belum ada status untuk Flutter.
                if (confirm == null) "FAILED" else try { startActivity(confirm); null } catch (_: Exception) { "FAILED" }
            }
            PackageInstaller.STATUS_SUCCESS -> "SUCCESS"
            PackageInstaller.STATUS_FAILURE_ABORTED -> "ABORTED"
            PackageInstaller.STATUS_FAILURE_STORAGE -> "STORAGE"
            PackageInstaller.STATUS_FAILURE_CONFLICT, PackageInstaller.STATUS_FAILURE_INCOMPATIBLE -> "CONFLICT"
            else -> "FAILED"
        }
        if (code != null) updateChannel?.invokeMethod("status", code)
    }
}
