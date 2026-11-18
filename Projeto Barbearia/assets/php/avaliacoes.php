<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';

date_default_timezone_set('America/Sao_Paulo');

// Esta função SQL retorna apenas os campos públicos das avaliações.
$resposta = supabaseRequest(
    'POST',
    '/rest/v1/rpc/listar_avaliacoes_publicas',
    ['p_limite' => 100]
);
$consultaOk = ($resposta['status'] >= 200 && $resposta['status'] < 300 && is_array($resposta['data']));
$avaliacoes = $consultaOk ? $resposta['data'] : [];

$total = count($avaliacoes);
$somaNotas = 0;
foreach ($avaliacoes as $item) {
    $somaNotas += (int)($item['nota'] ?? 0);
}
$media = $total > 0 ? $somaNotas / $total : 0;

function dataPublicaAvaliacao(?string $data): string {
    if (!$data) return 'Data não informada';
    try {
        return (new DateTimeImmutable($data))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y');
    } catch (Throwable $e) {
        return 'Data não informada';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avaliações dos clientes - Barbearia Tierno</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<link rel="stylesheet" href="../css/pages/avaliacoes/avaliacoes.css">

<link rel="stylesheet" href="../css/tema.css">
</head>
<body>
<?php $navRoot = '../'; require __DIR__ . '/partials/navbar.php'; ?>
<main class="container">
    <header class="cabecalho">
        <h1>O que nossos clientes dizem</h1>
        <p>Confira as avaliações de quem já cortou o cabelo na Barbearia Tierno.</p>
    </header>

    <?php if ($total > 0): ?>
        <section class="resumo" aria-label="Resumo das avaliações">
            <div class="media"><?= number_format($media, 1, ',', '.') ?>/5</div>
            <div>
                <div class="estrelas-media" aria-label="Média <?= number_format($media, 1, ',', '.') ?> de cinco estrelas">
                    <?= str_repeat('★', (int)round($media)) . str_repeat('☆', 5 - (int)round($media)) ?>
                </div>
                <div class="total"><?= $total ?> <?= $total === 1 ? 'avaliação publicada' : 'avaliações publicadas' ?></div>
            </div>
        </section>
        <section class="lista" aria-label="Avaliações dos clientes">
            <?php foreach ($avaliacoes as $avaliacao): ?>
                <?php $nota = max(1, min(5, (int)($avaliacao['nota'] ?? 0))); ?>
                <article class="avaliacao">
                    <div class="linha-topo">
                        <span class="cliente"><i class="fas fa-user-check"></i> Cliente verificado</span>
                        <span class="data">Corte em <?= h(dataPublicaAvaliacao($avaliacao['atendimento_em'] ?? null)) ?></span>
                    </div>
                    <div class="nota" aria-label="Nota <?= $nota ?> de 5 estrelas">
                        <?= str_repeat('★', $nota) . str_repeat('☆', 5 - $nota) ?>
                    </div>
                    <h2 class="servico"><?= h($avaliacao['servico'] ?? 'Corte/serviço') ?></h2>
                    <p class="barbeiro"><i class="fas fa-cut"></i> <?= h($avaliacao['barbeiro_nome'] ?? 'Equipe Tierno') ?></p>
                    <?php if (trim((string)($avaliacao['comentario'] ?? '')) !== ''): ?>
                        <p class="comentario">“<?= h($avaliacao['comentario']) ?>”</p>
                    <?php else: ?>
                        <p class="comentario"><em>O cliente avaliou o atendimento sem deixar comentário.</em></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
    <?php elseif ($consultaOk): ?>
        <section class="vazio">
            <i class="far fa-comment-dots"></i>
            <h2>Ainda não temos avaliações publicadas</h2>
            <p>Assim que os primeiros clientes avaliarem cortes concluídos, as opiniões aparecerão aqui.</p>
        </section>
    <?php else: ?>
        <section class="erro">
            <h2>Não foi possível carregar as avaliações</h2>
            <p>Verifique se a migration de avaliações públicas foi executada no SQL Editor do Supabase e se as credenciais em <code>config.php</code> estão configuradas.</p>
        </section>
    <?php endif; ?>

    <div class="acoes"><a class="botao" href="agenda.php"><i class="far fa-calendar-check"></i> Agendar meu corte</a></div>
    <a class="rodape-link" href="../index.php">Voltar ao início</a>
</main>
<?php $navRoot = '../'; require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
