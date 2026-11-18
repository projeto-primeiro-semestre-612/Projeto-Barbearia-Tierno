<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Barbearia Tierno</title>
    <link rel="stylesheet" href="../css/pages/login/login.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.2/css/all.css">

<link rel="stylesheet" href="../css/tema.css">
</head>

<?php
    session_start();
    require_once __DIR__ . '/config.php';
    $modo = $_POST['modo'] ?? $_GET['modo'] ?? 'cadastro';
    $mensagem = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email'] ?? ''); $senha = $_POST['senha'] ?? '';

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mensagem = 'Informe um email válido.';
                } elseif ($senha === '') {
                    $mensagem = 'Informe sua senha.';
                } elseif ($modo === 'cadastro') {
                    $nome = trim($_POST['nome'] ?? ''); $telefone = preg_replace('/\D/', '', $_POST['telefone'] ?? '');
            
            if ($nome === '') {
                $mensagem = 'Informe seu nome.';
                } elseif (strlen($telefone) < 10 || strlen($telefone) > 13) {
                    $mensagem = 'Informe um telefone válido com DDD.';
                } elseif (strlen($senha) < 8) {
                    $mensagem = 'A senha deve ter pelo menos 8 caracteres.';
                } else {
                    $resultado = supabaseRequest('POST', '/auth/v1/signup', 
                    $dados = [ 'email' => $email,
                                'password' => $senha,
                                'data' => [ 
                                'name' => $nome,
                                'nome' => $nome,
                                'phone' => $telefone,
                                'telefone' => $telefone]]);
                
            if ($resultado['status'] >= 200 && $resultado['status'] < 300) {
                $dados = $resultado['data'];

            if (!empty($dados['access_token'])) {
                    session_regenerate_id(true);
                    $_SESSION['access_token'] = $dados['access_token'];
                    $_SESSION['usuario'] = $dados['user'] ?? [];
                    header('Location: ../index.php');
                        exit();
                }
                    $mensagem = 'Cadastro realizado! Verifique seu email para confirmar a conta antes de entrar.';
                    $modo = 'login';
                } else {
                    $mensagem = $resultado['data']['msg']
                    ?? $resultado['data']['message']
                    ?? 'Não foi possível realizar o cadastro.';
                    }
                }
                } elseif ($modo === 'login') {
                    $resultado = supabaseRequest('POST', '/auth/v1/token?grant_type=password', ['email' => $email, 'password' => $senha]);
            if ($resultado['status'] === 200 && !empty($resultado['data']['access_token'])) {
                session_regenerate_id(true);
                $_SESSION['access_token'] = $resultado['data']['access_token'];
                $_SESSION['usuario'] = $resultado['data']['user'] ?? [];
                header('Location: ../index.php');
                    exit();
                }
                $mensagem = $resultado['data']['msg']
                ?? $resultado['data']['message']
                ?? 'Email ou senha inválidos.';
            }
        }
?>

<body class="login-page <?= $modo === 'login' ? 'sign-in-js' : 'sign-up-js' ?>">
<?php $navRoot = '../'; require __DIR__ . '/partials/navbar.php'; ?>

    <div class="container">
        <div class="content first-content">
            <div class="first-column">
                <h2 class="title title-primary">Seja bem-vindo!</h2>
                    <p class="description description-primary">Para se manter conectado conosco</p>
                    <p class="description description-primary">Entre com suas informações pessoais</p>
                        <a class="btn btn-primary" href="login.php?modo=login">Entrar</a>
            </div>
            <div class="second-column">
                <h2 class="title title-second">Crie sua conta</h2>
                    <p class="description description-second">Use seu email para registro:</p>
                        <?php if ($mensagem !== ''): ?>
                            <p role="status"><?= h($mensagem) ?></p>
                        <?php endif; ?>
                <form class="form" method="POST" action="login.php">
                    <input type="hidden" name="modo" value="cadastro">
                    <label class="label-input" for="signupName"><i class="far fa-user icon-modify"></i><input id="signupName" name="nome" type="text" placeholder="Nome" autocomplete="name" required></label>
                    <label class="label-input" for="signupPhone"><i class="fas fa-phone icon-modify"></i><input id="signupPhone" name="telefone" type="tel" placeholder="Telefone com DDD" autocomplete="tel" inputmode="tel" minlength="10" maxlength="20" required></label>
                    <label class="label-input" for="signupEmail"><i class="far fa-envelope icon-modify"></i><input id="signupEmail" name="email" type="email" placeholder="Email" autocomplete="email" required></label>
                    <label class="label-input" for="signupPassword"><i class="fas fa-lock icon-modify"></i><input id="signupPassword" name="senha" type="password" placeholder="Senha" minlength="8" autocomplete="new-password" required></label>
                    <button type="submit" class="btn btn-second">Cadastrar</button>
                </form>
            </div>
        </div>

    <div class="content second-content">
        <div class="first-column">
            <h2 class="title title-primary">Olá, amigo!</h2>
                <p class="description description-primary">Insira seus dados pessoais</p>
                <p class="description description-primary">e comece sua jornada conosco</p>
                    <a class="btn btn-primary" href="login.php?modo=cadastro">Cadastrar</a>
        </div>
        <div class="second-column">
            <h2 class="title title-second">Entre em sua conta</h2>
                <p class="description description-second">Use sua conta de email:</p>
                    <?php if ($mensagem !== '' && $modo === 'login'): ?>
                        <p role="alert"><?= h($mensagem) ?></p>
                    <?php endif; ?>
                <form class="form" method="POST" action="login.php">
                    <input type="hidden" name="modo" value="login">
                    <label class="label-input" for="loginEmail"><i class="far fa-envelope icon-modify"></i><input id="loginEmail" name="email" type="email" placeholder="Email" autocomplete="email" required></label>
                    <label class="label-input" for="loginPassword"><i class="fas fa-lock icon-modify"></i><input id="loginPassword" name="senha" type="password" placeholder="Senha" autocomplete="current-password" required></label>
                    <button type="submit" class="btn btn-second">Entrar</button>
                </form>
        </div>
    </div>
    </div>
<?php $navRoot = '../'; require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
