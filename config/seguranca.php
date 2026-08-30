<?php
declare(strict_types=1);

/**
 * Funções de segurança compartilhadas: sessão segura, CSRF e cabeçalhos HTTP.
 */

function iniciarSessaoSegura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function enviarCabecalhosSeguranca(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
        . "script-src 'self' https://cdn.jsdelivr.net; "
        . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; "
        . "connect-src 'self'");
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfCampo(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrfValido(): bool
{
    $enviado = (string)($_POST['csrf_token'] ?? '');

    return $enviado !== '' && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $enviado);
}

/**
 * Barreira de defesa extra: garante login mesmo se a view for acessada
 * diretamente (ex.: .htaccess desabilitado no hosting).
 */
function exigirLogin(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        iniciarSessaoSegura();
    }

    if (empty($_SESSION['usuario_id'])) {
        header('Location: index.php?route=login');
        exit;
    }
}
