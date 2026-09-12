import java.net.URI

plugins {
    alias(libs.plugins.android.application)
    alias(libs.plugins.kotlin.compose)
    alias(libs.plugins.kotlin.serialization)
    alias(libs.plugins.ksp)
}

/**
 * Where the app looks for the Laravel API, and how it reaches it in development.
 *
 * Both are Gradle properties rather than constants in the source, so a
 * developer points a build at their own backend from `local.properties` or the
 * command line without editing - and a release build never inherits a
 * workstation's address.
 */
val apiBaseUrl: String = providers.gradleProperty("stands.apiBaseUrl")
    .getOrElse("https://magaya.chimsy.co.za/api/v1/")

/**
 * Whether the base URL names a backend served from the developer's own machine.
 *
 * Only then does the debug DNS override default to anything: a public host must
 * resolve the way it does everywhere else, or a debug build silently sends the
 * live API's traffic to whatever is listening on the workstation.
 */
val isLocalApiHost: Boolean = URI(apiBaseUrl).host.orEmpty().let { host ->
    host == "localhost" || host.endsWith(".test") || host.endsWith(".localhost")
}

/** The emulator reaches the machine it runs on at 10.0.2.2; a device needs the LAN address. */
val devHostAddress: String = providers.gradleProperty("stands.devHostAddress")
    .getOrElse(if (isLocalApiHost) "10.0.2.2" else "")

android {
    namespace = "za.co.chimsy.stands"
    compileSdk {
        version = release(37)
    }

    defaultConfig {
        applicationId = "za.co.chimsy.stands"
        minSdk = 25
        targetSdk = 37
        versionCode = 1
        versionName = "1.0"

        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"

        buildConfigField("String", "API_BASE_URL", "\"$apiBaseUrl\"")
    }

    buildTypes {
        debug {
            /**
             * Herd serves the backend from the developer's machine under a name
             * the emulator cannot resolve. Rewriting it here - rather than
             * pointing the base URL at a bare address - keeps the Host header
             * and the TLS handshake honest about which site is being asked for.
             */
            buildConfigField("String", "DEV_HOST_ADDRESS", "\"$devHostAddress\"")
        }
        release {
            buildConfigField("String", "DEV_HOST_ADDRESS", "\"\"")
            optimization {
                enable = false
            }
        }
    }
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_11
        targetCompatibility = JavaVersion.VERSION_11

        /**
         * `java.time` is API 26 and this app supports API 25. Desugaring
         * backports it rather than the alternatives - raising minSdk and
         * dropping devices, or hand-rolling date arithmetic - and it is what
         * lets `Instant` be the type for "when was this last true" throughout.
         */
        isCoreLibraryDesugaringEnabled = true
    }
    buildFeatures {
        compose = true
        buildConfig = true
    }
    testOptions {
        unitTests {
            isReturnDefaultValues = true
        }
    }
}

dependencies {
    coreLibraryDesugaring(libs.desugar.jdk.libs)

    implementation(platform(libs.androidx.compose.bom))
    implementation(libs.androidx.activity.compose)
    implementation(libs.androidx.compose.material3)
    implementation(libs.androidx.compose.material.icons.core)
    implementation(libs.androidx.compose.ui)
    implementation(libs.androidx.compose.ui.graphics)
    implementation(libs.androidx.compose.ui.tooling.preview)
    implementation(libs.androidx.core.ktx)
    implementation(libs.androidx.lifecycle.runtime.ktx)
    implementation(libs.androidx.lifecycle.runtime.compose)
    implementation(libs.androidx.lifecycle.viewmodel.compose)
    implementation(libs.androidx.navigation.compose)
    implementation(libs.androidx.datastore.preferences)
    implementation(libs.retrofit)
    implementation(libs.retrofit.kotlinx.serialization)
    implementation(libs.okhttp)
    implementation(libs.okhttp.logging)
    implementation(libs.kotlinx.serialization.json)
    implementation(libs.androidx.room.runtime)
    implementation(libs.androidx.room.ktx)
    ksp(libs.androidx.room.compiler)

    testImplementation(libs.junit)
    testImplementation(libs.kotlinx.coroutines.test)
    testImplementation(libs.okhttp.mockwebserver)
    testImplementation(libs.androidx.room.testing)

    androidTestImplementation(platform(libs.androidx.compose.bom))
    androidTestImplementation(libs.androidx.compose.ui.test.junit4)
    androidTestImplementation(libs.androidx.espresso.core)
    androidTestImplementation(libs.androidx.junit)
    debugImplementation(libs.androidx.compose.ui.test.manifest)
    debugImplementation(libs.androidx.compose.ui.tooling)
}
