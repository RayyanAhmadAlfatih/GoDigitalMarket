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
$sourceUrl = custom_form_clean_text($_POST['source_url'] ?? '', 360);
$sourceType = custom_form_clean_text($_POST['source_type'] ?? '', 80);
$landingReturnUrl = '';

if ($sourceType === 'landing_page' && $sourceUrl !== '' && function_exists('custom_form_safe_return_url')) {
    $landingReturnUrl = custom_form_safe_return_url($sourceUrl);
}

$landingFeedbackUrl = static function (string $target, string $formSlug, string $key, string $message): string {
    $join = str_contains($target, '?') ? '&' : '?';
    return $target
        . $join
        . 'submitted_form=' . rawurlencode($formSlug)
        . '&' . $key . '=' . rawurlencode($message)
        . '#form-' . rawurlencode($formSlug);
};

try {
    $result = custom_form_submit($_POST);
    $form = $result['form'];
    $formSlug = (string)($form['slug'] ?? $slug);
    $message = (string)($form['success_message'] ?? 'Terima kasih, data Anda sudah masuk.');
    $redirect = trim((string)($form['redirect_url'] ?? ''));

    if ($redirect !== '') {
        if (str_starts_with($redirect, 'http://') || str_starts_with($redirect, 'https://')) {
            header('Location: ' . $redirect, true, 302);
            exit;
        }
        redirect_302(ltrim($redirect, '/') . (str_contains($redirect, '?') ? '&' : '?') . 'success=' . rawurlencode($message));
    }

    if ($landingReturnUrl !== '') {
        header('Location: ' . $landingFeedbackUrl($landingReturnUrl, $formSlug, 'success', $message), true, 302);
        exit;
    }

    redirect_302('form/' . rawurlencode($formSlug) . '?success=' . rawurlencode($message));
} catch (Throwable $e) {
    $errorMessage = $e->getMessage();
    if (preg_match('/permission|folder|storage|logs|upload|file/i', $errorMessage)) {
        $errorMessage = 'Form belum bisa diproses. Coba lagi beberapa saat atau hubungi admin.';
    }

    if ($landingReturnUrl !== '' && $slug !== '') {
        header('Location: ' . $landingFeedbackUrl($landingReturnUrl, $slug, 'error', $errorMessage), true, 302);
        exit;
    }

    redirect_302($returnUrl . '?error=' . rawurlencode($errorMessage));
}
