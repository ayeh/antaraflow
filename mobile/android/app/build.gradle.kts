import java.util.Properties

plugins {
    id("com.android.application")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

// Upload-key credentials, kept out of the repository. `key.properties` and the
// keystore it points at are gitignored; losing the keystore means never being
// able to update the app on Play again, so it lives in a password manager, not
// here. See mobile/README.md, "Shipping to Play".
//
// When the file is absent the release build falls back to the debug key, so
// `flutter run --release` still works for anyone without the key. That
// fallback is deliberately noisy — a debug-signed AAB is accepted by Gradle
// and rejected by Play Console, and the error there does not say why.
val keystorePropertiesFile = rootProject.file("key.properties")
val keystoreProperties = Properties().apply {
    if (keystorePropertiesFile.exists()) {
        keystorePropertiesFile.inputStream().use { load(it) }
    }
}
val hasUploadKey = keystorePropertiesFile.exists()

android {
    namespace = "cloud.antara.note"

    // Pinned rather than taking flutter.compileSdkVersion, which is 36 in this
    // Flutter release. flutter_secure_storage and permission_handler_android
    // both ship AAR metadata demanding 37, and the build fails outright at
    // checkDebugAarMetadata without it — the app has never been buildable for
    // Android as configured.
    //
    // Compiling against a newer SDK does not change runtime behaviour; that is
    // targetSdk's job, and it stays where Flutter puts it.
    compileSdk = 37

    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    defaultConfig {
        applicationId = "cloud.antara.note"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = 36
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    signingConfigs {
        if (hasUploadKey) {
            create("upload") {
                storeFile = rootProject.file(keystoreProperties.getProperty("storeFile"))
                storePassword = keystoreProperties.getProperty("storePassword")
                keyAlias = keystoreProperties.getProperty("keyAlias")
                keyPassword = keystoreProperties.getProperty("keyPassword")
            }
        }
    }

    buildTypes {
        release {
            signingConfig = if (hasUploadKey) {
                signingConfigs.getByName("upload")
            } else {
                logger.warn(
                    "android/key.properties is missing — signing the release build with the " +
                        "DEBUG key. This installs fine but Play Console will reject the upload."
                )
                signingConfigs.getByName("debug")
            }
        }
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

flutter {
    source = "../.."
}
