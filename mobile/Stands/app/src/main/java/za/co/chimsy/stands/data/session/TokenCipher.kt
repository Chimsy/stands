package za.co.chimsy.stands.data.session

import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import android.util.Base64
import java.security.KeyStore
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec

/**
 * Encrypts the API token before it is written to disk.
 *
 * The key itself never leaves the Android Keystore - this class only ever holds
 * a handle to it - so the stored bytes are useless on another device and
 * useless to anything that can read the app's files without being the app.
 * A bearer token is a password equivalent; storing it in the clear because the
 * sandbox "should" protect it is the usual way it ends up in a backup or a bug
 * report.
 */
class TokenCipher(private val keyAlias: String = DEFAULT_ALIAS) {

    fun encrypt(plaintext: String): String {
        val cipher = Cipher.getInstance(TRANSFORMATION)
        cipher.init(Cipher.ENCRYPT_MODE, secretKey())

        val encrypted = cipher.doFinal(plaintext.toByteArray(Charsets.UTF_8))

        /** GCM needs its nonce to decrypt, and it is not a secret - it ships alongside. */
        return "${encode(cipher.iv)}$SEPARATOR${encode(encrypted)}"
    }

    /** Returns null for anything this device can no longer decrypt, which reads as "signed out". */
    fun decrypt(stored: String): String? {
        val parts = stored.split(SEPARATOR)
        if (parts.size != 2) return null

        return runCatching {
            val cipher = Cipher.getInstance(TRANSFORMATION)
            cipher.init(Cipher.DECRYPT_MODE, secretKey(), GCMParameterSpec(TAG_BITS, decode(parts[0])))
            String(cipher.doFinal(decode(parts[1])), Charsets.UTF_8)
        }.getOrNull()
    }

    private fun secretKey(): SecretKey {
        val keyStore = KeyStore.getInstance(PROVIDER).apply { load(null) }
        (keyStore.getEntry(keyAlias, null) as? KeyStore.SecretKeyEntry)?.let { return it.secretKey }

        val generator = KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, PROVIDER)
        generator.init(
            KeyGenParameterSpec.Builder(
                keyAlias,
                KeyProperties.PURPOSE_ENCRYPT or KeyProperties.PURPOSE_DECRYPT,
            )
                .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
                .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
                .build(),
        )

        return generator.generateKey()
    }

    private fun encode(bytes: ByteArray) = Base64.encodeToString(bytes, Base64.NO_WRAP)

    private fun decode(value: String): ByteArray = Base64.decode(value, Base64.NO_WRAP)

    private companion object {
        const val PROVIDER = "AndroidKeyStore"
        const val TRANSFORMATION = "AES/GCM/NoPadding"
        const val DEFAULT_ALIAS = "stands.session.token"
        const val SEPARATOR = ":"
        const val TAG_BITS = 128
    }
}
