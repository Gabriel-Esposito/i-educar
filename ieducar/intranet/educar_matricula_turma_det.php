<?php

use App\Models\LegacySchoolClassType;

return new class extends clsDetalhe
{
    public $titulo;

    public $ref_cod_matricula;

    public $ref_cod_turma;

    public $ref_usuario_exc;

    public $ref_usuario_cad;

    public $data_cadastro;

    public $data_exclusao;

    public $ativo;

    public $ref_cod_serie;

    public $ref_cod_escola;

    public $ref_cod_turma_origem;

    public $ref_cod_curso;

    public $ano_letivo;

    public $sequencial;

    public $data_enturmacao;

    public function Gerar()
    {
        $this->titulo = 'Matricula Turma - Detalhe';

        foreach ($_POST as $key => $value) {
            $this->$key = $value;
        }

        if (!$this->ref_cod_matricula) {
            $this->simpleRedirect(url: 'educar_matricula_lst.php');
        }

        $obj_mat_turma = new clsPmieducarMatriculaTurma;
        $det_mat_turma = $obj_mat_turma->lista(
            int_ref_cod_matricula: $this->ref_cod_matricula,
            int_ativo: 1
        );

        if ($det_mat_turma) {
            $det_mat_turma = array_shift(array: $det_mat_turma);
            $obj_turma = new clsPmieducarTurma(cod_turma: $det_mat_turma['ref_cod_turma']);
            $det_turma = $obj_turma->detalhe();
            $this->nm_turma = $det_turma['nm_turma'];

            $this->ref_cod_turma_origem = $det_turma['cod_turma'];
            $this->sequencial = $det_mat_turma['sequencial'];
        }

        $tmp_obj = new clsPmieducarTurma;
        $lst_obj = $tmp_obj->lista(int_cod_turma: $this->ref_cod_turma);
        $registro = array_shift(array: $lst_obj);

        $db = new clsBanco;

        $ano = $db->CampoUnico(consulta: "select ano from pmieducar.matricula where cod_matricula = $this->ref_cod_matricula");
        $sql = "select count(cod_matricula) as qtd_matriculas from pmieducar.matricula, pmieducar.matricula_turma, pmieducar.aluno where aluno.cod_aluno = matricula.ref_cod_aluno and ano = {$ano} and aluno.ativo = 1 and matricula.ativo = 1 and matricula_turma.ativo = matricula.ativo and matricula.dependencia = 'f' and cod_matricula = ref_cod_matricula and ref_cod_turma = $this->ref_cod_turma";

        $total_alunos = $db->CampoUnico(consulta: $sql);

        $this->ref_cod_curso = $registro['ref_cod_curso'];

        if (!$registro || !$_POST) {
            $this->simpleRedirect(url: 'educar_matricula_lst.php');
        }

        // Tipo da turma
        $registro['ref_cod_turma_tipo'] = LegacySchoolClassType::findOrFail(id: $registro['ref_cod_turma_tipo'])->nm_tipo;

        // Código da instituição
        $obj_cod_instituicao = new clsPmieducarInstituicao(cod_instituicao: $registro['ref_cod_instituicao']);
        $obj_cod_instituicao_det = $obj_cod_instituicao->detalhe();
        $registro['ref_cod_instituicao'] = $obj_cod_instituicao_det['nm_instituicao'];

        $habilita_enturmar = ($obj_cod_instituicao_det['restringir_multiplas_enturmacoes'] != 't');

        // Nome da escola
        $obj_ref_cod_escola = new clsPmieducarEscola(cod_escola: $registro['ref_ref_cod_escola']);
        $det_ref_cod_escola = $obj_ref_cod_escola->detalhe();
        $registro['ref_ref_cod_escola'] = $det_ref_cod_escola['nome'];

        // Nome do curso
        $obj_ref_cod_curso = new clsPmieducarCurso(cod_curso: $registro['ref_cod_curso']);
        $det_ref_cod_curso = $obj_ref_cod_curso->detalhe();
        $registro['ref_cod_curso'] = $det_ref_cod_curso['nm_curso'];

        // Nome da série
        $obj_ser = new clsPmieducarSerie(cod_serie: $registro['ref_ref_cod_serie']);
        $det_ser = $obj_ser->detalhe();
        $registro['ref_ref_cod_serie'] = $det_ser['nm_serie'];

        // Matrícula
        $obj_ref_cod_matricula = new clsPmieducarMatricula;
        $objMatricula = $obj_ref_cod_matricula->lista(int_cod_matricula: $this->ref_cod_matricula);
        $detalhe_aluno = array_shift(array: $objMatricula);

        $obj_aluno = new clsPmieducarAluno;

        $det_aluno = $obj_aluno->lista(
            int_cod_aluno: $detalhe_aluno['ref_cod_aluno'],
            int_ativo: 1
        );

        $det_aluno = array_shift(array: $det_aluno);

        $obj_escola = new clsPmieducarEscola(
            cod_escola: $this->ref_cod_escola,
            bloquear_lancamento_diario_anos_letivos_encerrados: 1
        );
        $obj_escola->detalhe();

        $this->addDetalhe(detalhe: ['Nome do Aluno', $det_aluno['nome_aluno']]);

        $objTemp = new clsPmieducarTurma(cod_turma: $this->ref_cod_turma);
        $objTemp->detalhe();

        if ($registro['ref_ref_cod_escola']) {
            $this->addDetalhe(detalhe: ['Escola', $registro['ref_ref_cod_escola']]);
        }

        if ($registro['ref_cod_curso']) {
            $this->addDetalhe(detalhe: ['Curso', $registro['ref_cod_curso']]);
        }

        if ($registro['ref_ref_cod_serie']) {
            $this->addDetalhe(detalhe: ['Série', $registro['ref_ref_cod_serie']]);
        }

        // (enturmações) turma atual
        $objEnturmacoes = new clsPmieducarMatriculaTurma;
        $enturmacoes = $objEnturmacoes->lista(int_ref_cod_matricula: $this->ref_cod_matricula, int_ativo: 1);

        $this->possuiEnturmacao = !empty($enturmacoes);

        if ($this->possuiEnturmacao) {
            $dependente = $objEnturmacoes->verficaEnturmacaoDeDependencia(matriculaId: $this->ref_cod_matricula, turmaId: $enturmacoes[0]['ref_cod_turma']);
        }

        $this->possuiEnturmacaoTurmaDestino = false;
        $this->turmaOrigemMesmaDestino = false;

        $this->addDetalhe(detalhe: ['<b>Turma selecionada</b>', '<b>' . $registro['nm_turma'] . '</b>']);

        $totalVagas = $registro['max_aluno'];

        $this->addDetalhe(detalhe: ['Total de vagas', $totalVagas]);

        if (is_numeric(value: $total_alunos)) {
            $this->addDetalhe(detalhe: ['Alunos enturmados', $total_alunos]);
            $this->addDetalhe(detalhe: ['Vagas disponíveis', $totalVagas - $total_alunos]);
        }

        if ($this->possuiEnturmacao) {
            // se possui uma enturmacao mostra o nome, se mais de uma mostra select para selecionar
            if (count(value: $enturmacoes) > 1) {
                $selectEnturmacoes = '<select id=\'ref_cod_turma_origem\' class=\'obrigatorio\'>';
                $selectEnturmacoes .= '<option value=\'\'>Selecione</option>';

                foreach ($enturmacoes as $enturmacao) {
                    if ($enturmacao['ref_cod_turma'] != $this->ref_cod_turma) {
                        $selectEnturmacoes .= "<option value='{$enturmacao['ref_cod_turma']}'>{$enturmacao['nm_turma']}</option>";
                    } elseif (!$this->possuiEnturmacaoTurmaDestino) {
                        $this->possuiEnturmacaoTurmaDestino = true;
                    }
                }
                $selectEnturmacoes .= '</select>';
            } else {
                if ($enturmacoes[0]['ref_cod_turma'] == $this->ref_cod_turma) {
                    $this->possuiEnturmacaoTurmaDestino = true;
                    $this->turmaOrigemMesmaDestino = true;
                }

                $selectEnturmacoes = "<input id='ref_cod_turma_origem' type='hidden' value = '{$enturmacoes[0]['ref_cod_turma']}'/>{$enturmacoes[0]['nm_turma']}";
            }

            $this->addDetalhe(detalhe: ['<b>Enturmação atual</b>', $selectEnturmacoes]);
        }

        if (!$this->possuiEnturmacaoTurmaDestino) {
            $this->addDetalhe(detalhe: ['Data da enturmação', '<input onkeypress="formataData(this,event);" value="" class="geral" type="text" name="data_enturmacao" id="data_enturmacao" size="9" maxlength="10"/>']);
        }

        $this->addDetalhe(detalhe: [
            '-',
            sprintf('
                <form name="formcadastro" method="post" action="educar_matricula_turma_cad.php">
                  <input type="hidden" name="ref_cod_matricula" value="">
                  <input type="hidden" name="ref_cod_serie" value="">
                  <input type="hidden" name="ref_cod_escola" value="">
                  <input type="hidden" name="ref_cod_turma_origem" value="%d">
                  <input type="hidden" name="ref_cod_turma_destino" value="">
                  <input type="hidden" name="data_enturmacao" value="">
                  <input type="hidden" name="sequencial" value="%d">
                </form>
            ', $this->ref_cod_turma_origem, $this->sequencial),
        ]);

        if (($totalVagas - $total_alunos <= 0) && !$dependente) {
            $escolaSerie = $this->getEscolaSerie(escolaId: $det_ref_cod_escola['cod_escola'], serieId: $det_ser['cod_serie']);

            if ($escolaSerie['bloquear_enturmacao_sem_vagas'] != 1) {
                $msg = sprintf(format: 'Atenção! Turma sem vagas! Deseja continuar com a enturmação mesmo assim?');
                $jsEnturmacao = sprintf('if (!confirm("%s")) return false;', $msg);
            } else {
                $msg = sprintf(format: 'Enturmação não pode ser realizada,\n\no limite de vagas da turma já foi atingido e para esta série e escola foi definido bloqueio de enturmação após atingir tal limite.');
                $jsEnturmacao = sprintf('alert("%s"); return false;', $msg);
            }
        } else {
            $jsEnturmacao = 'if (!confirm("Confirma a enturmação?")) return false;';
        }

        $script = sprintf(
            '
            <script type="text/javascript">

                function enturmar(ref_cod_matricula, ref_cod_turma_destino, tipo){
                  var data = $j("#data_enturmacao").val();
                  var array_data = data.split("/");
                  var data_valida = IsDate(array_data[0], array_data[1], array_data[2]);
                  if (data_valida == false) {
                    alert("Informe a data corretamente");
                    return false;
                  }

                  document.formcadastro.ref_cod_turma_origem.value = "";

                  if(tipo == "transferir") {
                    var turmaOrigemId = document.getElementById("ref_cod_turma_origem");
                    if (turmaOrigemId && turmaOrigemId.value)
                      document.formcadastro.ref_cod_turma_origem.value = turmaOrigemId.value;
                    else {
                      alert("Por favor, selecione a enturmação a ser transferida.");
                      return false;
                    }
                  }

                  %s

                  document.formcadastro.ref_cod_matricula.value = ref_cod_matricula;
                  document.formcadastro.ref_cod_turma_destino.value = ref_cod_turma_destino;
                  document.formcadastro.data_enturmacao.value = document.getElementById("data_enturmacao").value;
                  document.formcadastro.submit();
                }

                function IsDate(day, month, year) {
                  var date = new Date();
                  var blnRet = false;
                  var blnDay;
                  var blnMonth;
                  var blnYear;
                  date.setFullYear(year, month -1, day);
                  blnDay   = (date.getDate()      == day);
                  blnMonth = (date.getMonth()     == month -1);
                  blnYear  = (date.getFullYear()  == year);
                  if (blnDay && blnMonth && blnYear) {
                    blnRet = true;
                  }
                  return blnRet;
                }

                function removerEnturmacao(ref_cod_matricula, ref_cod_turma_destino, data_enturmacao) {

                  if (! confirm("Confirma remoção da enturmação?"))
                    return false;

                  document.formcadastro.ref_cod_turma_origem.value = "remover-enturmacao-destino";
                  document.formcadastro.ref_cod_matricula.value = ref_cod_matricula;
                  document.formcadastro.ref_cod_turma_destino.value = ref_cod_turma_destino;
                  document.formcadastro.submit();
                }

            </script>',
            $jsEnturmacao
        );

        echo $script;

        $canCreate = new clsPermissoes;
        $canCreate = $canCreate->permissao_cadastra(int_processo_ap: 578, int_idpes_usuario: $this->pessoa_logada, int_soma_nivel_acesso: 7);
        $this->data_enturmacao = $enturmacoes[0]['data_enturmacao'];

        if ($this->possuiEnturmacaoTurmaDestino && $canCreate) {
            $this->array_botao = ['Remover (enturmação) da turma selecionada'];
            $this->array_botao_url_script = ["removerEnturmacao({$this->ref_cod_matricula}, {$this->ref_cod_turma}, {$this->data_enturmacao})"];
        }

        if (!$this->turmaOrigemMesmaDestino && $canCreate) {
            // mover enturmação
            if ($this->possuiEnturmacao) {
                $this->array_botao[] = 'Transferir para turma selecionada';
                $this->array_botao_url_script[] = "enturmar({$this->ref_cod_matricula}, {$this->ref_cod_turma}, \"transferir\")";
            }

            if ($habilita_enturmar || !$this->possuiEnturmacao) {
                // nova enturmação
                if (!$this->possuiEnturmacaoTurmaDestino && $canCreate) {
                    $this->array_botao[] = 'Enturmar na turma selecionada';
                    $this->array_botao_url_script[] = "enturmar({$this->ref_cod_matricula}, {$this->ref_cod_turma}, \"nova\")";
                }
            }
        }

        $this->array_botao[] = 'Voltar';
        $this->array_botao_url_script[] = "go(\"educar_matricula_turma_lst.php?ref_cod_matricula={$this->ref_cod_matricula}&ano_letivo={$this->ano_letivo}\");";

        $this->largura = '100%';

        $this->breadcrumb(currentPage: 'Enturmações da matrícula', breadcrumbs: [
            url(path: 'intranet/educar_index.php') => 'Escola',
        ]);
    }

    protected function getEscolaSerie($escolaId, $serieId)
    {
        $escolaSerie = new clsPmieducarEscolaSerie;
        $escolaSerie->ref_cod_escola = $escolaId;
        $escolaSerie->ref_cod_serie = $serieId;

        return $escolaSerie->detalhe();
    }

    public function Formular()
    {
        $this->title = 'Matricula Turma';
        $this->processoAp = 578;
    }
};
