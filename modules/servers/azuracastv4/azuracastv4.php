<?php
// Módulo Azuracast - Ver 4.0 para uso no WHMCS 8.13 em PHP 8.3
// Versão de uso gratuito, proibida venda.
// Desenvolvido por: Cloud Atlântico - www.cloudatlantico.com.br
// Data: 16/03/2026 por Gustavo Fracaro

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

function azuracastv4_MetaData()
{
    return [
        'DisplayName' => 'Azuracast V4',
        'APIVersion' => '1.1',
        'RequiresServer' => true,
        'DefaultNonSSLPort' => '80',
        'DefaultSSLPort' => '443',
    ];
}

function azuracastv4_ConfigOptions()
{
    return [
        'Disk Quota' => [
            'FriendlyName' => 'Quota de Disco',
            'Type' => 'text',
            'Size' => '15',
            'Default' => '2.0 GB',
            'Description' => 'Formato em GB, ex.: 2.0 GB',
        ],
        'Max Listeners' => [
            'FriendlyName' => 'Ouvintes Máximos',
            'Type' => 'text',
            'Size' => '10',
            'Default' => '100',
            'Description' => 'Limite de ouvintes simultâneos',
        ],
        'Max Bitrate (kbps)' => [
            'FriendlyName' => 'Bitrate Máximo (kbps)',
            'Type' => 'text',
            'Size' => '10',
            'Default' => '128',
            'Description' => 'Bitrate máximo permitido',
        ],
        'Enable AutoDJ' => [
            'FriendlyName' => 'Habilitar AutoDJ',
            'Type' => 'yesno',
            'Description' => 'Habilitar AutoDJ para esta estação',
        ],
        'Mount Points Limit' => [
            'FriendlyName' => 'Limite de Mount Points',
            'Type' => 'text',
            'Size' => '10',
            'Default' => '5',
            'Description' => 'Quantidade máxima de pontos de montagem',
        ],
        'Streamer Type' => [
            'FriendlyName' => 'Tipo de Streamer',
            'Type' => 'dropdown',
            'Options' => 'Icecast,Shoutcast',
            'Default' => 'Icecast',
            'Description' => 'Tipo principal de streamer',
        ],
        'Module Language' => [
            'FriendlyName' => 'Idioma do Módulo',
            'Type' => 'dropdown',
            'Options' => 'portuguese,english,spanish,russian,italian,german,french,japanese',
            'Default' => 'portuguese',
            'Description' => 'Idioma padrão para textos do módulo (admin/cliente)',
        ],
    ];
}

function azuracastv4_TestConnection(array $params)
{
    try {
        $response = azuracastv4_apiRequest($params, 'GET', '/api/status');

        return [
            'success' => true,
            'error' => '',
            'response' => 'Conexão realizada com sucesso. Versão: ' . ($response['azuracast_version'] ?? 'n/d'),
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
        ];
    }
}

function azuracastv4_CreateAccount(array $params)
{
    try {
        azuracastv4_ensureProductCustomFields($params);

        $existingMeta = azuracastv4_getServiceMeta($params);

        $email = trim((string) ($params['clientsdetails']['email'] ?? ''));
        if ($email === '') {
            throw new RuntimeException('E-mail do cliente não informado no WHMCS.');
        }

        $password = null;
        $userName = trim(($params['clientsdetails']['firstname'] ?? '') . ' ' . ($params['clientsdetails']['lastname'] ?? ''));

        $existingUser = azuracastv4_findUserByEmail($params, $email);

        if (!empty($existingUser['id'])) {
            $userId = (int) $existingUser['id'];
        } else {
            $password = azuracastv4_generatePassword();

            $userPayload = [
                'email' => $email,
                'new_password' => $password,
                'name' => $userName,
            ];

            try {
                $createdUser = azuracastv4_apiRequest($params, 'POST', '/api/admin/users', $userPayload);
                $userId = $createdUser['id'] ?? null;
            } catch (Throwable $e) {
                if (stripos($e->getMessage(), 'already used') !== false) {
                    $retryUser = azuracastv4_findUserByEmail($params, $email);
                    $userId = $retryUser['id'] ?? null;
                } else {
                    throw $e;
                }
            }
        }

        if (!$userId) {
            throw new RuntimeException('Não foi possível obter o ID do usuário no Azuracast.');
        }

        $stationName = azuracastv4_getStationNameFromRequest($params);
        $stationDescription = azuracastv4_getStationDescriptionFromRequest($params);
        $stationShortName = azuracastv4_generateStationShortName($stationName, (int) ($params['serviceid'] ?? 0));
        $maxBitrate = azuracastv4_toInt(azuracastv4_config($params, 'Max Bitrate (kbps)', 128), 128);
        $maxListeners = azuracastv4_toInt(azuracastv4_config($params, 'Max Listeners', 100), 100);
        $maxMounts = azuracastv4_toInt(azuracastv4_config($params, 'Mount Points Limit', 5), 5);
        $diskQuota = azuracastv4_parseQuotaToBytes(
            azuracastv4_config($params, 'Disk Quota', azuracastv4_config($params, 'Disk Quota (MB)', '2.0 GB')),
            2 * 1024 * 1024 * 1024
        );

        // Evita criar múltiplas estações no mesmo serviço quando o WHMCS reexecuta o provisionamento.
        if (!empty($existingMeta['station_id'])) {
            azuracastv4_applyDiskQuota($params, (int) $existingMeta['station_id'], $diskQuota);
            return 'success';
        }

        $diskQuotaBytes = max(1, (int) $diskQuota['bytes']);
        $diskQuotaMb = (int) max(1, floor($diskQuotaBytes / (1024 * 1024)));

        $stationPayload = [
            'name' => $stationName,
            'short_name' => $stationShortName,
            'max_bitrate' => $maxBitrate,
            'max_mounts' => $maxMounts,
            'enable_streamers' => true,
            'enable_autodj' => azuracastv4_config($params, 'Enable AutoDJ', false) ? true : false,
            'frontend_type' => azuracastv4_normalizeFrontendType(
                azuracastv4_config($params, 'Streamer Type', 'Icecast')
            ),
            'frontend_config' => [
                'max_listeners' => $maxListeners,
            ],
            'backend_config' => [
                // Evita validação da API quando plano possui bitrate menor que o default da instalação.
                'record_streams_bitrate' => $maxBitrate,
            ],
            // Compatibilidade entre versões da API do AzuraCast.
            'media_storage_quota_bytes' => (string) $diskQuotaBytes,
            'recordings_storage_quota_bytes' => (string) $diskQuotaBytes,
            'podcasts_storage_quota_bytes' => (string) $diskQuotaBytes,
        ];

        if ($stationDescription !== '') {
            $stationPayload['description'] = $stationDescription;
        }

        $createdStation = azuracastv4_createStationWithUniqueShortName($params, $stationPayload, $stationName);
        $stationId = $createdStation['id'] ?? null;

        if (!$stationId) {
            throw new RuntimeException('Não foi possível obter o ID da estação criada no Azuracast.');
        }

        azuracastv4_applyDiskQuota($params, (int) $stationId, $diskQuota);

        $rolePayload = [
            'name' => 'whmcs_station_' . $params['serviceid'],
            'permissions' => [
                'global' => [],
                'station' => [
                    [
                        'id' => (int) $stationId,
                        'permissions' => ['administer all'],
                    ],
                ],
            ],
        ];

        $createdRole = azuracastv4_apiRequest($params, 'POST', '/api/admin/roles', $rolePayload);
        $roleId = $createdRole['id'] ?? null;

        if (!$roleId) {
            throw new RuntimeException('Não foi possível obter o ID da role criada no Azuracast.');
        }

        azuracastv4_assignRoleToUser($params, (int) $userId, (int) $roleId, $email, $userName);

        azuracastv4_storeServiceCredentials($params, $email, $password);
        azuracastv4_storeServiceMeta($params, [
            'user_id' => $userId,
            'role_id' => $roleId,
            'station_id' => $stationId,
        ]);

        return 'success';
    } catch (Throwable $e) {
        logModuleCall('azuracastv4', __FUNCTION__, $params, null, $e->getMessage());
        return $e->getMessage();
    }
}

function azuracastv4_SuspendAccount(array $params)
{
    try {
        $meta = azuracastv4_getServiceMeta($params);
        $stationId = $meta['station_id'] ?? null;

        if (!$stationId) {
            throw new RuntimeException('ID da estação não encontrado para suspensão.');
        }

        azuracastv4_apiRequest(
            $params,
            'PUT',
            '/api/admin/station/' . $stationId,
            ['is_enabled' => false]
        );

        return 'success';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

function azuracastv4_UnsuspendAccount(array $params)
{
    try {
        $meta = azuracastv4_getServiceMeta($params);
        $stationId = $meta['station_id'] ?? null;

        if (!$stationId) {
            throw new RuntimeException('ID da estação não encontrado para remoção de suspensão.');
        }

        azuracastv4_apiRequest(
            $params,
            'PUT',
            '/api/admin/station/' . $stationId,
            ['is_enabled' => true]
        );

        return 'success';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

function azuracastv4_TerminateAccount(array $params)
{
    try {
        $meta = azuracastv4_getServiceMeta($params);

        if (!empty($meta['station_id'])) {
            azuracastv4_apiRequest($params, 'DELETE', '/api/admin/station/' . $meta['station_id']);
        }

        if (!empty($meta['role_id'])) {
            azuracastv4_apiRequest($params, 'DELETE', '/api/admin/role/' . $meta['role_id']);
        }

        if (!empty($meta['user_id'])) {
            azuracastv4_apiRequest($params, 'DELETE', '/api/admin/user/' . $meta['user_id']);
        }

        azuracastv4_storeServiceMeta($params, []);

        return 'success';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

function azuracastv4_ClientAreaCustomButtonArray()
{
    return [
        'Iniciar Rádio' => 'StartRadio',
        'Desligar Rádio' => 'StopRadio',
    ];
}


function azuracastv4_ClientArea(array $params)
{
    try {
        $lang = azuracastv4_getModuleLanguage($params);
        $i18n = azuracastv4_getTranslations($lang);

        $meta = azuracastv4_getServiceMeta($params);
        $stationId = $meta['station_id'] ?? null;

        $station = [];
        $adminStation = [];
        $stationStatus = [];
        $stationQuota = [];
        if ($stationId) {
            $station = azuracastv4_apiRequest($params, 'GET', '/api/station/' . $stationId);
            $adminStation = azuracastv4_apiRequest($params, 'GET', '/api/admin/station/' . $stationId);
            $stationStatus = azuracastv4_apiRequest($params, 'GET', '/api/station/' . $stationId . '/status');
            $stationQuota = azuracastv4_apiRequest($params, 'GET', '/api/station/' . $stationId . '/quota/station_media');
        }

        $frontendConfig = $adminStation['frontend_config'] ?? [];
        $backendConfig = $adminStation['backend_config'] ?? [];
        $frontendRunning = !empty($stationStatus['frontendRunning']);
        $backendRunning = !empty($stationStatus['backendRunning']);
        $isOnline = ($frontendRunning || $backendRunning);
        $loginUrl = azuracastv4_buildWebLoginUrl($params, $meta, $station);

        return [
            'tabOverviewReplacementTemplate' => 'templates/clientarea.tpl',
            'vars' => [
                'labels' => $i18n,
                'station' => [
                    'name' => $adminStation['name'] ?? ($station['name'] ?? '-'),
                    'short_name' => $adminStation['short_name'] ?? ($station['short_name'] ?? '-'),
                    'frontend_type' => $adminStation['frontend_type'] ?? ($station['frontend'] ?? '-'),
                    'public_url' => $station['public_player_url'] ?? ($station['url'] ?? '-'),
                    'station_port' => $frontendConfig['port'] ?? '-',
                    'autodj_port' => $backendConfig['dj_port'] ?? '-',
                    'max_bitrate' => $adminStation['max_bitrate'] ?? azuracastv4_config($params, 'Max Bitrate (kbps)', '-'),
                    'max_listeners' => $frontendConfig['max_listeners'] ?? azuracastv4_config($params, 'Max Listeners', '-'),
                    'status' => $isOnline ? 'Online' : 'Offline',
                    'disk_quota' => $stationQuota['quota'] ?? '-',
                    'admin_username' => azuracastv4_getFrontendAdminUsername($adminStation),
                    'admin_password' => $frontendConfig['admin_pw'] ?? '-',
                    'source_username' => azuracastv4_getFrontendSourceUsername($adminStation),
                    'source_password' => $frontendConfig['source_pw'] ?? '-',
                    'relay_username' => azuracastv4_getFrontendRelayUsername($adminStation),
                    'relay_password' => $frontendConfig['relay_pw'] ?? '-',
                    'dj_username' => $params['username'] ?? '-',
                    'dj_password' => $params['password'] ?? '-',
                    'login_url' => $loginUrl,
                ],
            ],
        ];
    } catch (Throwable $e) {
        $lang = azuracastv4_getModuleLanguage($params);
        $i18n = azuracastv4_getTranslations($lang);

        return [
            'tabOverviewReplacementTemplate' => 'templates/clientarea.tpl',
            'vars' => [
                'labels' => $i18n,
                'error' => $e->getMessage(),
                'station' => [],
            ],
        ];
    }
}
function azuracastv4_StartRadio(array $params)
{
    try {
        $meta = azuracastv4_getServiceMeta($params);
        $stationId = $meta['station_id'] ?? null;

        if (!$stationId) {
            throw new RuntimeException('ID da estação não encontrado para iniciar transmissão.');
        }

        azuracastv4_apiRequest($params, 'POST', '/api/station/' . $stationId . '/backend/start');
        azuracastv4_apiRequest($params, 'POST', '/api/station/' . $stationId . '/frontend/start');

        return 'success';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

function azuracastv4_StopRadio(array $params)
{
    try {
        $meta = azuracastv4_getServiceMeta($params);
        $stationId = $meta['station_id'] ?? null;

        if (!$stationId) {
            throw new RuntimeException('ID da estação não encontrado para parar transmissão.');
        }

        azuracastv4_apiRequest($params, 'POST', '/api/station/' . $stationId . '/backend/stop');
        azuracastv4_apiRequest($params, 'POST', '/api/station/' . $stationId . '/frontend/stop');

        return 'success';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

function azuracastv4_apiRequest(array $params, $method, $endpoint, array $payload = null)
{
    $host = trim((string) ($params['serverhostname'] ?: $params['serverip']));

    if (!$host) {
        throw new InvalidArgumentException('Hostname/IP do servidor não definido no WHMCS.');
    }

    $baseUrl = preg_match('#^https?://#i', $host) ? $host : ('https://' . $host);
    $baseUrl = rtrim($baseUrl, '/');

    if (!preg_match('#/api$#i', parse_url($baseUrl, PHP_URL_PATH) ?: '')) {
        $baseUrl .= '/api';
    }

    $endpointPath = '/' . ltrim($endpoint, '/');
    if (strpos($endpointPath, '/api/') === 0) {
        $endpointPath = substr($endpointPath, 4);
    }

    $url = $baseUrl . $endpointPath;

    $apiKey = $params['serveraccesshash'] ?? '';

    if (trim($apiKey) === '') {
        throw new InvalidArgumentException('API Key não definida no campo Access Hash do servidor no WHMCS.');
    }

    $ch = curl_init($url);

    $headers = [
        'Accept: application/json',
        'X-API-Key: ' . trim($apiKey),
    ];

    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new RuntimeException('Erro cURL: ' . $curlError);
    }

    $decoded = json_decode((string) $raw, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException('Erro API (' . $httpCode . '): ' . ($raw ?: 'Resposta vazia'));
    }

    logModuleCall('azuracastv4', 'API ' . strtoupper($method) . ' ' . $endpoint, $payload, $decoded ?: $raw);

    return is_array($decoded) ? $decoded : [];
}

function azuracastv4_generatePassword($length = 16)
{
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%';
    $max = strlen($chars) - 1;
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }

    return $password;
}

function azuracastv4_storeServiceCredentials(array $params, $email, $password = null)
{
    $update = [
        'username' => $email,
    ];

    if ($password !== null && $password !== '') {
        $update['password'] = encrypt($password);
    }

    Capsule::table('tblhosting')
        ->where('id', $params['serviceid'])
        ->update($update);
}

function azuracastv4_storeServiceMeta(array $params, array $meta)
{
    $flatMeta = [];
    foreach ($meta as $key => $value) {
        $flatMeta[] = $key . '=' . $value;
    }

    Capsule::table('tblhosting')
        ->where('id', $params['serviceid'])
        ->update([
            'subscriptionid' => implode(';', $flatMeta),
        ]);
}


function azuracastv4_assignRoleToUser(array $params, $userId, $roleId, $email = null, $name = null)
{
    $basePayload = [];

    if ($email !== null && $email !== '') {
        $basePayload['email'] = $email;
    }

    if ($name !== null && $name !== '') {
        $basePayload['name'] = $name;
    }

    $payloadVariants = [
        array_merge($basePayload, ['roles' => [(int) $roleId]]),
        array_merge($basePayload, ['roles' => [['id' => (int) $roleId]]]),
    ];

    $lastException = null;

    foreach ($payloadVariants as $payload) {
        try {
            azuracastv4_apiRequest(
                $params,
                'PUT',
                '/api/admin/user/' . $userId,
                $payload
            );

            return;
        } catch (Throwable $e) {
            $lastException = $e;
        }
    }

    if ($lastException !== null) {
        throw $lastException;
    }
}

function azuracastv4_findUserByEmail(array $params, $email)
{
    $users = azuracastv4_apiRequest($params, 'GET', '/api/admin/users');
    $targetEmail = strtolower(trim((string) $email));

    if (!is_array($users)) {
        return null;
    }

    foreach ($users as $user) {
        if (!is_array($user)) {
            continue;
        }

        $candidate = strtolower(trim((string) ($user['email'] ?? '')));
        if ($candidate !== '' && $candidate === $targetEmail) {
            return $user;
        }
    }

    return null;
}

function azuracastv4_getServiceMeta(array $params)
{
    $subscriptionId = Capsule::table('tblhosting')
        ->where('id', $params['serviceid'])
        ->value('subscriptionid');

    $meta = [];
    foreach (explode(';', (string) $subscriptionId) as $pair) {
        if (strpos($pair, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $pair, 2);
        $meta[trim($key)] = trim($value);
    }

    return $meta;
}

function azuracastv4_config(array $params, $optionName, $default = null)
{
    $moduleOptions = azuracastv4_ConfigOptions();

    if (isset($params['configoptions'][$optionName])) {
        return $params['configoptions'][$optionName];
    }

    $optionIndex = 1;
    foreach (array_keys($moduleOptions) as $moduleOptionName) {
        if ($moduleOptionName === $optionName) {
            $rawConfigOption = 'configoption' . $optionIndex;
            if (isset($params[$rawConfigOption]) && trim((string) $params[$rawConfigOption]) !== '') {
                return $params[$rawConfigOption];
            }

            break;
        }

        $optionIndex++;
    }

    return $default;
}

function azuracastv4_getStationNameFromRequest(array $params)
{
    $customFields = azuracastv4_getCustomFieldValues($params);

    $stationName = trim((string) (
        $customFields['Nome da Estação']
        ?? $customFields['Nome da Estacao']
        ?? $customFields['Station Name']
        ?? ''
    ));

    if ($stationName === '') {
        $stationName = trim((string) ($params['domain'] ?? ''));
    }

    if ($stationName === '') {
        $stationName = 'estacao-' . ($params['serviceid'] ?? uniqid());
    }

    return $stationName;
}

function azuracastv4_getStationDescriptionFromRequest(array $params)
{
    $customFields = azuracastv4_getCustomFieldValues($params);

    return trim((string) (
        $customFields['Descrição da Estação']
        ?? $customFields['Descricao da Estacao']
        ?? $customFields['Station Description']
        ?? ''
    ));
}

function azuracastv4_getCustomFieldValues(array $params)
{
    $values = is_array($params['customfields'] ?? null) ? $params['customfields'] : [];

    $serviceId = (int) ($params['serviceid'] ?? 0);
    if ($serviceId <= 0) {
        return $values;
    }

    $dbValues = Capsule::table('tblcustomfieldsvalues as cfv')
        ->join('tblcustomfields as cf', 'cf.id', '=', 'cfv.fieldid')
        ->where('cfv.relid', $serviceId)
        ->where('cf.type', 'product')
        ->select('cf.fieldname', 'cfv.value')
        ->get();

    foreach ($dbValues as $row) {
        $fieldName = trim((string) ($row->fieldname ?? ''));
        if ($fieldName === '') {
            continue;
        }

        // Em alguns casos o fieldname pode ter validações após '|'.
        $normalizedName = trim(explode('|', $fieldName)[0]);
        $values[$normalizedName] = (string) ($row->value ?? '');
    }

    return $values;
}

function azuracastv4_ensureProductCustomFields(array $params)
{
    $productId = (int) ($params['packageid'] ?? 0);
    if ($productId <= 0) {
        return;
    }

    azuracastv4_ensureSingleProductCustomField(
        $productId,
        'Nome da Estação',
        'text',
        1,
        'Nome da estação que será criada no AzuraCast.'
    );

    azuracastv4_ensureSingleProductCustomField(
        $productId,
        'Descrição da Estação',
        'textarea',
        0,
        'Descrição opcional da estação.'
    );
}

function azuracastv4_ensureSingleProductCustomField($productId, $fieldName, $fieldType, $required, $description)
{
    $existing = Capsule::table('tblcustomfields')
        ->where('type', 'product')
        ->where('relid', $productId)
        ->where(function ($query) use ($fieldName) {
            $query->where('fieldname', $fieldName)
                ->orWhere('fieldname', 'like', $fieldName . '|%');
        })
        ->first();

    if ($existing) {
        return;
    }

    $nextSortOrder = (int) Capsule::table('tblcustomfields')
        ->where('type', 'product')
        ->where('relid', $productId)
        ->max('sortorder');

    Capsule::table('tblcustomfields')->insert([
        'type' => 'product',
        'relid' => $productId,
        'fieldname' => $fieldName,
        'fieldtype' => $fieldType,
        'description' => $description,
        'fieldoptions' => '',
        'regexpr' => '',
        'adminonly' => '',
        'required' => $required ? 'on' : '',
        'showorder' => 'on',
        'showinvoice' => '',
        'sortorder' => $nextSortOrder + 1,
    ]);
}

function azuracastv4_toInt($value, $default = 0)
{
    if (is_int($value)) {
        return $value;
    }

    $value = trim((string) $value);
    if ($value === '') {
        return (int) $default;
    }

    if (preg_match('/-?\d+/', $value, $matches)) {
        return (int) $matches[0];
    }

    return (int) $default;
}

function azuracastv4_normalizeFrontendType($value)
{
    $normalized = strtolower(trim((string) $value));

    if ($normalized === 'shoutcast' || $normalized === 'shoutcast2') {
        return 'shoutcast2';
    }

    return 'icecast';
}

function azuracastv4_getFrontendAdminUsername(array $station)
{
    $frontendType = strtolower((string) ($station['frontend_type'] ?? ''));

    if ($frontendType === 'shoutcast2') {
        return (string) (($station['frontend_config']['admin_user'] ?? '') ?: 'admin');
    }

    return 'admin';
}

function azuracastv4_getFrontendSourceUsername(array $station)
{
    $frontendType = strtolower((string) ($station['frontend_type'] ?? ''));

    if ($frontendType === 'shoutcast2') {
        return (string) (($station['frontend_config']['source_user'] ?? '') ?: 'source');
    }

    return 'source';
}

function azuracastv4_getFrontendRelayUsername(array $station)
{
    $frontendType = strtolower((string) ($station['frontend_type'] ?? ''));

    if ($frontendType === 'shoutcast2') {
        return (string) (($station['frontend_config']['relay_user'] ?? '') ?: 'relay');
    }

    return 'relay';
}

function azuracastv4_applyDiskQuota(array $params, $stationId, array $diskQuota)
{
    $quotaMb = max(1, (int) floor(((int) $diskQuota['bytes']) / (1024 * 1024)));
    $quotaBytes = max(1, (int) $diskQuota['bytes']);
    $quotaText = (string) ($diskQuota['text'] ?? ($quotaMb . ' MB'));

    $adminStation = [];
    try {
        $adminStation = azuracastv4_apiRequest($params, 'GET', '/api/admin/station/' . (int) $stationId);
    } catch (Throwable $e) {
        // Continua com os fallbacks sem quebrar imediatamente.
    }

    $mediaStorageId = azuracastv4_getStorageLocationIdFromStation($adminStation, 'media_storage_location');
    $recordingsStorageId = azuracastv4_getStorageLocationIdFromStation($adminStation, 'recordings_storage_location');
    $podcastsStorageId = azuracastv4_getStorageLocationIdFromStation($adminStation, 'podcasts_storage_location');

    $appliedViaStorageEndpoint = false;
    foreach ([$mediaStorageId, $recordingsStorageId, $podcastsStorageId] as $storageId) {
        if ($storageId <= 0) {
            continue;
        }

        try {
            azuracastv4_applyQuotaToStorageLocation($params, $storageId, $quotaText, $quotaBytes);
            $appliedViaStorageEndpoint = true;
        } catch (Throwable $e) {
            $lastError = $e;
        }
    }

    if ($appliedViaStorageEndpoint) {
        if (azuracastv4_verifyStationQuotas($params, (int) $stationId)) {
            return;
        }
    }

    $variants = [
        [
            'media_storage_quota_bytes' => (string) $quotaBytes,
            'recordings_storage_quota_bytes' => (string) $quotaBytes,
            'podcasts_storage_quota_bytes' => (string) $quotaBytes,
        ],
        ['media_storage_quota_bytes' => (string) $quotaBytes],
        ['recordings_storage_quota_bytes' => (string) $quotaBytes],
        ['podcasts_storage_quota_bytes' => (string) $quotaBytes],
    ];

    $lastError = null;

    foreach ($variants as $payload) {
        try {
            azuracastv4_apiRequest($params, 'PUT', '/api/admin/station/' . (int) $stationId, $payload);
            if (azuracastv4_verifyStationQuotas($params, (int) $stationId)) {
                return;
            }
        } catch (Throwable $e) {
            $lastError = $e;
        }
    }

    $quotaDebug = azuracastv4_fetchStationQuotaDebug($params, (int) $stationId);

    $message = 'Não foi possível aplicar a quota de disco da estação via API.';
    if ($lastError !== null) {
        $message .= ' Último erro: ' . $lastError->getMessage();
    }
    if (!empty($quotaDebug)) {
        $message .= ' Quotas atuais: ' . json_encode($quotaDebug);
    }

    throw new RuntimeException($message);
}

function azuracastv4_getStorageLocationIdFromStation(array $station, $key)
{
    $candidates = [
        $key,
        azuracastv4_snakeToCamel($key),
        $key . '_id',
        azuracastv4_snakeToCamel($key) . 'Id',
    ];

    foreach ($candidates as $candidate) {
        if (!array_key_exists($candidate, $station)) {
            continue;
        }

        $value = $station[$candidate];

        if (is_array($value)) {
            $id = (int) ($value['id'] ?? $value['storage_location_id'] ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        $id = (int) $value;
        if ($id > 0) {
            return $id;
        }
    }

    return 0;
}

function azuracastv4_applyQuotaToStorageLocation(array $params, $storageId, $quotaText, $quotaBytes)
{
    $storageLocation = azuracastv4_apiRequest($params, 'GET', '/api/admin/storage_location/' . (int) $storageId);
    $basePayload = azuracastv4_buildStorageLocationPayloadForUpdate($storageLocation);

    $variants = [
        array_merge($basePayload, ['id' => (int) $storageId, 'storageQuotaBytes' => (string) $quotaBytes]),
        array_merge($basePayload, ['id' => (int) $storageId, 'storage_quota_bytes' => (string) $quotaBytes]),
    ];

    $lastError = null;

    foreach ($variants as $payload) {
        try {
            azuracastv4_apiRequest($params, 'PUT', '/api/admin/storage_location/' . (int) $storageId, $payload);

            $after = azuracastv4_apiRequest($params, 'GET', '/api/admin/storage_location/' . (int) $storageId);
            $quotaAfter = trim((string) ($after['storageQuota'] ?? $after['storage_quota'] ?? ''));
            $quotaBytesAfterRaw = trim((string) ($after['storageQuotaBytes'] ?? $after['storage_quota_bytes'] ?? ''));
            $quotaBytesAfter = (int) preg_replace('/\D+/', '', $quotaBytesAfterRaw);

            if ($quotaAfter !== '' || $quotaBytesAfter > 0) {
                return;
            }
        } catch (Throwable $e) {
            $lastError = $e;
        }
    }

    if ($lastError !== null) {
        throw $lastError;
    }
}

function azuracastv4_snakeToCamel($value)
{
    $parts = explode('_', (string) $value);
    $camel = array_shift($parts);
    foreach ($parts as $part) {
        $camel .= ucfirst($part);
    }

    return $camel;
}

function azuracastv4_verifyStationQuotas(array $params, $stationId)
{
    $quotas = azuracastv4_fetchStationQuotaDebug($params, $stationId);

    foreach (['station_media', 'station_recordings', 'station_podcasts'] as $type) {
        $quota = trim((string) ($quotas[$type]['quota'] ?? ''));
        $quotaBytesRaw = trim((string) ($quotas[$type]['quota_bytes'] ?? ''));
        $quotaBytes = (int) preg_replace('/\D+/', '', $quotaBytesRaw);

        if ($quota === '' || strtolower($quota) === 'null' || $quotaBytesRaw === '' || strtolower($quotaBytesRaw) === 'null') {
            return false;
        }

        if ($quotaBytes <= 0) {
            return false;
        }
    }

    return true;
}

function azuracastv4_fetchStationQuotaDebug(array $params, $stationId)
{
    $result = [];

    foreach (['station_media', 'station_recordings', 'station_podcasts'] as $type) {
        try {
            $result[$type] = azuracastv4_apiRequest($params, 'GET', '/api/station/' . (int) $stationId . '/quota/' . $type);
        } catch (Throwable $e) {
            $result[$type] = ['error' => $e->getMessage()];
        }
    }

    return $result;
}

function azuracastv4_buildStorageLocationPayloadForUpdate(array $storageLocation)
{
    $allowedKeys = [
        'type',
        'adapter',
        'path',
        's3CredentialKey',
        's3CredentialSecret',
        's3Region',
        's3Version',
        's3Bucket',
        's3Endpoint',
        's3UsePathStyle',
        'dropboxAppKey',
        'dropboxAppSecret',
        'dropboxAuthToken',
        'sftpHost',
        'sftpUsername',
        'sftpPassword',
        'sftpPort',
        'sftpPrivateKey',
        'sftpPrivateKeyPassPhrase',
    ];

    $payload = [];
    foreach ($allowedKeys as $key) {
        if (array_key_exists($key, $storageLocation)) {
            $payload[$key] = $storageLocation[$key];
        }
    }

    return $payload;
}

function azuracastv4_parseQuotaToBytes($value, $defaultBytes)
{
    $raw = trim((string) $value);
    if ($raw === '') {
        $raw = (string) $defaultBytes;
    }

    if (!preg_match('/^\s*([0-9]+(?:[\.,][0-9]+)?)\s*([a-zA-Z]{0,3})\s*$/', $raw, $matches)) {
        return [
            'bytes' => (int) $defaultBytes,
            'text' => azuracastv4_formatQuotaGb((int) $defaultBytes),
        ];
    }

    $number = (float) str_replace(',', '.', $matches[1]);
    // Se não informar unidade, assume GB para evitar conversão involuntária para MB.
    $unit = strtoupper(trim($matches[2] ?: 'GB'));

    $multipliers = [
        'B' => 1,
        'KB' => 1024,
        'MB' => 1024 * 1024,
        'GB' => 1024 * 1024 * 1024,
        'TB' => 1024 * 1024 * 1024 * 1024,
    ];

    if (!isset($multipliers[$unit])) {
        $unit = 'MB';
    }

    $bytes = (int) max(0, round($number * $multipliers[$unit]));

    return [
        'bytes' => $bytes,
        'text' => azuracastv4_formatQuotaGb($bytes),
    ];
}

function azuracastv4_formatQuotaGb($bytes)
{
    $gb = ((float) $bytes) / (1024 * 1024 * 1024);
    return number_format($gb, 1, '.', '') . ' GB';
}

function azuracastv4_generateStationShortName($stationName, $serviceId = 0, $withRandomSuffix = false)
{
    $base = strtolower(trim((string) $stationName));
    $base = preg_replace('/[^a-z0-9]+/', '_', $base);
    $base = trim((string) $base, '_');

    if ($base === '') {
        $base = 'station';
    }

    if ($serviceId > 0) {
        $base .= '_' . $serviceId;
    }

    if ($withRandomSuffix) {
        $base .= '_' . substr(bin2hex(random_bytes(3)), 0, 6);
    }

    return substr($base, 0, 100);
}

function azuracastv4_createStationWithUniqueShortName(array $params, array $stationPayload, $stationName)
{
    $serviceId = (int) ($params['serviceid'] ?? 0);
    $attempt = 0;
    $lastException = null;

    while ($attempt < 10) {
        if ($attempt > 0) {
            $stationPayload['short_name'] = azuracastv4_generateStationShortName($stationName, $serviceId, true)
                . '_' . $attempt;
            $stationPayload['short_name'] = substr($stationPayload['short_name'], 0, 100);
        }

        try {
            return azuracastv4_apiRequest($params, 'POST', '/api/admin/stations', $stationPayload);
        } catch (Throwable $e) {
            $lastException = $e;

            $isShortNameCollision = (
                stripos($e->getMessage(), 'short_name') !== false
                && stripos($e->getMessage(), 'already used') !== false
            );

            if (!$isShortNameCollision) {
                throw $e;
            }
        }

        $attempt++;
    }

    if ($lastException !== null) {
        throw new RuntimeException(
            'Não foi possível gerar short_name único para a estação após múltiplas tentativas. Último erro: '
            . $lastException->getMessage()
        );
    }

    throw new RuntimeException('Não foi possível criar estação no Azuracast.');
}

function azuracastv4_getModuleLanguage(array $params)
{
    $language = strtolower(trim((string) azuracastv4_config($params, 'Module Language', 'portuguese')));
    return in_array($language, ['portuguese', 'english', 'spanish', 'russian', 'italian', 'german', 'french', 'japanese'], true) ? $language : 'portuguese';
}

function azuracastv4_getTranslations($language)
{
    $file = __DIR__ . '/lang/' . $language . '.php';

    if (!file_exists($file)) {
        $file = __DIR__ . '/lang/portuguese.php';
    }

    $translations = include $file;
    return is_array($translations) ? $translations : [];
}

function azuracastv4_buildWebLoginUrl(array $params, array $meta, array $station)
{
    $baseUrl = azuracastv4_getBaseUrl($params);
    $userId = (int) ($meta['user_id'] ?? 0);

    if ($userId > 0) {
        try {
            $response = azuracastv4_apiRequest($params, 'POST', '/api/admin/login_tokens', [
                'user' => $userId,
                'type' => 'login',
                'comment' => 'WHMCS SSO',
                'expires_minutes' => 15,
            ]);

            $links = $response['links'] ?? [];
            foreach ($links as $key => $url) {
                if (stripos((string) $key, 'login') !== false && filter_var($url, FILTER_VALIDATE_URL)) {
                    return $url;
                }
            }

            $record = $response['record'] ?? [];
            if (!empty($record['id']) && !empty($record['verifier'])) {
                return $baseUrl . '/login?token=' . urlencode($record['id'] . ':' . $record['verifier']);
            }
        } catch (Throwable $e) {
            // Fallback silencioso para a tela de login padrão.
        }
    }

    if (!empty($station['public_player_url']) && filter_var($station['public_player_url'], FILTER_VALIDATE_URL)) {
        return $station['public_player_url'];
    }

    return $baseUrl . '/login';
}

function azuracastv4_getBaseUrl(array $params)
{
    $host = trim((string) ($params['serverhostname'] ?: $params['serverip']));
    $baseUrl = preg_match('#^https?://#i', $host) ? $host : ('https://' . $host);
    return rtrim($baseUrl, '/');
}
