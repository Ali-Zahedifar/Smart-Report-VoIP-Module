<?php

declare(strict_types=1);

namespace SmartReport\Core;

final class Response
{
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    public static function notFound(string $message = ''): void
    {
        http_response_code(404);
        if (SMR_CLI) {
            fwrite(STDERR, '[Error] ' . ($message !== '' ? $message : 'Not found') . PHP_EOL);
            exit(1);
        }
        View::render('errors/404', [
            'title' => Lang::t('errors.404.title'),
            'message' => $message !== '' ? $message : Lang::t('errors.404.body'),
        ]);
        exit;
    }

    public static function forbidden(string $message = ''): void
    {
        http_response_code(403);
        if (SMR_CLI) {
            fwrite(STDERR, '[Error] Forbidden: ' . $message . PHP_EOL);
            exit(1);
        }
        View::render('errors/403', [
            'title' => Lang::t('errors.403.title'),
            'message' => $message !== '' ? $message : Lang::t('errors.403.body'),
        ]);
        exit;
    }

    public static function methodNotAllowed(): void
    {
        http_response_code(405);
        if (SMR_CLI) {
            fwrite(STDERR, '[Error] Method not allowed' . PHP_EOL);
            exit(1);
        }
        View::render('errors/404', [
            'title' => Lang::t('errors.405.title'),
            'message' => Lang::t('errors.405.body'),
        ]);
        exit;
    }
}