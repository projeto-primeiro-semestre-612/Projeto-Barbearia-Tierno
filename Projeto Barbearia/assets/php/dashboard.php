<?php
session_start();
require_once __DIR__ . '/config.php';


/* VERIFICA LOGIN */

    if (empty($_SESSION['access_token'])) {
        redirect('login.php?modo=login');
    }

    $token = $_SESSION['access_token'];


/* BUSCA USUÁRIO LOGADO */

    $auth = supabaseRequest('GET', '/auth/v1/user', null, $token);

    if ($auth['status'] !== 200 || empty($auth['data']['id'])) {
        $_SESSION = [];
        session_destroy();
        redirect('login.php?modo=login');
    }

    $usuario = $auth['data'];
    $_SESSION['usuario'] = $usuario;
    $userId = $usuario['id'];


/*BUSCA PERFIL*/

    $perfilResult = supabaseRequest('GET', '/rest/v1/perfis?select=user_id,nome,tipo_usuario&user_id=eq.' . rawurlencode($userId) . '&limit=1', null, $token);
    $perfil = $perfilResult['data'][0] ?? null;


/* FALLBACK COM SERVICE ROLE */

    if (!$perfil && defined('SUPABASE_SERVICE_ROLE_KEY')) {
        $perfilResult = supabaseRequest('GET', '/rest/v1/perfis?select=user_id,nome,tipo_usuario&user_id=eq.' . rawurlencode($userId) . '&limit=1', null, null, true);
        $perfil = $perfilResult['data'][0] ?? null;
    }


/* PERFIL NÃO ENCONTRADO */

if (!$perfil) {

    $detalhe =
        $perfilResult['data']['message']
        ?? $perfilResult['error']
        ?? 'Registro não encontrado.';

    http_response_code(500);

    die(
        'Login realizado, mas não foi possível consultar seu perfil.'
        . '<br>'
        . '<small>'
        . h($detalhe)
        . '</small>'
    );
}


/*
|--------------------------------------------------------------------------
| TIPO DE USUÁRIO
|--------------------------------------------------------------------------
*/

$tipoUsuario = strtolower(
    trim(
        (string) (
            $perfil['tipo_usuario']
            ?? 'cliente'
        )
    )
);


$ehAdmin = ($tipoUsuario === 'admin');

$ehBarbeiro = ($tipoUsuario === 'barbeiro');


/*
|--------------------------------------------------------------------------
| DADOS DO USUÁRIO
|--------------------------------------------------------------------------
*/

$nomePerfil = trim((string)($perfil['nome'] ?? ''));
$metadataUsuario = is_array($usuario['user_metadata'] ?? null) ? $usuario['user_metadata'] : [];
$nomeMetadata = trim((string)(
    $metadataUsuario['name']
    ?? $metadataUsuario['nome']
    ?? $metadataUsuario['full_name']
    ?? $metadataUsuario['display_name']
    ?? ''
));
$emailUsuario = strtolower(trim((string)($usuario['email'] ?? '')));
$parteEmail = strtolower((string)(explode('@', $emailUsuario)[0] ?? ''));
$nomePareceEmail = str_contains($nomePerfil, '@')
    || ($parteEmail !== '' && strtolower($nomePerfil) === $parteEmail);
$nome = $nomeMetadata !== ''
    ? $nomeMetadata
    : (($nomePerfil !== '' && !$nomePareceEmail) ? $nomePerfil : 'Usuário');


$email =
    $usuario['email']
    ?? '';


$telefone =
    $usuario['user_metadata']['phone']
    ?? '';


$foto =
    $usuario['user_metadata']['avatar_url']
    ?? '';


/*
|--------------------------------------------------------------------------
| ALTERAÇÃO DE PERFIL
|--------------------------------------------------------------------------
*/

$mensagem = '';
$erro = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $acao = $_POST['acao'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | ALTERAR DADOS
    |--------------------------------------------------------------------------
    */

    if ($acao === 'perfil') {

        $novoNome =
            trim(
                $_POST['nome'] ?? ''
            );


        $novoTelefone =
            preg_replace(
                '/\D/',
                '',
                $_POST['telefone'] ?? ''
            );


        $novoEmail =
            trim(
                $_POST['email'] ?? ''
            );


        if (
            $novoNome === ''
            || !filter_var(
                $novoEmail,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $erro =
                'Informe um nome e um email válido.';

        } else {

            $authData = [

                'user_metadata' => [

                    'name' =>
                        $novoNome,

                    'phone' =>
                        $novoTelefone,

                    'avatar_url' =>
                        $foto
                ]
            ];


            /*
            | Altera email somente se realmente mudou
            */

            if ($novoEmail !== $email) {

                $authData['email'] =
                    $novoEmail;
            }


            $r = supabaseRequest(
                'PUT',
                '/auth/v1/user',
                $authData,
                $token
            );


            if (
                $r['status'] >= 200
                && $r['status'] < 300
            ) {

                $usuario =
                    $r['data'];

                $_SESSION['usuario'] =
                    $usuario;

                // Mantém perfis.nome sincronizado para que a agenda também exiba o nome correto.
                supabaseRequest(
                    'PATCH',
                    '/rest/v1/perfis?user_id=eq.' . rawurlencode($userId),
                    ['nome' => $novoNome],
                    $token
                );

                $nome =
                    $novoNome;


                $email =
                    $usuario['email']
                    ?? $novoEmail;


                $telefone =
                    $novoTelefone;


                if (
                    $novoEmail !==
                    ($auth['data']['email'] ?? '')
                ) {

                    $mensagem =
                        'Perfil atualizado. '
                        . 'Verifique seu email para confirmar '
                        . 'a alteração.';

                } else {

                    $mensagem =
                        'Perfil atualizado com sucesso.';
                }

            } else {

                $erro =
                    $r['data']['message']
                    ?? $r['data']['msg']
                    ?? 'Não foi possível atualizar o perfil.';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | ALTERAR SENHA
    |--------------------------------------------------------------------------
    */

    if ($acao === 'senha') {

        $senha =
            $_POST['senha'] ?? '';


        if (strlen($senha) < 8) {

            $erro =
                'A nova senha deve ter pelo menos 8 caracteres.';

        } else {

            $r = supabaseRequest(
                'PUT',
                '/auth/v1/user',
                [
                    'password' => $senha
                ],
                $token
            );


            if (
                $r['status'] >= 200
                && $r['status'] < 300
            ) {

                $mensagem =
                    'Senha alterada com sucesso.';

            } else {

                $erro =
                    $r['data']['message']
                    ?? $r['data']['msg']
                    ?? 'Não foi possível alterar a senha.';
            }
        }
    }
}

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
        Minha conta - Barbearia Tierno
    </title>

    <link
        rel="stylesheet"
        href="../css/pages/dashboard/dashboard.css"
    >

    <link
        rel="stylesheet"
        href="https://use.fontawesome.com/releases/v5.8.2/css/all.css"
    >


<link rel="stylesheet" href="../css/tema.css">
</head>


<body>
<?php $navRoot = '../'; require __DIR__ . '/partials/navbar.php'; ?>

<main class="perfil">

    <h1>
        Minha conta
    </h1>


    <p class="tipo-usuario">

        Tipo de usuário:

        <?= h($tipoUsuario) ?>

    </p>


    <?php if ($mensagem !== ''): ?>

        <p class="mensagem">

            <?= h($mensagem) ?>

        </p>

    <?php endif; ?>


    <?php if ($erro !== ''): ?>

        <p class="erro">

            <?= h($erro) ?>

        </p>

    <?php endif; ?>


    <?php if ($foto !== ''): ?>

        <img
            class="avatar"
            src="<?= h($foto) ?>"
            alt="Foto do usuário"
        >

    <?php else: ?>

        <div class="avatar"></div>

    <?php endif; ?>


    <hr class="separador">


    <h2>
        Dados pessoais
    </h2>


    <form method="POST">

        <input
            type="hidden"
            name="acao"
            value="perfil"
        >


        <label for="nome">
            Nome
        </label>

        <input
            id="nome"
            name="nome"
            value="<?= h($nome) ?>"
            required
        >


        <label for="telefone">
            Telefone
        </label>

        <input
            id="telefone"
            name="telefone"
            type="tel"
            value="<?= h($telefone) ?>"
        >


        <label for="email">
            Email
        </label>

        <input
            id="email"
            name="email"
            type="email"
            value="<?= h($email) ?>"
            required
        >


        <button type="submit">

            Salvar alterações

        </button>

    </form>


    <!-- ==================================================
         AVALIAR ATENDIMENTOS CONCLUÍDOS
    ================================================== -->

    <div class="acoes">
        <a href="minhas-avaliacoes.php" class="link-btn">
            <i class="fas fa-star"></i>
            Avaliar meus cortes
        </a>
        <a href="avaliacoes.php" class="link-btn">
            <i class="fas fa-star"></i>
            Ver avaliações dos clientes
        </a>
    </div>


    <!-- ==================================================
         PAINEL DO BARBEIRO
    ================================================== -->

    <?php if ($ehBarbeiro): ?>

        <div class="acoes">

            <a
                href="barbeiro.php"
                class="link-btn barbeiro-link"
            >

                <i class="fas fa-cut"></i>

                Painel do Barbeiro

            </a>

        </div>

    <?php endif; ?>


    <!-- ==================================================
         PAINEL ADMIN
    ================================================== -->

    <?php if ($ehAdmin): ?>

        <div class="acoes">

            <a
                href="admin.php"
                class="link-btn admin-link"
            >

                <i class="fas fa-user-shield"></i>

                Painel Admin

            </a>

        </div>

    <?php endif; ?>


    <hr class="separador">


    <h2>
        Alterar senha
    </h2>


    <form method="POST">

        <input
            type="hidden"
            name="acao"
            value="senha"
        >


        <label for="senha">
            Nova senha
        </label>

        <input
            id="senha"
            name="senha"
            type="password"
            minlength="8"
            autocomplete="new-password"
            required
        >


        <button type="submit">

            Trocar senha

        </button>

    </form>


    <div class="acoes">

        <a
            class="link-btn"
            href="../index.php"
        >
            Voltar ao início
        </a>


        <a
            class="link-btn"
            href="logout.php"
        >
            Sair da conta
        </a>

    </div>

</main>

<?php $navRoot = '../'; require __DIR__ . '/partials/footer.php'; ?>
</body>

</html>