<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    redirect_302('');
}

$slug = custom_form_slug((string)($_POST['form_slug'] ?? ''), '');
$returnUrl = $slug !== '' ? 'form/' . $slug : '';

try {
    $result = custom_form_submit($_POST);
    $form = $result['form'];
    $message = (string)($form['success_message'] ?? 'Terima kasih, data Anda sudah masuk.');
    $redirect = trim((string)($form['redirect_url'] ?? ''));

    if ($redirect !== '') {
        if (str_starts_with($redirect, 'http://') || str_starts_with($redirect, 'https://')) {
            header('Location: ' . $redirect, true, 302);
            exit;
        }
        redirect_302(ltrim($redirect, '/') . (str_contains($redirect, '?') ? '&' : '?') . 'success=' . rawurlencode($message));
    }

    $sourceUrl = custom_form_clean_text($_POST['source_url'] ?? '', 360);
    $sourceType = custom_form_clean_text($_POST['source_type'] ?? '', 80);
    if ($sourceType === 'landing_page' && $sourceUrl !== '' && function_exists('custom_form_safe_return_url')) {
        $safeSourceUrl = custom_form_safe_return_url($sourceUrl);
        if ($safeSourceUrl !== '') {
            $join = str_contains($safeSourceUrl, '?') ? '&' : '?';
            header('Location: ' . $safeSourceUrl . $join . 'success=' . rawurlencode($message) . '#form-' . rawurlencode((string)$form['slug']), true, 302);
            exit;
        }
    }

    redirect_302('form/' . rawurlencode((string)$form['slug']) . '?success=' . rawurlencode($message));
} catch (Throwable $e) {
    $errorMessage = $e->getMessage();
    if (preg_match('/permission|folder|storage|logs|upload|file/i', $errorMessage)) {
        $errorMessage = 'Form belum bisa diproses. Coba lagi beberapa saat atau hubungi admin.';
    }
    redirect_302($returnUrl . '?error=' . rawurlencode($errorMessage));
}
