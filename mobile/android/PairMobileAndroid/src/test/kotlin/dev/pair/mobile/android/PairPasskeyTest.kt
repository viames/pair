package dev.pair.mobile.android

import org.junit.Assert.assertEquals
import org.junit.Test

/** Covers the platform-neutral JSON contract used around Credential Manager. */
class PairPasskeyTest {

    /** Verify that standard Pair options preserve WebAuthn JSON for Credential Manager. */
    @Test
    fun passkeyOptionsDecodeStandardPairContract() {
        val options = PairJson.default.decodeFromString(
            PairPasskeyOptions.serializer(),
            """{"flow_id":"pair-passkey-abc","publicKey":{"challenge":"AQID","rpId":"example.test","userVerification":"required"}}"""
        )

        assertEquals("pair-passkey-abc", options.flowId)
        assertEquals("example.test", options.publicKey["rpId"]?.toString()?.trim('"'))
        assertEquals("required", options.publicKey["userVerification"]?.toString()?.trim('"'))
    }

    /** Verify that account-management metadata follows the shared snake-case contract. */
    @Test
    fun passkeyMetadataDecodesSharedContract() {
        val passkey = PairJson.default.decodeFromString(
            PairPasskey.serializer(),
            """{"id":7,"label":"Telefono","created_at":"2026-09-17T12:00:00+00:00","last_used_at":null,"transports":["internal"]}"""
        )

        assertEquals(7, passkey.id)
        assertEquals("Telefono", passkey.label)
        assertEquals(listOf("internal"), passkey.transports)
    }
}
