<?php
session_start();
require_once __DIR__ . '/config.php';

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
$idLogado = $usuario['id'];

$perfilR = supabaseRequest(
    'GET',
    '/rest/v1/perfis?select=user_id,nome,tipo_usuario&user_id=eq.' . rawurlencode($idLogado) . '&limit=1',
    null, null, true
);
$perfil = $perfilR['data'][0] ?? null;
$tipo = strtolower(trim((string)($perfil['tipo_usuario'] ?? '')));

if (!in_array($tipo, ['admin', 'barbeiro'], true)) {
    http_response_code(403);
    exit('Acesso negado. Somente administradores e barbeiros podem definir horários.');
}

$mensagem = '';
$erro = '';

$barbeiros = supabaseRequest(
    'GET',
    '/rest/v1/perfis?select=user_id,nome&tipo_usuario=eq.barbeiro&order=nome.asc',
    null, null, true
)['data'] ?? [];

if ($tipo === 'barbeiro') {
    $barbeiros = array_values(array_filter($barbeiros, fn($b) => ($b['user_id'] ?? '') === $idLogado));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $barbeiroId = trim($_POST['barbeiro_id'] ?? '');

    if ($tipo === 'barbeiro') {
        $barbeiroId = $idLogado;
    }

    if (!preg_match('/^[0-9a-f-]{36}$/i', $barbeiroId)) {
        $erro = 'Barbeiro inválido.';
    } elseif ($acao === 'criar') {
        $data = trim($_POST['data'] ?? '');
        $inicio = trim($_POST['hora_inicio'] ?? '');
        $fim = trim($_POST['hora_fim'] ?? '');
        $intervalo = (int)($_POST['intervalo_minutos'] ?? 30);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) ||
            !preg_match('/^\d{2}:\d{2}$/', $inicio) ||
            !preg_match('/^\d{2}:\d{2}$/', $fim) ||
            $intervalo < 5 || $intervalo > 240 ||
            strtotime("$data $inicio") >= strtotime("$data $fim")) {
            $erro = 'Informe data, início, fim e intervalo corretamente.';
        } else {
            $r = supabaseRequest(
                'POST',
                '/rest/v1/disponibilidades',
                [
                    'barbeiro_id' => $barbeiroId,
                    'data' => $data,
                    'hora_inicio' => $inicio . ':00',
                    'hora_fim' => $fim . ':00',
                    'intervalo_minutos' => $intervalo,
                    'ativo' => true
                ],
                null, true
            );
            if ($r['status'] >= 200 && $r['status'] < 300) {
                $mensagem = 'Horário disponibilizado com sucesso.';
            } else {
                $erro = $r['data']['message'] ?? $r['data']['details'] ?? 'Não foi possível salvar o horário.';
            }
        }
    } elseif ($acao === 'excluir') {
        $id = (int)($_POST['id'] ?? 0);
        $r = supabaseRequest(
            'DELETE',
            '/rest/v1/disponibilidades?id=eq.' . $id . '&barbeiro_id=eq.' . rawurlencode($barbeiroId),
            null, null, true
        );
        if ($r['status'] >= 200 && $r['status'] < 300) {
            $mensagem = 'Horário removido.';
        } else {
            $erro = $r['data']['message'] ?? 'Não foi possível remover o horário.';
        }
    }
}

$lista = supabaseRequest(
    'GET',
    '/rest/v1/disponibilidades?select=id,barbeiro_id,data,hora_inicio,hora_fim,intervalo_minutos,ativo&order=data.asc,hora_inicio.asc',
    null, null, true
)['data'] ?? [];

if ($tipo === 'barbeiro') {
    $lista = array_values(array_filter($lista, fn($d) => ($d['barbeiro_id'] ?? '') === $idLogado));
}

$nomes = [];
foreach ($barbeiros as $b) $nomes[$b['user_id']] = $b['nome'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Horários - Barbearia Tierno</title>

<link rel="stylesheet" href="../css/pages/horarios/horarios.css">

<link rel="stylesheet" href="../css/tema.css">
</head>
<body>
<?php $navRoot = '../'; require __DIR__ . '/partials/navbar.php'; ?>
<main class="container">
<h1>Definir horários disponíveis</h1>
<p>O barbeiro libera os horários. O cliente apenas escolhe entre os horários liberados.</p>
<?php if($mensagem):?><p class="ok"><?=h($mensagem)?></p><?php endif;?>
<?php if($erro):?><p class="err"><?=h($erro)?></p><?php endif;?>

<section class="card">
<form method="POST">
<input type="hidden" name="acao" value="criar">
<div class="grid">
<div>
<label>Barbeiro</label>
<select name="barbeiro_id" <?= $tipo==='barbeiro'?'disabled':'' ?> required>
<?php foreach($barbeiros as $b):?>
<option value="<?=h($b['user_id'])?>"><?=h($b['nome'])?></option>
<?php endforeach;?>
</select>
<?php if($tipo==='barbeiro'):?><input type="hidden" name="barbeiro_id" value="<?=h($idLogado)?>"><?php endif;?>
</div>
<div><label>Data</label><input type="date" name="data" min="<?=h(date('Y-m-d'))?>" required></div>
<div><label>Hora inicial</label><input type="time" name="hora_inicio" required></div>
<div><label>Hora final</label><input type="time" name="hora_fim" required></div>
<div><label>Intervalo entre clientes (minutos)</label><input type="number" name="intervalo_minutos" value="30" min="5" max="240" required></div>
</div>
<button type="submit">Liberar horários</button>
</form>
</section>

<section class="card">
<h2>Horários cadastrados</h2>
<table class="table"><thead><tr><th>Barbeiro</th><th>Data</th><th>Período</th><th>Intervalo</th><th>Ação</th></tr></thead><tbody>
<?php foreach($lista as $d):?>
<tr>
<td><?=h($nomes[$d['barbeiro_id']] ?? $d['barbeiro_id'])?></td>
<td><?=h(date('d/m/Y',strtotime($d['data'])))?></td>
<td><?=h(substr($d['hora_inicio'],0,5))?> às <?=h(substr($d['hora_fim'],0,5))?></td>
<td><?=h($d['intervalo_minutos'])?> min</td>
<td><form method="POST" onsubmit="return confirm('Remover este período?')"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?=h($d['id'])?>"><input type="hidden" name="barbeiro_id" value="<?=h($d['barbeiro_id'])?>"><button class="del">Remover</button></form></td>
</tr>
<?php endforeach;?>
<?php if(!$lista):?><tr><td colspan="5">Nenhum horário cadastrado.</td></tr><?php endif;?>
</tbody></table>
</section>
</main>
<?php $navRoot = '../'; require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
