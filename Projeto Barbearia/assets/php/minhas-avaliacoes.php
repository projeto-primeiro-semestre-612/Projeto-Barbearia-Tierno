<?php
session_start();
require_once __DIR__ . '/config.php';

date_default_timezone_set('America/Sao_Paulo');

if (empty($_SESSION['access_token'])) {
    redirect('login.php?modo=login');
}

$token = $_SESSION['access_token'];
$auth = supabaseRequest('GET', '/auth/v1/user', null, $token);
if ($auth['status'] !== 200 || empty($auth['data']['id'])) {
    $_SESSION = [];
    session_destroy();
    redirect('login.php?modo=login');
}

$usuario = $auth['data'];
$_SESSION['usuario'] = $usuario;
$userId = (string)$usuario['id'];
$metadata = is_array($usuario['user_metadata'] ?? null) ? $usuario['user_metadata'] : [];
$nome = trim((string)($metadata['name'] ?? $metadata['nome'] ?? $metadata['full_name'] ?? ''));

if ($nome === '') {
    $perfilResp = supabaseRequest(
        'GET',
        '/rest/v1/perfis?select=nome&user_id=eq.' . rawurlencode($userId) . '&limit=1',
        null,
        $token
    );
    $nome = trim((string)($perfilResp['data'][0]['nome'] ?? ''));
}
if ($nome === '') {
    $nome = 'Cliente';
}

if (empty($_SESSION['csrf_avaliacoes'])) {
    $_SESSION['csrf_avaliacoes'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_avaliacoes'];
$mensagem = $_SESSION['flash_avaliacoes'] ?? '';
unset($_SESSION['flash_avaliacoes']);
$erro = '';

function avaliacaoDataHora(array $agendamento): string
{
    try {
        if (!empty($agendamento['data_hora'])) {
            $data = new DateTimeImmutable($agendamento['data_hora']);
            return $data->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y \à\s H:i');
        }
        if (!empty($agendamento['data'])) {
            $hora = $agendamento['hora'] ?? '00:00:00';
            $data = new DateTimeImmutable($agendamento['data'] . ' ' . $hora, new DateTimeZone('America/Sao_Paulo'));
            return $data->format('d/m/Y \à\s H:i');
        }
    } catch (Throwable $e) {
        // Mantém a página funcional mesmo se um registro antigo tiver data inválida.
    }
    return 'Data não informada';
}

// Um cliente só pode avaliar um atendimento concluído que pertença à sua conta.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'avaliar') {
    $csrfInformado = (string)($_POST['csrf'] ?? '');
    $agendamentoId = trim((string)($_POST['agendamento_id'] ?? ''));
    $nota = filter_var($_POST['nota'] ?? null, FILTER_VALIDATE_INT);
    $comentario = trim((string)($_POST['comentario'] ?? ''));

    if (!hash_equals($csrf, $csrfInformado)) {
        $erro = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } elseif (!ctype_digit($agendamentoId) || (int)$agendamentoId < 1) {
        $erro = 'Atendimento inválido.';
    } elseif ($nota === false || $nota < 1 || $nota > 5) {
        $erro = 'Selecione uma nota de 1 a 5 estrelas.';
    } elseif ((preg_match_all('/./us', $comentario, $caracteresComentario) ?: 0) > 1000) {
        $erro = 'O comentário deve ter no máximo 1.000 caracteres.';
    } else {
        $filtroDono = 'or=(cliente_id.eq.' . rawurlencode($userId) . ',user_id.eq.' . rawurlencode($userId) . ')';
        $agendamentoResp = supabaseRequest(
            'GET',
            '/rest/v1/agendamentos?select=id,cliente_id,user_id,barbeiro_id,status'
            . '&id=eq.' . rawurlencode($agendamentoId)
            . '&' . $filtroDono
            . '&status=eq.concluido&limit=1',
            null,
            $token
        );
        $agendamento = $agendamentoResp['data'][0] ?? null;

        if (!$agendamento) {
            $erro = 'Esse atendimento ainda não está concluído ou não pertence à sua conta.';
        } else {
            $jaExiste = supabaseRequest(
                'GET',
                '/rest/v1/avaliacoes?select=id&agendamento_id=eq.' . rawurlencode($agendamentoId) . '&limit=1',
                null,
                $token
            );

            if (!empty($jaExiste['data'])) {
                $erro = 'Esse corte já recebeu uma avaliação. Cada atendimento pode ser avaliado uma única vez.';
            } else {
                $novaAvaliacao = [
                    'agendamento_id' => (int)$agendamentoId,
                    'cliente_id' => $userId,
                    'barbeiro_id' => $agendamento['barbeiro_id'] ?? null,
                    'nota' => (int)$nota,
                    'comentario' => ($comentario !== '' ? $comentario : null)
                ];

                $salvar = supabaseRequest('POST', '/rest/v1/avaliacoes', $novaAvaliacao, $token);
                if ($salvar['status'] >= 200 && $salvar['status'] < 300) {
                    $_SESSION['flash_avaliacoes'] = 'Obrigado! Sua avaliação foi registrada.';
                    redirect('minhas-avaliacoes.php');
                }

                $mensagemBanco = $salvar['data']['message'] ?? $salvar['data']['details'] ?? '';
                $erro = $mensagemBanco !== ''
                    ? 'Não foi possível registrar a avaliação: ' . $mensagemBanco
                    : 'Não foi possível registrar a avaliação. Verifique se o SQL de avaliações foi aplicado no Supabase.';
            }
        }
    }
}

// Lista somente os atendimentos concluídos do cliente conectado.
$filtroDono = 'or=(cliente_id.eq.' . rawurlencode($userId) . ',user_id.eq.' . rawurlencode($userId) . ')';
$agendamentosResp = supabaseRequest(
    'GET',
    '/rest/v1/agendamentos?select=id,servico,servico_id,barbeiro_id,data_hora,data,hora,status'
    . '&' . $filtroDono
    . '&status=eq.concluido&order=data_hora.desc.nullslast,id.desc',
    null,
    $token
);
$agendamentos = ($agendamentosResp['status'] === 200 && is_array($agendamentosResp['data']))
    ? $agendamentosResp['data'] : [];

$avaliacoesResp = supabaseRequest(
    'GET',
    '/rest/v1/avaliacoes?select=id,agendamento_id,nota,comentario,criado_em&cliente_id=eq.'
    . rawurlencode($userId) . '&order=criado_em.desc',
    null,
    $token
);
$avaliacoesPorAgendamento = [];
if ($avaliacoesResp['status'] === 200 && is_array($avaliacoesResp['data'])) {
    foreach ($avaliacoesResp['data'] as $avaliacao) {
        $avaliacoesPorAgendamento[(string)$avaliacao['agendamento_id']] = $avaliacao;
    }
}

// Complementa os nomes quando registros antigos não guardaram o texto do serviço.
$servicosCache = [];
$barbeirosCache = [];
foreach ($agendamentos as &$agendamento) {
    $agendamento['servico_exibicao'] = trim((string)($agendamento['servico'] ?? ''));
    $servicoId = (string)($agendamento['servico_id'] ?? '');
    if ($agendamento['servico_exibicao'] === '' && ctype_digit($servicoId)) {
        if (!array_key_exists($servicoId, $servicosCache)) {
            $r = supabaseRequest('GET', '/rest/v1/servicos?select=nome&id=eq.' . rawurlencode($servicoId) . '&limit=1', null, $token);
            $servicosCache[$servicoId] = $r['data'][0]['nome'] ?? 'Serviço';
        }
        $agendamento['servico_exibicao'] = $servicosCache[$servicoId];
    }
    if ($agendamento['servico_exibicao'] === '') {
        $agendamento['servico_exibicao'] = 'Corte/serviço';
    }

    $barbeiroId = (string)($agendamento['barbeiro_id'] ?? '');
    if ($barbeiroId !== '' && !array_key_exists($barbeiroId, $barbeirosCache)) {
        $r = supabaseRequest('GET', '/rest/v1/perfis?select=nome&user_id=eq.' . rawurlencode($barbeiroId) . '&limit=1', null, $token);
        $barbeirosCache[$barbeiroId] = $r['data'][0]['nome'] ?? 'Barbeiro';
    }
    $agendamento['barbeiro_exibicao'] = $barbeirosCache[$barbeiroId] ?? 'Barbeiro';
    $agendamento['data_exibicao'] = avaliacaoDataHora($agendamento);
}
unset($agendamento);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avaliar meus cortes - Barbearia Tierno</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<link rel="stylesheet" href="../css/pages/minhas-avaliacoes/minhas-avaliacoes.css">

<link rel="stylesheet" href="../css/tema.css">
</head>
<body>
<?php $navRoot = '../'; require __DIR__ . '/partials/navbar.php'; ?>

<main class="container">
    <header class="cabecalho">
        <h1><i class="fas fa-star"></i> Avalie seus cortes</h1>
        <p>Olá, <?= h($nome) ?>! Aqui aparecem somente os atendimentos que já foram concluídos.<br>Conte como foi sua experiência e ajude a melhorar nossos serviços.</p>
    </header>

    <?php if ($mensagem !== ''): ?><div class="alert ok"><i class="fas fa-check-circle"></i> <?= h($mensagem) ?></div><?php endif; ?>
    <?php if ($erro !== ''): ?><div class="alert bad"><i class="fas fa-exclamation-circle"></i> <?= h($erro) ?></div><?php endif; ?>

    <section class="card">
        <?php if (!$agendamentos): ?>
            <div class="vazio">
                <i class="far fa-calendar-check"></i>
                <h2>Nenhum corte disponível para avaliação</h2>
                <p>Quando um atendimento for marcado como concluído pelo barbeiro, ele aparecerá nesta página.</p>
                <a class="voltar" href="agenda.php">Agendar um horário</a>
            </div>
        <?php else: ?>
            <?php foreach ($agendamentos as $agendamento): ?>
                <?php $idAgendamento = (string)$agendamento['id']; $avaliacao = $avaliacoesPorAgendamento[$idAgendamento] ?? null; ?>
                <article class="atendimento">
                    <div class="detalhes">
                        <h2><?= h($agendamento['servico_exibicao']) ?></h2>
                        <p><i class="far fa-calendar-alt"></i> <?= h($agendamento['data_exibicao']) ?></p>
                        <p><i class="fas fa-user-tie"></i> <?= h($agendamento['barbeiro_exibicao']) ?></p>
                        <?php if ($avaliacao): ?>
                            <div class="nota-exibida" aria-label="Nota <?= (int)$avaliacao['nota'] ?> de 5">
                                <?= str_repeat('★', max(0, min(5, (int)$avaliacao['nota']))) . str_repeat('☆', 5 - max(0, min(5, (int)$avaliacao['nota']))) ?>
                            </div>
                            <?php if (trim((string)($avaliacao['comentario'] ?? '')) !== ''): ?>
                                <p class="comentario-exibido">“<?= h($avaliacao['comentario']) ?>”</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($avaliacao): ?>
                        <div><span class="enviada"><i class="fas fa-check"></i> Avaliação enviada</span></div>
                    <?php else: ?>
                        <form class="form-avaliacao" method="POST" action="minhas-avaliacoes.php">
                            <input type="hidden" name="acao" value="avaliar">
                            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                            <input type="hidden" name="agendamento_id" value="<?= h($idAgendamento) ?>">
                            <label class="titulo">Como você avalia este atendimento?</label>
                            <div class="estrelas" role="radiogroup" aria-label="Nota de 1 a 5 estrelas">
                                <?php for ($estrela = 1; $estrela <= 5; $estrela++): ?>
                                    <input type="radio" id="nota-<?= h($idAgendamento) ?>-<?= $estrela ?>" name="nota" value="<?= $estrela ?>" required>
                                    <label for="nota-<?= h($idAgendamento) ?>-<?= $estrela ?>" title="<?= $estrela ?> estrela(s)">★</label>
                                <?php endfor; ?>
                            </div>
                            <textarea name="comentario" maxlength="1000" placeholder="Deixe um comentário sobre seu corte (opcional)"></textarea>
                            <button type="submit"><i class="fas fa-paper-plane"></i> Enviar avaliação</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
    <a class="voltar" href="dashboard.php"><i class="fas fa-arrow-left"></i> Voltar para minha conta</a>
</main>
<?php $navRoot = '../'; require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
