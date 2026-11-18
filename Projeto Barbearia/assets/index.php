<?php
session_start();
require_once __DIR__ . '/php/config.php';

/* VERIFICA LOGIN */

$logado = !empty($_SESSION['access_token']);
$usuario = $_SESSION['usuario'] ?? [];
$nome = '';
$ehAdmin = false;
$ehBarbeiro = false;


/* BUSCA DADOS DO USUÁRIO */

if ($logado) {
    $token = $_SESSION['access_token'];

/* CONFIRMA TOKEN */

    $auth = supabaseRequest('GET', '/auth/v1/user', null, $token);
    if ($auth['status'] === 200 && !empty($auth['data']['id'])) {
        $usuario = $auth['data'];
        $_SESSION['usuario'] = $usuario;
        $userId = $usuario['id'];

/* BUSCA PERFIL */

        $r = supabaseRequest('GET','/rest/v1/perfis?select=user_id,nome,tipo_usuario&user_id=eq.' . rawurlencode($userId) . '&limit=1', null, $token);
        $perfil = $r['data'][0] ?? null;

/* IDENTIFICA TIPO */

    if ($perfil) {
        $tipoUsuario = strtolower(trim((string) ($perfil['tipo_usuario']?? 'cliente')));
         $ehAdmin = ($tipoUsuario === 'admin');
        $ehBarbeiro = ($tipoUsuario === 'barbeiro');

/* NOME VEM DO PERFIL */

        $nome = $perfil['nome'] ?? $usuario['user_metadata']['name'] ?? $usuario['user_metadata']['full_name'] ?? $usuario['email'] ?? '';
        
    } else {
           $nome = $usuario['user_metadata']['name'] ?? $usuario['user_metadata']['full_name'] ?? $usuario['email'] ?? '';
    }
    } else {

/* TOKEN INVÁLIDO */

    $_SESSION = []; session_destroy(); $logado = false;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"content="width=device-width, initial-scale=1.0">
    <title>Barbearia Tierno</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="./css/pages/index/index.css">
    <link rel="stylesheet" href="./css/tema.css">
</head>



<body>

<!-- NAVBAR -->
<?php $navRoot = ''; require __DIR__ . '/php/partials/navbar.php'; ?>

    <div class="container">

        <img class="inicio" id="home" src="./images/global/Tierno2.png" alt="Barbearia Tierno">

<!-- SOBRE -->

    <div class="sobre" id="sobre">
        <h1 class="titulo"> QUEM SOU EU?</h1>
        <div>
            <p class="texto">
                Desde 2016, a Barbearia Tierno tem se dedicado
                a oferecer mais do que apenas cortes de cabelo —
                oferecemos experiências.
                <br>
                Nossa barbearia nasceu com o propósito de
                resgatar a tradição e o estilo do verdadeiro
                atendimento masculino, com um toque moderno
                e acolhedor.
                <br>
                A Barbearia Tierno é referência em cortes,
                barba, cuidados com o visual e, principalmente,
                em atendimento personalizado.
                <br>
                Aqui, cada cliente é tratado com atenção,
                respeito e profissionalismo — porque acredito
                que cada detalhe faz a diferença.
                <br>
                Ao longo dos anos, nos tornamos um ponto de
                encontro, um espaço para relaxar, conversar
                e sair de lá se sentindo renovado.
                <br>
                Seja para um corte de rotina, um novo visual
                ou apenas aquele momento de cuidado com você
                mesmo, a Barbearia Tierno está de portas
                abertas para te receber.
                <br>
                Venha fazer parte dessa história.
            </p>
        </div>

    </div>

<!-- SERVIÇOS -->

    <div class="repertorio">
        <h1 class="titulo">NOSSOS SERVIÇOS</h1>
            <ul class="imagens">
                <li>
                    <img id="img1" src="./images/pages/index/img 1.jpg" alt="Serviço">
                </li>
                <li>
                    <img id="img2" src="./images/pages/index/img 2.jpg" alt="Serviço">
                </li>
                <li>
                    <img id="img3" src="./images/pages/index/img 3.jpg" alt="Serviço">
                </li>
            </ul>
    </div>

<!-- MAPA -->

    <div class="mapa-container">
        <h1 class="titulo">ONDE ESTAMOS</h1>
            <div class="mapa-wrapper">
                <iframe
                    class="mapa"
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3656.783108466228!2d-46.55795192636314!3d-23.576231978789536!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x94ce5c353aaeb265%3A0xe7680d43c2b7a640!2sBarbearia%20Tierno!5e0!3m2!1spt-BR!2sbr!4v1791810507945!5m2!1spt-BR!2sbr"
                    allowfullscreen
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
    </div>

</div>

<!-- FOOTER -->
<?php require __DIR__ . '/php/partials/footer.php'; ?>
</body>
</html>