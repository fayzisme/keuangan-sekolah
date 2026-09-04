<?php

namespace App\Infrastructure\PaymentGateways;

use App\Domain\Billing\Contracts\PaymentGatewayInterface;
use RuntimeException;

/**
 * MidtransGateway — ADAPTER (fast-follow post-MVP)
 *
 * Implementasi interface Domain untuk Midtrans (Snap).
 * Phase MVP belum aktif; job implementasi di sprint STRETCH (Midtrans).
 * Integrasi runbook: ADR-0007, ARCHITECTURE.md §8.
 */
final class MidtransGateway implements PaymentGatewayInterface
{
    public function createTransaction(array $params): array
    {
        // TODO(post-MVP): POST https://api.sandbox.midtrans.com/v1/snap/transactions
        //   body:  order_id, gross_amount, currency=IDR, payment_type,
        //          redirect_url, notification_url...
        //   header: Authorization: Basic base64(server_key:)
        //   return: { snap_token, redirect_url, transaction_status, ... }
        throw new RuntimeException('MidtransGateway::createTransaction belum diimplementasi (post-MVP).');
    }

    public function verifyWebhookSignature(
        string $rawBody,
        string $signatureHeader,
        array $providerConfig,
    ): array
    {
        // TODO(post-MVP): signature per provider dokumentasi (Midtrans snap:
        //   sha512(order_id+status_code+gross_amount+server_key)).
        //   Original body dibaca raw, dicek byte-for-byte.
        throw new RuntimeException('MidtransGateway::verifyWebhookSignature belum diimplementasi (post-MVP).');
    }

    public function parseWebhookPayload(array $payload): array
    {
        // TODO(post-MVP): map midtrans keys -> internal canonical shape:
        //   { payment_id, gateway_trx_id, status, gross_amount_cents }
        throw new RuntimeException('MidtransGateway::parseWebhookPayload belum diimplementasi (post-MVP).');
    }
}