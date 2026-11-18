<?php
session_start();
require_once __DIR__ . '/config.php';

date_default_timezone_set('America/Sao_Paulo');
$timezone = new DateTimeZone('America/Sao_Paulo');

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
$userId = $usuario['id'];
$metadata = is_array($usuario['user_metadata'] ?? null) ? $usuario['user_metadata'] : [];
$nomeCliente = trim((string)($metadata['name'] ?? $metadata['nome'] ?? $metadata['full_name'] ?? ''));
if ($nomeCliente === '') {
    $nomeCliente = 'Cliente';
}

$mensagem = '';
$erro = '';

function agendaRows(string $path, string $token): array {
    $resposta = supabaseRequest('GET', $path, null, $token);
    return ($resposta['status'] === 200 && is_array($resposta['data'])) ? $resposta['data'] : [];
}

// Catálogo global de serviços: todos os barbeiros visualizam os mesmos serviços ativos.
$servicos = agendaRows(
    '/rest/v1/servicos?select=id,nome,preco,duracao_minutos,ativo&ativo=eq.true&order=nome.asc',
    $token
);
$barbeiros = agendaRows(
    '/rest/v1/perfis?select=user_id,nome&tipo_usuario=eq.barbeiro&order=nome.asc',
    $token
);

$dataSelecionada = trim($_GET['data'] ?? (new DateTimeImmutable('today', $timezone))->format('Y-m-d'));
$barbeiroSelecionado = trim($_GET['barbeiro_id'] ?? '');
$servicoSelecionado = trim($_GET['servico_id'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'agendar') {
    $barbeiroId = trim($_POST['barbeiro_id'] ?? '');
    $servicoId = trim($_POST['servico_id'] ?? '');
    $data = trim($_POST['data'] ?? '');
    $hora = trim($_POST['hora'] ?? '');

    if (
        !preg_match('/^[0-9a-f-]{36}$/i', $barbeiroId) ||
        !ctype_digit($servicoId) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) ||
        !preg_match('/^\d{2}:\d{2}$/', $hora) ||
        !checkdate((int)substr($data, 5, 2), (int)substr($data, 8, 2), (int)substr($data, 0, 4)) ||
        (int)substr($hora, 0, 2) > 23 || (int)substr($hora, 3, 2) > 59
    ) {
        $erro = 'Selecione barbeiro, serviço, data e horário válidos.';
    } else {
        $dataHoraObj = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $data . ' ' . $hora, $timezone);
        if (!$dataHoraObj || $dataHoraObj <= new DateTimeImmutable('now', $timezone)) {
            $erro = 'Escolha um horário futuro.';
        } else {
            // ISO com offset local; o Postgres converte corretamente para timestamptz.
            $dataHoraIso = $dataHoraObj->format('Y-m-d\\TH:i:sP');

            $servicoResp = supabaseRequest(
                'GET',
                '/rest/v1/servicos?select=id,nome,preco,duracao_minutos&id=eq.' . rawurlencode($servicoId) . '&ativo=eq.true&limit=1',
                null,
                $token
            );
            $servico = $servicoResp['data'][0] ?? null;

            // O horário deve estar liberado na mesma tabela que o painel do barbeiro alimenta.
            $slotResp = supabaseRequest(
                'GET',
                '/rest/v1/horarios_disponiveis?select=id&barbeiro_id=eq.' . rawurlencode($barbeiroId)
                . '&data_hora=eq.' . rawurlencode($dataHoraIso)
                . '&disponivel=eq.true&limit=1',
                null,
                $token
            );

            $agendamentoResp = supabaseRequest(
                'GET',
                '/rest/v1/agendamentos?select=id&barbeiro_id=eq.' . rawurlencode($barbeiroId)
                . '&data_hora=eq.' . rawurlencode($dataHoraIso)
                . '&status=neq.cancelado&limit=1',
                null,
                $token
            );

            if (!$servico) {
                $erro = 'O serviço selecionado não está disponível.';
            } elseif ($slotResp['status'] !== 200 || empty($slotResp['data'])) {
                $erro = 'Esse horário não está mais liberado pelo barbeiro. Atualize a lista e escolha outro.';
            } elseif ($agendamentoResp['status'] === 200 && !empty($agendamentoResp['data'])) {
                $erro = 'Esse horário já foi reservado. Escolha outro.';
            } else {
                $novoAgendamento = supabaseRequest(
                    'POST',
                    '/rest/v1/agendamentos',
                    [
                        'cliente_id' => $userId,
                        'nome_cliente' => $nomeCliente,
                        'barbeiro_id' => $barbeiroId,
                        'servico_id' => (int)$servicoId,
                        'data_hora' => $dataHoraIso,
                        'status' => 'agendado'
                    ],
                    $token
                );

                if ($novoAgendamento['status'] >= 200 && $novoAgendamento['status'] < 300) {
                    // O gatilho do banco marca o slot como indisponível automaticamente.
                    $mensagem = 'Agendamento realizado com sucesso!';
                    $dataSelecionada = $data;
                    $barbeiroSelecionado = $barbeiroId;
                    $servicoSelecionado = $servicoId;
                } else {
                    $erro = $novoAgendamento['data']['message']
                        ?? $novoAgendamento['data']['details']
                        ?? 'Não foi possível realizar o agendamento.';
                }
            }
        }
    }
}

$horarios = [];
if (
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataSelecionada) &&
    preg_match('/^[0-9a-f-]{36}$/i', $barbeiroSelecionado)
) {
    $dia = DateTimeImmutable::createFromFormat('!Y-m-d', $dataSelecionada, $timezone);
    if ($dia) {
        $proximoDia = $dia->modify('+1 day');
        $inicioIso = $dia->format('Y-m-d\\TH:i:sP');
        $fimIso = $proximoDia->format('Y-m-d\\TH:i:sP');

        $slots = agendaRows(
            '/rest/v1/horarios_disponiveis?select=id,data_hora,disponivel'
            . '&barbeiro_id=eq.' . rawurlencode($barbeiroSelecionado)
            . '&data_hora=gte.' . rawurlencode($inicioIso)
            . '&data_hora=lt.' . rawurlencode($fimIso)
            . '&disponivel=eq.true&order=data_hora.asc',
            $token
        );

        $reservas = agendaRows(
            '/rest/v1/agendamentos?select=data_hora,status'
            . '&barbeiro_id=eq.' . rawurlencode($barbeiroSelecionado)
            . '&data_hora=gte.' . rawurlencode($inicioIso)
            . '&data_hora=lt.' . rawurlencode($fimIso)
            . '&status=neq.cancelado',
            $token
        );
        $ocupados = [];
        foreach ($reservas as $reserva) {
            if (!empty($reserva['data_hora'])) {
                $instante = (new DateTimeImmutable($reserva['data_hora']))->setTimezone($timezone);
                $ocupados[$instante->format('Y-m-d H:i')] = true;
            }
        }

        $agora = new DateTimeImmutable('now', $timezone);
        foreach ($slots as $slot) {
            if (empty($slot['data_hora']) || empty($slot['disponivel'])) {
                continue;
            }
            $instante = (new DateTimeImmutable($slot['data_hora']))->setTimezone($timezone);
            $chave = $instante->format('Y-m-d H:i');
            if ($instante > $agora && !isset($ocupados[$chave])) {
                $horarios[] = ['id' => $slot['id'], 'hora' => $instante->format('H:i')];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agendamento - Barbearia Tierno</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="../css/pages/agenda/agenda.css">

<link rel="stylesheet" href="../css/tema.css">
</head>
<body>
<?php $navRoot = '../'; require __DIR__ . '/partials/navbar.php'; ?>

<main class="container">
    <h1>Agende seu horário</h1>
    <p class="nota">Você escolhe o serviço, barbeiro, data e um horário liberado pelo barbeiro.</p>

    <?php if ($mensagem): ?><div class="alert ok"><?= h($mensagem) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alert bad"><?= h($erro) ?></div><?php endif; ?>

    <section class="card">
        <h2>Escolha o atendimento</h2>
        <form method="GET">
            <div class="form-grid">
                <div>
                    <label for="servico_id">Tipo de corte/serviço</label>
                    <select id="servico_id" name="servico_id" required>
                        <option value="">Selecione</option>
                        <?php foreach ($servicos as $s): ?>
                            <option value="<?= h($s['id']) ?>" <?= $servicoSelecionado === (string)$s['id'] ? 'selected' : '' ?>>
                                <?= h($s['nome']) ?> - R$ <?= number_format((float)$s['preco'],2,',','.') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="barbeiro_id">Barbeiro</label>
                    <select id="barbeiro_id" name="barbeiro_id" required>
                        <option value="">Selecione</option>
                        <?php foreach ($barbeiros as $b): ?>
                            <option value="<?= h($b['user_id']) ?>" <?= $barbeiroSelecionado === $b['user_id'] ? 'selected' : '' ?>>
                                <?= h($b['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="data">Data</label>
                    <input id="data" type="date" name="data" min="<?= h(date('Y-m-d')) ?>" value="<?= h($dataSelecionada) ?>" required>
                </div>
            </div>
            <button type="submit">Ver horários disponíveis</button>
        </form>
    </section>

    <?php if ($barbeiroSelecionado && $dataSelecionada): ?>
    <section class="card">
        <h2>Horários disponíveis</h2>
        <?php if (!$horarios): ?>
            <p class="nota">Nenhum horário liberado para esta data.</p>
        <?php else: ?>
            <div class="horarios">
                <?php foreach ($horarios as $slot): ?>
                    <form method="POST">
                        <input type="hidden" name="acao" value="agendar">
                        <input type="hidden" name="barbeiro_id" value="<?= h($barbeiroSelecionado) ?>">
                        <input type="hidden" name="servico_id" value="<?= h($servicoSelecionado) ?>">
                        <input type="hidden" name="data" value="<?= h($dataSelecionada) ?>">
                        <input type="hidden" name="hora" value="<?= h($slot['hora']) ?>">
                        <button class="horario" type="submit"><?= h($slot['hora']) ?></button>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</main>
<?php $navRoot = '../'; require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
