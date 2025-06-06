<?php

use App\Models\RegistrationStatus;

return new class extends clsListagem
{
    /**
     * Titulo no topo da pagina
     *
     * @var int
     */
    public $titulo;

    /**
     * Quantidade de registros a ser apresentada em cada pagina
     *
     * @var int
     */
    public $limite;

    /**
     * Inicio dos registros a serem exibidos (limit)
     *
     * @var int
     */
    public $offset;

    public $cod_matricula;

    public $ref_ref_cod_escola;

    public $ref_ref_cod_serie;

    public $ref_usuario_exc;

    public $ref_usuario_cad;

    public $ref_cod_aluno;

    public $aprovado;

    public $data_cadastro;

    public $data_exclusao;

    public $ativo;

    public $ano;

    public $ultima_matricula;

    public $ref_cod_instituicao;

    public $ref_cod_curso;

    public $ref_cod_escola;

    public function Gerar()
    {
        $this->titulo = 'Matrícula - Listagem';

        foreach ($_GET as $var => $val) { // passa todos os valores obtidos no GET para atributos do objeto
            $this->$var = ($val === '') ? null : $val;
        }

        if (!$this->ref_cod_aluno) {
            $this->simpleRedirect(url: 'educar_aluno_lst.php');
        }

        $this->campoOculto(nome: 'ref_cod_aluno', valor: $this->ref_cod_aluno);

        $lista_busca = [
            'Ano',
            'Matrícula',
            'Situação',
            'Turma',
            'Série',
            'Curso',
        ];

        $obj_permissoes = new clsPermissoes;
        $nivel_usuario = $obj_permissoes->nivel_acesso(int_idpes_usuario: $this->pessoa_logada);

        if ($nivel_usuario == 1) {
            $lista_busca[] = 'Escola';
            $lista_busca[] = 'Instituição';
        } elseif ($nivel_usuario == 2) {
            $lista_busca[] = 'Escola';
        }

        $this->addCabecalhos(coluna: $lista_busca);

        $get_escola = true;
        $get_curso = true;
        $get_escola_curso_serie = true;
        include 'include/pmieducar/educar_campo_lista.php';

        if ($this->ref_cod_escola) {
            $this->ref_ref_cod_escola = $this->ref_cod_escola;
        }

        // Paginador
        $this->limite = 20;
        $this->offset = ($_GET["pagina_{$this->nome}"]) ? $_GET["pagina_{$this->nome}"] * $this->limite - $this->limite : 0;

        $obj_matricula = new clsPmieducarMatricula;
        $obj_matricula->setOrderby(strNomeCampo: 'ano DESC, ref_ref_cod_serie DESC, aprovado, cod_matricula');
        $obj_matricula->setLimite(intLimiteQtd: $this->limite, intLimiteOffset: $this->offset);

        $lista = $obj_matricula->lista(
            int_cod_matricula: $this->cod_matricula,
            int_ref_ref_cod_escola: $this->ref_ref_cod_escola,
            int_ref_ref_cod_serie: $this->ref_ref_cod_serie,
            ref_cod_aluno: $this->ref_cod_aluno,
            int_ativo: 1,
            int_ref_cod_curso2: $this->ref_cod_curso,
            int_ref_cod_instituicao: $this->ref_cod_instituicao,
            int_ultima_matricula: 1
        );

        $total = $obj_matricula->_total;

        // monta a lista
        if (is_array(value: $lista) && count(value: $lista)) {
            foreach ($lista as $registro) {
                $obj_ref_cod_curso = new clsPmieducarCurso(cod_curso: $registro['ref_cod_curso']);
                $det_ref_cod_curso = $obj_ref_cod_curso->detalhe();
                $registro['ref_cod_curso'] = $det_ref_cod_curso['nm_curso'];

                $obj_serie = new clsPmieducarSerie(cod_serie: $registro['ref_ref_cod_serie']);
                $det_serie = $obj_serie->detalhe();
                $registro['ref_ref_cod_serie'] = $det_serie['nm_serie'];

                $obj_cod_instituicao = new clsPmieducarInstituicao(cod_instituicao: $registro['ref_cod_instituicao']);
                $obj_cod_instituicao_det = $obj_cod_instituicao->detalhe();
                $registro['ref_cod_instituicao'] = $obj_cod_instituicao_det['nm_instituicao'];

                $obj_ref_cod_escola = new clsPmieducarEscola(cod_escola: $registro['ref_ref_cod_escola']);
                $det_ref_cod_escola = $obj_ref_cod_escola->detalhe();
                $registro['ref_ref_cod_escola'] = $det_ref_cod_escola['nome'];

                $enturmacoes = new clsPmieducarMatriculaTurma;
                $enturmacoes = $enturmacoes->lista(
                    int_ref_cod_matricula: $registro['cod_matricula'],
                    int_ativo: 1
                );

                $nomesTurmas = [];

                foreach ($enturmacoes as $enturmacao) {
                    $turma = new clsPmieducarTurma(cod_turma: $enturmacao['ref_cod_turma']);
                    $turma = $turma->detalhe();
                    $nomesTurmas[] = $turma['nm_turma'];
                }

                $nomesTurmas = implode(separator: '<br />', array: $nomesTurmas);

                $situacao = RegistrationStatus::getRegistrationStatus()[$registro['aprovado']];

                $lista_busca = [];

                $lista_busca[] = "<a href=\"educar_matricula_det.php?cod_matricula={$registro['cod_matricula']}\">{$registro['ano']}</a>";
                $lista_busca[] = "<a href=\"educar_matricula_det.php?cod_matricula={$registro['cod_matricula']}\">{$registro['cod_matricula']}</a>";
                $lista_busca[] = "<a href=\"educar_matricula_det.php?cod_matricula={$registro['cod_matricula']}\">$situacao</a>";

                if ($nomesTurmas) {
                    $lista_busca[] = "<a href=\"educar_matricula_det.php?cod_matricula={$registro['cod_matricula']}\">$nomesTurmas</a>";
                } else {
                    $lista_busca[] = '';
                }

                if ($registro['ref_ref_cod_serie']) {
                    $lista_busca[] = "<a href=\"educar_matricula_det.php?cod_matricula={$registro['cod_matricula']}\">{$registro['ref_ref_cod_serie']}</a>";
                } else {
                    $lista_busca[] = "<a href=\"educar_matricula_det.php?cod_matricula={$registro['cod_matricula']}\">-</a>";
                }

                $lista_busca[] = "<a href=\"educar_matricula_det.php?cod_matricula={$registro['cod_matricula']}\">{$registro['ref_cod_curso']}</a>";

                if ($nivel_usuario == 1) {
                    if ($registro['ref_ref_cod_escola']) {
                        $lista_busca[] = "<a href=\"educar_matricula_det.php?cod_matricula={$registro['cod_matricula']}\">{$registro['ref_ref_cod_escola']}</a>";
                    } else {
                        $lista_busca[] = "<a href=\"educar_matricula_det.php?cod_matricula={$registro['cod_matricula']}\">-</a>";
                    }

                    $lista_busca[] = "<a href=\"educar_matricula_det.php?cod_matricula={$registro['cod_matricula']}\">{$registro['ref_cod_instituicao']}</a>";
                } elseif ($nivel_usuario == 2) {
                    if ($registro['ref_ref_cod_escola']) {
                        $lista_busca[] = "<a href=\"educar_matricula_det.php?cod_matricula={$registro['cod_matricula']}\">{$registro['ref_ref_cod_escola']}</a>";
                    } else {
                        $lista_busca[] = "<a href=\"educar_matricula_det.php?cod_matricula={$registro['cod_matricula']}\">-</a>";
                    }
                }
                $this->addLinhas(linha: $lista_busca);
            }
        } else {
            $this->addLinhas(linha: ['Aluno sem matrículas em andamento na sua escola.']);
        }

        $this->addPaginador2(strUrl: 'educar_matricula_lst.php', intTotalRegistros: $total, mixVariaveisMantidas: $_GET, nome: $this->nome, intResultadosPorPagina: $this->limite);

        if ($obj_permissoes->permissao_cadastra(int_processo_ap: 578, int_idpes_usuario: $this->pessoa_logada, int_soma_nivel_acesso: 7)) {
            $this->acao = "go(\"educar_matricula_cad.php?ref_cod_aluno={$this->ref_cod_aluno}\")";
            $this->nome_acao = 'Nova Matrícula';
        }

        $this->array_botao[] = 'Voltar';
        $this->array_botao_url[] = "educar_aluno_det.php?cod_aluno={$this->ref_cod_aluno}";
        $this->largura = '100%';
    }

    public function makeExtra()
    {
        return file_get_contents(filename: __DIR__ . '/scripts/extra/educar-matricula-abandono-cad.js');
    }

    public function Formular()
    {
        $this->title = 'Matrícula';
        $this->processoAp = '578';
    }
};
