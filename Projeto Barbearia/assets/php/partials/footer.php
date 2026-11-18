<?php
// Rodapé compartilhado; usa o mesmo prefixo de links da navegação.
$footerRoot = $navRoot ?? '../';
$footerPhp = ($footerRoot === '') ? 'php/' : '';
$footerHome = basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'index.php';
$footerAbout = $footerHome ? '#sobre' : $footerRoot . 'index.php#sobre';
$footerContact = $footerHome ? '#contatos' : $footerRoot . 'index.php#contatos';
?>
<footer class="site-footer" id="contatos">
    <div class="site-footer-inner">
        <section class="site-footer-brand">
            <a class="site-brand" href="<?= htmlspecialchars($footerRoot . 'index.php', ENT_QUOTES, 'UTF-8') ?>">
                <span class="site-brand-mark">T</span>
                <span class="site-brand-name">BARBEARIA <strong>TIERNO</strong></span>
            </a>
            <p>Tradição, estilo e atendimento de qualidade. Seu visual merece cuidado em cada detalhe.</p>
        </section>
        <section class="site-footer-links">
            <h2>Navegação</h2>
            <a href="<?= htmlspecialchars($footerRoot . 'index.php', ENT_QUOTES, 'UTF-8') ?>">Início</a>
            <a href="<?= htmlspecialchars($footerAbout, ENT_QUOTES, 'UTF-8') ?>">Sobre</a>
            <a href="<?= htmlspecialchars($footerContact, ENT_QUOTES, 'UTF-8') ?>">Contato</a>
            <a href="<?= htmlspecialchars($footerPhp . 'agenda.php', ENT_QUOTES, 'UTF-8') ?>">Agendar horário</a>
            <a href="<?= htmlspecialchars($footerPhp . 'avaliacoes.php', ENT_QUOTES, 'UTF-8') ?>">Avaliações</a>
        </section>
        <section class="site-footer-links">
            <h2>Atendimento</h2>
            <span>Segunda a sexta: 09h às 20h</span>
            <span>Sábado: 08h30 às 15h</span>
            <span>Domingo: 10h às 12h</span>
        </section>
        <section class="site-footer-links">
            <h2>Redes sociais</h2>
            <a href="https://www.facebook.com/BarbeariaTierno" target="_blank" rel="noopener noreferrer">Facebook</a>
            <a href="https://www.instagram.com/barbeariatierno/" target="_blank" rel="noopener noreferrer">Instagram</a>
            <a href="https://api.whatsapp.com/send?phone=5511999999999" target="_blank" rel="noopener noreferrer">WhatsApp</a>
        </section>
    </div>
    <div class="site-footer-bottom">© <?= date('Y') ?> Barbearia Tierno. Todos os direitos reservados.</div>
</footer>
