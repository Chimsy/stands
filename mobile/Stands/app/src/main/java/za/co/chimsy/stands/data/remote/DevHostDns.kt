package za.co.chimsy.stands.data.remote

import okhttp3.Dns
import java.net.InetAddress

/**
 * Resolves the development backend's hostname to the machine the emulator runs
 * on.
 *
 * Herd serves the API from a `.test` name that only the developer's machine can
 * resolve. Overriding DNS - rather than pointing the base URL at a bare address
 * - keeps the Host header and the TLS handshake naming the real site, so the
 * request Herd receives is indistinguishable from the browser's.
 *
 * Debug builds only: [hostAddress] is empty in release, where this delegates to
 * the system resolver like any other client.
 */
class DevHostDns(
    private val hostname: String,
    private val hostAddress: String,
    private val delegate: Dns = Dns.SYSTEM,
) : Dns {

    override fun lookup(hostname: String): List<InetAddress> {
        if (hostAddress.isNotBlank() && hostname.equals(this.hostname, ignoreCase = true)) {
            return listOf(InetAddress.getByName(hostAddress))
        }

        return delegate.lookup(hostname)
    }
}
