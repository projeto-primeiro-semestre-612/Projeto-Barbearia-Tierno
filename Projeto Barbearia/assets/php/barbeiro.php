<?php

session_start();

require_once __DIR__ . '/config.php';

/*
 * |--------------------------------------------------------------------------
 *
 * | VERIFICA LOGIN
 *
 * |--------------------------------------------------------------------------
 */

if (
    empty($_SESSION['access_token'])
) {
    redirect(
        'login.php?modo=login'
    );
}

$token =
    $_SESSION['access_token'];

/*
 * |--------------------------------------------------------------------------
 *
 * | BUSCAR USUÁRIO LOGADO
 *
 * |--------------------------------------------------------------------------
 */

$respostaUsuario =
    supabaseRequest(
        'GET',
        '/auth/v1/user',
        null,
        $token
    );

if (
    !is_array($respostaUsuario) ||
    $respostaUsuario['status'] !== 200 ||
    empty($respostaUsuario['data']['id'])
) {
    $_SESSION = [];

    session_destroy();

    redirect(
        'login.php?modo=login'
    );
}

$usuario =
    $respostaUsuario['data'];

$idBarbeiro =
    $usuario['id'];

$_SESSION['usuario'] =
    $usuario;

/*
 * |--------------------------------------------------------------------------
 *
 * | BUSCAR PERFIL
 *
 * |--------------------------------------------------------------------------
 */

$respostaPerfil =
    supabaseRequest(
        'GET',
        '/rest/v1/perfis'
            . '?select=user_id,nome,tipo_usuario'
            . '&user_id=eq.'
            . rawurlencode($idBarbeiro)
            . '&limit=1',
        null,
        $token
    );

$perfil = null;

if (
    is_array($respostaPerfil) &&
    $respostaPerfil['status'] === 200 &&
    is_array($respostaPerfil['data']) &&
    !empty($respostaPerfil['data'][0])
) {
    $perfil =
        $respostaPerfil['data'][0];
}

/*
 * |--------------------------------------------------------------------------
 *
 * | TIPO DE USUÁRIO
 *
 * |--------------------------------------------------------------------------
 */

$tipoUsuario =
    strtolower(
        trim(
            (string) (
                $perfil['tipo_usuario']
                    ?? ''
            )
        )
    );

// Acesso restrito a barbeiros e administradores.
if (!in_array($tipoUsuario, ['barbeiro', 'admin'], true)) {
    redirect('dashboard.php');
    exit;
}

/*
 * |--------------------------------------------------------------------------
 *
 * | NOME
 *
 * |--------------------------------------------------------------------------
 */

$nomePerfil = trim((string) ($perfil['nome'] ?? ''));
$metadata = is_array($usuario['user_metadata'] ?? null) ? $usuario['user_metadata'] : [];
$nomeMetadata = trim((string) (
    $metadata['name']
        ?? $metadata['nome']
        ?? $metadata['full_name']
        ?? $metadata['display_name']
        ?? ''
));

// Um nome com @ indica que o perfil foi preenchido incorretamente com o e-mail.
$emailUsuario = strtolower(trim((string) ($usuario['email'] ?? '')));
$parteEmail = strtolower((string) (explode('@', $emailUsuario)[0] ?? ''));
$nomePareceEmail = str_contains($nomePerfil, '@') ||
    ($parteEmail !== '' && strtolower($nomePerfil) === $parteEmail);
$nomeBarbeiro = $nomeMetadata !== ''
    ? $nomeMetadata
    : (($nomePerfil !== '' && !$nomePareceEmail) ? $nomePerfil : 'Barbeiro');

/*
 * |--------------------------------------------------------------------------
 *
 * | MENSAGENS
 *
 * |--------------------------------------------------------------------------
 */

$mensagem = '';

$erro = '';

/*
 * |--------------------------------------------------------------------------
 *
 * | FUNÇÃO AUXILIAR PARA PEGAR ERRO DO SUPABASE
 *
 * |--------------------------------------------------------------------------
 */

function mensagemErroSupabase(
    array $resultado,
    string $padrao
): string {
    $dados =
        $resultado['data']
            ?? [];

    if (
        is_array($dados)
    ) {
        if (
            !empty($dados['message'])
        ) {
            return (string) $dados['message'];
        }

        if (
            !empty($dados['msg'])
        ) {
            return (string) $dados['msg'];
        }

        if (
            !empty($dados['error_description'])
        ) {
            return (string) $dados['error_description'];
        }
    }

    return $padrao;
}

/*
 * |--------------------------------------------------------------------------
 *
 * | POST
 *
 * |--------------------------------------------------------------------------
 */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {
    $acao =
        $_POST['acao']
            ?? '';

    /*
     * |--------------------------------------------------------------------------
     *
     * | CADASTRAR SERVIÇO
     *
     * |--------------------------------------------------------------------------
     */

    if (
        $acao === 'criar_servico'
    ) {
        $nome =
            trim(
                $_POST['nome']
                    ?? ''
            );

        $descricao =
            trim(
                $_POST['descricao']
                    ?? ''
            );

        $precoTexto =
            str_replace(
                ',',
                '.',
                trim(
                    $_POST['preco']
                        ?? ''
                )
            );

        $duracao =
            (int) (
                $_POST['duracao_minutos']
                    ?? 30
            );

        $preco =
            is_numeric($precoTexto)
                ? (float) $precoTexto
                : -1;

        if (
            $nome === ''
        ) {
            $erro =
                'Informe o nome do serviço.';
        } elseif (
            $preco < 0
        ) {
            $erro =
                'Informe um preço válido.';
        } elseif (
            $duracao <= 0
        ) {
            $erro =
                'Informe uma duração válida.';
        } else {
            $resultado =
                supabaseRequest(
                    'POST',
                    '/rest/v1/servicos',
                    [
                        'barbeiro_id' => null,
                        'nome' =>
                            $nome,
                        'descricao' =>
                            $descricao,
                        'preco' =>
                            $preco,
                        'duracao_minutos' =>
                            $duracao,
                        'ativo' =>
                            true
                    ],
                    $token
                );

            if (
                $resultado['status'] >= 200 &&
                $resultado['status'] < 300
            ) {
                $mensagem =
                    'Serviço cadastrado com sucesso.';
            } else {
                $erro =
                    mensagemErroSupabase(
                        $resultado,
                        'Não foi possível cadastrar o serviço.'
                    );
            }
        }
    }
    /*
     * |--------------------------------------------------------------------------
     *
     * | EDITAR SERVIÇO
     *
     * |--------------------------------------------------------------------------
     */ elseif (
        $acao === 'editar_servico'
    ) {
        $id =
            trim(
                $_POST['id']
                    ?? ''
            );

        $nome =
            trim(
                $_POST['nome']
                    ?? ''
            );

        $descricao =
            trim(
                $_POST['descricao']
                    ?? ''
            );

        $precoTexto =
            str_replace(
                ',',
                '.',
                trim(
                    $_POST['preco']
                        ?? ''
                )
            );

        $duracao =
            (int) (
                $_POST['duracao_minutos']
                    ?? 30
            );

        $preco =
            is_numeric($precoTexto)
                ? (float) $precoTexto
                : -1;

        if (
            $id === ''
        ) {
            $erro =
                'Serviço inválido.';
        } elseif (
            $nome === ''
        ) {
            $erro =
                'Informe o nome do serviço.';
        } elseif (
            $preco < 0
        ) {
            $erro =
                'Informe um preço válido.';
        } elseif (
            $duracao <= 0
        ) {
            $erro =
                'Informe uma duração válida.';
        } else {
            $resultado =
                supabaseRequest(
                    'PATCH',
                    '/rest/v1/servicos'
                        . '?id=eq.'
                        . rawurlencode($id),
                    [
                        'nome' =>
                            $nome,
                        'descricao' =>
                            $descricao,
                        'preco' =>
                            $preco,
                        'duracao_minutos' =>
                            $duracao
                    ],
                    $token
                );

            if (
                $resultado['status'] >= 200 &&
                $resultado['status'] < 300
            ) {
                $mensagem =
                    'Serviço atualizado com sucesso.';
            } else {
                $erro =
                    mensagemErroSupabase(
                        $resultado,
                        'Não foi possível atualizar o serviço.'
                    );
            }
        }
    }
    /*
     * |--------------------------------------------------------------------------
     *
     * | ATIVAR / DESATIVAR SERVIÇO
     *
     * |--------------------------------------------------------------------------
     */ elseif (
        $acao === 'alternar_servico'
    ) {
        $id =
            trim(
                $_POST['id']
                    ?? ''
            );

        $ativo =
            isset(
                $_POST['ativo']
            ) &&
            $_POST['ativo'] === '1';

        if (
            $id === ''
        ) {
            $erro =
                'Serviço inválido.';
        } else {
            $resultado =
                supabaseRequest(
                    'PATCH',
                    '/rest/v1/servicos'
                        . '?id=eq.'
                        . rawurlencode($id),
                    [
                        'ativo' =>
                            $ativo
                    ],
                    $token
                );

            if (
                $resultado['status'] >= 200 &&
                $resultado['status'] < 300
            ) {
                $mensagem =
                    $ativo
                        ? 'Serviço ativado.'
                        : 'Serviço desativado.';
            } else {
                $erro =
                    mensagemErroSupabase(
                        $resultado,
                        'Não foi possível alterar o serviço.'
                    );
            }
        }
    }
    /*
     * |--------------------------------------------------------------------------
     *
     * | EXCLUIR SERVIÇO
     *
     * |--------------------------------------------------------------------------
     */ elseif (
        $acao === 'excluir_servico'
    ) {
        $id =
            trim(
                $_POST['id']
                    ?? ''
            );

        if (
            $id === ''
        ) {
            $erro =
                'Serviço inválido.';
        } else {
            $resultado =
                supabaseRequest(
                    'DELETE',
                    '/rest/v1/servicos'
                        . '?id=eq.'
                        . rawurlencode($id),
                    null,
                    $token
                );

            if (
                $resultado['status'] >= 200 &&
                $resultado['status'] < 300
            ) {
                $mensagem =
                    'Serviço excluído com sucesso.';
            } else {
                $erro =
                    mensagemErroSupabase(
                        $resultado,
                        'Não foi possível excluir o serviço.'
                    );
            }
        }
    }
    /*
     * |--------------------------------------------------------------------------
     *
     * | CRIAR HORÁRIO
     *
     * |--------------------------------------------------------------------------
     */ elseif ($acao === 'criar_horarios_lote') {
        $data = trim($_POST['data'] ?? '');
        $horaInicio = trim($_POST['hora_inicio'] ?? '');
        $horaFim = trim($_POST['hora_fim'] ?? '');
        $intervalo = (int) ($_POST['intervalo_minutos'] ?? 30);
        $timezone = new DateTimeZone('America/Sao_Paulo');

        if (
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) ||
            !preg_match('/^\d{2}:\d{2}$/', $horaInicio) ||
            !preg_match('/^\d{2}:\d{2}$/', $horaFim) ||
            !checkdate((int) substr($data, 5, 2), (int) substr($data, 8, 2), (int) substr($data, 0, 4)) ||
            (int) substr($horaInicio, 0, 2) > 23 ||
            (int) substr($horaInicio, 3, 2) > 59 ||
            (int) substr($horaFim, 0, 2) > 23 ||
            (int) substr($horaFim, 3, 2) > 59
        ) {
            $erro = 'Informe uma data e horários válidos.';
        } elseif ($intervalo < 5 || $intervalo > 240) {
            $erro = 'O intervalo deve ficar entre 5 e 240 minutos.';
        } else {
            $inicio = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $data . ' ' . $horaInicio, $timezone);
            $fim = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $data . ' ' . $horaFim, $timezone);
            $agora = new DateTimeImmutable('now', $timezone);

            if (!$inicio || !$fim || $fim <= $inicio) {
                $erro = 'O horário final precisa ser posterior ao horário inicial.';
            } elseif ($fim <= $agora) {
                $erro = 'O período informado já passou. Escolha horários futuros.';
            } else {
                $inicioDia = new DateTimeImmutable($data . ' 00:00:00', $timezone);
                $proximoDia = $inicioDia->modify('+1 day');
                $consulta = supabaseRequest(
                    'GET',
                    '/rest/v1/horarios_disponiveis?select=id,data_hora,disponivel'
                        . '&barbeiro_id=eq.' . rawurlencode($idBarbeiro)
                        . '&data_hora=gte.' . rawurlencode($inicioDia->format('c'))
                        . '&data_hora=lt.' . rawurlencode($proximoDia->format('c')),
                    null,
                    $token
                );

                if ($consulta['status'] !== 200 || !is_array($consulta['data'])) {
                    $erro = mensagemErroSupabase($consulta, 'Não foi possível consultar os horários já cadastrados.');
                } else {
                    $existentes = [];
                    foreach ($consulta['data'] as $linha) {
                        if (!empty($linha['data_hora'])) {
                            $dtExistente = (new DateTimeImmutable($linha['data_hora']))->setTimezone($timezone);
                            $existentes[$dtExistente->format('Y-m-d H:i')] = true;
                        }
                    }

                    $novos = [];
                    $cursor = $inicio;
                    while ($cursor < $fim && count($novos) < 100) {
                        if ($cursor > $agora && !isset($existentes[$cursor->format('Y-m-d H:i')])) {
                            $novos[] = [
                                'barbeiro_id' => $idBarbeiro,
                                'data_hora' => $cursor->format('Y-m-d\TH:i:sP'),
                                'disponivel' => true
                            ];
                        }
                        $cursor = $cursor->modify('+' . $intervalo . ' minutes');
                    }

                    if ($cursor < $fim) {
                        $erro = 'O período geraria mais de 100 horários. Reduza o período ou aumente o intervalo.';
                    } elseif (!$novos) {
                        $erro = 'Não há novos horários futuros para liberar nesse período.';
                    } else {
                        $resultado = supabaseRequest(
                            'POST',
                            '/rest/v1/horarios_disponiveis',
                            $novos,
                            $token
                        );

                        if ($resultado['status'] >= 200 && $resultado['status'] < 300) {
                            $quantidade = count($novos);
                            $mensagem = $quantidade . ' horário(s) liberado(s) com sucesso.';
                        } else {
                            $erro = mensagemErroSupabase($resultado, 'Não foi possível liberar os horários em massa.');
                        }
                    }
                }
            }
        }
    }
    /*
     * |--------------------------------------------------------------------------
     *
     * | EXCLUIR HORÁRIO
     *
     * |--------------------------------------------------------------------------
     */ elseif (
        $acao === 'excluir_horario'
    ) {
        $id =
            trim(
                $_POST['id']
                    ?? ''
            );

        if (
            $id === ''
        ) {
            $erro =
                'Horário inválido.';
        } else {
            $resultado =
                supabaseRequest(
                    'DELETE',
                    '/rest/v1/horarios_disponiveis'
                        . '?id=eq.'
                        . rawurlencode($id)
                        . '&barbeiro_id=eq.'
                        . rawurlencode($idBarbeiro),
                    null,
                    $token
                );

            if (
                $resultado['status'] >= 200 &&
                $resultado['status'] < 300
            ) {
                $mensagem =
                    'Horário excluído com sucesso.';
            } else {
                $erro =
                    mensagemErroSupabase(
                        $resultado,
                        'Não foi possível excluir o horário.'
                    );
            }
        }
    }
    /*
     * |--------------------------------------------------------------------------
     *
     * | ALTERAR DISPONIBILIDADE
     *
     * |--------------------------------------------------------------------------
     */ elseif (
        $acao === 'alternar_horario'
    ) {
        $id =
            trim(
                $_POST['id']
                    ?? ''
            );

        $disponivel =
            isset(
                $_POST['disponivel']
            ) &&
            $_POST['disponivel'] === '1';

        if (
            $id === ''
        ) {
            $erro =
                'Horário inválido.';
        } else {
            $resultado =
                supabaseRequest(
                    'PATCH',
                    '/rest/v1/horarios_disponiveis'
                        . '?id=eq.'
                        . rawurlencode($id)
                        . '&barbeiro_id=eq.'
                        . rawurlencode($idBarbeiro),
                    [
                        'disponivel' =>
                            $disponivel
                    ],
                    $token
                );

            if (
                $resultado['status'] >= 200 &&
                $resultado['status'] < 300
            ) {
                $mensagem =
                    $disponivel
                        ? 'Horário liberado.'
                        : 'Horário bloqueado.';
            } else {
                $erro =
                    mensagemErroSupabase(
                        $resultado,
                        'Não foi possível alterar o horário.'
                    );
            }
        }
    }
    /*
     * |--------------------------------------------------------------------------
     *
     * | ALTERAR STATUS DO AGENDAMENTO
     *
     * |--------------------------------------------------------------------------
     */ elseif (
        $acao === 'status_agendamento'
    ) {
        $id =
            trim(
                $_POST['id']
                    ?? ''
            );

        $novoStatus =
            $_POST['status']
                ?? '';

        $statusPermitidos = [
            'agendado',
            'cancelado',
            'concluido'
        ];

        if (
            $id === ''
        ) {
            $erro =
                'Agendamento inválido.';
        } elseif (
            !in_array(
                $novoStatus,
                $statusPermitidos,
                true
            )
        ) {
            $erro =
                'Status inválido.';
        } else {
            $resultado =
                supabaseRequest(
                    'PATCH',
                    '/rest/v1/agendamentos'
                        . '?id=eq.'
                        . rawurlencode($id)
                        . '&barbeiro_id=eq.'
                        . rawurlencode($idBarbeiro),
                    [
                        'status' =>
                            $novoStatus
                    ],
                    $token
                );

            if (
                $resultado['status'] >= 200 &&
                $resultado['status'] < 300
            ) {
                $mensagem =
                    'Status do agendamento atualizado.';
            } else {
                $erro =
                    mensagemErroSupabase(
                        $resultado,
                        'Não foi possível atualizar o agendamento.'
                    );
            }
        }
    }
}

/*
 * |--------------------------------------------------------------------------
 *
 * | BUSCA SERVIÇOS
 *
 * |--------------------------------------------------------------------------
 */

$respostaServicos =
    supabaseRequest(
        'GET',
        '/rest/v1/servicos'
            . '?select=id,barbeiro_id,nome,descricao,preco,duracao_minutos,ativo'
            . '&order=ativo.desc,nome.asc',
        null,
        $token
    );

$servicos = [];

if (
    $respostaServicos['status'] === 200 &&
    is_array(
        $respostaServicos['data']
    )
) {
    $servicos =
        $respostaServicos['data'];
}

/*
 * |--------------------------------------------------------------------------
 *
 * | BUSCA HORÁRIOS
 *
 * |--------------------------------------------------------------------------
 */

$respostaHorarios =
    supabaseRequest(
        'GET',
        '/rest/v1/horarios_disponiveis'
            . '?select=id,barbeiro_id,data_hora,disponivel,criado_em'
            . '&barbeiro_id=eq.'
            . rawurlencode($idBarbeiro)
            . '&order=data_hora.asc',
        null,
        $token
    );

$horarios = [];

if (
    $respostaHorarios['status'] === 200 &&
    is_array(
        $respostaHorarios['data']
    )
) {
    $horarios =
        $respostaHorarios['data'];
}

/*
 * |--------------------------------------------------------------------------
 *
 * | BUSCA AGENDAMENTOS
 *
 * |--------------------------------------------------------------------------
 */

$respostaAgendamentos =
    supabaseRequest(
        'GET',
        '/rest/v1/agendamentos'
            . '?select=id,user_id,nome_cliente,servico,data_hora,status,criado_em,barbeiro_id'
            . '&barbeiro_id=eq.'
            . rawurlencode($idBarbeiro)
            . '&order=data_hora.asc',
        null,
        $token
    );

$agendamentos = [];

if (
    $respostaAgendamentos['status'] === 200 &&
    is_array(
        $respostaAgendamentos['data']
    )
) {
    $agendamentos =
        $respostaAgendamentos['data'];
}

?>



  < ! DOCTYPE html >

    < html lang = "pt-br" >

    < head >

    < meta charset = "UTF-8" >

    < meta name = "viewport"content = "width=device-width, initial-scale=1.0" >

    < title >

    PaineldoBarbeiro - Barbearia Tierno

    <  / title >

    < link rel = "stylesheet"href = "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" >

    < link rel = "stylesheet"href = "../css/pages/barbeiro/barbeiro.css" >

    < link rel = "stylesheet"href = "../css/tema.css" >
    <  / head >

    < body >
    < ?php $navRoot = '../';
require __DIR__ . '/partials/navbar.php'; ?>





    <main class="container">





        <h1 class="titulo">



            Painel do Barbeiro



        </h1>





        <p class="subtitulo">



            Olá,

            <?php echo h($nomeBarbeiro) ?>!



            Aqui você pode gerenciar seus serviços,

            horários e agendamentos.



        </p>





        <?php if ($mensagem !== ''): ?>



            <div class="mensagem">



                <?php echo h($mensagem) ?>



            </div>



        <?php endif; ?>





        <?php if ($erro !== ''): ?>



            <div class="erro">



                <?php echo h($erro) ?>



            </div>



        <?php endif; ?>





        <div class="grid">





            <!--==================================================

        SERVIÇOS

        ==================================================-->



            <section class="card">



                <h2>



                    <i class="fas fa-scissors"></i>



                    Cadastrar serviço para todos



                </h2>





                <form method="POST">



                    <input type="hidden" name="acao" value="criar_servico">





                    <label for="nome">



                        Nome do corte/serviço



                    </label>





                    <input id="nome" name="nome" type="text" placeholder="Ex.: Corte masculino" required>





                    <label for="descricao">



                        Descrição



                    </label>





                    <textarea id="descricao" name="descricao" placeholder="Descrição do serviço"></textarea>





                    <label for="preco">



                        Valor



                    </label>





                    <input id="preco" name="preco" type="number" step="0.01" min="0" placeholder="Ex.: 35.00" required>





                    <label for="duracao_minutos">



                        Duração em minutos



                    </label>





                    <input id="duracao_minutos" name="duracao_minutos" type="number" min="1" value="30" required>





                    <button type="submit">



                        <i class="fas fa-plus"></i>



                        Adicionar serviço



                    </button>



                </form>



            </section>





            <!--==================================================

        HORÁRIOS

        ==================================================-->



            <section class="card">



                <h2>



                    <i class="fas fa-clock"></i>



                    Liberar horários em massa



                </h2>





                <form method="POST">
                    <input type="hidden" name="acao" value="criar_horarios_lote">

                    <label for="data_horarios">Data</label>
                    <input id="data_horarios" name="data" type="date" min="<?php echo date('Y-m-d') ?>" required>

                    <label for="hora_inicio">Começa às</label>
                    <input id="hora_inicio" name="hora_inicio" type="time" value="08:00" required>

                    <label for="hora_fim">Termina às</label>
                    <input id="hora_fim" name="hora_fim" type="time" value="18:00" required>

                    <label for="intervalo_minutos">Intervalo entre horários</label>
                    <select id="intervalo_minutos" name="intervalo_minutos" required>
                        <option value="15">A cada 15 minutos</option>
                        <option value="30" selected>A cada 30 minutos</option>
                        <option value="45">A cada 45 minutos</option>
                        <option value="60">A cada 1 hora</option>
                        <option value="90">A cada 1 hora e 30 minutos</option>
                    </select>

                    <button type="submit">
                        <i class="fas fa-plus"></i>
                        Liberar horários em massa
                    </button>
                    <small class="ajuda-horarios">Exemplo: das 08:00 às 18:00, a cada 30 minutos.</small>
                </form>



            </section>



        </div>





        <!--==================================================

    MEUS SERVIÇOS

    ==================================================-->



        <section class="card">



            <h2>



                <i class="fas fa-list"></i>



                Serviços da barbearia



            </h2>





            <?php if (empty($servicos)): ?>



                <p class="vazio">



                    Nenhum serviço cadastrado.



                </p>



            <?php else: ?>



                <div class="lista">



                    <?php foreach (
                            $servicos as $servico
                    ): ?>



                        <?php

                            $servicoId =
                            $servico['id'] ?? '';

                            $ativo =
                            ! empty($servico['ativo']);

                        ?>



                        <div class="item">



                            <strong>



                                <?php echo h(
                                        $servico['nome'] ?? ''
                                ) ?>



                            </strong>





                            <small>



                                <?php echo h(
                                        $servico['descricao'] ?? ''
                                ) ?>



                            </small>





                            <p>



                                <span class="preco">



                                    R$

                                    <?php echo number_format(
                                            (float) (
                                                $servico['preco'] ?? 0
                                            ),
                                            2,
                                            ',',
                                            '.'
                                    ) ?>



                                </span>





                                &nbsp;



                                <?php echo h(
                                        $servico['duracao_minutos'] ?? 0
                                ) ?>



                                minutos





                                &nbsp;





                                <span class="badge <?php echo ! $ativo
                                                       ? 'badge-off'
                                                       : '' ?>">



                                    <?php echo $ativo
                                            ? 'ATIVO'
                                        : 'INATIVO' ?>



                                </span>



                            </p>





                            <div class="item-acoes">





                                <!-- EDITAR -->



                                <button type="button" onclick="abrirEdicao(

                                    'editar-<?php echo h($servicoId) ?>'

                                )">



                                    <i class="fas fa-edit"></i>



                                    Editar



                                </button>





                                <!-- ATIVAR/DESATIVAR -->



                                <form method="POST">



                                    <input type="hidden" name="acao" value="alternar_servico">





                                    <input type="hidden" name="id" value="<?php echo h(
                                                                              $servicoId
                                                                          ) ?>">





                                    <input type="hidden" name="ativo" value="<?php echo $ativo
                                                                                 ? '0'
                                                                                 : '1' ?>">





                                    <button type="submit" class="<?php echo $ativo
                                                                     ? 'btn-dark'
                                                                     : 'btn-success' ?>">



                                        <?php echo $ativo
                                                ? 'Desativar'
                                            : 'Ativar' ?>



                                    </button>



                                </form>





                                <!-- EXCLUIR -->



                                <form method="POST" onsubmit="

                                    return confirm(

                                        'Deseja excluir este serviço?'

                                    );

                                ">



                                    <input type="hidden" name="acao" value="excluir_servico">





                                    <input type="hidden" name="id" value="<?php echo h(
                                                                              $servicoId
                                                                          ) ?>">





                                    <button type="submit" class="btn-danger">



                                        <i class="fas fa-trash"></i>



                                        Excluir



                                    </button>



                                </form>



                            </div>





                            <!-- FORM EDITAR -->



                            <div id="editar-<?php echo h($servicoId) ?>" class="form-editar">



                                <form method="POST">



                                    <input type="hidden" name="acao" value="editar_servico">





                                    <input type="hidden" name="id" value="<?php echo h(
                                                                              $servicoId
                                                                          ) ?>">





                                    <label>



                                        Nome



                                    </label>





                                    <input name="nome" value="<?php echo h(
                                                                  $servico['nome'] ?? ''
                                                              ) ?>" required>





                                    <label>



                                        Descrição



                                    </label>





                                    <textarea name="descricao"><?php echo h(
                                                                   $servico['descricao'] ?? ''
                                                               ) ?></textarea>





                                    <label>



                                        Valor



                                    </label>





                                    <input name="preco" type="number" step="0.01" min="0" value="<?php echo h(
                                                                                                     $servico['preco'] ?? 0
                                                                                                 ) ?>" required>





                                    <label>



                                        Duração



                                    </label>





                                    <input name="duracao_minutos" type="number" min="1" value="<?php echo h(
                                                                                                   $servico['duracao_minutos'] ?? 30
                                                                                               ) ?>" required>





                                    <button type="submit">



                                        <i class="fas fa-save"></i>



                                        Salvar



                                    </button>



                                </form>



                            </div>



                        </div>



                    <?php endforeach; ?>



                </div>



            <?php endif; ?>



        </section>





        <!--==================================================

    HORÁRIOS

    ==================================================-->



        <section class="card">



            <h2>



                <i class="fas fa-calendar-check"></i>



                Meus horários disponíveis



            </h2>





            <?php if (empty($horarios)): ?>



                <p class="vazio">



                    Nenhum horário cadastrado.



                </p>



            <?php else: ?>



                <div class="lista">



                    <?php foreach (
                            $horarios as $horario
                    ): ?>



                        <?php

                            $horarioId =
                            $horario['id'] ?? '';

                            $dataHora =
                            $horario['data_hora'] ?? '';

                            $disponivel =
                            ! empty($horario['disponivel']);

                            $timestamp =
                                strtotime($dataHora);

                        ?>



                        <div class="item">



                            <strong>



                                <?php

                                    if (
                                        $timestamp !== false
                                    ) {
                                        echo h(
                                            date(
                                                'd/m/Y H:i',
                                                $timestamp
                                            )
                                        );
                                    } else {
                                        echo h(
                                            $dataHora
                                        );
                                    }

                                ?>



                            </strong>





                            <span class="badge <?php echo ! $disponivel
                                                   ? 'badge-off'
                                                   : '' ?>">



                                <?php echo $disponivel
                                        ? 'DISPONÍVEL'
                                    : 'BLOQUEADO' ?>



                            </span>





                            <div class="item-acoes">





                                <!-- ALTERAR -->



                                <form method="POST">



                                    <input type="hidden" name="acao" value="alternar_horario">





                                    <input type="hidden" name="id" value="<?php echo h(
                                                                              $horarioId
                                                                          ) ?>">





                                    <input type="hidden" name="disponivel" value="<?php echo $disponivel
                                                                                      ? '0'
                                                                                      : '1' ?>">





                                    <button type="submit" class="<?php echo $disponivel
                                                                     ? 'btn-dark'
                                                                     : 'btn-success' ?>">



                                        <?php echo $disponivel
                                                ? 'Bloquear'
                                            : 'Liberar' ?>



                                    </button>



                                </form>





                                <!-- EXCLUIR -->



                                <form method="POST" onsubmit="

                                    return confirm(

                                        'Deseja excluir este horário?'

                                    );

                                ">



                                    <input type="hidden" name="acao" value="excluir_horario">





                                    <input type="hidden" name="id" value="<?php echo h(
                                                                              $horarioId
                                                                          ) ?>">





                                    <button type="submit" class="btn-danger">



                                        <i class="fas fa-trash"></i>



                                        Excluir



                                    </button>



                                </form>



                            </div>



                        </div>



                    <?php endforeach; ?>



                </div>



            <?php endif; ?>



        </section>





        <!--==================================================

    AGENDAMENTOS

    ==================================================-->



        <section class="card">



            <h2>



                <i class="fas fa-calendar-alt"></i>



                Meus agendamentos



            </h2>





            <?php if (empty($agendamentos)): ?>



                <p class="vazio">



                    Nenhum agendamento encontrado.



                </p>



            <?php else: ?>



                <div class="lista">



                    <?php foreach (
                            $agendamentos as $agendamento
                    ): ?>



                        <?php

                            $agendamentoId =
                            $agendamento['id'] ?? '';

                            $dataHora =
                            $agendamento['data_hora'] ?? '';

                            $timestamp =
                                strtotime($dataHora);

                            $status =
                            $agendamento['status'] ?? 'agendado';

                        ?>



                        <div class="item agendamento">



                            <strong>



                                Cliente:



                                <?php echo h(
                                        $agendamento['nome_cliente'] ?? 'Cliente'
                                ) ?>



                            </strong>





                            <p>



                                <strong>



                                    Serviço:



                                </strong>



                                <?php echo h(
                                        $agendamento['servico'] ?? ''
                                ) ?>



                            </p>





                            <p>



                                <strong>



                                    Data e horário:



                                </strong>



                                <?php

                                    if (
                                        $timestamp !== false
                                    ) {
                                        echo h(
                                            date(
                                                'd/m/Y H:i',
                                                $timestamp
                                            )
                                        );
                                    } else {
                                        echo h(
                                            $dataHora
                                        );
                                    }

                                ?>



                            </p>





                            <p>



                                <strong>



                                    Status:



                                </strong>





                                <span class="badge">



                                    <?php echo h(
                                            strtoupper(
                                                $status
                                            )
                                    ) ?>



                                </span>



                            </p>





                            <?php if (
                                    $status === 'agendado'
                            ): ?>



                                <div class="item-acoes">





                                    <!-- CONCLUIR -->



                                    <form method="POST">



                                        <input type="hidden" name="acao" value="status_agendamento">





                                        <input type="hidden" name="id" value="<?php echo h(
                                                                                  $agendamentoId
                                                                              ) ?>">




                                        <input type="hidden" name="status" value="concluido">





                                        <button type="submit" class="btn-success">



                                            <i class="fas fa-check"></i>



                                            Concluir



                                        </button>



                                    </form>





                                    <!-- CANCELAR -->



                                    <form method="POST" onsubmit="

                                        return confirm(

                                            'Deseja cancelar este agendamento?'

                                        );

                                    ">



                                        <input type="hidden" name="acao" value="status_agendamento">





                                        <input type="hidden" name="id" value="<?php echo h(
                                                                                  $agendamentoId
                                                                              ) ?>">





                                        <input type="hidden" name="status" value="cancelado">





                                        <button type="submit" class="btn-danger">



                                            <i class="fas fa-times"></i>



                                            Cancelar



                                        </button>



                                    </form>



                                </div>



                            <?php endif; ?>



                        </div>



                    <?php endforeach; ?>



                </div>



            <?php endif; ?>



        </section>





    </main>





    <script>
        function abrirEdicao(id) {



            const elemento =

                document.getElementById(id);



            if (!elemento) {

                return;

            }



            elemento.classList.toggle(

                'aberto'

            );

        }
    </script>





<?php $navRoot = '../';
require __DIR__ . '/partials/footer.php'; ?>
</body>

</html>