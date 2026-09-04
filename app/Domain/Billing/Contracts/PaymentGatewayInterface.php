<?php

namespace App\Domain\Billing\Contracts;

/**
 * PaymentGatewayInterface — PORT
 *
 * Modul Domain hanya tahu interface; provider (Midtrans/Xendit/dll)
 * implementasi di Infrastructure/PaymentGateways/. Jadi ganti gateway =
 * tambah 1 adapter, tanpa menyentuh domain (ADR-0007).
 *
 * Fast-follow (post-MVP) — scaffold ama already siap.
 */
interface PaymentGatewayInterface
{
    public function createTransaction(array $params): array;

    /**
     * Verify webhook signature provider.
     * @return array parsed payload bila valid; @throws RuntimeException bila tamper.
     */
    public function verifyWebhookSignature(
        string $rawBody,
        string $signatureHeader,
        array $providerConfig,
    ): array;

    public function parseWebhookPayload(array $payload): array;
}