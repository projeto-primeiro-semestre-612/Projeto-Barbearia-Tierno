<?php

session_start();

require_once __DIR__ . '/config.php';

/*
|--------------------------------------------------------------------------
| VERIFICA LOGIN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['access_token'])) {
    redirect('login.php?modo=login');
}

$token = $_SESSION['access_token'];


/*
|--------------------------------------------------------------------------
| BUSCA USUÁRIO LOGADO
|--------------------------------------------------------------------------
*/

$auth = supabaseRequest(
    'GET',
    '/auth/v1/user',
    null,
    $token
);

if (
    $auth['status'] !== 200 ||
    empty($auth['data']['id'])
) {
    $_SESSION = [];
    session_destroy();

    redirect('login.php?modo=login');
}

$usuarioLogado = $auth['data'];
$idLogado = $usuarioLogado['id'];

$_SESSION['usuario'] = $usuarioLogado;


/*
|--------------------------------------------------------------------------
| BUSCA PERFIL DO ADMINISTRADOR
|--------------------------------------------------------------------------
|
| Usamos SERVICE ROLE somente no servidor.
| Nunca envie essa chave para o navegador.
|
*/

$perfilLogadoResult = supabaseRequest(
    'GET',
    '/rest/v1/perfis'
        . '?select=user_id,nome,tipo_usuario'
        . '&user_id=eq.'
        . rawurlencode($idLogado)
        . '&limit=1',
    null,
    null,
    true
);

$perfilLogado =
    $perfilLogadoResult['data'][0] ?? null;


/*
|--------------------------------------------------------------------------
| VERIFICA ADMIN
|--------------------------------------------------------------------------
*/

if (
    !$perfilLogado ||
    strtolower(
        trim(
            (string)($perfilLogado['tipo_usuario'] ?? '')
        )
    ) !== 'admin'
) {

    http_response_code(403);

    die(
        'Acesso negado. Esta área é exclusiva para administradores.'
    );
}


/*
|--------------------------------------------------------------------------
| MENSAGENS
|--------------------------------------------------------------------------
*/

$mensagem = '';
$erro = '';
$tipoMensagem = '';


/*
|--------------------------------------------------------------------------
| FUNÇÃO PARA MOSTRAR ERROS DO SUPABASE
|--------------------------------------------------------------------------
|
| NÃO usamos o nome h() aqui porque ela já existe no config.php.
|
*/

function mensagemErroSupabase(
    array $resultado,
    string $padrao = 'Erro desconhecido.'
): string {

    if (!empty($resultado['error'])) {
        return (string)$resultado['error'];
    }

    $dados = $resultado['data'] ?? [];

    if (is_array($dados)) {

        if (!empty($dados['message'])) {
            return (string)$dados['message'];
        }

        if (!empty($dados['msg'])) {
            return (string)$dados['msg'];
        }

        if (!empty($dados['details'])) {
            return (string)$dados['details'];
        }

        if (!empty($dados['hint'])) {
            return (string)$dados['hint'];
        }

        if (!empty($dados)) {
            return json_encode(
                $dados,
                JSON_UNESCAPED_UNICODE
            );
        }
    }

    if (
        isset($resultado['raw']) &&
        $resultado['raw'] !== ''
    ) {
        return (string)$resultado['raw'];
    }

    return $padrao;
}


/*
|--------------------------------------------------------------------------
| CARREGA USUÁRIOS
|--------------------------------------------------------------------------
*/

function carregarUsuarios(): array
{
    /*
    |--------------------------------------------------------------------------
    | BUSCA PERFIS
    |--------------------------------------------------------------------------
    */

    $p = supabaseRequest(
        'GET',
        '/rest/v1/perfis'
            . '?select=user_id,nome,tipo_usuario'
            . '&order=nome.asc',
        null,
        null,
        true
    );

    $perfis = $p['data'] ?? [];

    if (!is_array($perfis)) {
        return [];
    }


    /*
    |--------------------------------------------------------------------------
    | BUSCA USUÁRIOS DO AUTH
    |--------------------------------------------------------------------------
    */

    $a = supabaseRequest(
        'GET',
        '/auth/v1/admin/users?per_page=1000&page=1',
        null,
        null,
        true
    );

    $authUsers =
        $a['data']['users'] ?? [];

    $mapaUsuarios = [];


    if (is_array($authUsers)) {

        foreach ($authUsers as $u) {

            if (!empty($u['id'])) {

                $mapaUsuarios[$u['id']] = $u;
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | JUNTA PERFIL + AUTH
    |--------------------------------------------------------------------------
    */

    foreach ($perfis as &$perfil) {

        $u =
            $mapaUsuarios[$perfil['user_id']]
            ?? [];

        $perfil['email'] =
            $u['email'] ?? '';

        $perfil['telefone'] =
            $u['user_metadata']['phone']
            ?? '';

    }

    unset($perfil);


    /*
    |--------------------------------------------------------------------------
    | RETORNA USUÁRIOS
    |--------------------------------------------------------------------------
    */

    return $perfis;
}


/*
|--------------------------------------------------------------------------
| PROCESSAMENTO POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $acao =
        $_POST['acao'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | ALTERAR TIPO DE USUÁRIO
    |--------------------------------------------------------------------------
    |
    | cliente
    | barbeiro
    | admin
    |
    */

    if ($acao === 'alterar_tipo') {

        $alvoId =
            trim(
                $_POST['user_id'] ?? ''
            );

        $novoTipo =
            strtolower(
                trim(
                    $_POST['tipo_usuario'] ?? ''
                )
            );


        /*
        |--------------------------------------------------------------------------
        | TIPOS PERMITIDOS
        |--------------------------------------------------------------------------
        */

        $tiposPermitidos = [
            'cliente',
            'barbeiro',
            'admin'
        ];


        /*
        |--------------------------------------------------------------------------
        | VALIDA ID
        |--------------------------------------------------------------------------
        */

        if (
            !preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
                $alvoId
            )
        ) {

            $erro =
                'ID de usuário inválido.';

            $tipoMensagem =
                'erro';

        } elseif (
            !in_array(
                $novoTipo,
                $tiposPermitidos,
                true
            )
        ) {

            $erro =
                'Tipo de usuário inválido.';

            $tipoMensagem =
                'erro';

        } elseif (
            $alvoId === $idLogado &&
            $novoTipo !== 'admin'
        ) {

            /*
            |--------------------------------------------------------------------------
            | NÃO PERMITE ADMIN REMOVER O PRÓPRIO ADMIN
            |--------------------------------------------------------------------------
            */

            $erro =
                'Você não pode remover seu próprio acesso de administrador.';

            $tipoMensagem =
                'erro';

        } else {


            /*
            |--------------------------------------------------------------------------
            | 1. ALTERA TIPO NA TABELA PERFIS
            |--------------------------------------------------------------------------
            */

            $atualizarPerfil =
                supabaseRequest(
                    'PATCH',
                    '/rest/v1/perfis?user_id=eq.'
                        . rawurlencode($alvoId),
                    [
                        'tipo_usuario' =>
                            $novoTipo
                    ],
                    null,
                    true
                );


            if (
                $atualizarPerfil['status'] < 200 ||
                $atualizarPerfil['status'] >= 300
            ) {

                $erro =
                    'Não foi possível alterar o tipo do usuário: '
                    . mensagemErroSupabase(
                        $atualizarPerfil,
                        'Erro ao atualizar perfil.'
                    );

                $tipoMensagem =
                    'erro';

            } else {


                /*
                |--------------------------------------------------------------------------
                | 2. SE VIROU BARBEIRO
                |--------------------------------------------------------------------------
                |
                | Cria o registro na tabela barbeiros.
                |
                */

                if ($novoTipo === 'barbeiro') {


                    /*
                    |--------------------------------------------------------------------------
                    | VERIFICA SE JÁ EXISTE
                    |--------------------------------------------------------------------------
                    */

                    $verificarBarbeiro =
                        supabaseRequest(
                            'GET',
                            '/rest/v1/barbeiros'
                                . '?select=id,perfil_id,especialidade,ativo'
                                . '&perfil_id=eq.'
                                . rawurlencode($alvoId)
                                . '&limit=1',
                            null,
                            null,
                            true
                        );


                    $barbeiroExistente =
                        $verificarBarbeiro['data'][0]
                        ?? null;


                    /*
                    |--------------------------------------------------------------------------
                    | SE NÃO EXISTIR, CRIA
                    |--------------------------------------------------------------------------
                    */

                    if (!$barbeiroExistente) {

                        $criarBarbeiro =
                            supabaseRequest(
                                'POST',
                                '/rest/v1/barbeiros?on_conflict=perfil_id',
                                [
                                    'perfil_id' =>
                                        $alvoId,

                                    'especialidade' =>
                                        null,

                                    'ativo' =>
                                        true
                                ],
                                null,
                                true
                            );


                        if (
                            $criarBarbeiro['status'] < 200 ||
                            $criarBarbeiro['status'] >= 300
                        ) {

                        // 1. Atualiza o perfil
    $atualizarPerfil = supabaseRequest(
        'PATCH',
        '/rest/v1/perfis?user_id=eq.' . rawurlencode($alvoId),
        [
            'tipo_usuario' => 'barbeiro'
        ],
        $token
    );

    // 2. Cria registro na tabela barbeiros
    $criarBarbeiro = supabaseRequest(
        'POST',
        '/rest/v1/barbeiros',
        [
            'perfil_id' => $alvoId,
            'ativo' => true
        ],
        $token
    );
                            /*
                            |--------------------------------------------------------------------------
                            | DESFAZ A PROMOÇÃO
                            |--------------------------------------------------------------------------
                            */

                            supabaseRequest(
                                'PATCH',
                                '/rest/v1/perfis?user_id=eq.'
                                    . rawurlencode($alvoId),
                                [
                                    'tipo_usuario' =>
                                        'cliente'
                                ],
                                null,
                                true
                            );


                            $erro =
                                'O usuário foi promovido, mas não foi possível '
                                . 'criar o registro na tabela barbeiros: '
                                . mensagemErroSupabase(
                                    $criarBarbeiro,
                                    'Erro ao criar barbeiro.'
                                );

                            $tipoMensagem =
                                'erro';

                        } else {

                            $mensagem =
                                'Usuário promovido para barbeiro com sucesso. '
                                . 'O registro também foi criado na tabela barbeiros.';

                            $tipoMensagem =
                                'sucesso';
                        }


                    } else {


                        /*
                        |--------------------------------------------------------------------------
                        | JÁ EXISTE
                        |--------------------------------------------------------------------------
                        |
                        | Apenas ativa o barbeiro.
                        |
                        */

                        $ativarBarbeiro =
                            supabaseRequest(
                                'PATCH',
                                '/rest/v1/barbeiros?id=eq.'
                                    . rawurlencode(
                                        $barbeiroExistente['id']
                                    ),
                                [
                                    'ativo' =>
                                        true
                                ],
                                null,
                                true
                            );


                        if (
                            $ativarBarbeiro['status'] >= 200 &&
                            $ativarBarbeiro['status'] < 300
                        ) {

                            $mensagem =
                                'Usuário promovido para barbeiro com sucesso.';

                            $tipoMensagem =
                                'sucesso';

                        } else {

                            $erro =
                                'Usuário promovido, mas não foi possível '
                                . 'ativar o cadastro do barbeiro: '
                                . mensagemErroSupabase(
                                    $ativarBarbeiro,
                                    'Erro ao ativar barbeiro.'
                                );

                            $tipoMensagem =
                                'erro';
                        }
                    }


                } else {


                    /*
                    |--------------------------------------------------------------------------
                    | 3. SE NÃO FOR BARBEIRO
                    |--------------------------------------------------------------------------
                    |
                    | Remove o registro da tabela barbeiros.
                    |
                    */

                    $removerBarbeiro =
                        supabaseRequest(
                            'DELETE',
                            '/rest/v1/barbeiros?perfil_id=eq.'
                                . rawurlencode($alvoId),
                            null,
                            null,
                            true
                        );


                    if (
                        $removerBarbeiro['status'] >= 200 &&
                        $removerBarbeiro['status'] < 300
                    ) {

                        $mensagem =
                            'Tipo de usuário alterado para '
                            . $novoTipo
                            . ' e cadastro de barbeiro removido.';

                        $tipoMensagem =
                            'sucesso';

                    } else {

                        $erro =
                            'Tipo alterado, mas não foi possível remover '
                            . 'o cadastro da tabela barbeiros: '
                            . mensagemErroSupabase(
                                $removerBarbeiro,
                                'Erro ao remover barbeiro.'
                            );

                        $tipoMensagem =
                            'erro';
                    }
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | SALVAR USUÁRIO
    |--------------------------------------------------------------------------
    |
    | Altera:
    |
    | - nome
    | - email
    | - telefone
    |
    */

    if ($acao === 'salvar') {

        $userId =
            trim(
                $_POST['user_id'] ?? ''
            );

        $nome =
            trim(
                $_POST['nome'] ?? ''
            );

        $email =
            trim(
                $_POST['email'] ?? ''
            );

        $telefone =
            preg_replace(
                '/\D/',
                '',
                $_POST['telefone'] ?? ''
            );


        /*
        |--------------------------------------------------------------------------
        | VALIDAÇÕES
        |--------------------------------------------------------------------------
        */

        if (
            !preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
                $userId
            )
        ) {

            $erro =
                'ID de usuário inválido.';

        } elseif ($nome === '') {

            $erro =
                'O nome é obrigatório.';

        } elseif (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $erro =
                'Email inválido.';

        } else {


            /*
            |--------------------------------------------------------------------------
            | ATUALIZA PERFIL
            |--------------------------------------------------------------------------
            */

            $r =
                supabaseRequest(
                    'PATCH',
                    '/rest/v1/perfis?user_id=eq.'
                        . rawurlencode($userId),
                    [
                        'nome' =>
                            $nome
                    ],
                    null,
                    true
                );


            if (
                $r['status'] >= 200 &&
                $r['status'] < 300
            ) {


                /*
                |--------------------------------------------------------------------------
                | ATUALIZA AUTH
                |--------------------------------------------------------------------------
                */

                $rAuth =
                    supabaseRequest(
                        'PUT',
                        '/auth/v1/admin/users/'
                            . rawurlencode($userId),
                        [
                            'email' =>
                                $email,

                            'user_metadata' => [
                                'name' =>
                                    $nome,

                                'phone' =>
                                    $telefone
                            ]
                        ],
                        null,
                        true
                    );


                if (
                    $rAuth['status'] >= 200 &&
                    $rAuth['status'] < 300
                ) {

                    $mensagem =
                        'Usuário atualizado com sucesso.';

                    $tipoMensagem =
                        'sucesso';

                } else {

                    $erro =
                        'Perfil atualizado, mas não foi possível atualizar '
                        . 'o usuário no Auth: '
                        . mensagemErroSupabase(
                            $rAuth,
                            'Erro ao atualizar Auth.'
                        );

                    $tipoMensagem =
                        'erro';
                }

            } else {

                $erro =
                    'Não foi possível atualizar o perfil: '
                    . mensagemErroSupabase(
                        $r,
                        'Erro ao atualizar perfil.'
                    );

                $tipoMensagem =
                    'erro';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | EXCLUIR USUÁRIO
    |--------------------------------------------------------------------------
    */

    if ($acao === 'excluir') {

        $userId =
            trim(
                $_POST['user_id'] ?? ''
            );


        if (
            !preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
                $userId
            )
        ) {

            $erro =
                'ID de usuário inválido.';

        } elseif ($userId === $idLogado) {

            $erro =
                'Você não pode excluir sua própria conta pelo painel administrativo.';

        } else {


            /*
            |--------------------------------------------------------------------------
            | REMOVE PRIMEIRO DA TABELA BARBEIROS
            |--------------------------------------------------------------------------
            |
            | Isso evita problemas com chave estrangeira.
            |
            */

            $removerBarbeiro =
                supabaseRequest(
                    'DELETE',
                    '/rest/v1/barbeiros?perfil_id=eq.'
                        . rawurlencode($userId),
                    null,
                    null,
                    true
                );


            /*
            |--------------------------------------------------------------------------
            | REMOVE PERFIL
            |--------------------------------------------------------------------------
            */

            $r =
                supabaseRequest(
                    'DELETE',
                    '/rest/v1/perfis?user_id=eq.'
                        . rawurlencode($userId),
                    null,
                    null,
                    true
                );


            if (
                $r['status'] >= 200 &&
                $r['status'] < 300
            ) {


                /*
                |--------------------------------------------------------------------------
                | REMOVE USUÁRIO DO AUTH
                |--------------------------------------------------------------------------
                */

                $rAuth =
                    supabaseRequest(
                        'DELETE',
                        '/auth/v1/admin/users/'
                            . rawurlencode($userId),
                        null,
                        null,
                        true
                    );


                if (
                    $rAuth['status'] >= 200 &&
                    $rAuth['status'] < 300
                ) {

                    $mensagem =
                        'Usuário excluído com sucesso.';

                    $tipoMensagem =
                        'sucesso';

                } else {

                    $erro =
                        'Perfil excluído, mas não foi possível excluir '
                        . 'o usuário do Auth: '
                        . mensagemErroSupabase(
                            $rAuth,
                            'Erro ao excluir usuário do Auth.'
                        );

                    $tipoMensagem =
                        'erro';
                }

            } else {

                $erro =
                    'Não foi possível excluir o perfil: '
                    . mensagemErroSupabase(
                        $r,
                        'Erro ao excluir perfil.'
                    );

                $tipoMensagem =
                    'erro';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| CARREGA USUÁRIOS NOVAMENTE
|--------------------------------------------------------------------------
*/

$usuarios =
    carregarUsuarios();

?>

<!DOCTYPE html>

<html lang="pt-br">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Painel Administrativo - Barbearia Tierno
    </title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
    >


<link rel="stylesheet" href="../css/pages/admin/admin.css">

<link rel="stylesheet" href="../css/tema.css">
</head>

<body>
<?php $navRoot = '../'; require __DIR__ . '/partials/navbar.php'; ?>


<main class="container">

    <h1 class="titulo">
        Painel Administrativo
    </h1>

    <p class="subtitulo">
        Usuários cadastrados e permissões
    </p>


    <div class="info">

        <h2>
            <i class="fas fa-user-shield"></i>
            Administrador
        </h2>

        <p>
            Você pode alterar os dados dos usuários,
            promover clientes para barbeiros ou administradores
            e remover usuários.
        </p>

    </div>


    <?php if ($mensagem !== ''): ?>

        <div class="alert ok">

            <?= h($mensagem) ?>

        </div>

    <?php endif; ?>


    <?php if ($erro !== ''): ?>

        <div class="alert bad">

            <?= h($erro) ?>

        </div>

    <?php endif; ?>


    <div class="table-wrap">

        <table class="tabela">

            <thead>

                <tr>

                    <th>
                        Nome
                    </th>

                    <th>
                        Email
                    </th>

                    <th>
                        Telefone
                    </th>

                    <th>
                        Função
                    </th>

                    <th>
                        Salvar
                    </th>

                    <th>
                        Excluir
                    </th>

                </tr>

            </thead>


            <tbody>

            <?php foreach ($usuarios as $i => $u): ?>

                <?php

                $formId =
                    'editar_' . $i;

                $tipoAtual =
                    strtolower(
                        trim(
                            (string)(
                                $u['tipo_usuario']
                                ?? 'cliente'
                            )
                        )
                    );

                ?>

                <tr>

                    <!-- NOME -->

                    <td>

                        <form
                            id="<?= h($formId) ?>"
                            method="POST"
                        >

                            <input
                                type="hidden"
                                name="acao"
                                value="salvar"
                            >

                            <input
                                type="hidden"
                                name="user_id"
                                value="<?= h($u['user_id']) ?>"
                            >

                            <input
                                type="text"
                                name="nome"
                                value="<?= h($u['nome'] ?? '') ?>"
                                required
                            >

                        </form>

                    </td>


                    <!-- EMAIL -->

                    <td>

                        <input
                            form="<?= h($formId) ?>"
                            type="email"
                            name="email"
                            value="<?= h($u['email'] ?? '') ?>"
                            required
                        >

                    </td>


                    <!-- TELEFONE -->

                    <td>

                        <input
                            form="<?= h($formId) ?>"
                            type="text"
                            name="telefone"
                            value="<?= h($u['telefone'] ?? '') ?>"
                        >

                    </td>


                    <!-- TIPO -->

                    <td>

                        <form
                            method="POST"
                            style="display:flex;gap:6px;align-items:center;"
                        >

                            <input
                                type="hidden"
                                name="acao"
                                value="alterar_tipo"
                            >

                            <input
                                type="hidden"
                                name="user_id"
                                value="<?= h($u['user_id']) ?>"
                            >

                            <select
                                name="tipo_usuario"
                            >

                                <option
                                    value="cliente"
                                    <?= $tipoAtual === 'cliente'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Cliente
                                </option>

                                <option
                                    value="barbeiro"
                                    <?= $tipoAtual === 'barbeiro'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Barbeiro
                                </option>

                                <option
                                    value="admin"
                                    <?= $tipoAtual === 'admin'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Administrador
                                </option>

                            </select>

                            <button
                                class="btn salvar"
                                type="submit"
                                title="Alterar função"
                            >
                                <i class="fas fa-user-edit"></i>
                            </button>

                        </form>


                        <div style="margin-top:7px;">

                            <?php if ($tipoAtual === 'admin'): ?>

                                <span class="badge badge-admin">
                                    Admin
                                </span>

                            <?php elseif ($tipoAtual === 'barbeiro'): ?>

                                <span class="badge badge-barbeiro">
                                    Barbeiro
                                </span>

                            <?php else: ?>

                                <span class="badge badge-cliente">
                                    Cliente
                                </span>

                            <?php endif; ?>

                        </div>

                    </td>


                    <!-- SALVAR -->

                    <td>

                        <button
                            form="<?= h($formId) ?>"
                            class="btn salvar"
                            type="submit"
                        >

                            <i class="fas fa-save"></i>

                            Salvar

                        </button>

                    </td>


                    <!-- EXCLUIR -->

                    <td>

                        <?php if (
                            $u['user_id'] === $idLogado
                        ): ?>

                            <span class="badge">

                                <i class="fas fa-user"></i>

                                Você

                            </span>

                        <?php else: ?>

                            <form
                                method="POST"
                                onsubmit="return confirm('Excluir este usuário definitivamente?');"
                            >

                                <input
                                    type="hidden"
                                    name="acao"
                                    value="excluir"
                                >

                                <input
                                    type="hidden"
                                    name="user_id"
                                    value="<?= h($u['user_id']) ?>"
                                >

                                <button
                                    class="btn excluir"
                                    type="submit"
                                >

                                    <i class="fas fa-trash"></i>

                                    Excluir

                                </button>

                            </form>

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endforeach; ?>


            <?php if (!$usuarios): ?>

                <tr>

                    <td colspan="6">
                        Nenhum perfil encontrado.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>


    <div class="rodape-acoes">

        <a
            class="link"
            href="dashboard.php"
        >
            <i class="fas fa-user"></i>
            Minha conta
        </a>

        <a
            class="link"
            href="barbeiro.php"
        >
            <i class="fas fa-cut"></i>
            Painel Barbeiro
        </a>

        <a
            class="link"
            href="../index.php"
        >
            <i class="fas fa-home"></i>
            Página inicial
        </a>

    </div>

</main>

<?php $navRoot = '../'; require __DIR__ . '/partials/footer.php'; ?>
</body>

</html>