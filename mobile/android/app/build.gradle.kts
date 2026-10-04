import java.util.Properties

plugins {
    id("com.android.application")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

android {
    namespace = "com.viratech.viratech"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    buildFeatures {
        resValues = true
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    defaultConfig {
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    // Deux applications à partir du même code : "client" (Viratech) et "admin" (Viratech Admin, équipe).
    flavorDimensions += "app"
    productFlavors {
        create("client") {
            dimension = "app"
            applicationId = "com.viratech.app"
            resValue("string", "app_name", "Viratech")
        }
        create("admin") {
            dimension = "app"
            applicationId = "com.viratech.app.admin"
            resValue("string", "app_name", "Viratech Admin")
        }
    }

    // Signature de publication : android/key.properties (jamais dans le dépôt). Sans ce fichier, clé de débogage (tests seulement).
    val keyFile = rootProject.file("key.properties")
    val keyProps = Properties()
    if (keyFile.exists()) keyFile.inputStream().use { keyProps.load(it) }

    signingConfigs {
        if (keyFile.exists()) {
            create("publication") {
                storeFile = rootProject.file(keyProps.getProperty("storeFile"))
                storePassword = keyProps.getProperty("storePassword")
                keyAlias = keyProps.getProperty("keyAlias")
                keyPassword = keyProps.getProperty("keyPassword")
            }
        }
    }

    buildTypes {
        release {
            signingConfig = if (keyFile.exists()) signingConfigs.getByName("publication") else signingConfigs.getByName("debug")
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