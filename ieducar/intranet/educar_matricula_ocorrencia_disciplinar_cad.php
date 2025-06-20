<?php

use App\Models\LegacyDisciplinaryOccurrenceType;
use App\Models\LegacyRegistrationDisciplinaryOccurrenceType;
use Illuminate\Validation\ValidationException;

return new class extends clsCadastro
{
    /**
     * Referencia pega da session para o idpes do usuario atual
     *
     * @var int
     */
    public $pessoa_logada;

    public $cod_ocorrencia_disciplinar;

    public $ref_cod_matricula;

    public $ref_cod_tipo_ocorrencia_disciplinar;

    public $sequencial;

    public $ref_usuario_exc;

    public $ref_usuario_cad;

    public $observacao;

    public $data_exclusao;

    public $ativo;

    public $data_cadastro;

    public $ref_cod_instituicao;

    public $ref_cod_escola;

    public $hora_cadastro;

    public function Inicializar()
    {
        $retorno = 'Novo';

        $this->ref_cod_matricula = $_GET['ref_cod_matricula'];

        $obj_permissoes = new clsPermissoes;

        $obj_permissoes->permissao_cadastra(21145, $this->pessoa_logada, 7, 'educar_matricula_lst.php');

        $data = getdate();

        $data['mday'] = sprintf('%02d', $data['mday']);
        $data['mon'] = sprintf('%02d', $data['mon']);
        $data['hours'] = sprintf('%02d', $data['hours']);
        $data['minutes'] = sprintf('%02d', $data['minutes']);

        $this->data_cadastro = "{$data['mday']}/{$data['mon']}/{$data['year']}";
        $this->hora_cadastro = "{$data['hours']}:{$data['minutes']}";

        $this->sequencial = $_GET['sequencial'];
        $this->ref_cod_matricula = $_GET['ref_cod_matricula'];
        $this->ref_cod_tipo_ocorrencia_disciplinar = $_GET['ref_cod_tipo_ocorrencia_disciplinar'];

        if (is_numeric($this->ref_cod_matricula) &&
            is_numeric($this->ref_cod_tipo_ocorrencia_disciplinar) &&
            is_numeric($this->sequencial)) {

            $registro = LegacyRegistrationDisciplinaryOccurrenceType::query()
                ->where('ref_cod_matricula', $this->ref_cod_matricula)
                ->where('ref_cod_tipo_ocorrencia_disciplinar', $this->ref_cod_tipo_ocorrencia_disciplinar)
                ->where('sequencial', $this->sequencial)
                ->where('ativo', 1)
                ->orderBy('cod_ocorrencia_disciplinar', 'DESC')
                ->first()
                ?->getAttributes();

            if ($registro) {
                foreach ($registro as $campo => $val) {  // passa todos os valores obtidos no registro para atributos do objeto
                    $this->$campo = $val;
                }

                $this->hora_cadastro = dataFromPgToBr($this->data_cadastro, 'H:i');
                $this->data_cadastro = dataFromPgToBr($this->data_cadastro);

                $obj_permissoes = new clsPermissoes;
                if ($obj_permissoes->permissao_excluir(578, $this->pessoa_logada, 7)) {
                    $this->fexcluir = true;
                }

                $retorno = 'Editar';
            }
        }

        if (is_numeric($this->ref_cod_matricula)) {
            $obj_ref_cod_matricula = new clsPmieducarMatricula;
            $detalhe_aluno = $obj_ref_cod_matricula->lista($this->ref_cod_matricula);
            array_shift($detalhe_aluno);
            $this->ref_cod_escola = $detalhe_aluno['ref_ref_cod_escola'];
            $obj_escola = new clsPmieducarEscola($this->ref_cod_escola);
            $det_escola = $obj_escola->detalhe();
            $this->ref_cod_instituicao = $det_escola['ref_cod_instituicao'];

            $this->url_cancelar = ($retorno == 'Editar') ? "educar_matricula_ocorrencia_disciplinar_det.php?ref_cod_matricula={$registro['ref_cod_matricula']}&ref_cod_tipo_ocorrencia_disciplinar={$registro['ref_cod_tipo_ocorrencia_disciplinar']}&sequencial={$registro['sequencial']}" : "educar_matricula_ocorrencia_disciplinar_lst.php?ref_cod_matricula={$this->ref_cod_matricula}";
        }

        $this->breadcrumb('Ocorrências disciplinares da matrícula', [
            url('intranet/educar_index.php') => 'Escola',
        ]);

        $this->nome_url_cancelar = 'Cancelar';

        return $retorno;
    }

    public function Gerar()
    {
        if (is_numeric($this->ref_cod_matricula)) {
            $obj_ref_cod_matricula = new clsPmieducarMatricula;
            $detalhe_aluno = $obj_ref_cod_matricula->lista($this->ref_cod_matricula);
            if ($detalhe_aluno) {
                $detalhe_aluno = array_shift($detalhe_aluno);
            }
            $obj_aluno = new clsPmieducarAluno;
            $det_aluno = $obj_aluno->lista($detalhe_aluno['ref_cod_aluno'], null, null, null, null, null, null, null, null, null, 1);
            $det_aluno = current($det_aluno);

            $this->campoRotulo('nm_pessoa', 'Nome do Aluno', $det_aluno['nome_aluno']);
        } else {
            $this->inputsHelper()->dynamic(['ano', 'instituicao', 'escola']);
            // FIXME #parameters
            $this->inputsHelper()->simpleSearchMatricula(null);
            $this->inputsHelper()->hidden('somente_andamento');
        }

        // primary keys
        $this->campoOculto('ref_cod_matricula', $this->ref_cod_matricula);
        $this->campoOculto('ref_cod_tipo_ocorrencia_disciplinar', $this->ref_cod_tipo_ocorrencia_disciplinar);
        $this->campoOculto('sequencial', $this->sequencial);
        $this->campoOculto('cod_ocorrencia_disciplinar', $this->cod_ocorrencia_disciplinar);

        $this->campoData('data_cadastro', 'Data Atual', $this->data_cadastro, true);
        $this->campoHora('hora_cadastro', 'Horas', $this->hora_cadastro, true);

        $opcoes = LegacyDisciplinaryOccurrenceType::query()
            ->where('ativo', 1)
            ->orderBy('nm_tipo', 'ASC')
            ->pluck('nm_tipo', 'cod_tipo_ocorrencia_disciplinar')
            ->prepend('Selecione', '');

        $this->campoLista('ref_cod_tipo_ocorrencia_disciplinar', 'Tipo Ocorrência Disciplinar', $opcoes, $this->ref_cod_tipo_ocorrencia_disciplinar);

        // text
        $this->campoMemo('observacao', 'Observacão', $this->observacao, 60, 10, true);

        $this->campoCheck(
            'visivel_pais',
            'Visível aos pais',
            $this->visivel_pais,
            'Marque este campo, caso deseje que os pais do aluno possam visualizar tal ocorrência disciplinar.'
        );
    }

    public function Novo()
    {
        $obj_permissoes = new clsPermissoes;
        $obj_permissoes->permissao_cadastra(578, $this->pessoa_logada, 7, 'educar_matricula_ocorrencia_disciplinar_lst.php');

        $this->visivel_pais = is_null($this->visivel_pais) ? 0 : 1;

        $voltaListagem = is_numeric($this->ref_cod_matricula);

        $this->ref_cod_matricula = is_numeric($this->ref_cod_matricula) ? $this->ref_cod_matricula : $this->getRequest()->matricula_id;

        if (empty($this->ref_cod_matricula)) {
            throw ValidationException::withMessages([
                'ref_cod_matricula' => ['Cadastro não efetuado. A matrícula é inválida!'],
            ]);
        }

        $obj_ref_cod_matricula = new clsPmieducarMatricula($this->ref_cod_matricula);
        $detalhe_mat = $obj_ref_cod_matricula->detalhe();
        $this->ref_cod_instituicao = $detalhe_mat['ref_cod_instituicao'];

        $obj = new LegacyRegistrationDisciplinaryOccurrenceType;
        $obj->ref_cod_matricula = $this->ref_cod_matricula;
        $obj->ref_cod_tipo_ocorrencia_disciplinar = $this->ref_cod_tipo_ocorrencia_disciplinar;
        $obj->ref_usuario_exc = $this->pessoa_logada;
        $obj->ref_usuario_cad = $this->pessoa_logada;
        $obj->observacao = $this->observacao;
        $obj->visivel_pais = $this->visivel_pais;
        $obj->data_cadastro = $this->getDataHoraCadastro();

        if ($obj->save()) {
            if (($this->visivel_pais) && ($this->possuiConfiguracaoNovoEducacao())) {
                $resposta = json_decode($this->enviaOcorrenciaNovoEducacao($obj->id));

                if (is_array($resposta->errors)) {
                    echo 'Erro ao enviar ocorrencia disciplinar ao sistema externo: ' . $resposta->errors[0];
                    exit;
                }
            }
            $this->mensagem .= 'Cadastro efetuado com sucesso.<br>';
            if ($voltaListagem) {
                $this->simpleRedirect("educar_matricula_ocorrencia_disciplinar_lst.php?ref_cod_matricula={$this->ref_cod_matricula}");
            } else {
                echo '<script language=\'javascript\' type=\'text/javascript\'>alert(\'Cadastro efetuado com sucesso.\');</script>';
                echo '<script language=\'javascript\' type=\'text/javascript\'>window.location.href=\'educar_matricula_ocorrencia_disciplinar_cad.php\'</script>';
            }

            return true;
        }

        $this->mensagem = 'Cadastro não realizado.<br>';

        return false;
    }

    public function Editar()
    {
        $obj_permissoes = new clsPermissoes;
        $obj_permissoes->permissao_cadastra(578, $this->pessoa_logada, 7, 'educar_matricula_ocorrencia_disciplinar_lst.php');

        $this->visivel_pais = is_null($this->visivel_pais) ? 0 : 1;
        $voltaListagem = is_numeric($this->ref_cod_matricula);
        $this->ref_cod_matricula = is_numeric($this->ref_cod_matricula) ? $this->ref_cod_matricula : $this->getRequest()->matricula_id;

        $obj = LegacyRegistrationDisciplinaryOccurrenceType::find($this->cod_ocorrencia_disciplinar);
        $obj->ref_cod_matricula = $this->ref_cod_matricula;
        $obj->ref_cod_tipo_ocorrencia_disciplinar = $this->ref_cod_tipo_ocorrencia_disciplinar;
        $obj->ref_usuario_exc = $this->pessoa_logada;
        $obj->ref_usuario_cad = $this->pessoa_logada;
        $obj->observacao = $this->observacao;
        $obj->visivel_pais = $this->visivel_pais;
        $obj->data_cadastro = $this->getDataHoraCadastro();

        if ($obj->save()) {
            $this->mensagem = 'Edição efetuada com sucesso.<br>';
            if ($voltaListagem) {
                $this->simpleRedirect("educar_matricula_ocorrencia_disciplinar_lst.php?ref_cod_matricula={$this->ref_cod_matricula}");
            } else {
                $this->simpleRedirect('educar_matricula_ocorrencia_disciplinar_cad.php');
            }
        }

        $this->mensagem = 'Edição não realizada.<br>';

        return false;
    }

    public function Excluir()
    {
        $obj_permissoes = new clsPermissoes;
        $obj_permissoes->permissao_excluir(578, $this->pessoa_logada, 7, 'educar_matricula_ocorrencia_disciplinar_lst.php');

        $obj = LegacyRegistrationDisciplinaryOccurrenceType::find($this->cod_ocorrencia_disciplinar);
        $obj->ativo = 0;
        $obj->ref_usuario_exc = $this->pessoa_logada;

        if ($obj->save()) {
            $this->mensagem = 'Exclusão efetuada com sucesso.<br>';
            $this->simpleRedirect("educar_matricula_ocorrencia_disciplinar_lst.php?ref_cod_matricula={$this->ref_cod_matricula}");
        }

        $this->mensagem = 'Exclusão não realizada.<br>';

        return false;
    }

    protected function getDataHoraCadastro()
    {
        return $this->data_cadastro = dataToBanco($this->data_cadastro) . ' ' . $this->hora_cadastro;
    }

    protected function enviaOcorrenciaNovoEducacao($cod_ocorrencia_disciplinar)
    {
        $tmp_obj = new clsPmieducarConfiguracoesGerais($this->ref_cod_instituicao);
        $instituicao = $tmp_obj->detalhe();

        $obj_tmp = new clsPmieducarMatricula($this->ref_cod_matricula);
        $det_tmp = $obj_tmp->detalhe();
        $cod_aluno = $det_tmp['ref_cod_aluno'];

        $cod_escola = $det_tmp['ref_ref_cod_escola'];

        $obj_tmp = LegacyDisciplinaryOccurrenceType::find($this->ref_cod_tipo_ocorrencia_disciplinar)?->getAttributes();
        $tipo_ocorrencia = $det_tmp['nm_tipo'];

        $params = [
            'token' => config('legacy.apis.access_key'),
            'api_code' => $cod_ocorrencia_disciplinar,
            'student_code' => $cod_aluno,
            'description' => $this->observacao,
            'occurred_at' => $this->data_cadastro,
            'unity_code' => $cod_escola,
            'kind' => $tipo_ocorrencia,
        ];

        $requisicao = new ApiExternaController(
            [
                'url' => $instituicao['url_novo_educacao'],
                'recurso' => 'ocorrencias-disciplinares',
                'tipoRequisicao' => ApiExternaController::REQUISICAO_POST,
                'params' => $params,
                'token_header' => config('legacy.apis.educacao_token_header'),
                'token_key' => config('legacy.apis.educacao_token_key'),
            ]
        );

        return $requisicao->executaRequisicao();
    }

    protected function possuiConfiguracaoNovoEducacao()
    {
        $tmp_obj = new clsPmieducarConfiguracoesGerais($this->ref_cod_instituicao);
        $instituicao = $tmp_obj->detalhe();

        return strlen($instituicao['url_novo_educacao']) > 0;
    }

    public function Formular()
    {
        $this->title = 'Ocorrência Disciplinar';
        $this->processoAp = '578';
    }
};
