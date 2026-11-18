<?php

date_default_timezone_set('America/Sao_Paulo');

/*
 * |--------------------------------------------------------------------------
 * | DADOS DO SUPABASE
 * |--------------------------------------------------------------------------
 */

define('SUPABASE_URL', 'https://syyojqrkqwadelibimmc.supabase.co');
define('SUPABASE_KEY', 'sb_publishable_eIkBJneM0zfSdwXuwcZnRw_vNrrldWy');
define('SUPABASE_SERVICE_ROLE_KEY', 'sb_secret_8DlEnVRSBTeq1lw-HJtJ6A_N4ZMkC0m');

/*
 * |--------------------------------------------------------------------------
 * | ESCAPA HTML
 * |--------------------------------------------------------------------------
 * |
 * | Evita problemas quando exibimos dados vindos do banco.
 * |
 */

if (!function_exists('h')) {
    function h($valor): string
    {
        return htmlspecialchars(
            (string) $valor,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

/*
 * |--------------------------------------------------------------------------
 * | REQUISIÇÃO AO SUPABASE
 * |--------------------------------------------------------------------------
 * |
 * | Esta é a ÚNICA função supabaseRequest() do projeto.
 * |
 * | Não declare outra função com o mesmo nome em:
 * |
 * | - admin.php
 * | - dashboard.php
 * | - barbeiro.php
 * | - index.php
 * | - qualquer outro arquivo
 * |
 */

if (!function_exists('supabaseRequest')) {
    function supabaseRequest(
        string $method,
        string $path,
        ?array $data = null,
        ?string $token = null,
        bool $serviceRole = false
    ): array {
        /*
         * |--------------------------------------------------------------------------
         * | ESCOLHE A CHAVE
         * |--------------------------------------------------------------------------
         */

        if ($serviceRole) {
            $key = SUPABASE_SERVICE_ROLE_KEY;
        } else {
            $key = SUPABASE_KEY;
        }

        /*
         * |--------------------------------------------------------------------------
         * | VERIFICA CONFIGURAÇÃO
         * |--------------------------------------------------------------------------
         */

        if (
            $key === '' ||
            str_contains($key, 'COLOQUE_SUA') ||
            str_contains($key, 'SEU-PROJETO')
        ) {
            return [
                'status' => 0,
                'data' => [],
                'raw' => '',
                'error' =>
                    $serviceRole
                        ? 'SUPABASE_SERVICE_ROLE_KEY não configurada.'
                        : 'SUPABASE_KEY não configurada.'
            ];
        }

        /*
         * |--------------------------------------------------------------------------
         * | MONTA URL
         * |--------------------------------------------------------------------------
         */

        $url =
            rtrim(
                SUPABASE_URL,
                '/'
            )
            . '/'
            . ltrim(
                $path,
                '/'
            );

        /*
         * |--------------------------------------------------------------------------
         * | HEADERS
         * |--------------------------------------------------------------------------
         */

        $headers = [
            'apikey: ' . $key,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        /*
         * |--------------------------------------------------------------------------
         * | TOKEN DO USUÁRIO
         * |--------------------------------------------------------------------------
         */

        if (
            $token !== null &&
            $token !== ''
        ) {
            $headers[] =
                'Authorization: Bearer '
                . $token;
        } elseif ($serviceRole) {
            /*
             * |--------------------------------------------------------------------------
             * | SERVICE ROLE
             * |--------------------------------------------------------------------------
             */

            $headers[] =
                'Authorization: Bearer '
                . SUPABASE_SERVICE_ROLE_KEY;
        }

        /*
         * |--------------------------------------------------------------------------
         * | CURL
         * |--------------------------------------------------------------------------
         */

        $curl =
            curl_init($url);

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST =>
                strtoupper($method),
            CURLOPT_HTTPHEADER =>
                $headers,
            CURLOPT_CONNECTTIMEOUT =>
                10,
            CURLOPT_TIMEOUT =>
                30
        ];

        /*
         * |--------------------------------------------------------------------------
         * | ENVIA JSON
         * |--------------------------------------------------------------------------
         */

        if ($data !== null) {
            $json = json_encode(
                $data,
                JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
            );

            if ($json === false) {
                return [
                    'status' => 0,
                    'data' => [],
                    'raw' => '',
                    'error' =>
                        'Erro ao converter os dados para JSON.'
                ];
            }

            $options[
                CURLOPT_POSTFIELDS
            ] = $json;
        }

        /*
         * |--------------------------------------------------------------------------
         * | EXECUTA
         * |--------------------------------------------------------------------------
         */

        curl_setopt_array(
            $curl,
            $options
        );

        $raw =
            curl_exec($curl);

        /*
         * |--------------------------------------------------------------------------
         * | STATUS HTTP
         * |--------------------------------------------------------------------------
         */

        $status =
            curl_getinfo(
                $curl,
                CURLINFO_HTTP_CODE
            );

        /*
         * |--------------------------------------------------------------------------
         * | ERRO CURL
         * |--------------------------------------------------------------------------
         */

        $error =
            curl_error($curl);

        curl_close($curl);

        /*
         * |--------------------------------------------------------------------------
         * | FALHA DE CONEXÃO
         * |--------------------------------------------------------------------------
         */

        if ($raw === false) {
            return [
                'status' => 0,
                'data' => [],
                'raw' => '',
                'error' =>
                    $error
                        ?: 'Falha na conexão com o Supabase.'
            ];
        }

        /*
         * |--------------------------------------------------------------------------
         * | CONVERTE JSON
         * |--------------------------------------------------------------------------
         */

        $decoded =
            json_decode(
                $raw,
                true
            );

        /*
         * |--------------------------------------------------------------------------
         * | RETORNO
         * |--------------------------------------------------------------------------
         */

        return [
            'status' =>
                $status,
            'data' =>
                is_array($decoded)
                    ? $decoded
                    : [],
            'raw' =>
                $raw,
            'error' => ''
        ];
    }
}

/*
 * |--------------------------------------------------------------------------
 * | REDIRECT
 * |--------------------------------------------------------------------------
 * |
 * | Função utilizada pelos demais arquivos.
 * |
 */

if (!function_exists('redirect')) {
    function redirect(
        string $url
    ): never {
        header(
            'Location: ' . $url
        );

        exit;
    }
}
