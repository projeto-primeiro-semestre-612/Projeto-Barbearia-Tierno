<?php
// Navegação compartilhada da Barbearia Tierno.
$navRoot = $navRoot ?? '../';
$navPhp = ($navRoot === '') ? 'php/' : '';
$navLogado = !empty($_SESSION['access_token']);
$navPerfilTipo = $tipoUsuario
    ?? $tipo
    ?? ($perfil['tipo_usuario'] ?? null)
    ?? ($perfilLogado['tipo_usuario'] ?? null)
    ?? ($_SESSION['tipo_usuario'] ?? null)
    ?? ($_SESSION['usuario']['user_metadata']['tipo_usuario'] ?? 'cliente');
$navPerfilTipo = strtolower(trim((string)$navPerfilTipo));
$navAdmin = (($ehAdmin ?? false) === true) || $navPerfilTipo === 'admin';
$navBarbeiro = (($ehBarbeiro ?? false) === true) || in_array($navPerfilTipo, ['barbeiro', 'admin'], true);
$navIsHome = basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'index.php';
$navHome = $navIsHome ? '#home' : $navRoot . 'index.php#home';
$navSobre = $navIsHome ? '#sobre' : $navRoot . 'index.php#sobre';
$navContato = $navIsHome ? '#contatos' : $navRoot . 'index.php#contatos';
?>
<header class="site-header">
    <div class="site-header-inner">
        <a class="site-brand" href="<?= htmlspecialchars($navRoot . 'index.php', ENT_QUOTES, 'UTF-8') ?>" aria-label="Barbearia Tierno - Início">
            <span class="site-brand-mark">T</span>
            <span class="site-brand-name">BARBEARIA <strong>TIERNO</strong></span>
        </a>
        <nav class="site-nav" aria-label="Navegação principal">
            <a href="<?= htmlspecialchars($navHome, ENT_QUOTES, 'UTF-8') ?>">Início</a>
            <a href="<?= htmlspecialchars($navSobre, ENT_QUOTES, 'UTF-8') ?>">Sobre</a>
            <a href="<?= htmlspecialchars($navContato, ENT_QUOTES, 'UTF-8') ?>">Contato</a>
            <a href="<?= htmlspecialchars($navPhp . 'agenda.php', ENT_QUOTES, 'UTF-8') ?>">Agendar</a>
            <a href="<?= htmlspecialchars($navPhp . 'avaliacoes.php', ENT_QUOTES, 'UTF-8') ?>">Avaliações</a>
            <?php if ($navLogado): ?>
                <a href="<?= htmlspecialchars($navPhp . 'dashboard.php', ENT_QUOTES, 'UTF-8') ?>">Minha conta</a>
                <a href="<?= htmlspecialchars($navPhp . 'minhas-avaliacoes.php', ENT_QUOTES, 'UTF-8') ?>">Avaliar cortes</a>
                <?php if ($navBarbeiro): ?>
                    <a href="<?= htmlspecialchars($navPhp . 'barbeiro.php', ENT_QUOTES, 'UTF-8') ?>">Painel do barbeiro</a>
                <?php endif; ?>
                <?php if ($navAdmin): ?>
                    <a href="<?= htmlspecialchars($navPhp . 'admin.php', ENT_QUOTES, 'UTF-8') ?>">Administração</a>
                <?php endif; ?>
                <a class="site-nav-cta" href="<?= htmlspecialchars($navPhp . 'logout.php', ENT_QUOTES, 'UTF-8') ?>">Sair</a>
            <?php else: ?>
                <a class="site-nav-cta" href="<?= htmlspecialchars($navPhp . 'login.php?modo=login', ENT_QUOTES, 'UTF-8') ?>">Entrar</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
