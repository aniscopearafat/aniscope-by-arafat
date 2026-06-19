<?php
declare(strict_types=1);

function env_value(string $key, string $default = ''): string
{
    static $values = null;
    if ($values === null) {
        $values = [];
        $file = dirname(__DIR__) . '/.env';
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
                [$name, $value] = array_map('trim', explode('=', $line, 2));
                $values[$name] = trim($value, "\"'");
            }
        }
    }
    return $_ENV[$key] ?? getenv($key) ?: ($values[$key] ?? $default);
}

function api_request(string $method, string $path, ?array $payload = null, ?string $token = null): array
{
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $defaultApiUrl = ($host === 'localhost' || str_starts_with($host, '127.0.0.1'))
        ? 'http://localhost:8000'
        : 'https://aniscope.hidenfree.com';
    $url = rtrim(env_value('API_URL', $defaultApiUrl), '/') . $path;
    $headers = ['Accept: application/json', 'User-Agent: AniScope-Frontend/1.0'];
    if ($payload !== null) $headers[] = 'Content-Type: application/json';
    if ($token) $headers[] = 'Authorization: Bearer ' . $token;

    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        if ($payload !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = $body === false ? curl_error($curl) : null;
        curl_close($curl);
        $data = $body !== false ? json_decode($body, true) : null;
        return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'data' => is_array($data) ? $data : [], 'error' => $error];
    }

    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => $payload !== null ? json_encode($payload, JSON_UNESCAPED_SLASHES) : '',
        'ignore_errors' => true,
        'timeout' => 8,
    ]]);
    $body = @file_get_contents($url, false, $context);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $match)) $status = (int)$match[1];
    $data = $body !== false ? json_decode($body, true) : null;
    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'data' => is_array($data) ? $data : [], 'error' => $body === false ? 'Could not connect to the API.' : null];
}

function api_data(string $path): array
{
    $response = api_request('GET', $path);
    return $response['ok'] ? $response['data'] : [];
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function image_url(string $url): string
{
    if (preg_match('#^https?://#', $url)) return $url;
    return '/' . ltrim($url, '/');
}

function format_date(string $date): string
{
    $timestamp = strtotime($date);
    return $timestamp ? date('M j, Y', $timestamp) : '';
}

function site_settings(): array
{
    static $settings = null;
    if ($settings === null) {
        $settings = api_data('/api/settings') ?: [
            'site_name' => 'AniScope by Arafat',
            'site_tagline' => 'Bangla Anime Reviews, Character Analysis, Manga Updates & Anime News',
            'donation_label' => 'Support AniScope',
            'donation_number' => '',
            'home_background_url' => '/assets/images/hero-original.svg',
            'copyright_text' => '© ' . date('Y') . ' AniScope by Arafat. All rights reserved.',
        ];
    }
    return $settings;
}

function youtube_embed_url(?string $url): string
{
    if (!$url) return '';
    $id = '';
    $parts = parse_url(trim($url));
    $host = strtolower($parts['host'] ?? '');
    if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) $id = trim($parts['path'] ?? '', '/');
    if (str_contains($host, 'youtube.com')) {
        parse_str($parts['query'] ?? '', $query);
        $id = $query['v'] ?? '';
        if (!$id && preg_match('#/(?:embed|shorts)/([A-Za-z0-9_-]{6,})#', $parts['path'] ?? '', $match)) $id = $match[1];
    }
    return preg_match('/^[A-Za-z0-9_-]{6,}$/', $id) ? 'https://www.youtube-nocookie.com/embed/' . $id : '';
}
